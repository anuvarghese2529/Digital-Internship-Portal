<?php

session_start();

date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/db.php";

$user_id = (int)$_SESSION["user_id"];


/* =========================================================
   GET STUDENT SKILLS
========================================================= */

$user_sql = "
    SELECT skills
    FROM users
    WHERE id = ?
";

$user_stmt = mysqli_prepare($conn, $user_sql);

if (!$user_stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $user_stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($user_stmt);

$user_result = mysqli_stmt_get_result($user_stmt);

if (mysqli_num_rows($user_result) === 0) {
    die("Student not found.");
}

$user = mysqli_fetch_assoc($user_result);

mysqli_stmt_close($user_stmt);


/* =========================================================
   STUDENT SKILLS
========================================================= */

$student_skills_text = $user["skills"] ?? "";

$student_skills = [];

if (!empty($student_skills_text)) {

    $student_skills = preg_split(
        "/[,;]+/",
        strtolower($student_skills_text)
    );

    $student_skills = array_map(
        "trim",
        $student_skills
    );

    $student_skills = array_filter(
        $student_skills
    );

    $student_skills = array_unique(
        $student_skills
    );
}


/* =========================================================
   GET ALL INTERNSHIPS
========================================================= */

$sql = "
    SELECT *
    FROM internships
    ORDER BY id DESC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Database Error: " . mysqli_error($conn));
}


/* =========================================================
   SKILL MATCH FUNCTION
========================================================= */

function calculateSkillMatch(
    $student_skills,
    $internship_skills_text
) {

    if (empty($internship_skills_text)) {

        return [
            "percentage" => 0,
            "matched" => [],
            "status" => "No Skills",
            "can_apply" => false
        ];
    }


    $required_skills = preg_split(
        "/[,;]+/",
        strtolower($internship_skills_text)
    );

    $required_skills = array_map(
        "trim",
        $required_skills
    );

    $required_skills = array_filter(
        $required_skills
    );

    $required_skills = array_unique(
        $required_skills
    );


    if (count($required_skills) === 0) {

        return [
            "percentage" => 0,
            "matched" => [],
            "status" => "No Skills",
            "can_apply" => false
        ];
    }


    $matched_skills = [];


    foreach ($required_skills as $required) {

        foreach ($student_skills as $student) {

            if ($student === $required) {

                $matched_skills[] = $required;

                break;
            }
        }
    }


    $matched_skills = array_unique(
        $matched_skills
    );


    $percentage = round(
        (
            count($matched_skills) /
            count($required_skills)
        ) * 100
    );


    if ($percentage >= 80) {

        $status = "Perfect Match";

    } elseif ($percentage >= 60) {

        $status = "Eligible Match";

    } else {

        $status = "Low Match";
    }


    return [
        "percentage" => $percentage,
        "matched" => $matched_skills,
        "status" => $status,
        "can_apply" => ($percentage >= 60)
    ];
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Browse Internships - Digital Internship
</title>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}


/* =========================================================
   BODY
========================================================= */

body {
    font-family: Arial, sans-serif;
    background: #f5f6fa;
    color: #333;
}


/* =========================================================
   LAYOUT
========================================================= */

.page-container {
    min-height: 100vh;
    display: flex;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    width: 250px;

    min-height: 100vh;

    background: #ffffff;

    padding: 25px 15px;

    box-shadow:
        3px 0 15px rgba(0,0,0,0.15);

    position: fixed;

    left: 0;

    top: 0;
}


.sidebar-header {

    text-align: center;

    margin-bottom: 30px;
}


.sidebar-header h2 {

    color: #667eea;

    font-size: 22px;

    margin-bottom: 5px;
}


.sidebar-header p {

    color: #777;

    font-size: 13px;
}


.sidebar nav {

    display: flex;

    flex-direction: column;

    gap: 8px;
}


.sidebar nav a {

    text-decoration: none;

    color: #444;

    padding: 12px 15px;

    border-radius: 8px;

    font-size: 15px;

    transition: 0.3s;
}


.sidebar nav a:hover,
.sidebar nav a.active {

    background: #667eea;

    color: white;
}


/* =========================================================
   MAIN CONTENT
========================================================= */

.main-content {

    margin-left: 250px;

    width: calc(100% - 250px);

    padding: 30px;

    min-height: 100vh;
}


/* =========================================================
   BACK TO DASHBOARD BUTTON
========================================================= */

.dashboard-button {

    display: inline-block;

    background: #667eea;

    color: white;

    text-decoration: none;

    padding: 12px 20px;

    border-radius: 8px;

    font-size: 15px;

    font-weight: bold;

    margin-bottom: 20px;

    box-shadow:
        0 4px 10px rgba(102,126,234,0.25);

    transition: 0.3s;
}


.dashboard-button:hover {

    background: #5568d8;

    color: white;

    text-decoration: none;

    transform: translateY(-1px);
}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    background: white;

    padding: 22px 25px;

    border-radius: 12px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.08);
}


.topbar h1 {

    color: #333;

    margin-bottom: 5px;
}


.topbar p {

    color: #777;
}


