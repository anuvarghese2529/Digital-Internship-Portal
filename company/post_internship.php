<?php

session_start();

require_once "../config/db.php";


/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isset($_SESSION["user_id"])) {

    header("Location: ../login.php");
    exit();

}

$user_id = (int)$_SESSION["user_id"];

$user_role = $_SESSION["user_role"] ?? "";


if ($user_role !== "company") {

    header("Location: ../login.php");
    exit();

}


/* =========================================================
   GET COMPANY DETAILS
========================================================= */

$company_name = "";

$company_email = "";


$stmt = mysqli_prepare(
    $conn,
    "SELECT name, email
     FROM users
     WHERE id = ?
     AND role = 'company'"
);


if (!$stmt) {

    die(
        "Database Error: " .
        mysqli_error($conn)
    );

}


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);


mysqli_stmt_execute($stmt);


$result = mysqli_stmt_get_result($stmt);


if (mysqli_num_rows($result) !== 1) {

    mysqli_stmt_close($stmt);

    session_destroy();

    header("Location: ../login.php");
    exit();

}


$company = mysqli_fetch_assoc($result);


$company_name =
    $company["name"];

$company_email =
    $company["email"];


mysqli_stmt_close($stmt);


/* =========================================================
   CHECK COMPANY APPROVAL
========================================================= */

$admin_status = "Pending";


$stmt = mysqli_prepare(
    $conn,
    "SELECT admin_status
     FROM company_verifications
     WHERE user_id = ?
     LIMIT 1"
);


if ($stmt) {

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $user_id
    );


    mysqli_stmt_execute($stmt);


    $result =
        mysqli_stmt_get_result($stmt);


    if (
        $row =
        mysqli_fetch_assoc($result)
    ) {

        $admin_status =
            $row["admin_status"];

    }


    mysqli_stmt_close($stmt);

}


/* =========================================================
   VARIABLES
========================================================= */

$message = "";

$message_type = "";


