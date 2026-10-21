<?php

session_start();

require_once __DIR__ . "/../config/db.php";


/* ==================================================
   STUDENT LOGIN CHECK
================================================== */

if (!isset($_SESSION["user_id"])) {

    header("Location: ../login.php");
    exit();

}

if (($_SESSION["user_role"] ?? "") !== "student") {

    header("Location: ../login.php");
    exit();

}

$student_id = (int)$_SESSION["user_id"];


/* ==================================================
   GET NOTIFICATIONS
================================================== */

$sql = "
    SELECT *
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {

    die("Database Error: " . mysqli_error($conn));

}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $student_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

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
    Notifications - Digital Internship
</title>


<style>

* {

    margin: 0;
    padding: 0;
    box-sizing: border-box;

}


body {

    font-family: Arial, sans-serif;

    background: #f4f6fb;

    color: #333;

}


.page-container {

    min-height: 100vh;

    display: flex;

}


/* ==================================================
   SIDEBAR
================================================== */

.sidebar {

    width: 250px;

    min-height: 100vh;

    background: white;

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


/* ==================================================
   MAIN CONTENT
================================================== */

.main-content {

    margin-left: 250px;

    width: calc(100% - 250px);

    padding: 30px;

    min-height: 100vh;

    background: #f4f6fb;

}


/* ==================================================
   TOPBAR
================================================== */

.topbar {

    background: white;

    padding: 25px;

    border-radius: 12px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.08);

}


.topbar h1 {

    color: #667eea;

    margin-bottom: 8px;

}


.topbar p {

    color: #777;

}


/* ==================================================
   NOTIFICATION
================================================== */

.notification {

    background: white;

    padding: 20px;

    margin-bottom: 15px;

    border-radius: 12px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.08);

    border-left: 5px solid #667eea;

}


.notification.unread {

    background: #f3f4ff;

}


.notification-message {

    font-size: 16px;

    color: #333;

    margin-bottom: 10px;

    line-height: 1.5;

}


.notification-date {

    font-size: 13px;

    color: #888;

    margin-bottom: 12px;

}


/* ==================================================
   VIEW INTERVIEW LETTER BUTTON
================================================== */

.view-letter-btn {

    display: inline-block;

    text-decoration: none;

    background: #6f42c1;

    color: white;

    padding: 10px 18px;

    border-radius: 7px;

    font-size: 14px;

    font-weight: bold;

    margin-top: 5px;

    transition: 0.3s;

}


.view-letter-btn:hover {

    background: #59359c;

    color: white;

}


/* ==================================================
   VIEW SHORTLIST BUTTON
================================================== */

.view-shortlist-btn {

    display: inline-block;

    text-decoration: none;

    background: #198754;

    color: white;

    padding: 10px 18px;

    border-radius: 7px;

    font-size: 14px;

    font-weight: bold;

    margin-top: 5px;

    transition: 0.3s;

}


.view-shortlist-btn:hover {

    background: #146c43;

    color: white;

}


/* ==================================================
   EMPTY
================================================== */

.no-notifications {

    background: white;

    padding: 50px;

    text-align: center;

    border-radius: 12px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.08);

}


.no-notifications h2 {

    color: #667eea;

    margin-bottom: 10px;

}


.no-notifications p {

    color: #777;

}


/* ==================================================
   MOBILE
================================================== */

@media (max-width: 700px) {

    .sidebar {

        width: 200px;

    }


    .main-content {

        margin-left: 200px;

        width: calc(100% - 200px);

        padding: 15px;

    }

}

</style>

</head>


<body>


