<?php

session_start();

date_default_timezone_set("Asia/Kolkata");

require_once "../config/db.php";


/* =========================================================
   STUDENT LOGIN CHECK
========================================================= */

if (!isset($_SESSION["user_id"])) {

    header("Location: ../login.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];


/* =========================================================
   GET STUDENT SKILLS
========================================================= */

$user_sql = "
    SELECT skills
    FROM users
    WHERE id = ?
";

$user_stmt = mysqli_prepare(
    $conn,
    $user_sql
);

if (!$user_stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $user_stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($user_stmt);

$user_result =
    mysqli_stmt_get_result($user_stmt);

$user_data =
    mysqli_fetch_assoc($user_result);

mysqli_stmt_close($user_stmt);

$student_skills =
    $user_data["skills"] ?? "";


/* =========================================================
   CONVERT STUDENT SKILLS INTO ARRAY
========================================================= */

$student_skill_array = [];

if (!empty($student_skills)) {

    $student_skill_array = preg_split(
        "/[,;]+/",
        strtolower($student_skills)
    );

    $student_skill_array =
        array_map(
            "trim",
            $student_skill_array
        );

    $student_skill_array =
        array_filter(
            $student_skill_array
        );

    $student_skill_array =
        array_unique(
            $student_skill_array
        );
}


/* =========================================================
   GET ALREADY APPLIED INTERNSHIPS
========================================================= */

$applied_internships = [];

$applied_sql = "
    SELECT internship_id
    FROM applications
    WHERE student_id = ?
    AND status != 'Cancelled'
";

$applied_stmt = mysqli_prepare(
    $conn,
    $applied_sql
);

if ($applied_stmt) {

    mysqli_stmt_bind_param(
        $applied_stmt,
        "i",
        $user_id
    );

    mysqli_stmt_execute(
        $applied_stmt
    );

    $applied_result =
        mysqli_stmt_get_result(
            $applied_stmt
        );

    while (
        $applied_row =
        mysqli_fetch_assoc(
            $applied_result
        )
    ) {

        $applied_internships[] =
            (int)$applied_row["internship_id"];
    }

    mysqli_stmt_close(
        $applied_stmt
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

$result = mysqli_query(
    $conn,
    $sql
);

if (!$result) {
    die("Database Error: " . mysqli_error($conn));
}


$internships = [];


/* =========================================================
   CALCULATE SKILL MATCH
========================================================= */

if (
    $result &&
    mysqli_num_rows($result) > 0
) {

    while (
        $row =
        mysqli_fetch_assoc($result)
    ) {


        /* -------------------------------------------------
           REQUIRED SKILLS
        ------------------------------------------------- */

        $required_skills =
            strtolower(
                $row["skills"] ?? ""
            );


        $required_skill_array =
            preg_split(
                "/[,;]+/",
                $required_skills
            );


        $required_skill_array =
            array_map(
                "trim",
                $required_skill_array
            );


        $required_skill_array =
            array_filter(
                $required_skill_array
            );


        $required_skill_array =
            array_unique(
                $required_skill_array
            );


        /* -------------------------------------------------
           MATCHED / MISSING
        ------------------------------------------------- */

        $matched_skills = [];

        $missing_skills = [];


        foreach (
            $required_skill_array
            as $required_skill
        ) {

            $found = false;


            foreach (
                $student_skill_array
                as $student_skill
            ) {

                if (
                    $student_skill ===
                    $required_skill
                ) {

                    $matched_skills[] =
                        $required_skill;

                    $found = true;

                    break;
                }
            }


            if (!$found) {

                $missing_skills[] =
                    $required_skill;
            }
        }


        /* -------------------------------------------------
           MATCH PERCENTAGE
        ------------------------------------------------- */

        $total_required =
            count(
                $required_skill_array
            );

        $total_matched =
            count(
                $matched_skills
            );


        if ($total_required > 0) {

            $match_percentage =
                round(
                    (
                        $total_matched /
                        $total_required
                    ) * 100
                );

        } else {

            $match_percentage = 0;
        }


        /* -------------------------------------------------
           SAVE RESULT
        ------------------------------------------------- */

        $row["match_percentage"] =
            $match_percentage;

        $row["matched_skills"] =
            $matched_skills;

        $row["missing_skills"] =
            $missing_skills;

        $row["already_applied"] =
            in_array(
                (int)$row["id"],
                $applied_internships,
                true
            );


        $internships[] =
            $row;
    }
}


/* =========================================================
   SORT BY MATCH PERCENTAGE
========================================================= */

usort(
    $internships,
    function ($a, $b) {

        return
            $b["match_percentage"]
            -
            $a["match_percentage"];
    }
);

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
   PAGE LAYOUT
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

    background: #f5f6fa;

    min-height: 100vh;
}


/* =========================================================
   BACK TO DASHBOARD
========================================================= */

.back-dashboard {

    display: inline-block;

    background: #667eea;

    color: white;

    padding: 12px 20px;

    border-radius: 8px;

    text-decoration: none;

    font-weight: bold;

    margin-bottom: 20px;

    box-shadow:
        0 4px 10px rgba(102,126,234,0.25);
}


.back-dashboard:hover {

    background: #5568d8;

    color: white;

    text-decoration: none;
}


/* =========================================================
   TOP BAR
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

    background: #ffffff;

    padding: 18px 22px;

    border-radius: 12px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.08);
}


.student-skills strong {

    color: #333;
}


.student-skills span {

    color: #667eea;

    font-weight: bold;
}


/* =========================================================
   GRID
========================================================= */

.internship-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 20px;
}


/* =========================================================
   CARD
========================================================= */

.internship-card {

    background: white;

    padding: 22px;

    border-radius: 12px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.08);

    transition: 0.3s;

    position: relative;
}


.internship-card:hover {

    transform:
        translateY(-5px);
}


/* =========================================================
   BEST MATCH
========================================================= */

.best-match {

    border: 3px solid #667eea;
}


.best-badge {

    position: absolute;

    top: -12px;

    right: 15px;

    background: #667eea;

    color: white;

    padding: 6px 12px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;
}


/* =========================================================
   TITLE
========================================================= */

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

    margin-top: 15px;

    padding: 14px;

    border-radius: 10px;

    background: #f8f9ff;
}


.match-title {

    font-weight: bold;

    margin-bottom: 8px;
}


.match-percentage {

    font-size: 22px;

    font-weight: bold;

    margin-bottom: 10px;
}


/* =========================================================
   MATCH COLORS
========================================================= */

.high-match {

    color: #16a34a;
}


.medium-match {

    color: #d97706;
}


.low-match {

    color: #dc2626;
}


/* =========================================================
   PROGRESS
========================================================= */

.progress {

    width: 100%;

    height: 9px;

    background: #e5e7eb;

    border-radius: 10px;

    overflow: hidden;

    margin-bottom: 10px;
}


.progress-bar {

    height: 100%;

    background: #667eea;
}


/* =========================================================
   MATCHED / MISSING
========================================================= */

.matched {

    color: #16a34a;

    font-size: 13px;

    margin-top: 5px;
}


.missing {

    color: #dc2626;

    font-size: 13px;

    margin-top: 5px;
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


/* =========================================================
   ALREADY APPLIED
========================================================= */

.already-applied {

    display: block;

    width: 100%;

    text-align: center;

    background: #d4edda;

    color: #155724;

    padding: 12px;

    border-radius: 8px;

    font-weight: bold;

    margin-top: 15px;
}


/* =========================================================
   LOW MATCH
========================================================= */

.not-eligible {

    display: block;

    width: 100%;

    text-align: center;

    background: #adb5bd;

    color: white;

    padding: 12px;

    border-radius: 8px;

    font-weight: bold;

    margin-top: 15px;
}


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
   APPLICATION CLOSED
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
            href="internships.php"
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
        class="back-dashboard"
    >
        ← Back to Dashboard
    </a>


    <!-- =================================================
         TOP BAR
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

        <span>

            <?php

            if (!empty($student_skills)) {

                echo htmlspecialchars(
                    $student_skills
                );

            } else {

                echo "No skills added";

            }

            ?>

        </span>

    </div>



<?php if (count($internships) > 0): ?>


    <div class="internship-grid">


<?php foreach ($internships as $row): ?>


<?php

/* =====================================================
   MATCH PERCENTAGE
===================================================== */

$percentage =
    (int)$row["match_percentage"];


/* =====================================================
   MATCH CLASS
===================================================== */

if ($percentage >= 70) {

    $match_class =
        "high-match";

} elseif ($percentage >= 40) {

    $match_class =
        "medium-match";

} else {

    $match_class =
        "low-match";
}


/* =====================================================
   DEADLINE CHECK
===================================================== */

$today =
    date("Y-m-d");

$application_closed =
    false;


if (!empty($row["deadline"])) {

    if (
        $today >
        $row["deadline"]
    ) {

        $application_closed =
            true;
    }
}


/* =====================================================
   DATE
===================================================== */

$deadline_display =
    "Not specified";


if (!empty($row["deadline"])) {

    $deadline_display =
        date(
            "d M Y",
            strtotime(
                $row["deadline"]
            )
        );
}

?>



<div
    class="internship-card
    <?php
    echo (
        $percentage >= 70
    )
        ? "best-match"
        : "";
    ?>"
>


<?php if ($percentage >= 70): ?>

    <div class="best-badge">

        ⭐ Best Match

    </div>

<?php endif; ?>



    <!-- TITLE -->

    <h2>

        <?php

        echo htmlspecialchars(
            $row["title"]
        );

        ?>

    </h2>



    <!-- COMPANY -->

    <div class="company">

        🏢

        <?php

        echo htmlspecialchars(
            $row["company_name"]
        );

        ?>

    </div>



    <!-- DESCRIPTION -->

    <div class="description">

        <?php

        echo nl2br(
            htmlspecialchars(
                $row["description"]
            )
        );

        ?>

    </div>



    <!-- LOCATION -->

    <div class="info">

        📍

        <strong>
            Location:
        </strong>

        <?php

        echo htmlspecialchars(
            $row["location"]
            ??
            "Not specified"
        );

        ?>

    </div>



    <!-- DURATION -->

    <div class="info">

        ⏳

        <strong>
            Duration:
        </strong>

        <?php

        echo htmlspecialchars(
            $row["duration"]
            ??
            "Not specified"
        );

        ?>

    </div>



    <!-- DEADLINE -->

    <div class="info">

        📅

        <strong>
            Deadline:
        </strong>

        <?php

        echo htmlspecialchars(
            $deadline_display
        );

        ?>

    </div>



    <!-- SKILLS -->

    <div class="skills">

        <strong>
            Skills Required:
        </strong>

        <br>

        <?php

        echo htmlspecialchars(
            $row["skills"]
        );

        ?>

    </div>



    <!-- =================================================
         SKILL MATCH
    ================================================= -->

    <div class="match-box">


        <div class="match-title">

            🎯 Skill Match

        </div>


        <div
            class="match-percentage
            <?php
            echo $match_class;
            ?>"
        >

            <?php
            echo $percentage;
            ?>%

        </div>


        <div class="progress">

            <div
                class="progress-bar"
                style="
                    width:
                    <?php
                    echo $percentage;
                    ?>%;
                "
            >
            </div>

        </div>



<?php if (
    count(
        $row["matched_skills"]
    ) > 0
): ?>

        <div class="matched">

            ✅

            <strong>
                Matched:
            </strong>

            <?php

            echo htmlspecialchars(
                implode(
                    ", ",
                    $row["matched_skills"]
                )
            );

            ?>

        </div>

<?php endif; ?>



<?php if (
    count(
        $row["missing_skills"]
    ) > 0
): ?>

        <div class="missing">

            ❌

            <strong>
                Missing:
            </strong>

            <?php

            echo htmlspecialchars(
                implode(
                    ", ",
                    $row["missing_skills"]
                )
            );

            ?>

        </div>

<?php endif; ?>


    </div>



    <!-- =================================================
         APPLICATION STATUS
    ================================================= -->


<?php if ($application_closed): ?>


    <!-- DEADLINE PASSED -->

    <div class="closed-btn">

        ❌ Application Closed

    </div>


    <div class="closed-note">

        The application deadline
        has passed.

        <br>

        Deadline:

        <strong>
            <?php
            echo htmlspecialchars(
                $deadline_display
            );
            ?>
        </strong>

    </div>


<?php elseif (
    $row["already_applied"]
): ?>


    <!-- ALREADY APPLIED -->

    <div class="already-applied">

        ✓ Already Applied

    </div>


    <div
        class="closed-note"
        style="
            background:#d4edda;
            color:#155724;
        "
    >

        You have already applied
        for this internship.

    </div>


<?php elseif (
    $percentage >= 60
): ?>


    <!-- ELIGIBLE TO APPLY -->

    <a
        href="
        apply.php?id=
        <?php
        echo (int)$row["id"];
        ?>"
        class="apply-btn"
    >

        Apply Now

    </a>


<?php else: ?>


    <!-- LOW MATCH -->

    <div class="not-eligible">

        Apply Not Available

    </div>


    <div class="minimum-note">

        Minimum 60% skill match
        is required to apply.

    </div>


<?php endif; ?>


</div>


<?php endforeach; ?>


    </div>


<?php else: ?>


    <!-- NO INTERNSHIPS -->

    <div class="no-data">

        <h2>

            No Internships Available

        </h2>

        <br>

        <p>

            Currently there are no
            internship opportunities available.

        </p>

    </div>


<?php endif; ?>


</main>


</div>


</body>

</html>