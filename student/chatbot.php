<?php

// WAMP's default PHP timezone is often UTC (or some other server default),
// which doesn't match India time — that's why greetings/"days ago" were off.
// Setting it explicitly here fixes BOTH the time-of-day greeting and the
// notification "days ago" calculation.
date_default_timezone_set('Asia/Kolkata');

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/db.php";
require_once "../includes/NaiveBayes.php";

$student_id = (int)$_SESSION["user_id"];
$user_name  = $_SESSION["user_name"] ?? "Student";

if (!isset($_SESSION["chat_history"])) {
    $_SESSION["chat_history"] = [];
}

// Keep the latest recommendation list so follow-up questions such as
// "which company is better?" can use the previous chatbot context.
if (!isset($_SESSION["last_recommendations"])) {
    $_SESSION["last_recommendations"] = [];
}

// Practice mode state. This keeps the practice conversation inside the chatbot
// instead of sending the student away to another page.
if (!isset($_SESSION["practice_mode"])) {
    $_SESSION["practice_mode"] = "none";
    $_SESSION["practice_topic"] = null;
    $_SESSION["practice_index"] = 0;
    $_SESSION["practice_score"] = 0;
}

$practiceQuestions = [
    "aptitude" => [
        ["q" => "If 5 workers can complete a work in 10 days, how many days will 10 workers take to complete the same work?", "options" => ["A. 2 days", "B. 5 days", "C. 10 days", "D. 20 days"], "answer" => "B"],
        ["q" => "A train travels 120 km in 2 hours. What is its average speed?", "options" => ["A. 40 km/h", "B. 50 km/h", "C. 60 km/h", "D. 80 km/h"], "answer" => "C"],
        ["q" => "What is 20% of 250?", "options" => ["A. 25", "B. 40", "C. 50", "D. 60"], "answer" => "C"],
        ["q" => "If the ratio of boys to girls is 3:2 and there are 30 boys, how many girls are there?", "options" => ["A. 15", "B. 20", "C. 25", "D. 45"], "answer" => "B"],
        ["q" => "A product costs ₹800 and is sold at a 10% discount. What is the selling price?", "options" => ["A. ₹700", "B. ₹720", "C. ₹740", "D. ₹780"], "answer" => "B"],
    ],
    "technical" => [
        ["q" => "Which data structure follows the LIFO principle?", "options" => ["A. Queue", "B. Stack", "C. Array", "D. Linked List"], "answer" => "B"],
        ["q" => "Which HTTP method is normally used to retrieve data from a server?", "options" => ["A. GET", "B. POST", "C. PUT", "D. DELETE"], "answer" => "A"],
        ["q" => "Which language is primarily used to style web pages?", "options" => ["A. Python", "B. Java", "C. CSS", "D. SQL"], "answer" => "C"],
        ["q" => "What does SQL stand for?", "options" => ["A. Simple Query Language", "B. Structured Query Language", "C. System Query Logic", "D. Sequential Query Language"], "answer" => "B"],
        ["q" => "Which Git command downloads changes from a remote repository without merging them?", "options" => ["A. git push", "B. git commit", "C. git fetch", "D. git init"], "answer" => "C"],
    ],
    "logical" => [
        ["q" => "Find the next number: 2, 4, 8, 16, ?", "options" => ["A. 20", "B. 24", "C. 30", "D. 32"], "answer" => "D"],
        ["q" => "If CAT is coded as DBU, how is DOG coded using the same pattern?", "options" => ["A. EPH", "B. EOG", "C. FPH", "D. DNF"], "answer" => "A"],
        ["q" => "Which number does not belong: 3, 5, 7, 9, 11?", "options" => ["A. 3", "B. 5", "C. 9", "D. 11"], "answer" => "C"],
        ["q" => "If all roses are flowers and some flowers fade quickly, which statement is definitely true?", "options" => ["A. All roses fade quickly", "B. Some roses are not flowers", "C. Roses are flowers", "D. No flowers are roses"], "answer" => "C"],
        ["q" => "Find the next letter: A, C, F, J, O, ?", "options" => ["A. T", "B. U", "C. V", "D. W"], "answer" => "B"],
    ],
];

$practiceLabels = [
    "aptitude" => "Aptitude",
    "technical" => "Technical",
    "logical" => "Logical Reasoning",
];