/* =========================================================
   STUDENT SKILLS
========================================================= */

.student-skills {

    background: white;

    padding: 18px 22px;

    border-radius: 12px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.08);
}


.student-skills strong {

    color: #667eea;
}


/* =========================================================
   INTERNSHIP GRID
========================================================= */

.internship-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 20px;
}


.internship-card {

    background: white;

    padding: 22px;

    border-radius: 12px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.08);

    transition: 0.3s;
}


.internship-card:hover {

    transform:
        translateY(-5px);
}


.internship-card h2 {

    color: #667eea;

    font-size: 20px;

    margin-bottom: 8px;
}


.company {

    font-weight: bold;

    color: #333;

    margin-bottom: 12px;
}


.description {

    color: #666;

    line-height: 1.5;

    margin-bottom: 15px;
}


.info {

    margin-bottom: 8px;

    color: #555;

    font-size: 14px;
}


.info strong {

    color: #333;
}


/* =========================================================
   SKILLS
========================================================= */

.skills {

    background: #f3f4ff;

    color: #667eea;

    padding: 10px;

    border-radius: 8px;

    margin: 15px 0;

    font-size: 14px;
}


/* =========================================================
   MATCH BOX
========================================================= */

.match-box {

    padding: 12px;

    border-radius: 8px;

    margin-top: 15px;

    font-weight: bold;

    font-size: 14px;
}


.match-box.perfect {

    background: #d4edda;

    color: #155724;
}


.match-box.good {

    background: #fff3cd;

    color: #856404;
}


.match-box.low {

    background: #f8d7da;

    color: #721c24;
}


.match-percentage {

    font-size: 18px;

    margin-bottom: 4px;
}


.matched-skills {

    font-size: 13px;

    margin-top: 5px;

    font-weight: normal;
}


/* =========================================================
   APPLY BUTTON
========================================================= */

.apply-btn {

    display: block;

    width: 100%;

    text-align: center;

    text-decoration: none;

    background: #667eea;

    color: white;

    padding: 12px;

    border-radius: 8px;

    font-weight: bold;

    margin-top: 15px;
}


.apply-btn:hover {

    background: #5568d8;

    color: white;
}


.apply-btn.disabled {

    background: #adb5bd;

    cursor: not-allowed;

    pointer-events: none;
}


/* =========================================================
   CLOSED BUTTON
========================================================= */

.closed-btn {

    display: block;

    width: 100%;

    text-align: center;

    background: #6c757d;

    color: white;

    padding: 12px;

    border-radius: 8px;

    font-weight: bold;

    margin-top: 15px;

    cursor: not-allowed;
}


.closed-note {

    margin-top: 10px;

    padding: 9px;

    border-radius: 8px;

    background: #f8d7da;

    color: #721c24;

    font-size: 13px;

    text-align: center;
}


/* =========================================================
   MINIMUM NOTE
========================================================= */

.minimum-note {

    margin-top: 10px;

    padding: 9px;

    border-radius: 8px;

    background: #f8d7da;

    color: #721c24;

    font-size: 13px;

    text-align: center;
}


/* =========================================================
   NO DATA
========================================================= */

.no-data {

    background: white;

    padding: 40px;

    text-align: center;

    border-radius: 12px;

    color: #777;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1000px) {

    .internship-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }
}


