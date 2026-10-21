<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/db.php";

$user_id = (int)$_SESSION["user_id"];
$user_name = $_SESSION["user_name"] ?? "Company";
$user_email = $_SESSION["user_email"] ?? "";
$user_role = $_SESSION["user_role"] ?? "";


/* --------------------------------------------------
   ONLY COMPANY CAN ACCESS
-------------------------------------------------- */

if ($user_role !== "company") {
    header("Location: ../login.php");
    exit();
}


/* --------------------------------------------------
   COUNT INTERNSHIPS
-------------------------------------------------- */

$internship_count = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM internships
    WHERE company_id = ?
";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {

    mysqli_stmt_bind_param($stmt, "i", $user_id);

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if ($result) {

        $row = mysqli_fetch_assoc($result);

        $internship_count = (int)$row["total"];
    }

    mysqli_stmt_close($stmt);
}


/* --------------------------------------------------
   COUNT APPLICATIONS
-------------------------------------------------- */

$application_count = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM applications a
    INNER JOIN internships i
        ON a.internship_id = i.id
    WHERE i.company_id = ?
";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {

    mysqli_stmt_bind_param($stmt, "i", $user_id);

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if ($result) {

        $row = mysqli_fetch_assoc($result);

        $application_count = (int)$row["total"];
    }

    mysqli_stmt_close($stmt);
}


/* --------------------------------------------------
   PENDING APPLICATIONS
-------------------------------------------------- */

$pending_count = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM applications a
    INNER JOIN internships i
        ON a.internship_id = i.id
    WHERE i.company_id = ?
    AND a.status = 'Pending'
";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {

    mysqli_stmt_bind_param($stmt, "i", $user_id);

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if ($result) {

        $row = mysqli_fetch_assoc($result);

        $pending_count = (int)$row["total"];
    }

    mysqli_stmt_close($stmt);
}


/* --------------------------------------------------
   SHORTLISTED APPLICATIONS
-------------------------------------------------- */

$shortlisted_count = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM applications a
    INNER JOIN internships i
        ON a.internship_id = i.id
    WHERE i.company_id = ?
    AND a.status = 'Shortlisted'
";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {

    mysqli_stmt_bind_param($stmt, "i", $user_id);

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if ($result) {

        $row = mysqli_fetch_assoc($result);

        $shortlisted_count = (int)$row["total"];
    }

    mysqli_stmt_close($stmt);
}


/* --------------------------------------------------
   FINAL TEST PASSED / INTERVIEW ELIGIBLE
-------------------------------------------------- */

$interview_eligible_count = 0;

$sql = "
    SELECT COUNT(DISTINCT a.id) AS total
    FROM applications a
    INNER JOIN internships i
        ON a.internship_id = i.id
    INNER JOIN test_results tr
        ON tr.application_id = a.id
    WHERE i.company_id = ?
    AND tr.level = 2
    AND tr.passed = 1
    AND tr.score >= 4
    AND tr.total_questions = 5
";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {

    mysqli_stmt_bind_param($stmt, "i", $user_id);

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if ($result) {

        $row = mysqli_fetch_assoc($result);

        $interview_eligible_count = (int)$row["total"];
    }

    mysqli_stmt_close($stmt);
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
    Company Dashboard - Digital Internship
</title>


<style>

/* --------------------------------------------------
   GENERAL
-------------------------------------------------- */

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


/* --------------------------------------------------
   DASHBOARD
-------------------------------------------------- */

.dashboard {

    display: flex;

    min-height: 100vh;

}


/* --------------------------------------------------
   SIDEBAR
-------------------------------------------------- */

.sidebar {

    width: 250px;

    min-height: 100vh;

    background: white;

    padding: 25px 15px;

    position: fixed;

    left: 0;

    top: 0;

    box-shadow:
        3px 0 15px rgba(0,0,0,0.10);

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

    padding: 13px 15px;

    border-radius: 8px;

    font-size: 15px;

    display: block;

    transition: 0.3s;

}


.sidebar nav a:hover,
.sidebar nav a.active {

    background: #667eea;

    color: white;

}


/* --------------------------------------------------
   MAIN CONTENT
-------------------------------------------------- */

.main-content {

    margin-left: 250px;

    width: calc(100% - 250px);

    padding: 30px;

}


