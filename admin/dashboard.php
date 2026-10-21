<?php

session_start();

require_once __DIR__ . '/../config/db.php';


/* =========================================================
   ADMIN LOGIN CHECK
   ========================================================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}


$admin_name = $_SESSION['user_name'] ?? 'Admin';


/* =========================================================
   COUNT FUNCTION
   ========================================================= */

function getCount(mysqli $conn, string $sql): int
{
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_assoc($result);

    return (int)($row['total'] ?? 0);
}


/* =========================================================
   COUNTS
   ========================================================= */

$student_count = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'student'"
);


$company_count = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'company'"
);


$internship_count = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM internships"
);


$application_count = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM applications"
);


$test_count = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM test_results"
);


/* =========================================================
   PENDING COMPANY VERIFICATION
   ========================================================= */

$pending_verification = 0;

$check_verification_table = mysqli_query(
    $conn,
    "SHOW TABLES LIKE 'company_verifications'"
);

if (
    $check_verification_table &&
    mysqli_num_rows($check_verification_table) > 0
) {

    $pending_verification = getCount(
        $conn,
        "SELECT COUNT(*) AS total
         FROM company_verifications
         WHERE admin_status = 'Pending'"
    );
}


/* =========================================================
   PENDING SUGGESTIONS
   ========================================================= */

$suggestion_count = 0;

$check_suggestion_table = mysqli_query(
    $conn,
    "SHOW TABLES LIKE 'internship_suggestions'"
);

if (
    $check_suggestion_table &&
    mysqli_num_rows($check_suggestion_table) > 0
) {

    $suggestion_count = getCount(
        $conn,
        "SELECT COUNT(*) AS total
         FROM internship_suggestions
         WHERE status = 'Pending'"
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Admin Dashboard</title>


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
    background: #f4f6fb;
    color: #333;
}


/* =========================================================
   LAYOUT
   ========================================================= */

.layout {
    min-height: 100vh;
    display: flex;
}


/* =========================================================
   SIDEBAR
   ========================================================= */

.sidebar {
    width: 245px;
    min-height: 100vh;

    background: white;

    padding: 25px 15px;

    position: fixed;

    left: 0;
    top: 0;

    box-shadow: 3px 0 15px rgba(0, 0, 0, 0.10);

    z-index: 1000;
}


.sidebar h2 {
    text-align: center;
    color: #667eea;
    margin-bottom: 5px;
}


.sidebar p {
    text-align: center;
    color: #777;
    font-size: 13px;
    margin-bottom: 28px;
}


/* =========================================================
   SIDEBAR LINKS
   ========================================================= */

.sidebar a {
    display: block;

    text-decoration: none;

    color: #444;

    padding: 12px 14px;

    margin-bottom: 7px;

    border-radius: 8px;

    transition: 0.2s;
}


.sidebar a:hover,
.sidebar a.active {
    background: #667eea;
    color: white;
}


/* =========================================================
   MAIN
   ========================================================= */

.main {
    margin-left: 245px;
    width: calc(100% - 245px);
    padding: 30px;
}


/* =========================================================
   HEADER
   ========================================================= */

.header {
    background: white;

    padding: 25px;

    border-radius: 12px;

    margin-bottom: 25px;

    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.07);
}


.header h1 {
    color: #667eea;
    margin-bottom: 7px;
}


.header p {
    color: #777;
}


/* =========================================================
   CARDS
   ========================================================= */

.cards {
    display: grid;

    grid-template-columns: repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}


/* IMPORTANT:
   CARD IS A LINK
*/

.card-link {
    text-decoration: none;
    color: inherit;
    display: block;
}


.card {
    background: white;

    padding: 22px;

    border-radius: 12px;

    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.07);

    transition: all 0.2s ease;

    cursor: pointer;

    border: 2px solid transparent;
}


.card:hover {
    transform: translateY(-5px);

    border-color: #667eea;

    box-shadow: 0 10px 22px rgba(102, 126, 234, 0.20);
}


.card h3 {
    font-size: 14px;

    color: #777;

    margin-bottom: 10px;
}


.number {
    font-size: 32px;

    font-weight: bold;

    color: #667eea;
}


/* =========================================================
   SUMMARY
   ========================================================= */

.summary {
    display: grid;

    grid-template-columns: repeat(3, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}


/* SUMMARY CARDS ARE ALSO LINKS */

.summary-link {
    text-decoration: none;
    color: inherit;
    display: block;
}


.summary-card {
    background: white;

    padding: 22px;

    border-radius: 12px;

    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.07);

    transition: all 0.2s ease;

    cursor: pointer;

    border: 2px solid transparent;
}


.summary-card:hover {
    transform: translateY(-5px);

    border-color: #667eea;

    box-shadow: 0 10px 22px rgba(102, 126, 234, 0.20);
}


.summary-card h3 {
    margin-bottom: 8px;

    font-size: 15px;

    color: #555;
}


.summary-number {
    font-size: 28px;

    font-weight: bold;

    color: #667eea;
}


/* =========================================================
   WELCOME
   ========================================================= */

.welcome {
    background: white;

    padding: 28px;

    border-radius: 12px;

    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.07);
}


.welcome h2 {
    margin-bottom: 10px;
}


