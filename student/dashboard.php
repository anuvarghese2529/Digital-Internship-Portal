<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/db.php";

$user_id = (int)$_SESSION["user_id"];
$user_name = $_SESSION["user_name"] ?? "Student";
$user_email = $_SESSION["user_email"] ?? "";
$user_role = $_SESSION["user_role"] ?? "student";

if ($user_role !== "student") {
    header("Location: ../login.php");
    exit();
}


/* ==========================================
   Count Available Internships
========================================== */

$internship_count = 0;

$sql = "SELECT COUNT(*) AS total FROM internships";

$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $internship_count = (int)$row["total"];
}


/* ==========================================
   Count Student's ACTIVE Applications
   Cancelled applications are NOT counted
========================================== */

$application_count = 0;

$sql = "SELECT COUNT(*) AS total
        FROM applications
        WHERE student_id = ?
        AND status != 'Cancelled'";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $user_id
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if ($result) {

        $row = mysqli_fetch_assoc($result);

        $application_count = (int)$row["total"];
    }

    mysqli_stmt_close($stmt);
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        Student Dashboard - Digital Internship
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
            min-height: 100vh;
        }


        .dashboard {
            display: flex;
            min-height: 100vh;
        }


        /* ==========================================
           SIDEBAR
        ========================================== */

        .sidebar {
            width: 250px;
            min-height: 100vh;
            background: white;
            padding: 25px 15px;
            position: fixed;
            left: 0;
            top: 0;
            box-shadow: 3px 0 15px rgba(0,0,0,0.10);
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


        .sidebar nav a:hover {
            background: #667eea;
            color: white;
        }


        .sidebar nav a.active {
            background: #667eea;
            color: white;
        }


        /* ==========================================
           MAIN CONTENT
        ========================================== */

        .main-content {
            margin-left: 250px;
            width: calc(100% - 250px);
            padding: 30px;
        }


        /* ==========================================
           TOP BAR
        ========================================== */

        .topbar {
            background: white;
            padding: 22px 25px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.06);
        }


        .topbar h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 7px;
        }


        .topbar p {
            color: #777;
            font-size: 15px;
        }


        .topbar strong {
            color: #667eea;
        }


        .profile-info {
            background: #f3f4ff;
            color: #667eea;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px;
        }


        /* ==========================================
           WELCOME
        ========================================== */

        .welcome-box {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.06);
        }


        .welcome-box h2 {
            color: #333;
            font-size: 22px;
            margin-bottom: 10px;
        }


        .welcome-box p {
            color: #666;
            line-height: 1.6;
        }


        /* ==========================================
           DASHBOARD CARDS
        ========================================== */

        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }


        /*
           IMPORTANT:
           Dashboard cards are now clickable.
        */

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
            box-shadow: 0 5px 15px rgba(0,0,0,0.06);
            transition: 0.3s;
            height: 100%;
            cursor: pointer;
        }


        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.10);
        }


        .card-icon {
            font-size: 28px;
            margin-bottom: 12px;
        }


        .dashboard-card h3 {
            font-size: 16px;
            color: #444;
            margin-bottom: 8px;
        }


        .card-number {
            font-size: 30px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }


        .dashboard-card span {
            color: #888;
            font-size: 13px;
        }


        /* ==========================================
           CONTENT SECTION
        ========================================== */

        .content-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.06);
        }


        .content-section h2 {
            color: #333;
            font-size: 21px;
            margin-bottom: 20px;
        }


        /* ==========================================
           QUICK ACTIONS
        ========================================== */

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }


        .action-btn {
            text-decoration: none;
            background: #f3f4ff;
            color: #667eea;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            transition: 0.3s;
        }


        .action-btn:hover {
            background: #667eea;
            color: white;
        }


        /* ==========================================
           GETTING STARTED
        ========================================== */

        .steps {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }


        .step {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            background: #f8f9ff;
            border-radius: 10px;
        }


        .step-number {
            min-width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
        }


        .step h3 {
            color: #444;
            font-size: 16px;
            margin-bottom: 5px;
        }


        .step p {
            color: #777;
            font-size: 14px;
            line-height: 1.5;
        }


        /* ==========================================
           RESPONSIVE
        ========================================== */

        @media (max-width: 1000px) {

            .dashboard-cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
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

            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .steps {
                grid-template-columns: 1fr;
            }

        }


        @media (max-width: 500px) {

            .sidebar {
                width: 170px;
            }

            .main-content {
                margin-left: 170px;
                width: calc(100% - 170px);
                padding: 12px;
            }

            .dashboard-cards {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .sidebar nav a {
                font-size: 12px;
                padding: 10px;
            }

        }

    </style>

</head>


<body>


<div class="dashboard">


    <!-- ==========================================
         SIDEBAR
    ========================================== -->

    <aside class="sidebar">

        <div class="sidebar-header">

            <h2>Digital Internship</h2>

            <p>Student Portal</p>

        </div>


        <nav>

            <a href="dashboard.php" class="active">
                🏠 Dashboard
            </a>


            <a href="profile.php">
                👤 My Profile
            </a>


            <a href="internships.php">
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


            <a href="notifications.php">
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


    <!-- ==========================================
         MAIN CONTENT
    ========================================== -->

    <main class="main-content">


        <!-- TOP BAR -->

        <div class="topbar">

            <div>

                <h1>
                    Student Dashboard
                </h1>

                <p>

                    Welcome back,

                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $user_name
                        );

                        ?>

                    </strong>

                </p>

            </div>


            <div class="profile-info">

                <?php

                echo htmlspecialchars(
                    $user_email
                );

                ?>

            </div>

        </div>


        <!-- ==========================================
             WELCOME
        ========================================== -->

        <div class="welcome-box">

            <h2>

                Welcome,

                <?php

                echo htmlspecialchars(
                    $user_name
                );

                ?>

                ! 👋

            </h2>


            <p>

                Find internships that match your skills,
                apply online, attend mock tests and track
                your application status easily.

            </p>

        </div>


        <!-- ==========================================
             DASHBOARD CARDS
        ========================================== -->

        <div class="dashboard-cards">


            <!-- AVAILABLE INTERNSHIPS -->

            <a
                href="internships.php"
                class="dashboard-card-link"
            >

                <div class="dashboard-card">

                    <div class="card-icon">
                        🔍
                    </div>

                    <h3>
                        Available Internships
                    </h3>

                    <p class="card-number">

                        <?php

                        echo $internship_count;

                        ?>

                    </p>

                    <span>
                        Explore internship opportunities
                    </span>

                </div>

            </a>


            <!-- MY APPLICATIONS -->

            <a
                href="applications.php"
                class="dashboard-card-link"
            >

                <div class="dashboard-card">

                    <div class="card-icon">
                        📄
                    </div>

                    <h3>
                        My Applications
                    </h3>

                    <p class="card-number">

                        <?php

                        echo $application_count;

                        ?>

                    </p>

                    <span>
                        Track your applications
                    </span>

                </div>

            </a>


            <!-- MOCK TESTS -->

            <a
                href="mock_test.php"
                class="dashboard-card-link"
            >

                <div class="dashboard-card">

                    <div class="card-icon">
                        📝
                    </div>

                    <h3>
                        Mock Tests
                    </h3>

                    <p class="card-number">
                        0
                    </p>

                    <span>
                        Test your skills
                    </span>

                </div>

            </a>


            <!-- NOTIFICATIONS -->

            <a
                href="notifications.php"
                class="dashboard-card-link"
            >

                <div class="dashboard-card">

                    <div class="card-icon">
                        🔔
                    </div>

                    <h3>
                        Notifications
                    </h3>

                    <p class="card-number">
                        0
                    </p>

                    <span>
                        Check your updates
                    </span>

                </div>

            </a>


            <!-- CHATBOT -->

            <a
                href="chatbot.php"
                class="dashboard-card-link"
            >

                <div class="dashboard-card">

                    <div class="card-icon">
                        🤖
                    </div>

                    <h3>
                        Student Chatbot
                    </h3>

                    <p class="card-number">
                        💬
                    </p>

                    <span>
                        Get help and guidance
                    </span>

                </div>

            </a>


        </div>


        <!-- ==========================================
             QUICK ACTIONS
        ========================================== -->

        <div class="content-section">

            <h2>
                Quick Actions
            </h2>


            <div class="quick-actions">
               <a
                    href="profile.php"
                    class="action-btn"
                >
                    👤 Complete Profile
                </a>

                <a
                    href="internships.php"
                    class="action-btn"
                >
                    🔍 Browse Internships
                </a>


                <a
                    href="applications.php"
                    class="action-btn"
                >
                    📄 View Applications
                </a>


                <a
                    href="mock_test.php"
                    class="action-btn"
                >
                    📝 Take Mock Test
                </a>


                


                <a
                    href="chatbot.php"
                    class="action-btn chatbot-btn"
                >
                    🤖 Internship Assistant
                </a>

            </div>

        </div>


        <!-- ==========================================
             GETTING STARTED
        ========================================== -->

        <div class="content-section">

            <h2>
                Getting Started
            </h2>


            <div class="steps">


                <div class="step">

                    <span class="step-number">
                        1
                    </span>

                    <div>

                        <h3>
                            Complete Your Profile
                        </h3>

                        <p>
                            Add your skills, education
                            and resume.
                        </p>

                    </div>

                </div>


                <div class="step">

                    <span class="step-number">
                        2
                    </span>

                    <div>

                        <h3>
                            Find an Internship
                        </h3>

                        <p>
                            Search for internships
                            matching your skills.
                        </p>

                    </div>

                </div>


                <div class="step">

                    <span class="step-number">
                        3
                    </span>

                    <div>

                        <h3>
                            Apply & Take Tests
                        </h3>

                        <p>
                            Apply for internships and
                            complete mock tests.
                        </p>

                    </div>

                </div>


                <div class="step">

                    <span class="step-number">
                        4
                    </span>

                    <div>

                        <h3>
                            Track Your Application
                        </h3>

                        <p>
                            Check your application
                            and selection status.
                        </p>

                    </div>

                </div>


            </div>

        </div>


    </main>


</div>


</body>

</html>