/* --------------------------------------------------
   TOPBAR
-------------------------------------------------- */

.topbar {

    background: white;

    padding: 22px 25px;

    border-radius: 12px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.06);

}


.topbar h1 {

    font-size: 28px;

    margin-bottom: 7px;

}


.topbar p {

    color: #777;

}


.profile-info {

    background: #f3f4ff;

    color: #667eea;

    padding: 10px 15px;

    border-radius: 8px;

    font-size: 14px;

}


/* --------------------------------------------------
   WELCOME
-------------------------------------------------- */

.welcome-box {

    background: white;

    padding: 25px;

    border-radius: 12px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.06);

}


.welcome-box h2 {

    margin-bottom: 10px;

}


.welcome-box p {

    color: #666;

    line-height: 1.6;

}


/* --------------------------------------------------
   CARDS
-------------------------------------------------- */

.dashboard-cards {

    display: grid;

    grid-template-columns:
        repeat(5, 1fr);

    gap: 18px;

    margin-bottom: 30px;

}


/* Clickable card link */

.dashboard-card-link {

    text-decoration: none;

    color: inherit;

    display: block;

}


.dashboard-card-link:hover {

    text-decoration: none;

    color: inherit;

}


.dashboard-card {

    background: white;

    padding: 22px;

    border-radius: 12px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.06);

    height: 100%;

    cursor: pointer;

    transition: 0.3s;

}


.dashboard-card:hover {

    transform: translateY(-5px);

    box-shadow:
        0 8px 20px rgba(0,0,0,0.10);

}


.card-icon {

    font-size: 28px;

    margin-bottom: 12px;

}


.dashboard-card h3 {

    font-size: 15px;

    margin-bottom: 8px;

}


.card-number {

    font-size: 30px;

    font-weight: bold;

    color: #667eea;

}


.dashboard-card span {

    color: #888;

    font-size: 12px;

}


/* --------------------------------------------------
   QUICK ACTIONS
-------------------------------------------------- */

.content-section {

    background: white;

    padding: 25px;

    border-radius: 12px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.06);

}


.content-section h2 {

    margin-bottom: 20px;

}


.quick-actions {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

}


.action-btn {

    text-decoration: none;

    background: #f3f4ff;

    color: #667eea;

    padding: 16px;

    border-radius: 8px;

    text-align: center;

    font-weight: bold;

    transition: 0.3s;

}


.action-btn:hover {

    background: #667eea;

    color: white;

}


/* --------------------------------------------------
   INTERVIEW NOTICE
-------------------------------------------------- */

.interview-notice {

    background: #f3f4ff;

    border-left: 5px solid #667eea;

    padding: 20px;

    border-radius: 8px;

    margin-top: 20px;

}


.interview-notice h3 {

    margin-bottom: 8px;

    color: #667eea;

}


.interview-notice p {

    color: #666;

    line-height: 1.6;

}


/* --------------------------------------------------
   RESPONSIVE
-------------------------------------------------- */

@media (max-width: 1200px) {

    .dashboard-cards {

        grid-template-columns:
            repeat(3, 1fr);

    }

}


@media (max-width: 900px) {

    .dashboard-cards {

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

        width: calc(100% - 200px);

        padding: 20px;

    }


    .quick-actions {

        grid-template-columns: 1fr;

    }

}


@media (max-width: 550px) {

    .dashboard-cards {

        grid-template-columns: 1fr;

    }


    .topbar {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;

    }

}

</style>

</head>


<body>


<div class="dashboard">


<!-- ==================================================
     SIDEBAR
================================================== -->

<aside class="sidebar">


    <div class="sidebar-header">

        <h2>
            Digital Internship
        </h2>

        <p>
            Company Portal
        </p>

    </div>


    <nav>


        <a
            href="dashboard.php"
            class="active"
        >
            🏠 Dashboard
        </a>
        <a
            href="profile.php"
        >
            👤 Profile
        </a>

        <a
            href="post_internship.php"
        >
            ➕ Post Internship
        </a>


        <a
            href="manage_questions.php"
        >
            📝 Manage Mock Questions
        </a>


        <a
            href="my_internships.php"
        >
            📋 Manage Internships
        </a>


        <a
            href="applications.php"
        >
            📄 Applications
        </a>


        


        <a
            href="../logout.php"
        >
            🚪 Logout
        </a>


    </nav>

