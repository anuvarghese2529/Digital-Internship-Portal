<?php

session_start();

require_once __DIR__ . "/../config/db.php";

/* ==================================================
   COMPANY LOGIN CHECK
================================================== */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

if (($_SESSION["user_role"] ?? "") !== "company") {
    header("Location: ../login.php");
    exit();
}

$company_id = (int)$_SESSION["user_id"];

$application_id = isset($_GET["application_id"])
    ? (int)$_GET["application_id"]
    : 0;

if ($application_id <= 0) {
    die("Invalid application ID.");
}


/* ==================================================
   GET APPLICATION DETAILS
================================================== */

$sql = "
    SELECT
        a.id AS application_id,
        a.student_id,
        a.internship_id,
        a.status AS application_status,

        u.name AS student_name,
        u.email AS student_email,
        u.education,

        i.title AS internship_title,
        i.company_name

    FROM applications a

    INNER JOIN users u
        ON a.student_id = u.id

    INNER JOIN internships i
        ON a.internship_id = i.id

    WHERE a.id = ?
    AND i.company_id = ?

    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database error.");
}

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $application_id,
    $company_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$application = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$application) {
    die("Application not found or you do not have permission to access it.");
}


$student_id = (int)$application["student_id"];

$internship_id = (int)$application["internship_id"];

$student_name = $application["student_name"];

$student_email = $application["student_email"];

$internship_title = $application["internship_title"];

$company_name = !empty($application["company_name"])
    ? $application["company_name"]
    : "Digital Internship Company";


/* ==================================================
   CHECK FINAL LEVEL 2 PASS
   MUST BE 4/5 OR ABOVE
================================================== */

$pass_sql = "
    SELECT id

    FROM test_results

    WHERE application_id = ?
    AND internship_id = ?
    AND level = 2
    AND passed = 1
    AND score >= 4
    AND total_questions = 5

    LIMIT 1
";

$pass_stmt = mysqli_prepare($conn, $pass_sql);

if (!$pass_stmt) {
    die("Unable to check test result.");
}

mysqli_stmt_bind_param(
    $pass_stmt,
    "ii",
    $application_id,
    $internship_id
);

mysqli_stmt_execute($pass_stmt);

$pass_result = mysqli_stmt_get_result($pass_stmt);

$final_test = mysqli_fetch_assoc($pass_result);

mysqli_stmt_close($pass_stmt);


if (!$final_test) {

    die(
        "Interview letter cannot be issued. " .
        "The student must pass Level 2 with 4/5 or above."
    );

}


/* ==================================================
   CHECK EXISTING INTERVIEW LETTER
================================================== */

$existing_sql = "
    SELECT
        id,
        interview_date,
        interview_time,
        venue,
        message,
        created_at

    FROM interview_letters

    WHERE application_id = ?

    LIMIT 1
";

$existing_stmt = mysqli_prepare(
    $conn,
    $existing_sql
);

if (!$existing_stmt) {
    die("Database error.");
}

mysqli_stmt_bind_param(
    $existing_stmt,
    "i",
    $application_id
);

mysqli_stmt_execute($existing_stmt);

$existing_result =
    mysqli_stmt_get_result($existing_stmt);

$existing_letter =
    mysqli_fetch_assoc($existing_result);

mysqli_stmt_close($existing_stmt);


/* ==================================================
   MESSAGES
================================================== */

$success_message = "";

$error_message = "";