.welcome p {
    color: #666;

    line-height: 1.6;
}


/* =========================================================
   QUICK LINKS
   ========================================================= */

.quick {
    display: flex;

    flex-wrap: wrap;

    gap: 10px;

    margin-top: 20px;
}


.quick a {
    text-decoration: none;

    background: #eef2ff;

    color: #4f46e5;

    padding: 11px 15px;

    border-radius: 8px;

    font-weight: bold;

    transition: 0.2s;
}


.quick a:hover {
    background: #667eea;

    color: white;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 1050px) {

    .cards {
        grid-template-columns: repeat(2, 1fr);
    }

}


@media (max-width: 750px) {

    .sidebar {
        width: 200px;
    }

    .main {
        margin-left: 200px;

        width: calc(100% - 200px);

        padding: 15px;
    }

    .cards,
    .summary {
        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<div class="layout">


<!-- =====================================================
     SIDEBAR
     ===================================================== -->

<aside class="sidebar">


    <h2>
        Digital Internship
    </h2>


    <p>
        Admin Portal
    </p>


    <a href="dashboard.php" class="active">
        🏠 Dashboard
    </a>


    <a href="company_verification.php">
        🏢 Company Verification

        <?php if ($pending_verification > 0): ?>
            (<?= $pending_verification ?>)
        <?php endif; ?>

    </a>


    <a href="students.php">
        🎓 Students
    </a>


    <a href="companies.php">
        🏢 Companies
    </a>


    <a href="internships.php">
        💼 Internships
    </a>


    <a href="applications.php">
        📄 Applications
    </a>


    <a href="mock_tests.php">
        📝 Mock Tests
    </a>


    <a href="suggestions.php">
        💡 Internship Suggestions

        <?php if ($suggestion_count > 0): ?>
            (<?= $suggestion_count ?>)
        <?php endif; ?>

    </a>


    <a href="../logout.php">
        🚪 Logout
    </a>


</aside>



<!-- =====================================================
     MAIN
     ===================================================== -->

<main class="main">


    <!-- HEADER -->

    <div class="header">

        <h1>
            Admin Dashboard
        </h1>

        <p>
            Welcome,
            <strong>
                <?= htmlspecialchars($admin_name) ?>
            </strong>.
            Manage the Digital Internship Portal.
        </p>

    </div>



    <!-- =================================================
         MAIN CLICKABLE CARDS
         ================================================= -->

    <div class="cards">


        <!-- STUDENTS -->

        <a href="students.php" class="card-link">

            <div class="card">

                <h3>
                    🎓 Students
                </h3>

                <div class="number">
                    <?= $student_count ?>
                </div>

            </div>

        </a>



        <!-- COMPANIES -->

        <a href="companies.php" class="card-link">

            <div class="card">

                <h3>
                    🏢 Companies
                </h3>

                <div class="number">
                    <?= $company_count ?>
                </div>

            </div>

        </a>



        <!-- INTERNSHIPS -->

        <a href="internships.php" class="card-link">

            <div class="card">

                <h3>
                    💼 Internships
                </h3>

                <div class="number">
                    <?= $internship_count ?>
                </div>

            </div>

        </a>



        <!-- APPLICATIONS -->

        <a href="applications.php" class="card-link">

            <div class="card">

                <h3>
                    📄 Applications
                </h3>

                <div class="number">
                    <?= $application_count ?>
                </div>

            </div>

        </a>

    </div>



    <!-- =================================================
         SECONDARY CLICKABLE CARDS
         ================================================= -->

    <div class="summary">


        <!-- MOCK TEST -->

        <a href="mock_tests.php" class="summary-link">

            <div class="summary-card">

                <h3>
                    📝 Mock Test Attempts
                </h3>

                <div class="summary-number">
                    <?= $test_count ?>
                </div>

            </div>

        </a>



        <!-- COMPANY VERIFICATION -->

        <a href="company_verification.php" class="summary-link">

            <div class="summary-card">

                <h3>
                    ⏳ Pending Company Verification
                </h3>

                <div class="summary-number">
                    <?= $pending_verification ?>
                </div>

            </div>

        </a>



        <!-- SUGGESTIONS -->

        <a href="suggestions.php" class="summary-link">

            <div class="summary-card">

                <h3>
                    💡 Pending Suggestions
                </h3>

                <div class="summary-number">
                    <?= $suggestion_count ?>
                </div>

            </div>

        </a>

    </div>



    <!-- =================================================
         WELCOME
         ================================================= -->

    <div class="welcome">

        <h2>
            Admin Management 👋
        </h2>

        <p>

            From here you can manage students,
            companies, internships, applications,
            mock-test results and internship suggestions.

        </p>


        <div class="quick">


            <a href="company_verification.php">
                🏢 Company Verification
            </a>


            <a href="students.php">
                🎓 Manage Students
            </a>


            <a href="companies.php">
                🏢 Manage Companies
            </a>


            <a href="internships.php">
                💼 Manage Internships
            </a>


            <a href="applications.php">
                📄 View Applications
            </a>


            <a href="mock_tests.php">
                📝 View Mock Tests
            </a>


            <a href="suggestions.php">
                💡 Suggest Internship
            </a>


        </div>

    </div>


</main>


</div>


</body>

</html>