</aside>



<!-- ==================================================
     MAIN CONTENT
================================================== -->

<main class="main-content">


    <!-- TOPBAR -->

    <div class="topbar">


        <div>

            <h1>
                Company Dashboard
            </h1>


            <p>

                Welcome back,

                <strong>

                    <?= htmlspecialchars($user_name) ?>

                </strong>

            </p>

        </div>


        <div class="profile-info">

            <?= htmlspecialchars($user_email) ?>

        </div>


    </div>



    <!-- WELCOME -->

    <div class="welcome-box">


        <h2>

            Welcome,
            <?= htmlspecialchars($user_name) ?>! 🏢

        </h2>


        <p>

            Manage your internships, review student
            applications, evaluate mock test performance
            and select suitable candidates through the
            Digital Internship Portal.

        </p>


    </div>



    <!-- ==================================================
         DASHBOARD CARDS
    ================================================== -->

    <div class="dashboard-cards">


        <!-- MY INTERNSHIPS -->

        <a
            href="my_internships.php"
            class="dashboard-card-link"
        >

            <div class="dashboard-card">

                <div class="card-icon">
                    📋
                </div>


                <h3>
                    My Internships
                </h3>


                <p class="card-number">

                    <?= $internship_count ?>

                </p>


                <span>
                    Internships posted by you
                </span>

            </div>

        </a>



        <!-- APPLICATIONS -->

        <a
            href="applications.php"
            class="dashboard-card-link"
        >

            <div class="dashboard-card">

                <div class="card-icon">
                    📄
                </div>


                <h3>
                    Applications
                </h3>


                <p class="card-number">

                    <?= $application_count ?>

                </p>


                <span>
                    Student applications
                </span>

            </div>

        </a>



        <!-- PENDING -->

        <a
            href="applications.php"
            class="dashboard-card-link"
        >

            <div class="dashboard-card">

                <div class="card-icon">
                    ⏳
                </div>


                <h3>
                    Pending
                </h3>


                <p class="card-number">

                    <?= $pending_count ?>

                </p>


                <span>
                    Applications to review
                </span>

            </div>

        </a>



        <!-- SHORTLISTED -->

        <a
            href="applications.php"
            class="dashboard-card-link"
        >

            <div class="dashboard-card">

                <div class="card-icon">
                    ⭐
                </div>


                <h3>
                    Shortlisted
                </h3>


                <p class="card-number">

                    <?= $shortlisted_count ?>

                </p>


                <span>
                    Selected candidates
                </span>

            </div>

        </a>



        <!-- INTERVIEW ELIGIBLE -->

        <a
            href="applications.php"
            class="dashboard-card-link"
        >

            <div class="dashboard-card">

                <div class="card-icon">
                    📄
                </div>


                <h3>
                    Interview Eligible
                </h3>


                <p class="card-number">

                    <?= $interview_eligible_count ?>

                </p>


                <span>
                    Students who passed Level 2
                </span>

            </div>

        </a>


    </div>



    <!-- ==================================================
         QUICK ACTIONS
    ================================================== -->

    <div class="content-section">


        <h2>
            Quick Actions
        </h2>


        <div class="quick-actions">

            <a
                href="profile.php"
                class="action-btn"
            >
                👤 Company Profile
            </a>
            <a
                href="post_internship.php"
                class="action-btn"
            >
                ➕ Post New Internship
            </a>


            <a
                href="my_internships.php"
                class="action-btn"
            >
                📋 Manage Internships
            </a>


            <a
                href="manage_questions.php"
                class="action-btn"
            >
                📝 Manage Mock Questions
            </a>


            <a
                href="applications.php"
                class="action-btn"
            >
                📄 View Applications
            </a>


            <a
                href="applications.php"
                class="action-btn"
            >
                📄 Issue Interview Letter
            </a>


            


        </div>



        <!-- INTERVIEW INFORMATION -->

        <div class="interview-notice">


            <h3>
                📄 Interview Letter
            </h3>


            <p>

                After a student successfully completes
                <strong>Level 2 with 4/5 or above</strong>, the
                application page will provide the
                <strong>Issue Interview Letter</strong>
                option.

                The company can then enter the interview
                date, time, venue and additional message.

            </p>


        </div>


    </div>


</main>


</div>


</body>

</html>