/* =========================================================
   POST INTERNSHIP
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /* -----------------------------------------------------
       ONLY APPROVED COMPANY CAN POST
    ----------------------------------------------------- */

    if ($admin_status !== "Approved") {

        $message =
            "Your company must be approved by the administrator before posting an internship.";

        $message_type = "error";

    }

    else {


        /* -------------------------------------------------
           GET FORM VALUES
        ------------------------------------------------- */

        $title =
            trim(
                $_POST["title"] ?? ""
            );

        $description =
            trim(
                $_POST["description"] ?? ""
            );

        $skills =
            trim(
                $_POST["skills"] ?? ""
            );

        $location =
            trim(
                $_POST["location"] ?? ""
            );

        $duration =
            trim(
                $_POST["duration"] ?? ""
            );

        $start_date =
            $_POST["start_date"] ?? "";

        $end_date =
            $_POST["end_date"] ?? "";

        $deadline =
            $_POST["deadline"] ?? "";


        /* -------------------------------------------------
           VALIDATION
        ------------------------------------------------- */

        if (
            $title === "" ||
            $description === "" ||
            $skills === "" ||
            $location === "" ||
            $duration === "" ||
            $start_date === "" ||
            $end_date === "" ||
            $deadline === ""
        ) {

            $message =
                "Please fill all internship details.";

            $message_type =
                "error";

        }

        elseif (
            $end_date < $start_date
        ) {

            $message =
                "End date must be after or equal to the start date.";

            $message_type =
                "error";

        }

        elseif (
            $deadline > $start_date
        ) {

            $message =
                "Application deadline must be on or before the internship start date.";

            $message_type =
                "error";

        }

        else {


            /* =================================================
               START TRANSACTION

               Internship + 100 questions are inserted
               together. If question copying fails,
               internship creation is also rolled back.
            ================================================= */

            mysqli_begin_transaction($conn);


            try {


                /* =============================================
                   INSERT INTERNSHIP
                ============================================= */

                $insert_sql = "

                    INSERT INTO internships
                    (
                        company_id,
                        company_name,
                        title,
                        description,
                        skills,
                        location,
                        start_date,
                        end_date,
                        duration,
                        deadline
                    )

                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)

                ";


                $insert_stmt =
                    mysqli_prepare(
                        $conn,
                        $insert_sql
                    );


                if (!$insert_stmt) {

                    throw new Exception(
                        "Unable to prepare internship insertion."
                    );

                }


                mysqli_stmt_bind_param(
                    $insert_stmt,
                    "isssssssss",
                    $user_id,
                    $company_name,
                    $title,
                    $description,
                    $skills,
                    $location,
                    $start_date,
                    $end_date,
                    $duration,
                    $deadline
                );


                if (
                    !mysqli_stmt_execute(
                        $insert_stmt
                    )
                ) {

                    throw new Exception(
                        "Unable to post internship."
                    );

                }


                /* ---------------------------------------------
                   GET NEW INTERNSHIP ID
                --------------------------------------------- */

                $new_internship_id =
                    mysqli_insert_id($conn);


                mysqli_stmt_close(
                    $insert_stmt
                );


                if (
                    $new_internship_id <= 0
                ) {

                    throw new Exception(
                        "Unable to get the new internship ID."
                    );

                }


                /* =================================================
                   FIND ALPHA MASTER INTERNSHIP

                   Alpha Nova Technologies:
                   hr@alphanovatech.in

                   The first Alpha internship that contains
                   the master question bank is used.
                ================================================= */

                $master_sql = "

                    SELECT
                        MIN(i.id) AS master_internship_id

                    FROM internships i

                    INNER JOIN users u
                        ON i.company_id = u.id

                    WHERE u.email =
                        'hr@alphanovatech.in'

                    AND u.role =
                        'company'

                ";


                $master_stmt =
                    mysqli_prepare(
                        $conn,
                        $master_sql
                    );


                if (!$master_stmt) {

                    throw new Exception(
                        "Unable to find the master question internship."
                    );

                }


                mysqli_stmt_execute(
                    $master_stmt
                );


                $master_result =
                    mysqli_stmt_get_result(
                        $master_stmt
                    );


                $master_row =
                    mysqli_fetch_assoc(
                        $master_result
                    );


                mysqli_stmt_close(
                    $master_stmt
                );


                $master_internship_id =
                    (int)(
                        $master_row[
                            "master_internship_id"
                        ] ?? 0
                    );


                if (
                    $master_internship_id <= 0
                ) {

                    throw new Exception(
                        "Alpha Nova master internship was not found."
                    );

                }


                /* =================================================
                   CHECK MASTER QUESTION COUNT
                ================================================= */

                $count_sql = "

                    SELECT COUNT(*) AS total

                    FROM questions

                    WHERE internship_id = ?

                ";


                $count_stmt =
                    mysqli_prepare(
                        $conn,
                        $count_sql
                    );


                if (!$count_stmt) {

                    throw new Exception(
                        "Unable to check master questions."
                    );

                }


                mysqli_stmt_bind_param(
                    $count_stmt,
                    "i",
                    $master_internship_id
                );


                mysqli_stmt_execute(
                    $count_stmt
                );


                $count_result =
                    mysqli_stmt_get_result(
                        $count_stmt
                    );


                $count_row =
                    mysqli_fetch_assoc(
                        $count_result
                    );


                mysqli_stmt_close(
                    $count_stmt
                );


                $master_question_count =
                    (int)(
                        $count_row["total"] ?? 0
                    );


                if (
                    $master_question_count < 100
                ) {

                    throw new Exception(
                        "Master question bank has only " .
                        $master_question_count .
                        " questions. At least 100 are required."
                    );

                }


                /* =================================================
                   COPY SAME 100 QUESTIONS
                ================================================= */

                $copy_sql = "

                    INSERT INTO questions
                    (
                        internship_id,
                        question,
                        option_a,
                        option_b,
                        option_c,
                        option_d,
                        correct_answer
                    )

                    SELECT

                        ?,

                        q.question,
                        q.option_a,
                        q.option_b,
                        q.option_c,
                        q.option_d,
                        q.correct_answer

                    FROM questions q

                    WHERE q.internship_id = ?

                    ORDER BY q.id

                    LIMIT 100

                ";


                $copy_stmt =
                    mysqli_prepare(
                        $conn,
                        $copy_sql
                    );


                if (!$copy_stmt) {

                    throw new Exception(
                        "Unable to prepare mock questions."
                    );

                }


                mysqli_stmt_bind_param(
                    $copy_stmt,
                    "ii",
                    $new_internship_id,
                    $master_internship_id
                );


                if (
                    !mysqli_stmt_execute(
                        $copy_stmt
                    )
                ) {

                    throw new Exception(
                        "Unable to copy mock questions."
                    );

                }


                $copied_questions =
                    mysqli_stmt_affected_rows(
                        $copy_stmt
                    );


                mysqli_stmt_close(
                    $copy_stmt
                );


                /* =================================================
                   VERIFY 100 QUESTIONS
                ================================================= */

                if (
                    $copied_questions !== 100
                ) {

                    throw new Exception(
                        "Only " .
                        $copied_questions .
                        " questions were copied. Exactly 100 are required."
                    );

                }


                /* =================================================
                   COMMIT
                ================================================= */

                mysqli_commit($conn);


                $message =
                    "Internship posted successfully.";

                $message_type =
                    "success";


                /* Clear fields */

                $_POST = [];


            }

            catch (Exception $e) {


                /* ---------------------------------------------
                   ROLLBACK
                --------------------------------------------- */

                mysqli_rollback($conn);


                $message =
                    $e->getMessage();

                $message_type =
                    "error";

            }

        }

    }

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
    Post Internship - Digital Internship
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

    background: #f4f6fb;

    color: #333;

}