function practiceQuestionHtml($questions, $index, $label) {
    $question = $questions[$index];
    $number = $index + 1;
    $total = count($questions);
    $html = "<b>📝 Question {$number}/{$total}</b><br><br>";
    $html .= htmlspecialchars($question["q"]) . "<br><br>";
    foreach ($question["options"] as $option) {
        $html .= htmlspecialchars($option) . "<br>";
    }
    $html .= "<br>💡 <b>Type A, B, C or D</b> to answer.";
    return $html;
}

// "Clear chat" button
if (isset($_GET["clear"])) {
    $_SESSION["chat_history"] = [];
    $_SESSION["last_recommendations"] = [];
    $_SESSION["practice_mode"] = "none";
    $_SESSION["practice_topic"] = null;
    $_SESSION["practice_index"] = 0;
    $_SESSION["practice_score"] = 0;
    header("Location: chatbot.php");
    exit();
}

// Confidence below this, the bot admits it doesn't know instead of
// confidently guessing the wrong thing.
const CHATBOT_CONFIDENCE_THRESHOLD = 0.12;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $rawMessage = trim($_POST["message"] ?? "");

    if ($rawMessage !== "") {

        $response = "";

        // ---- 1. Load training data & responses, train the classifier ----
        $intents   = require "../config/chatbot_intents.php";
        $responses = require "../config/chatbot_response.php";

        $classifier = new NaiveBayes();
        $classifier->train($intents);

        // ---- 2. Classify the student's message ----
        // A handful of very common short phrases (e.g. "how are you")
        // are made ENTIRELY of stopwords, so the classifier has no real
        // words left to learn from. Catch those directly first.
        $shortPhrases = [
            "how are you" => "small_talk",
            "how are you doing" => "small_talk",
            "how r u" => "small_talk",
            "hows it going" => "small_talk",
            "how is it going" => "small_talk",
            "whats up" => "small_talk",
            "what is up" => "small_talk",
            "sup" => "small_talk",
            "which company is better" => "company_comparison",
            "which company is best" => "company_comparison",
            "compare companies" => "company_comparison",
            "which company should i choose" => "company_comparison",
            "which internship would suit me" => "recommend_internship",
            "which internship suits me" => "recommend_internship",
            "which internship suits me best" => "recommend_internship",
            "what internship suits me" => "recommend_internship",
        ];

        $normalizedMsg = preg_replace('/[^a-z0-9\s]/', '', strtolower($rawMessage));
        $normalizedMsg = trim(preg_replace('/\s+/', ' ', $normalizedMsg));

        // ---- Practice mode ------------------------------------------------
        // Practice is handled before the classifier so short inputs such as
        // "1" or "A" are interpreted as the student's practice choice.
        $practiceHandled = false;

        if ($normalizedMsg === "exit" || $normalizedMsg === "quit practice") {
            // "exit" is an explicit chatbot command, so it must never fall
            // through to the ML classifier and show the generic fallback.
            // Clear any practice state and return the student to normal chat.
            $_SESSION["practice_mode"] = "none";
            $_SESSION["practice_topic"] = null;
            $_SESSION["practice_index"] = 0;
            $_SESSION["practice_score"] = 0;

            $response = "You've exited practice mode. 👍<br><br>You're back in normal chat. You can ask me about <b>internships</b>, <b>applications</b>, <b>application status</b>, <b>resume</b>, <b>profile</b>, or <b>skill match</b>.";
            $intent = "practice";
            $confidence = 1.0;
            $practiceHandled = true;
        }

        if (!$practiceHandled && in_array($normalizedMsg, ["practice", "i want to practice", "start practice", "practice again", "start practicing"], true)) {
            $_SESSION["practice_mode"] = "selecting";
            $_SESSION["practice_topic"] = null;
            $_SESSION["practice_index"] = 0;
            $_SESSION["practice_score"] = 0;
            $response = "Great choice, " . htmlspecialchars($user_name) . "! Let's test your knowledge. 💪<br><br>"
                . "<b>Choose a topic:</b><br><br>"
                . "1️⃣ <b>Aptitude</b><br>"
                . "2️⃣ <b>Technical</b><br>"
                . "3️⃣ <b>Logical Reasoning</b><br><br>"
                . "Type <b>1</b>, <b>2</b> or <b>3</b> to choose a topic.";
            $intent = "practice";
            $confidence = 1.0;
            $practiceHandled = true;
        }

        if (!$practiceHandled && $_SESSION["practice_mode"] === "selecting") {
            $topicMap = ["1" => "aptitude", "2" => "technical", "3" => "logical"];
            if (isset($topicMap[$normalizedMsg])) {
                $topic = $topicMap[$normalizedMsg];
                $_SESSION["practice_mode"] = "active";
                $_SESSION["practice_topic"] = $topic;
                $_SESSION["practice_index"] = 0;
                $_SESSION["practice_score"] = 0;
                $label = $practiceLabels[$topic];
                $response = "🎯 <b>{$label} Practice</b><br><br>"
                    . "You have " . count($practiceQuestions[$topic]) . " questions.<br>Let's begin! 🚀<br><br>"
                    . practiceQuestionHtml($practiceQuestions[$topic], 0, $label);
                $intent = "practice";
                $confidence = 1.0;
                $practiceHandled = true;
            } else {
                $response = "Please choose a topic by typing <b>1</b> for Aptitude, <b>2</b> for Technical, or <b>3</b> for Logical Reasoning.";
                $intent = "practice";
                $confidence = 1.0;
                $practiceHandled = true;
            }
        }

        if (!$practiceHandled && $_SESSION["practice_mode"] === "active") {
            $topic = $_SESSION["practice_topic"];
            $index = (int)$_SESSION["practice_index"];
            $questions = $practiceQuestions[$topic] ?? [];
            $answer = strtoupper($normalizedMsg);

            if (isset($questions[$index]) && in_array($answer, ["A", "B", "C", "D"], true)) {
                $correct = $questions[$index]["answer"];
                if ($answer === $correct) {
                    $_SESSION["practice_score"]++;
                    $feedback = "✅ <b>Correct!</b> Great job!";
                } else {
                    $feedback = "❌ <b>Not quite!</b><br>Your answer: <b>{$answer}</b><br>Correct answer: <b>{$correct}</b>";
                }

                $_SESSION["practice_index"]++;
                $nextIndex = (int)$_SESSION["practice_index"];
                $label = $practiceLabels[$topic];

                if ($nextIndex < count($questions)) {
                    $response = $feedback . "<br><br>" . practiceQuestionHtml($questions, $nextIndex, $label);
                } else {
                    $score = (int)$_SESSION["practice_score"];
                    $total = count($questions);
                    $percent = (int)round(($score / max(1, $total)) * 100);
                    $response = $feedback . "<br><br>🏁 <b>{$label} Practice Complete!</b><br><br>"
                        . "Your score: <b>{$score}/{$total}</b> ({$percent}%)<br><br>"
                        . ($percent >= 80 ? "Excellent work! 🎉" : ($percent >= 50 ? "Good effort! Keep practicing. 💪" : "Keep practicing — you’ll improve with each attempt. 🌱"))
                        . "<br><br>Type <b>practice</b> to try another topic.";
                    $_SESSION["practice_mode"] = "none";
                    $_SESSION["practice_topic"] = null;
                    $_SESSION["practice_index"] = 0;
                    $_SESSION["practice_score"] = 0;
                }
                $intent = "practice";
                $confidence = 1.0;
                $practiceHandled = true;
            } else {
                $response = "Please answer with <b>A</b>, <b>B</b>, <b>C</b> or <b>D</b>. You can also type <b>exit</b> to leave practice.";
                $intent = "practice";
                $confidence = 1.0;
                $practiceHandled = true;
            }
        }

        // Explicit routing for common natural-language questions prevents
        // the ML classifier from confusing company-comparison questions
        // with general internship recommendations.
        if (preg_match('/\b(which|what) company (is )?(better|best|good)\b/i', $normalizedMsg)
            || preg_match('/\bcompare (the )?companies\b/i', $normalizedMsg)) {
            $shortPhrases[$normalizedMsg] = "company_comparison";
        } elseif (preg_match('/\b(which|what) internship (would )?suit(s)? me\b/i', $normalizedMsg)
            || preg_match('/\bwhich internship should i (choose|pick)\b/i', $normalizedMsg)) {
            $shortPhrases[$normalizedMsg] = "recommend_internship";
        }

        if (!$practiceHandled && isset($shortPhrases[$normalizedMsg])) {

            $intent     = $shortPhrases[$normalizedMsg];
            $confidence = 1.0;

        } elseif (!$practiceHandled) {

            $prediction = $classifier->predict($rawMessage);
            $intent     = $prediction["intent"];
            $confidence = $prediction["confidence"];

            if ($confidence < CHATBOT_CONFIDENCE_THRESHOLD) {
                $intent = "fallback";
            }
        }

        // ---- 3. Build the reply ----
        if ($intent === "greeting") {

            // Time-of-day aware greeting, and a different tone for a
            // student who has already been chatting in this session.
            $hour = (int)date("G");

            if ($hour < 12) {
                $timeGreeting = "Good morning";
            } elseif ($hour < 17) {
                $timeGreeting = "Good afternoon";
            } else {
                $timeGreeting = "Good evening";
            }

            if (empty($_SESSION["chat_history"])) {

                $response = $timeGreeting . ", " . htmlspecialchars($user_name)
                    . "! 👋 I'm your Internship Assistant. How can I help you today?";

            } else {

                $openers = [
                    "Sure, " . htmlspecialchars($user_name) . " — what else can I help with?",
                    "I'm still here, " . htmlspecialchars($user_name) . "! What's next?",
                    "Hey again! What would you like to know?",
                ];
                $response = $openers[array_rand($openers)];
            }

        } elseif ($intent === "goodbye") {

            $response = "Goodbye " . htmlspecialchars($user_name) . "! 👋 Good luck with your internship search.";

        } elseif ($intent === "thanks") {

            $templates = $responses["thanks"];
            $response  = str_replace("{name}", htmlspecialchars($user_name), $templates[array_rand($templates)]);

        } elseif ($intent === "practice") {

            // Practice replies are fully built by the stateful practice
            // handler above, so keep the generated question/feedback intact.
            $response = $response;

        } elseif ($intent === "application_status") {

            $sql = "SELECT status
                    FROM applications
                    WHERE student_id = ?
                    ORDER BY id DESC";

            $stmt = mysqli_prepare($conn, $sql);

            if ($stmt) {

                mysqli_stmt_bind_param($stmt, "i", $student_id);
                mysqli_stmt_execute($stmt);

                $result_set = mysqli_stmt_get_result($stmt);
                $allStatuses = [];

                while ($row = mysqli_fetch_assoc($result_set)) {
                    $allStatuses[] = $row["status"];
                }

                if (!empty($allStatuses)) {

                    $latest = $allStatuses[0];
                    $total  = count($allStatuses);
                    $countText = $total === 1
                        ? "You've submitted <b>1</b> application so far."
                        : "You've submitted <b>{$total}</b> applications so far.";

                    $response = "Your latest application status is: <b>"
                        . htmlspecialchars($latest) . "</b>. 📌<br>" . $countText;

                    $statusLower = strtolower($latest);

                    if (strpos($statusLower, "select") !== false || strpos($statusLower, "shortlist") !== false) {
                        $response .= "<br><br>Congratulations, " . htmlspecialchars($user_name) . "! 🎉 That's great news — keep an eye on <b>Notifications</b> for next steps.";
                    } elseif (strpos($statusLower, "reject") !== false) {
                        $response .= "<br><br>Sorry to hear that one didn't work out. Don't let it discourage you — check <b>Browse Internships</b> for more openings that match your skills. 💪";
                    } elseif (strpos($statusLower, "pending") !== false || strpos($statusLower, "review") !== false) {
                        $response .= "<br><br>It's still being reviewed — these things can take a little time. I'll let you know here if you ask again!";
                    }

                } else {

                    $response = "You haven't submitted any internship application yet. Want me to point you to <b>Browse Internships</b> so you can find one? 🔍";
                }

                mysqli_stmt_close($stmt);

            } else {

                $response = "Sorry, I couldn't check your application status right now.";
            }

        } elseif ($intent === "company_comparison") {

            // Never invent a company ranking. If the student did not name
            // two companies, ask for the names. If they did, compare the
            // internships currently listed using the student's skill match.
            $skillStmt = mysqli_prepare($conn, "SELECT skills FROM users WHERE id = ?");
            mysqli_stmt_bind_param($skillStmt, "i", $student_id);
            mysqli_stmt_execute($skillStmt);
            $skillRow = mysqli_fetch_assoc(mysqli_stmt_get_result($skillStmt));
            mysqli_stmt_close($skillStmt);

            $studentSkills = array_filter(array_unique(array_map(
                "trim",
                preg_split("/[,;]+/", strtolower($skillRow["skills"] ?? ""))
            )));

            $companyResult = mysqli_query($conn, "SELECT company_name, title, skills FROM internships ORDER BY company_name, id DESC");
            $companies = [];

            while ($row = mysqli_fetch_assoc($companyResult)) {
                $companyName = trim($row["company_name"] ?? "");
                if ($companyName === "") {
                    continue;
                }

                $key = strtolower($companyName);
                if (!isset($companies[$key])) {
                    $companies[$key] = [
                        "name" => $companyName,
                        "roles" => 0,
                        "best_match" => 0,
                        "best_role" => "",
                    ];
                }

                $companies[$key]["roles"]++;

                $requiredSkills = array_filter(array_unique(array_map(
                    "trim",
                    preg_split("/[,;]+/", strtolower($row["skills"] ?? ""))
                )));

                if (!empty($requiredSkills) && !empty($studentSkills)) {
                    $matched = 0;
                    foreach ($requiredSkills as $required) {
                        foreach ($studentSkills as $student) {
                            if (
                                $student === $required ||
                                strpos($student, $required) !== false ||
                                strpos($required, $student) !== false
                            ) {
                                $matched++;
                                break;
                            }
                        }
                    }

                    $matchPercent = round(($matched / count($requiredSkills)) * 100);
                    if ($matchPercent > $companies[$key]["best_match"]) {
                        $companies[$key]["best_match"] = $matchPercent;
                        $companies[$key]["best_role"] = $row["title"] ?? "";
                    }
                }
            }

            $mentioned = [];
            foreach ($companies as $company) {
                if (stripos($normalizedMsg, strtolower($company["name"])) !== false) {
                    $mentioned[strtolower($company["name"])] = $company;
                }
            }

            // If this is a follow-up to a recommendation, reuse those
            // companies instead of making the student repeat their names.
            if (count($mentioned) < 2 && !empty($_SESSION["last_recommendations"])) {
                foreach ($_SESSION["last_recommendations"] as $previousMatch) {
                    $key = strtolower($previousMatch["company"] ?? "");
                    if ($key !== "" && isset($companies[$key])) {
                        $mentioned[$key] = $companies[$key];
                    }
                }
            }

            if (count($mentioned) < 2) {
                $response = "Sure — I can compare them, but I need the company names first. 😊<br><br>For example, ask:<br>• <b>Compare Alpha Nova Technologies and GreenByte Solutions</b><br>• <b>Which company is better for my skills?</b><br><br>If you just asked me for internship recommendations, I can also compare those recommendations for you.";
            } else {
                $compare = array_values($mentioned);
                usort($compare, function ($a, $b) {
                    return $b["best_match"] <=> $a["best_match"];
                });

                $first = $compare[0];
                $second = $compare[1];

                if ($first["best_match"] > $second["best_match"]) {
                    $winnerText = "Based on your profile skills, <b>" . htmlspecialchars($first["name"]) . "</b> is the stronger match right now.";
                } elseif ($first["best_match"] < $second["best_match"]) {
                    $winnerText = "Based on your profile skills, <b>" . htmlspecialchars($second["name"]) . "</b> is the stronger match right now.";
                } else {
                    $winnerText = "Both companies are an equal skill match from the internships currently listed.";
                }

                $response = $winnerText . "<br><br>";
                foreach ([$first, $second] as $company) {
                    $response .= "🏢 <b>" . htmlspecialchars($company["name"]) . "</b><br>";
                    $response .= "• " . (int)$company["roles"] . " internship" . ($company["roles"] === 1 ? "" : "s") . " listed<br>";
                    $response .= "• Best skill match: <b>" . (int)$company["best_match"] . "%</b>";
                    if ($company["best_role"] !== "") {
                        $response .= " — " . htmlspecialchars($company["best_role"]);
                    }
                    $response .= "<br><br>";
                }

                $response .= "This comparison is based on the internships currently posted in the system, not a general ranking of the companies. 🔎";
            }

        } elseif ($intent === "recommend_internship") {

            // ---- Get the student's skills from their profile ----
            $skillStmt = mysqli_prepare($conn, "SELECT skills FROM users WHERE id = ?");
            mysqli_stmt_bind_param($skillStmt, "i", $student_id);
            mysqli_stmt_execute($skillStmt);
            $skillRow = mysqli_fetch_assoc(mysqli_stmt_get_result($skillStmt));
            mysqli_stmt_close($skillStmt);

            $studentSkillsText = $skillRow["skills"] ?? "";
            $studentSkills = array_filter(array_unique(array_map(
                "trim",
                preg_split("/[,;]+/", strtolower($studentSkillsText))
            )));

            if (empty($studentSkills)) {

                $response = "I don't see any skills in your profile yet. Please add your skills in <b>My Profile</b> so I can recommend the best-matching companies and internships for you. 🎯";

            } else {

                // ---- Compare student skills against every posted internship ----
                $internshipsResult = mysqli_query(
                    $conn,
                    "SELECT title, company_name, skills FROM internships ORDER BY id DESC"
                );

                $matches = [];

                while ($row = mysqli_fetch_assoc($internshipsResult)) {

                    $requiredSkills = array_filter(array_unique(array_map(
                        "trim",
                        preg_split("/[,;]+/", strtolower($row["skills"] ?? ""))
                    )));

                    if (empty($requiredSkills)) {
                        continue;
                    }

                    $matchedSkills = [];

                    foreach ($requiredSkills as $required) {
                        foreach ($studentSkills as $student) {
                            if (
                                $student === $required ||
                                strpos($student, $required) !== false ||
                                strpos($required, $student) !== false
                            ) {
                                $matchedSkills[] = $required;
                                break;
                            }
                        }
                    }

                    $matchedSkills  = array_unique($matchedSkills);
                    $missingSkills  = array_diff($requiredSkills, $matchedSkills);
                    $matchPercent   = round((count($matchedSkills) / count($requiredSkills)) * 100);

                    $matches[] = [
                        "title"      => $row["title"],
                        "company"    => $row["company_name"],
                        "percentage" => $matchPercent,
                        "missing"    => array_values($missingSkills),
                    ];
                }

                usort($matches, function ($a, $b) {
                    return $b["percentage"] <=> $a["percentage"];
                });

                $topMatches = array_slice($matches, 0, 3);
                $_SESSION["last_recommendations"] = $topMatches;

                if (empty($topMatches)) {

                    $response = "There are no internships posted yet. Please check <b>Browse Internships</b> again later. 📭";

                } else {

                    $response = "Based on the skills in your profile, these are your strongest internship matches right now:<br><br>";

                    foreach ($topMatches as $match) {

                        $response .= "🎯 <b>" . htmlspecialchars($match["title"]) . "</b><br>"
                            . "&nbsp;&nbsp;Company: <b>" . htmlspecialchars($match["company"]) . "</b><br>"
                            . "&nbsp;&nbsp;Skill match: <b>" . $match["percentage"] . "%</b><br>";

                        if (!empty($match["missing"])) {
                            $response .= "&nbsp;&nbsp;Missing skills: <b>"
                                . htmlspecialchars(implode(", ", array_slice($match["missing"], 0, 3)))
                                . "</b><br>";
                        }

                        $response .= "<br>";
                    }

                    $response .= "Open <b>Browse Internships</b> to see the full list and apply. 🔍";
                }
            }

        } elseif ($intent === "notifications") {

            $notifStmt = mysqli_prepare(
                $conn,
                "SELECT message, is_read, created_at FROM notifications
                 WHERE user_id = ? ORDER BY created_at DESC LIMIT 5"
            );
            mysqli_stmt_bind_param($notifStmt, "i", $student_id);
            mysqli_stmt_execute($notifStmt);
            $notifResult = mysqli_stmt_get_result($notifStmt);

            $allNotifs   = [];
            $unreadCount = 0;

            while ($row = mysqli_fetch_assoc($notifResult)) {
                $allNotifs[] = $row;
                if ((int)$row["is_read"] === 0) {
                    $unreadCount++;
                }
            }
            mysqli_stmt_close($notifStmt);

            if (empty($allNotifs)) {

                $response = "You're all caught up, " . htmlspecialchars($user_name) . " — no notifications right now. 🎉";

            } else {

                if ($unreadCount > 0) {
                    $response = "🔔 You have <b>{$unreadCount}</b> unread notification" . ($unreadCount > 1 ? "s" : "") . ":<br><br>";
                } else {
                    $response = "No new notifications, but here's your recent activity:<br><br>";
                }

                foreach (array_slice($allNotifs, 0, 3) as $notif) {

                    $daysAgo = (int)((time() - strtotime($notif["created_at"])) / 86400);

                    if ($daysAgo <= 0) {
                        $timeText = "today";
                    } elseif ($daysAgo === 1) {
                        $timeText = "yesterday";
                    } else {
                        $timeText = $daysAgo . " days ago";
                    }

                    $unreadDot = ((int)$notif["is_read"] === 0) ? "🔵 " : "";

                    $response .= $unreadDot . htmlspecialchars($notif["message"])
                        . " <span style='color:#888;font-size:0.85em;'>(" . $timeText . ")</span><br>";
                }

                $response .= "<br>See all of them in <b>Notifications</b>. 🔔";
            }

        } elseif ($intent === "profile") {

            $profStmt = mysqli_prepare($conn, "SELECT skills, education, resume FROM users WHERE id = ?");
            mysqli_stmt_bind_param($profStmt, "i", $student_id);
            mysqli_stmt_execute($profStmt);
            $profData = mysqli_fetch_assoc(mysqli_stmt_get_result($profStmt));
            mysqli_stmt_close($profStmt);

            $checklist = [
                "Education" => !empty($profData["education"]),
                "Skills"    => !empty($profData["skills"]),
                "Resume"    => !empty($profData["resume"]),
            ];

            $filledCount = count(array_filter($checklist));
            $totalCount  = count($checklist);

            if ($filledCount === $totalCount) {

                $response = "Your profile looks complete, " . htmlspecialchars($user_name) . "! ✅ Nice work — that gives you the best chance of matching with internships.<br><br>You can still update anything anytime from <b>My Profile</b>. 👤";

            } else {

                $response = "Here's your profile status, " . htmlspecialchars($user_name)
                    . " (<b>{$filledCount}/{$totalCount}</b> complete):<br><br>";

                foreach ($checklist as $field => $isFilled) {
                    $icon = $isFilled ? "✅" : "❌";
                    $response .= $icon . " " . $field . "<br>";
                }

                $response .= "<br>Filling in what's missing in <b>My Profile</b> helps me match you to better internships. 👤";
            }

        } elseif ($intent === "resume_trouble") {

            $response = "Sorry to hear that, " . htmlspecialchars($user_name) . " — let's sort it out. A few common fixes:<br><br>"
                . "📄 Make sure the file is a <b>PDF</b> (some systems reject .docx or images)<br>"
                . "📦 Check the file is under the size limit (usually 2–5 MB)<br>"
                . "🔄 Try refreshing the page before uploading again<br>"
                . "🌐 If it still fails, try a different browser<br><br>"
                . "Still stuck after trying these? Let your admin/faculty know, or try again in a few minutes. 🙏";

        } elseif ($intent === "apply_internship" || $intent === "resume") {

            $templates = $responses[$intent];
            $response  = str_replace("{name}", htmlspecialchars($user_name), $templates[array_rand($templates)]);

            // Personal nudge: check if this student has a resume on file yet.
            $resStmt = mysqli_prepare($conn, "SELECT resume, skills FROM users WHERE id = ?");
            mysqli_stmt_bind_param($resStmt, "i", $student_id);
            mysqli_stmt_execute($resStmt);
            $profileRow = mysqli_fetch_assoc(mysqli_stmt_get_result($resStmt));
            mysqli_stmt_close($resStmt);

            $hasResume = !empty($profileRow["resume"]);
            $hasSkills = !empty($profileRow["skills"]);

            if ($intent === "apply_internship" && !$hasResume) {
                $response .= "<br><br>By the way, I noticed you haven't uploaded a resume yet — companies usually look for one, so it's worth adding it in <b>My Profile</b> first. 📑";
            } elseif ($intent === "apply_internship" && !$hasSkills) {
                $response .= "<br><br>Also, your <b>Skills</b> field in your profile looks empty — filling it in helps me (and companies) match you to the right internships. 🎯";
            }

        } elseif (isset($responses[$intent])) {

            $templates = $responses[$intent];
            $response  = str_replace("{name}", htmlspecialchars($user_name), $templates[array_rand($templates)]);

        } else {

            $templates = $responses["fallback"];
            $response  = $templates[array_rand($templates)];
        }

        // ---- 4. Log the conversation (optional — skips silently if the
        //         chatbot_logs table doesn't exist yet) ----
        $logSql  = "INSERT INTO chatbot_logs (student_id, message, intent, confidence) VALUES (?, ?, ?, ?)";
        $logStmt = @mysqli_prepare($conn, $logSql);

        if ($logStmt) {
            mysqli_stmt_bind_param($logStmt, "issd", $student_id, $rawMessage, $intent, $confidence);
            mysqli_stmt_execute($logStmt);
            mysqli_stmt_close($logStmt);
        }

        // ---- 5. Save this exchange into the session chat history ----
        $_SESSION["chat_history"][] = [
            "user" => $rawMessage,
            "bot"  => $response,
        ];
    }

    // Redirect after POST so refreshing the page (F5) doesn't resend the message
    header("Location: chatbot.php");
    exit();
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Student Chatbot</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f4f6fb;
}