/* ==================================================
   FORM SUBMISSION
================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if ($existing_letter) {

        $error_message =
            "An interview letter has already been issued for this application.";

    } else {

        $interview_date =
            trim($_POST["interview_date"] ?? "");

        $interview_time =
            trim($_POST["interview_time"] ?? "");

        $venue =
            trim($_POST["venue"] ?? "");

        $message =
            trim($_POST["message"] ?? "");


        /* ------------------------------------------
           REQUIRED FIELD VALIDATION
        ------------------------------------------ */

        if (
            $interview_date === "" ||
            $interview_time === "" ||
            $venue === ""
        ) {

            $error_message =
                "Please fill in all required fields.";

        } else {


            /* ------------------------------------------
               DATE VALIDATION
            ------------------------------------------ */

            $date_object = DateTime::createFromFormat(
                "Y-m-d",
                $interview_date
            );

            if (
                !$date_object ||
                $date_object->format("Y-m-d") !== $interview_date
            ) {

                $error_message =
                    "Please enter a valid interview date.";

            } else {

                $today = new DateTime(
                    date("Y-m-d")
                );

                if ($date_object < $today) {

                    $error_message =
                        "Interview date cannot be in the past.";

                } else {


                    /* ------------------------------------------
                       TIME VALIDATION
                    ------------------------------------------ */

                    $time_object =
                        DateTime::createFromFormat(
                            "H:i",
                            $interview_time
                        );

                    if (
                        !$time_object ||
                        $time_object->format("H:i")
                        !== $interview_time
                    ) {

                        $error_message =
                            "Please enter a valid interview time.";

                    } else {


                        /* ======================================
                           SAVE INTERVIEW LETTER
                        ====================================== */

                        $insert_sql = "
                            INSERT INTO interview_letters
                            (
                                application_id,
                                company_id,
                                student_id,
                                internship_id,
                                interview_date,
                                interview_time,
                                venue,
                                message
                            )

                            VALUES
                            (
                                ?, ?, ?, ?, ?, ?, ?, ?
                            )
                        ";

                        $insert_stmt =
                            mysqli_prepare(
                                $conn,
                                $insert_sql
                            );


                        if (!$insert_stmt) {

                            $error_message =
                                "Unable to save interview letter.";

                        } else {


                            mysqli_stmt_bind_param(
                                $insert_stmt,
                                "iiiissss",
                                $application_id,
                                $company_id,
                                $student_id,
                                $internship_id,
                                $interview_date,
                                $interview_time,
                                $venue,
                                $message
                            );


                            if (
                                mysqli_stmt_execute(
                                    $insert_stmt
                                )
                            ) {


                                /* ----------------------------------
                                   GET NEW LETTER ID
                                ---------------------------------- */

                                $letter_id =
                                    mysqli_insert_id($conn);


                                mysqli_stmt_close(
                                    $insert_stmt
                                );


                                /* ==================================
                                   CREATE STUDENT NOTIFICATION
                                ================================== */

                                $notification_message =
                                    "Interview Letter issued for " .
                                    $internship_title .
                                    ". Please view your Interview Letter.";


                                /*
                                 * Student-side page:
                                 *
                                 * student/interview_letter.php?id=LETTER_ID
                                 */

                                $notification_link =
                                    "interview_letter.php?id="
                                    . $letter_id;


                                $notification_sql = "
                                    INSERT INTO notifications
                                    (
                                        user_id,
                                        message,
                                        link,
                                        is_read
                                    )

                                    VALUES
                                    (
                                        ?, ?, ?, 0
                                    )
                                ";


                                $notification_stmt =
                                    mysqli_prepare(
                                        $conn,
                                        $notification_sql
                                    );


                                if ($notification_stmt) {


                                    mysqli_stmt_bind_param(
                                        $notification_stmt,
                                        "iss",
                                        $student_id,
                                        $notification_message,
                                        $notification_link
                                    );


                                    mysqli_stmt_execute(
                                        $notification_stmt
                                    );


                                    mysqli_stmt_close(
                                        $notification_stmt
                                    );

                                }


                                /* ==================================
                                   SUCCESS MESSAGE
                                ================================== */

                                $success_message =
                                    "Interview letter issued successfully. " .
                                    "The student has been notified.";


                                /* ----------------------------------
                                   SHOW LETTER IMMEDIATELY
                                ---------------------------------- */

                                $existing_letter = [

                                    "id" =>
                                        $letter_id,

                                    "interview_date" =>
                                        $interview_date,

                                    "interview_time" =>
                                        $interview_time,

                                    "venue" =>
                                        $venue,

                                    "message" =>
                                        $message,

                                    "created_at" =>
                                        date("Y-m-d H:i:s")
                                ];

                            } else {

                                mysqli_stmt_close(
                                    $insert_stmt
                                );

                                $error_message =
                                    "Unable to save interview letter.";
                            }

                        }

                    }

                }

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
    Issue Interview Letter
</title>


<style>

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    font-family: Arial, sans-serif;

    background: #f4f6fb;

    color: #333;

}


.container {

    width: 95%;

    max-width: 1100px;

    margin: 30px auto;

}


.header {

    background: #1769ff;

    color: white;

    padding: 20px 25px;

    border-radius: 10px 10px 0 0;

}


.header h1 {

    margin: 0;

    font-size: 27px;

}


.card {

    background: white;

    padding: 30px;

    border-radius: 0 0 10px 10px;

    box-shadow:
        0 4px 15px rgba(0,0,0,0.08);

}


.student-details {

    margin-bottom: 25px;

    padding: 20px;

    background: #f8f9ff;

    border-radius: 8px;

}


.student-details h2 {

    margin-top: 0;

}


.student-details p {

    margin: 10px 0;

    font-size: 16px;

}


.info {

    background: #e7f1ff;

    color: #084298;

    padding: 16px;

    border-radius: 8px;

    margin-bottom: 20px;

    line-height: 1.6;

}


.success {

    background: #d1e7dd;

    color: #0f5132;

    padding: 16px;

    border-radius: 8px;

    margin-bottom: 20px;

}


.error {

    background: #f8d7da;

    color: #842029;

    padding: 16px;

    border-radius: 8px;

    margin-bottom: 20px;

}


label {

    display: block;

    margin-top: 20px;

    margin-bottom: 8px;

    font-weight: bold;

}


.required {

    color: red;

}


input,
textarea {

    width: 100%;

    padding: 13px;

    border: 1px solid #d8dce5;

    border-radius: 7px;

    font-size: 15px;

}


textarea {

    min-height: 140px;

    resize: vertical;

}


input:focus,
textarea:focus {

    outline: none;

    border-color: #1769ff;

}


.btn {

    display: inline-block;

    border: none;

    padding: 13px 20px;

    border-radius: 7px;

    text-decoration: none;

    cursor: pointer;

    font-size: 15px;

    margin-top: 20px;

}


.generate-btn {

    background: #1769ff;

    color: white;

}


.generate-btn:hover {

    background: #0d55d9;

}


.back-btn {

    background: #6c757d;

    color: white;

    margin-left: 8px;

}


.letter-preview {

    margin-top: 30px;

    padding: 45px;

    border: 1px solid #ddd;

    background: white;

    line-height: 1.7;

}


.letter-header {

    text-align: center;

    border-bottom: 2px solid #1769ff;

    padding-bottom: 20px;

    margin-bottom: 25px;

}


.letter-header h2 {

    margin: 0;

    color: #1769ff;

}


.letter-title {

    text-align: center;

    margin: 25px 0;

    text-decoration: underline;

}


.details-box {

    background: #f5f7fb;

    padding: 20px;

    border-radius: 8px;

    margin: 20px 0;

}


.print-btn {

    background: #198754;

    color: white;

}


@media print {

    body {

        background: white;

    }


    .no-print {

        display: none !important;

    }


    .container {

        width: 100%;

        margin: 0;

    }


    .card {

        box-shadow: none;

        padding: 0;

    }


    .letter-preview {

        border: none;

        padding: 30px;

    }

}

</style>

</head>


<body>


<div class="container">


    <!-- HEADER -->

    <div class="header no-print">

        <h1>
            📄 Issue Interview Letter
        </h1>

    </div>


    <div class="card">


        <!-- SUCCESS -->

        <?php if ($success_message !== ""): ?>

            <div class="success no-print">

                ✅
                <?= htmlspecialchars(
                    $success_message
                ) ?>

            </div>

        <?php endif; ?>


        <!-- ERROR -->

        <?php if ($error_message !== ""): ?>

            <div class="error no-print">

                ❌
                <?= htmlspecialchars(
                    $error_message
                ) ?>

            </div>

        <?php endif; ?>


        <!-- STUDENT DETAILS -->

        <div class="student-details no-print">

            <h2>
                Student Details
            </h2>


            <p>

                <strong>
                    Name:
                </strong>

                <?= htmlspecialchars(
                    $student_name
                ) ?>

            </p>


            <p>

                <strong>
                    Email:
                </strong>

                <?= htmlspecialchars(
                    $student_email
                ) ?>

            </p>


            <p>

                <strong>
                    Internship:
                </strong>

                <?= htmlspecialchars(
                    $internship_title
                ) ?>

            </p>


            <p>

                <strong>
                    Company:
                </strong>

                <?= htmlspecialchars(
                    $company_name
                ) ?>

            </p>

        </div>


        <?php if ($existing_letter): ?>


            <!-- =========================================
                 EXISTING INTERVIEW LETTER
            ========================================== -->

            <div class="letter-preview">


                <div class="letter-header">

                    <h2>

                        <?= htmlspecialchars(
                            $company_name
                        ) ?>

                    </h2>


                    <p>
                        Digital Internship Portal
                    </p>


                    <p>
                        Official Internship Interview Letter
                    </p>

                </div>


                <p style="text-align:right;">

                    Date:

                    <?= date("d-m-Y") ?>

                </p>


                <h2 class="letter-title">

                    INTERVIEW LETTER

                </h2>


                <p>

                    <strong>
                        To,
                    </strong>

                </p>


                <p>

                    <strong>

                        <?= htmlspecialchars(
                            $student_name
                        ) ?>

                    </strong>

                    <br>

                    <?= htmlspecialchars(
                        $student_email
                    ) ?>

                </p>


                <p>

                    Dear

                    <strong>

                        <?= htmlspecialchars(
                            $student_name
                        ) ?>

                    </strong>,

                </p>


                <p>

                    We are pleased to invite you to attend an
                    <strong>
                        offline interview
                    </strong>
                    for the
                    <strong>

                        <?= htmlspecialchars(
                            $internship_title
                        ) ?>

                    </strong>
                    position through the Digital Internship Portal.

                </p>


                <p>

                    Your performance in the online mock test has
                    successfully met the required criteria. You are
                    therefore invited to attend the offline interview
                    as part of the internship selection process.

                </p>


                <!-- INTERVIEW DETAILS -->

                <div class="details-box">

                    <h3>
                        Interview Details
                    </h3>


                    <p>

                        <strong>
                            Interview Date:
                        </strong>

                        <?= date(
                            "d-m-Y",
                            strtotime(
                                $existing_letter[
                                    "interview_date"
                                ]
                            )
                        ) ?>

                    </p>


                    <p>

                        <strong>
                            Interview Time:
                        </strong>

                        <?= date(
                            "h:i A",
                            strtotime(
                                $existing_letter[
                                    "interview_time"
                                ]
                            )
                        ) ?>

                    </p>


                    <p>

                        <strong>
                            Venue:
                        </strong>

                        <?= htmlspecialchars(
                            $existing_letter["venue"]
                        ) ?>

                    </p>

                </div>


                <!-- ADDITIONAL MESSAGE -->

                <?php if (
                    !empty(
                        $existing_letter["message"]
                    )
                ): ?>

                    <h3>
                        Additional Instructions
                    </h3>


                    <p>

                        <?= nl2br(
                            htmlspecialchars(
                                $existing_letter["message"]
                            )
                        ) ?>

                    </p>

                <?php endif; ?>


                <!-- IMPORTANT INSTRUCTIONS -->

                <h3>
                    Important Instructions
                </h3>


                <ul>

                    <li>
                        Please bring your updated resume.
                    </li>

                    <li>
                        Bring your educational certificates
                        and relevant documents.
                    </li>

                    <li>
                        Carry a valid photo ID proof
                        for verification.
                    </li>

                    <li>
                        Please arrive at least 10–15 minutes
                        before the scheduled interview time.
                    </li>

                    <li>
                        Be prepared to discuss your technical
                        skills, academic projects and relevant
                        experience.
                    </li>

                </ul>


                <p>

                    We look forward to meeting you and learning more
                    about your skills and interests. We wish you the
                    very best for your interview and future career.

                </p>


                <br>


                <p>

                    <strong>
                        Regards,
                    </strong>

                    <br>

                    <?= htmlspecialchars(
                        $company_name
                    ) ?>

                    <br>

                    Recruitment Team

                    <br>

                    Digital Internship Portal

                </p>


            </div>


            <!-- BUTTONS -->

            <div class="no-print">


                

                <a
                    href="applications.php"
                    class="btn back-btn"
                >

                    ← Back to Applications

                </a>


            </div>


        <?php else: ?>


            <!-- =========================================
                 ISSUE LETTER FORM
            ========================================== -->


            <div class="info">

                ✅ The student has successfully completed

                <strong>
                    Level 2 with 4/5 or above
                </strong>.

                <br><br>

                You can now issue the offline interview letter.

            </div>


            <form method="POST">


                <!-- DATE -->

                <label>

                    Interview Date

                    <span class="required">
                        *
                    </span>

                </label>


                <input
                    type="date"
                    name="interview_date"
                    required
                    min="<?= date("Y-m-d") ?>"
                >


                <!-- TIME -->

                <label>

                    Interview Time

                    <span class="required">
                        *
                    </span>

                </label>


                <input
                    type="time"
                    name="interview_time"
                    required
                >


                <!-- VENUE -->

                <label>

                    Interview Venue

                    <span class="required">
                        *
                    </span>

                </label>


                <input
                    type="text"
                    name="venue"
                    placeholder="Enter interview venue"
                    required
                >


                <!-- MESSAGE -->

                <label>

                    Additional Message

                </label>


                <textarea
                    name="message"
                    placeholder="Enter additional interview instructions..."
                ></textarea>


                <!-- SUBMIT -->

                <button
                    type="submit"
                    class="btn generate-btn"
                >

                    📄 Issue Interview Letter

                </button>


                <a
                    href="applications.php"
                    class="btn back-btn"
                >

                    ← Back to Applications

                </a>


            </form>


        <?php endif; ?>


    </div>

</div>


</body>

</html>