/* =========================================================
   DASHBOARD
========================================================= */

.dashboard {

    display: flex;

    min-height: 100vh;

}


/* =========================================================
   SIDEBAR
========================================================= */

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


/* =========================================================
   MAIN
========================================================= */

.main-content {

    margin-left: 250px;

    width: calc(100% - 250px);

    padding: 30px;

}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    background: white;

    padding: 25px;

    border-radius: 12px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.06);

}


.page-header h1 {

    color: #667eea;

    margin-bottom: 8px;

}


.page-header p {

    color: #777;

}


/* =========================================================
   MESSAGE
========================================================= */

.message {

    padding: 13px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-weight: bold;

    text-align: center;

    line-height: 1.5;

}


.success {

    background: #d4edda;

    color: #155724;

}


.error {

    background: #f8d7da;

    color: #721c24;

}


/* =========================================================
   FORM BOX
========================================================= */

.form-container {

    background: white;

    padding: 30px;

    border-radius: 12px;

    max-width: 950px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.06);

}


/* =========================================================
   COMPANY INFO
========================================================= */

.company-info {

    background: #f3f4ff;

    border-left: 5px solid #667eea;

    padding: 18px;

    border-radius: 8px;

    margin-bottom: 25px;

}


.company-info h3 {

    color: #667eea;

    margin-bottom: 8px;

}


.company-info p {

    color: #555;

    margin-bottom: 5px;

}


/* =========================================================
   FORM
========================================================= */

.form-group {

    margin-bottom: 20px;

}


.form-group label {

    display: block;

    margin-bottom: 8px;

    font-weight: bold;

    color: #444;

}


.form-group input,

.form-group textarea,

.form-group select {

    width: 100%;

    padding: 13px 15px;

    border: 1px solid #ddd;

    border-radius: 8px;

    font-size: 15px;

    font-family: Arial, sans-serif;

    outline: none;

}


.form-group input:focus,

.form-group textarea:focus,

.form-group select:focus {

    border-color: #667eea;

}


.form-group textarea {

    min-height: 130px;

    resize: vertical;

}


/* =========================================================
   DATE GRID
========================================================= */

.date-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

}


/* =========================================================
   BUTTONS
========================================================= */

.button-group {

    display: flex;

    gap: 12px;

    margin-top: 25px;

    flex-wrap: wrap;

}


.post-btn {

    background: #667eea;

    color: white;

    border: none;

    padding: 13px 24px;

    border-radius: 8px;

    font-weight: bold;

    cursor: pointer;

}


.post-btn:hover {

    background: #5568d8;

}


.back-btn {

    display: inline-block;

    text-decoration: none;

    background: #eee;

    color: #444;

    padding: 13px 24px;

    border-radius: 8px;

    font-weight: bold;

}


.back-btn:hover {

    background: #ddd;

}


/* =========================================================
   NOT APPROVED
========================================================= */

.warning-box {

    background: #fff3cd;

    color: #856404;

    border-left: 5px solid #ffc107;

    padding: 20px;

    border-radius: 8px;

    margin-bottom: 25px;

}