.chat-container {
    width: 90%;
    max-width: 700px;
    margin: 50px auto;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    overflow: hidden;
}

.chat-header {
    background: #667eea;
    color: white;
    padding: 20px;
    text-align: center;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chat-header h2 {
    margin-bottom: 5px;
}

.chat-header p {
    font-size: 14px;
}

.clear-btn {
    background: rgba(255,255,255,0.2);
    color: white;
    border: none;
    padding: 8px 14px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    text-decoration: none;
    white-space: nowrap;
}

.clear-btn:hover {
    background: rgba(255,255,255,0.35);
}

.chat-body {
    min-height: 400px;
    max-height: 500px;
    overflow-y: auto;
    padding: 25px;
}

.bot-message {
    background: #f0f2ff;
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 20px;
    width: fit-content;
    max-width: 80%;
    line-height: 1.6;
}

.user-message {
    background: #667eea;
    color: white;
    padding: 15px;
    border-radius: 12px;
    margin: 10px 0 10px auto;
    width: fit-content;
    max-width: 80%;
    line-height: 1.6;
}

.quick-actions {
    padding: 0 15px 8px;
    border-top: 1px solid #eee;
}

.quick-form {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.quick-form button {
    border: 1px solid #9aa9f8;
    background: #f4f6ff;
    color: #4f63d8;
    border-radius: 18px;
    padding: 8px 13px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
}

.quick-form button:hover {
    background: #e9edff;
}

.chat-form {
    display: flex;
    padding: 15px;
    border-top: 1px solid #ddd;
    gap: 10px;
}

.chat-form input {
    flex: 1;
    padding: 13px;
    border: 1px solid #ccc;
    border-radius: 8px;
    outline: none;
}

.chat-form button {
    padding: 13px 22px;
    border: none;
    background: #667eea;
    color: white;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
}

.chat-form button:hover {
    background: #5568d8;
}

.back-btn {
    display: inline-block;
    margin: 20px;
    padding: 10px 18px;
    background: #667eea;
    color: white;
    text-decoration: none;
    border-radius: 7px;
}

</style>

</head>

<body>

<div class="chat-container">

    <div class="chat-header">

        <div>
            <h2>🤖 Internship Assistant</h2>
            <p>Ask me anything about your internship</p>
        </div>

        <a href="chatbot.php?clear=1" class="clear-btn">Clear chat</a>

    </div>


    <div class="chat-body">

        <div class="bot-message">

            👋 Hello <?php echo htmlspecialchars($user_name); ?>!

            <br><br>

            I am your Internship Assistant.

            <br>

            You can ask me about internships, applications,
            application status, resume, mock tests and profile.
            <br><br>
            You can also type <b>practice</b> to start a practice test. 🧠

        </div>


        <?php foreach ($_SESSION["chat_history"] as $chat): ?>

            <div class="user-message">
                <?php echo htmlspecialchars($chat["user"]); ?>
            </div>

            <div class="bot-message">
                <?php echo $chat["bot"]; ?>
            </div>

        <?php endforeach; ?>

    </div>


    <div class="quick-actions">
        <form method="POST" class="quick-form">
            <button type="submit" name="message" value="practice">🧠 Practice</button>
            <button type="submit" name="message" value="internships">🔎 Internships</button>
            <button type="submit" name="message" value="my status">📌 My Status</button>
        </form>
    </div>

    <form method="POST" class="chat-form">

        <input
            type="text"
            name="message"
            placeholder="Type your question..."
            required
            autofocus
        >

        <button type="submit">
            Send
        </button>

    </form>

</div>


<a href="dashboard.php" class="back-btn">
    ← Back to Dashboard
</a>

<script>
// Auto-scroll chat to the latest message
var chatBody = document.querySelector('.chat-body');
chatBody.scrollTop = chatBody.scrollHeight;
</script>

</body>

</html>