<div class="page-container">


    <!-- ==================================================
         SIDEBAR
    ================================================== -->

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

            <a href="browse.php">

                🔍 Browse Internships

            </a>


            <a href="applications.php">

                📄 My Applications

            </a>


            <a href="mock_test.php">

                📝 Mock Tests

            </a>


            <a href="test_scores.php">

                📊 Test Scores

            </a>


            <a
                href="notifications.php"
                class="active"
            >

                🔔 Notifications

            </a>


            <a href="chatbot.php">
                🤖 Internship Assistant
            </a>

            <a href="../logout.php">

                🚪 Logout

            </a>


        </nav>


    </aside>


    <!-- ==================================================
         MAIN CONTENT
    ================================================== -->

    <main class="main-content">


        <div class="topbar">

            <h1>
                🔔 Notifications
            </h1>

            <p>
                Stay updated about your internship applications.
            </p>

        </div>


        <?php if (
            $result &&
            mysqli_num_rows($result) > 0
        ): ?>


            <?php while (
                $row = mysqli_fetch_assoc($result)
            ): ?>


                <?php

                /* ------------------------------------------
                   DETERMINE NOTIFICATION TYPE AND LINK
                ------------------------------------------ */

                $notification_type = "general";
                $notification_link = "";

                $message_lower = strtolower(
                    trim($row["message"] ?? "")
                );

                if (
                    stripos($message_lower, "interview letter issued") !== false
                ) {
                    $notification_type = "interview";
                } elseif (
                    stripos($message_lower, "has been shortlisted") !== false
                    || stripos($message_lower, "application has been shortlisted") !== false
                ) {
                    $notification_type = "shortlisted";
                }


                /* ------------------------------------------
                   USE SAVED LINK IF AVAILABLE
                ------------------------------------------ */

                if (!empty($row["link"])) {

                    $notification_link =
                        $row["link"];

                }


                /* ------------------------------------------
                   FALLBACK FOR INTERVIEW LETTER
                ------------------------------------------ */

                if (
                    empty($notification_link) &&
                    stripos(
                        $row["message"],
                        "Interview Letter issued"
                    ) !== false
                ) {


                    /*
                     * Find the latest interview letter
                     * belonging to this student.
                     */

                    $letter_sql = "
                        SELECT id
                        FROM interview_letters
                        WHERE student_id = ?
                        ORDER BY created_at DESC
                        LIMIT 1
                    ";


                    $letter_stmt = mysqli_prepare(
                        $conn,
                        $letter_sql
                    );


                    if ($letter_stmt) {


                        mysqli_stmt_bind_param(
                            $letter_stmt,
                            "i",
                            $student_id
                        );


                        mysqli_stmt_execute(
                            $letter_stmt
                        );


                        $letter_result =
                            mysqli_stmt_get_result(
                                $letter_stmt
                            );


                        $letter_row =
                            mysqli_fetch_assoc(
                                $letter_result
                            );


                        if ($letter_row) {

                            $notification_link =
                                "interview_letter.php?id=" .
                                (int)$letter_row["id"];

                        }


                        mysqli_stmt_close(
                            $letter_stmt
                        );

                    }

                }

                ?>


                <div class="notification
                    <?php

                    if (
                        (int)$row["is_read"] === 0
                    ) {

                        echo "unread";

                    }

                    ?>"
                >


                    <!-- MESSAGE -->

                    <div class="notification-message">

                        🔔

                        <?= htmlspecialchars(
                            $row["message"]
                        ) ?>

                    </div>


                    <!-- DATE -->

                    <div class="notification-date">

                        📅

                        <?php

                        if (
                            !empty(
                                $row["created_at"]
                            )
                        ) {

                            echo date(
                                "d M Y, h:i A",
                                strtotime(
                                    $row["created_at"]
                                )
                            );

                        }

                        ?>

                    </div>


                    <!-- ==================================================
                         NOTIFICATION ACTION BUTTON
                    ================================================== -->

                    <?php if (
                        $notification_type === "interview" &&
                        !empty($notification_link)
                    ): ?>

                        <a
                            href="<?= htmlspecialchars(
                                $notification_link
                            ) ?>"
                            class="view-letter-btn"
                        >
                            📄 View Interview Letter
                        </a>

                    <?php elseif (
                        $notification_type === "shortlisted"
                    ): ?>

                        <a
                            href="applications.php"
                            class="view-shortlist-btn"
                        >
                            ✅ View Shortlist
                        </a>

                    <?php endif; ?>

                </div>


            <?php endwhile; ?>


        <?php else: ?>


            <div class="no-notifications">

                <h2>
                    No Notifications
                </h2>

                <p>
                    You don't have any notifications yet.
                </p>

            </div>


        <?php endif; ?>


    </main>


</div>


</body>

</html>