.warning-box h3 {

    margin-bottom: 8px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .date-grid {

        grid-template-columns: 1fr;

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


    .button-group {

        flex-direction: column;

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


        <a href="dashboard.php">
            🏠 Dashboard
        </a>


        <a href="profile.php">
            👤 Company Profile
        </a>


        <a
            href="post_internship.php"
            class="active"
        >
            ➕ Post Internship
        </a>


        <a href="my_internships.php">
            📋 My Internships
        </a>


        <a href="applications.php">
            📄 Applications
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


    <div class="page-header">

        <h1>
            ➕ Post New Internship
        </h1>

        <p>
            Create a new internship opportunity for students.
        </p>

    </div>


    <!-- ==================================================
         MESSAGE
    ================================================== -->

    <?php if ($message !== ""): ?>


        <div class="message <?= htmlspecialchars($message_type) ?>">

            <?= htmlspecialchars($message) ?>

        </div>


    <?php endif; ?>


    <!-- ==================================================
         APPROVAL CHECK
    ================================================== -->

    <?php if ($admin_status !== "Approved"): ?>


        <div class="warning-box">

            <h3>
                ⚠️ Company Approval Required
            </h3>

            <p>

                Your company account is currently

                <strong>
                    <?= htmlspecialchars(
                        $admin_status
                    ) ?>
                </strong>.

                You can post internships only after
                administrator approval.

            </p>

        </div>


    <?php else: ?>


        <div class="form-container">


            <!-- ==================================================
                 COMPANY DETAILS
            ================================================== -->

            <div class="company-info">

                <h3>
                    🏢 Company
                </h3>

                <p>

                    <strong>
                        Company Name:
                    </strong>

                    <?= htmlspecialchars(
                        $company_name
                    ) ?>

                </p>

                <p>

                    <strong>
                        Company Email:
                    </strong>

                    <?= htmlspecialchars(
                        $company_email
                    ) ?>

                </p>

            </div>


            <!-- ==================================================
                 FORM
            ================================================== -->

            <form
                method="POST"
                action="post_internship.php"
            >


                <!-- INTERNSHIP TITLE -->

                <div class="form-group">

                    <label>
                        Internship Title *
                    </label>

                    <input
                        type="text"
                        name="title"
                        placeholder="Example: Web Development Intern"
                        value="<?= htmlspecialchars(
                            $_POST["title"] ?? ""
                        ) ?>"
                        required
                    >

                </div>


                <!-- DESCRIPTION -->

                <div class="form-group">

                    <label>
                        Internship Description *
                    </label>

                    <textarea
                        name="description"
                        placeholder="Enter internship description..."
                        required
                    ><?= htmlspecialchars(
                        $_POST["description"] ?? ""
                    ) ?></textarea>

                </div>


                <!-- SKILLS -->

                <div class="form-group">

                    <label>
                        Required Skills *
                    </label>

                    <input
                        type="text"
                        name="skills"
                        placeholder="Example: HTML, CSS, JavaScript, Bootstrap, PHP, MySQL"
                        value="<?= htmlspecialchars(
                            $_POST["skills"] ?? ""
                        ) ?>"
                        required
                    >

                </div>


                <!-- LOCATION -->

                <div class="form-group">

                    <label>
                        Location *
                    </label>

                    <input
                        type="text"
                        name="location"
                        placeholder="Example: Kochi, Kerala"
                        value="<?= htmlspecialchars(
                            $_POST["location"] ?? ""
                        ) ?>"
                        required
                    >

                </div>


                <!-- DURATION -->

                <div class="form-group">

                    <label>
                        Duration *
                    </label>

                    <input
                        type="text"
                        name="duration"
                        placeholder="Example: 3 Months"
                        value="<?= htmlspecialchars(
                            $_POST["duration"] ?? ""
                        ) ?>"
                        required
                    >

                </div>


                <!-- ==================================================
                     DATES
                ================================================== -->

                <div class="date-grid">


                    <!-- START DATE -->

                    <div class="form-group">

                        <label>
                            Start Date *
                        </label>

                        <input
                            type="date"
                            name="start_date"
                            value="<?= htmlspecialchars(
                                $_POST["start_date"] ?? ""
                            ) ?>"
                            required
                        >

                    </div>


                    <!-- END DATE -->

                    <div class="form-group">

                        <label>
                            End Date *
                        </label>

                        <input
                            type="date"
                            name="end_date"
                            value="<?= htmlspecialchars(
                                $_POST["end_date"] ?? ""
                            ) ?>"
                            required
                        >

                    </div>


                    <!-- DEADLINE -->

                    <div class="form-group">

                        <label>
                            Application Deadline *
                        </label>

                        <input
                            type="date"
                            name="deadline"
                            value="<?= htmlspecialchars(
                                $_POST["deadline"] ?? ""
                            ) ?>"
                            required
                        >

                    </div>


                </div>


                <!-- ==================================================
                     BUTTONS
                ================================================== -->

                <div class="button-group">


                    <button
                        type="submit"
                        class="post-btn"
                    >

                        📢 Post Internship

                    </button>


                    <a
                        href="dashboard.php"
                        class="back-btn"
                    >

                        ← Back to Dashboard

                    </a>


                </div>


            </form>


        </div>


    <?php endif; ?>


</main>


</div>


</body>

</html>