@media (max-width: 700px) {

    .sidebar {

        width: 200px;
    }


    .main-content {

        margin-left: 200px;

        width:
            calc(100% - 200px);

        padding: 15px;
    }


    .internship-grid {

        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<div class="page-container">


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">


    <div class="sidebar-header">

        <h2>
            Digital Internship
        </h2>

        <p>
            Student Portal
        </p>

    </div>


    <nav>


        <a href="dashboard.php">
            🏠 Dashboard
        </a>


        <a href="profile.php">
            👤 My Profile
        </a>


        <a
            href="browse.php"
            class="active"
        >
            🔍 Browse Internships
        </a>


        <a href="applications.php">
            📄 My Applications
        </a>


        <a href="mock_tests.php">
            📝 Mock Tests
        </a>


        <a href="test_scores.php">
            📊 Test Scores
        </a>


        <a href="notifications.php">
            🔔 Notifications
        </a>


        <a href="../logout.php">
            🚪 Logout
        </a>


    </nav>

</aside>



<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<main class="main-content">


    <!-- =================================================
         BACK TO DASHBOARD
    ================================================= -->

    <a
        href="dashboard.php"
        class="dashboard-button"
    >
        ← Back to Dashboard
    </a>


    <!-- =================================================
         TOPBAR
    ================================================= -->

    <div class="topbar">

        <h1>
            Browse Internships
        </h1>

        <p>
            Find internships that match
            your skills and interests.
        </p>

    </div>


    <!-- =================================================
         STUDENT SKILLS
    ================================================= -->

    <div class="student-skills">

        <strong>
            Your Skills:
        </strong>


        <?php

        if (!empty($student_skills)) {

            echo htmlspecialchars(
                implode(
                    ", ",
                    $student_skills
                )
            );

        } else {

            echo "No skills added yet.";

        }

        ?>

    </div>



<?php if ($result && mysqli_num_rows($result) > 0): ?>


    <div class="internship-grid">


<?php while ($row = mysqli_fetch_assoc($result)): ?>


<?php

/* =====================================================
   SKILL MATCH
===================================================== */

$match = calculateSkillMatch(
    $student_skills,
    $row["skills"] ?? ""
);


if ($match["percentage"] >= 80) {

    $match_class = "perfect";

} elseif ($match["percentage"] >= 60) {

    $match_class = "good";

} else {

    $match_class = "low";
}


$can_apply =
    $match["percentage"] >= 60;


/* =====================================================
   DEADLINE CHECK
===================================================== */

$today = date("Y-m-d");

$application_closed = false;

if (!empty($row["deadline"])) {

    if ($today > $row["deadline"]) {

        $application_closed = true;
    }
}


/* =====================================================
   DATE DISPLAY
===================================================== */

$start_date = "";

$end_date = "";

$deadline = "";


if (!empty($row["start_date"])) {

    $start_date = date(
        "d M Y",
        strtotime($row["start_date"])
    );
}


if (!empty($row["end_date"])) {

    $end_date = date(
        "d M Y",
        strtotime($row["end_date"])
    );
}


if (!empty($row["deadline"])) {

    $deadline = date(
        "d M Y",
        strtotime($row["deadline"])
    );
}

?>


        <div class="internship-card">


            <!-- TITLE -->

            <h2>

                <?= htmlspecialchars(
                    $row["title"]
                ) ?>

            </h2>


            <!-- COMPANY -->

            <div class="company">

                🏢

                <?= htmlspecialchars(
                    $row["company_name"]
                ) ?>

            </div>


            <!-- DESCRIPTION -->

            <div class="description">

                <?= nl2br(
                    htmlspecialchars(
                        $row["description"]
                    )
                ) ?>

            </div>


            <!-- LOCATION -->

            <div class="info">

                📍

                <strong>
                    Location:
                </strong>

                <?= htmlspecialchars(
                    $row["location"]
                    ?? "Not specified"
                ) ?>

            </div>


            <!-- DURATION -->

            <div class="info">

                ⏳

                <strong>
                    Duration:
                </strong>

                <?= htmlspecialchars(
                    $row["duration"]
                    ?? "Not specified"
                ) ?>

            </div>


            <!-- START DATE -->

            <div class="info">

                📅

                <strong>
                    Start Date:
                </strong>

                <?= $start_date !== ""
                    ? htmlspecialchars($start_date)
                    : "Not specified"
                ?>

            </div>


            <!-- END DATE -->

            <div class="info">

                🏁

                <strong>
                    End Date:
                </strong>

                <?= $end_date !== ""
                    ? htmlspecialchars($end_date)
                    : "Not specified"
                ?>

            </div>


            <!-- DEADLINE -->

            <div class="info">

                ⏰

                <strong>
                    Application Deadline:
                </strong>

                <?= $deadline !== ""
                    ? htmlspecialchars($deadline)
                    : "Not specified"
                ?>

            </div>


            <!-- REQUIRED SKILLS -->

            <div class="skills">

                <strong>
                    Skills Required:
                </strong>

                <br>

                <?= htmlspecialchars(
                    $row["skills"]
                    ?? "Not specified"
                ) ?>

            </div>


            <!-- SKILL MATCH -->

            <div class="match-box <?= $match_class ?>">


                <div class="match-percentage">

                    🎯

                    <?= $match["percentage"] ?>% Match

                </div>


                <div>

                    <?= htmlspecialchars(
                        $match["status"]
                    ) ?>

                </div>


<?php if (!empty($match["matched"])): ?>

                    <div class="matched-skills">

                        Matched Skills:

                        <?= htmlspecialchars(
                            implode(
                                ", ",
                                $match["matched"]
                            )
                        ) ?>

                    </div>

<?php endif; ?>


            </div>


            <!-- =================================================
                 APPLY STATUS
            ================================================= -->


<?php if ($application_closed): ?>


            <!-- DEADLINE PASSED -->

            <div class="closed-btn">

                ❌ Application Closed

            </div>


            <div class="closed-note">

                The application deadline has passed.

                <br>

                Deadline:

                <strong>
                    <?= htmlspecialchars($deadline) ?>
                </strong>

            </div>


<?php elseif ($can_apply): ?>


            <!-- APPLICATION OPEN -->

            <a
                href="apply.php?id=<?= (int)$row["id"] ?>"
                class="apply-btn"
            >

                Apply Now

            </a>


<?php else: ?>


            <!-- LOW SKILL MATCH -->

            <div class="apply-btn disabled">

                Apply Not Available

            </div>


            <div class="minimum-note">

                Minimum 60% skill match is required to apply.

            </div>


<?php endif; ?>


        </div>


<?php endwhile; ?>


    </div>


<?php else: ?>


    <div class="no-data">

        <h2>
            No Internships Available
        </h2>

        <br>

        <p>
            Currently there are no internship opportunities available.
        </p>

    </div>


<?php endif; ?>


</main>


</div>


</body>

</html>