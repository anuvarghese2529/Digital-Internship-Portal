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

$letter_id = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

if ($letter_id <= 0) {
    die("Invalid interview letter.");
}


/* ==================================================
   GET INTERVIEW LETTER
================================================== */

$sql = "
    SELECT
        il.id,
        il.application_id,
        il.interview_date,
        il.interview_time,
        il.venue,
        il.message,
        il.created_at,

        u.name AS student_name,
        u.email AS student_email,

        i.title AS internship_title,
        i.company_name

    FROM interview_letters il

    INNER JOIN users u
        ON il.student_id = u.id

    INNER JOIN internships i
        ON il.internship_id = i.id

    WHERE il.id = ?
    AND il.student_id = ?

    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database error.");
}

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $letter_id,
    $student_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$letter = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if (!$letter) {
    die("Interview letter not found.");
}


/* ==================================================
   FORMAT DETAILS
================================================== */

$formatted_date = date(
    "d-m-Y",
    strtotime($letter["interview_date"])
);

$formatted_time = date(
    "h:i A",
    strtotime($letter["interview_time"])
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
    Interview Letter
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

.page {

    width: 95%;

    max-width: 1000px;

    margin: 30px auto;

}

.top-bar {

    background: #1769ff;

    color: white;

    padding: 18px 25px;

    border-radius: 10px 10px 0 0;

    display: flex;

    justify-content: space-between;

    align-items: center;

}

.top-bar h2 {

    margin: 0;

}

.letter {

    background: white;

    padding: 50px;

    box-shadow:
        0 4px 18px rgba(0,0,0,0.08);

    border-radius: 0 0 10px 10px;

    line-height: 1.7;

}

.company-header {

    text-align: center;

    border-bottom: 2px solid #1769ff;

    padding-bottom: 20px;

    margin-bottom: 25px;

}

.company-header h1 {

    margin: 0;

    color: #1769ff;

    font-size: 28px;

}

.company-header p {

    margin: 5px 0;

    color: #666;

}

.letter-date {

    text-align: right;

    margin-bottom: 25px;

}

.title {

    text-align: center;

    font-size: 23px;

    margin: 25px 0;

    text-decoration: underline;

}

.student-info {

    margin-bottom: 25px;

}

.details-box {

    background: #f5f7fb;

    border-left: 5px solid #1769ff;

    padding: 20px;

    margin: 25px 0;

    border-radius: 6px;

}

.details-box h3 {

    margin-top: 0;

    color: #1769ff;

}

.details-box p {

    margin: 8px 0;

}

.instructions {

    margin-top: 25px;

}

.instructions li {

    margin-bottom: 8px;

}

.signature {

    margin-top: 40px;

}

.buttons {

    margin-top: 25px;

    display: flex;

    gap: 10px;

    flex-wrap: wrap;

}

.btn {

    border: none;

    padding: 13px 20px;

    border-radius: 7px;

    text-decoration: none;

    cursor: pointer;

    font-size: 15px;

}

.print-btn {

    background: #198754;

    color: white;

}

.back-btn {

    background: #6c757d;

    color: white;

}


/* ==============================================
   PRINT
============================================== */

@media print {

    body {

        background: white;

    }

    .page {

        width: 100%;

        max-width: none;

        margin: 0;

    }

    .top-bar,
    .buttons {

        display: none !important;

    }

    .letter {

        box-shadow: none;

        border-radius: 0;

        padding: 30px;

    }

}


/* ==============================================
   MOBILE
============================================== */

@media (max-width: 600px) {

    .letter {

        padding: 25px;

    }

    .top-bar {

        flex-direction: column;

        gap: 10px;

        text-align: center;

    }

}

</style>

</head>


<body>


<div class="page">


    <!-- TOP BAR -->

    <div class="top-bar">

        <h2>
            📄 Interview Letter
        </h2>

        <span>
            Digital Internship Portal
        </span>

    </div>


    <!-- LETTER -->

    <div class="letter">


        <!-- COMPANY HEADER -->

        <div class="company-header">

            <h1>
                <?= htmlspecialchars(
                    $letter["company_name"]
                ) ?>
            </h1>

            <p>
                Digital Internship Portal
            </p>

            <p>
                Official Internship Interview Letter
            </p>

        </div>


        <!-- LETTER DATE -->

        <div class="letter-date">

            Date:
            <?= date("d-m-Y") ?>

        </div>


        <!-- TITLE -->

        <div class="title">

            INTERVIEW LETTER

        </div>


        <!-- STUDENT -->

        <div class="student-info">

            <p>
                <strong>To,</strong>
            </p>

            <p>

                <strong>
                    <?= htmlspecialchars(
                        $letter["student_name"]
                    ) ?>
                </strong>

                <br>

                <?= htmlspecialchars(
                    $letter["student_email"]
                ) ?>

            </p>

        </div>


        <!-- GREETING -->

        <p>

            Dear
            <strong>
                <?= htmlspecialchars(
                    $letter["student_name"]
                ) ?>
            </strong>,

        </p>


        <!-- MAIN MESSAGE -->

        <p>

            We are pleased to invite you to attend an
            <strong>offline interview</strong> for the
            <strong>
                <?= htmlspecialchars(
                    $letter["internship_title"]
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

                <?= $formatted_date ?>

            </p>

            <p>

                <strong>
                    Interview Time:
                </strong>

                <?= $formatted_time ?>

            </p>

            <p>

                <strong>
                    Venue:
                </strong>

                <?= htmlspecialchars(
                    $letter["venue"]
                ) ?>

            </p>

        </div>


        <!-- ADDITIONAL MESSAGE -->

        <?php if (!empty($letter["message"])): ?>

            <h3>
                Additional Instructions
            </h3>

            <p>

                <?= nl2br(
                    htmlspecialchars(
                        $letter["message"]
                    )
                ) ?>

            </p>

        <?php endif; ?>


        <!-- IMPORTANT INSTRUCTIONS -->

        <div class="instructions">

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
                    Carry a valid photo ID proof for verification.
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

        </div>


        <!-- CLOSING -->

        <p>

            We look forward to meeting you and learning more
            about your skills and interests. We wish you the
            very best for your interview and future career.

        </p>


        <!-- SIGNATURE -->

        <div class="signature">

            <p>

                <strong>
                    Regards,
                </strong>

                <br>

                <?= htmlspecialchars(
                    $letter["company_name"]
                ) ?>

                <br>

                Recruitment Team

                <br>

                Digital Internship Portal

            </p>

        </div>


        <!-- BUTTONS -->

        <div class="buttons">

            <button
                onclick="window.print()"
                class="btn print-btn"
            >
                🖨️ Download / Save as PDF
            </button>


            <a
                href="notifications.php"
                class="btn back-btn"
            >
                ← Back to Notifications
            </a>

        </div>


    </div>

</div>


</body>

</html>