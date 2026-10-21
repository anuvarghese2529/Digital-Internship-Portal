<?php

session_start();

date_default_timezone_set("Asia/Kolkata");

require_once "../config/db.php";


/* ==================================================
   ADMIN LOGIN CHECK
================================================== */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: ../login.php");
    exit();
}


/* ==================================================
   HELPER FUNCTION
================================================== */

function calculateSkillMatch($studentSkills, $internshipSkills)
{
    if (
        empty(trim($studentSkills)) ||
        empty(trim($internshipSkills))
    ) {
        return 0;
    }

    $studentSkillsArray = preg_split(
        '/[,;\n]+/',
        strtolower($studentSkills)
    );

    $internshipSkillsArray = preg_split(
        '/[,;\n]+/',
        strtolower($internshipSkills)
    );

    $studentSkillsArray = array_filter(
        array_map('trim', $studentSkillsArray)
    );

    $internshipSkillsArray = array_filter(
        array_map('trim', $internshipSkillsArray)
    );

    if (count($internshipSkillsArray) === 0) {
        return 0;
    }

    $matched = 0;

    foreach ($internshipSkillsArray as $requiredSkill) {

        if ($requiredSkill === "") {
            continue;
        }

        foreach ($studentSkillsArray as $studentSkill) {

            if (
                $studentSkill === $requiredSkill ||
                strpos($studentSkill, $requiredSkill) !== false ||
                strpos($requiredSkill, $studentSkill) !== false
            ) {
                $matched++;
                break;
            }
        }
    }

    return round(
        ($matched / count($internshipSkillsArray)) * 100
    );
}


/* ==================================================
   FORMAT TIME
================================================== */

function formatTestTime($seconds)
{
    if (
        $seconds === null ||
        $seconds === ""
    ) {
        return "-";
    }

    $seconds = max(
        0,
        (int)$seconds
    );

    $hours = floor(
        $seconds / 3600
    );

    $minutes = floor(
        ($seconds % 3600) / 60
    );

    $remainingSeconds =
        $seconds % 60;

    if ($hours > 0) {

        return sprintf(
            "%02d:%02d:%02d",
            $hours,
            $minutes,
            $remainingSeconds
        );
    }

    return sprintf(
        "%02d:%02d",
        $minutes,
        $remainingSeconds
    );
}


/* ==================================================
   DELETE APPLICATION
================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_application_id"])
) {

    $delete_application_id =
        (int)$_POST["delete_application_id"];

    if ($delete_application_id > 0) {

        mysqli_begin_transaction($conn);

        try {

            /* ------------------------------------------
               DELETE TEST RESULTS
            ------------------------------------------ */

            $delete_test_sql = "
                DELETE FROM test_results
                WHERE application_id = ?
            ";

            $delete_test_stmt =
                mysqli_prepare(
                    $conn,
                    $delete_test_sql
                );

            if (!$delete_test_stmt) {
                throw new Exception(
                    "Unable to delete test results."
                );
            }

            mysqli_stmt_bind_param(
                $delete_test_stmt,
                "i",
                $delete_application_id
            );

            if (
                !mysqli_stmt_execute(
                    $delete_test_stmt
                )
            ) {
                throw new Exception(
                    "Unable to delete test results."
                );
            }

            mysqli_stmt_close(
                $delete_test_stmt
            );


            /* ------------------------------------------
               DELETE INTERVIEW LETTER
            ------------------------------------------ */

            $delete_letter_sql = "
                DELETE FROM interview_letters
                WHERE application_id = ?
            ";

            $delete_letter_stmt =
                mysqli_prepare(
                    $conn,
                    $delete_letter_sql
                );

            if ($delete_letter_stmt) {

                mysqli_stmt_bind_param(
                    $delete_letter_stmt,
                    "i",
                    $delete_application_id
                );

                mysqli_stmt_execute(
                    $delete_letter_stmt
                );

                mysqli_stmt_close(
                    $delete_letter_stmt
                );
            }


            /* ------------------------------------------
               DELETE APPLICATION
            ------------------------------------------ */

            $delete_application_sql = "
                DELETE FROM applications
                WHERE id = ?
            ";

            $delete_application_stmt =
                mysqli_prepare(
                    $conn,
                    $delete_application_sql
                );

            if (!$delete_application_stmt) {
                throw new Exception(
                    "Unable to delete application."
                );
            }

            mysqli_stmt_bind_param(
                $delete_application_stmt,
                "i",
                $delete_application_id
            );

            if (
                !mysqli_stmt_execute(
                    $delete_application_stmt
                )
            ) {
                throw new Exception(
                    "Unable to delete application."
                );
            }

            mysqli_stmt_close(
                $delete_application_stmt
            );


            mysqli_commit($conn);

            header(
                "Location: applications.php?deleted=1"
            );

            exit();

        } catch (Exception $e) {

            mysqli_rollback($conn);

            header(
                "Location: applications.php?deleted=0"
            );

            exit();
        }
    }
}


/* ==================================================
   GET ALL APPLICATIONS
================================================== */

$sql = "
    SELECT
        a.id AS application_id,
        a.student_id,
        a.internship_id,
        a.status AS application_status,
        a.applied_at,

        u.name AS student_name,
        u.email AS student_email,
        u.education,
        u.skills AS student_skills,
        u.resume,

        i.title AS internship_title,
        i.company_name,
        i.skills AS internship_skills

    FROM applications a

    INNER JOIN users u
        ON a.student_id = u.id

    INNER JOIN internships i
        ON a.internship_id = i.id

    ORDER BY
        a.applied_at DESC,
        a.id DESC
";


$stmt =
    mysqli_prepare(
        $conn,
        $sql
    );

if (!$stmt) {
    die("Database error.");
}

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);

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
    Applications - Admin
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

    padding: 25px;

}


/* ==================================================
   MAIN CONTAINER
================================================== */

.container {

    width: 96%;

    max-width: 1500px;

    margin: auto;

}


/* ==================================================
   TOP SECTION
================================================== */

.top-section {

    background: white;

    padding: 25px;

    border-radius: 14px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 18px rgba(0,0,0,0.08);

}


.top-section h1 {

    color: #667eea;

    margin-bottom: 8px;

}


.top-section p {

    color: #777;

    line-height: 1.6;

}


.back-btn {

    display: inline-block;

    margin-top: 16px;

    padding: 10px 18px;

    background: #667eea;

    color: white;

    text-decoration: none;

    border-radius: 7px;

    font-weight: bold;

}


.back-btn:hover {

    background: #5568d9;

}


/* ==================================================
   ALERT
================================================== */

.alert {

    padding: 14px 18px;

    border-radius: 9px;

    margin-bottom: 20px;

    font-weight: bold;

}


.alert-success {

    background: #d1e7dd;

    color: #0f5132;

}


.alert-error {

    background: #f8d7da;

    color: #842029;

}


/* ==================================================
   APPLICATION CARD
================================================== */

.application-card {

    background: white;

    border-radius: 14px;

    margin-bottom: 25px;

    padding: 25px;

    box-shadow:
        0 5px 18px rgba(0,0,0,0.08);

}


/* ==================================================
   HEADER
================================================== */

.application-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-bottom: 20px;

    padding-bottom: 15px;

    border-bottom: 1px solid #eee;

}


.application-header h2 {

    color: #333;

    margin-bottom: 6px;

}


.application-header p {

    color: #777;

}


/* ==================================================
   STATUS
================================================== */

.status {

    padding: 8px 14px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: bold;

}


.status-pending {

    background: #fff3cd;

    color: #856404;

}


.status-shortlisted {

    background: #d1e7dd;

    color: #0f5132;

}


.status-rejected {

    background: #f8d7da;

    color: #842029;

}


/* ==================================================
   INFO GRID
================================================== */

.info-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 15px;

    margin-bottom: 20px;

}


.info-box {

    background: #f8f9ff;

    padding: 15px;

    border-radius: 9px;

}


.info-box strong {

    display: block;

    color: #667eea;

    margin-bottom: 6px;

}


/* ==================================================
   SKILL BOX
================================================== */

.skill-box {

    background: #f8f9ff;

    padding: 15px;

    border-radius: 9px;

    margin-bottom: 18px;

    line-height: 1.7;

}


.skill-box strong {

    color: #667eea;

}


/* ==================================================
   SKILL MATCH
================================================== */

.skill-match {

    background: #f3f4ff;

    padding: 18px;

    border-radius: 10px;

    margin: 20px 0;

}


.skill-match-title {

    font-weight: bold;

    margin-bottom: 10px;

}


.progress {

    width: 100%;

    height: 22px;

    background: #ddd;

    border-radius: 20px;

    overflow: hidden;

}


.progress-bar {

    height: 100%;

    background: #667eea;

    color: white;

    text-align: center;

    line-height: 22px;

    font-size: 12px;

    font-weight: bold;

}


/* ==================================================
   RESUME
================================================== */

.resume-section {

    margin: 22px 0;

    padding: 18px;

    background: #f8f9fa;

    border-radius: 10px;

}


.resume-title {

    font-size: 17px;

    font-weight: bold;

    margin-bottom: 12px;

}


.resume-btn {

    display: inline-block;

    padding: 9px 14px;

    background: #198754;

    color: white;

    text-decoration: none;

    border-radius: 7px;

    margin-right: 7px;

    margin-bottom: 5px;

    font-size: 14px;

}


.resume-btn:hover {

    background: #146c43;

}


.no-resume {

    color: #888;

}


/* ==================================================
   TEST SECTION
================================================== */

.test-section {

    background: #f8f9fa;

    border-radius: 10px;

    padding: 20px;

    margin-top: 20px;

}


.test-section h3 {

    color: #333;

    margin-bottom: 18px;

}


/* ==================================================
   TEST SUMMARY
================================================== */

.test-summary {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 12px;

    margin-bottom: 20px;

}


.test-box {

    background: white;

    border: 1px solid #eee;

    padding: 15px;

    border-radius: 8px;

    text-align: center;

}


.test-box strong {

    display: block;

    color: #667eea;

    font-size: 20px;

    margin-top: 7px;

}


/* ==================================================
   TEST HISTORY
================================================== */

.test-result-row {

    background: white;

    padding: 15px;

    margin-bottom: 10px;

    border-radius: 8px;

    border-left: 4px solid #667eea;

}


.test-line {

    margin-top: 7px;

    line-height: 1.7;

}


.pass {

    color: #198754;

    font-weight: bold;

}


.fail {

    color: #dc3545;

    font-weight: bold;

}


/* ==================================================
   TEST STATUS
================================================== */

.final-pass {

    background: #d1e7dd;

    color: #0f5132;

    padding: 15px;

    border-radius: 9px;

    font-weight: bold;

    margin-top: 15px;

}


.test-pending {

    background: #fff3cd;

    color: #856404;

    padding: 15px;

    border-radius: 9px;

    margin-top: 15px;

}


.test-locked {

    background: #f8d7da;

    color: #842029;

    padding: 15px;

    border-radius: 9px;

    margin-top: 15px;

}


/* ==================================================
   VIDEO BUTTON
================================================== */

.video-btn {

    display: inline-block;

    margin-top: 8px;

    padding: 8px 12px;

    background: #212529;

    color: white;

    text-decoration: none;

    border-radius: 6px;

    font-size: 13px;

}


.video-btn:hover {

    background: #000;

}


/* ==================================================
   DELETE SECTION
================================================== */

.bottom-section {

    margin-top: 20px;

    padding-top: 18px;

    border-top: 1px solid #eee;

    display: flex;

    justify-content: flex-end;

}


.delete-btn {

    border: none;

    background: #dc3545;

    color: white;

    padding: 10px 16px;

    border-radius: 7px;

    cursor: pointer;

    font-size: 14px;

    font-weight: bold;

}


.delete-btn:hover {

    background: #bb2d3b;

}


/* ==================================================
   NO APPLICATIONS
================================================== */

.no-applications {

    background: white;

    padding: 50px;

    text-align: center;

    border-radius: 14px;

    color: #777;

    box-shadow:
        0 5px 18px rgba(0,0,0,0.06);

}


.no-applications h2 {

    margin-bottom: 10px;

    color: #555;

}


/* ==================================================
   RESPONSIVE
================================================== */

@media (max-width: 1000px) {

    .test-summary {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media (max-width: 750px) {

    .info-grid {

        grid-template-columns: 1fr;

    }

    .application-header {

        flex-direction: column;

        align-items: flex-start;

    }

}


@media (max-width: 500px) {

    body {

        padding: 12px;

    }

    .container {

        width: 100%;

    }

    .test-summary {

        grid-template-columns: 1fr;

    }

}

</style>

</head>


<body>


<div class="container">


    <!-- ==================================================
         TOP
    ================================================== -->

    <div class="top-section">

        <h1>
            📄 Student Applications
        </h1>

        <p>
            View all student applications,
            resumes, skill matching and mock test performance.
        </p>

        <a
            href="dashboard.php"
            class="back-btn"
        >
            ← Back to Dashboard
        </a>

    </div>


    <!-- ==================================================
         ALERTS
    ================================================== -->

    <?php if (isset($_GET["deleted"])): ?>

        <?php if ($_GET["deleted"] == "1"): ?>

            <div class="alert alert-success">
                ✅ Application deleted successfully.
            </div>

        <?php else: ?>

            <div class="alert alert-error">
                ❌ Unable to delete application.
            </div>

        <?php endif; ?>

    <?php endif; ?>


    <!-- ==================================================
         APPLICATIONS
    ================================================== -->

    <?php if (mysqli_num_rows($result) === 0): ?>

        <div class="no-applications">

            <h2>
                No Applications Yet
            </h2>

            <p>
                Student applications will appear here.
            </p>

        </div>

    <?php endif; ?>


    <?php while ($row = mysqli_fetch_assoc($result)): ?>


        <?php

        $application_id =
            (int)$row["application_id"];

        $student_id =
            (int)$row["student_id"];

        $internship_id =
            (int)$row["internship_id"];


        /* ==========================================
           STATUS CLASS
        ========================================== */

        $status_class =
            "status-pending";

        if (
            $row["application_status"]
            === "Shortlisted"
        ) {

            $status_class =
                "status-shortlisted";
        }

        if (
            $row["application_status"]
            === "Rejected"
        ) {

            $status_class =
                "status-rejected";
        }


        /* ==========================================
           SKILL MATCH
        ========================================== */

        $skill_match =
            calculateSkillMatch(
                $row["student_skills"] ?? "",
                $row["internship_skills"] ?? ""
            );


        /* ==========================================
           RESUME FILE
        ========================================== */

        $resume_file = "";

        if (
            !empty($row["resume"])
        ) {

            /*
             * basename() is important.
             *
             * If database contains:
             * resumes/resume_18_xxx.pdf
             *
             * it becomes:
             * resume_18_xxx.pdf
             *
             * Therefore the wrong /resumes/
             * folder will NOT appear.
             */

            $resume_file =
                basename(
                    $row["resume"]
                );
        }


        /* ==========================================
           TEST RESULTS
        ========================================== */

        $test_results = [];

        $level1_attempts = 0;

        $final_level2_passed = false;

        $final_level2_score = 0;

        $last_level = 0;

        $last_score = 0;

        $last_percentage = 0;


        $test_sql = "
            SELECT
                id,
                level,
                score,
                total_questions,
                percentage,
                passed,
                time_limit_seconds,
                time_taken_seconds,
                video_path,
                created_at

            FROM test_results

            WHERE application_id = ?

            ORDER BY
                created_at ASC,
                id ASC
        ";


        $test_stmt =
            mysqli_prepare(
                $conn,
                $test_sql
            );


        if ($test_stmt) {

            mysqli_stmt_bind_param(
                $test_stmt,
                "i",
                $application_id
            );

            mysqli_stmt_execute(
                $test_stmt
            );

            $test_result =
                mysqli_stmt_get_result(
                    $test_stmt
                );


            while (
                $test =
                mysqli_fetch_assoc(
                    $test_result
                )
            ) {

                $test_results[] =
                    $test;


                $last_level =
                    (int)$test["level"];

                $last_score =
                    (int)$test["score"];

                $last_percentage =
                    (float)$test["percentage"];


                if (
                    (int)$test["level"] === 1
                ) {

                    $level1_attempts++;
                }


                /*
                 * FINAL LEVEL 2 PASS
                 * Minimum 4/5
                 */

                if (
                    (int)$test["level"] === 2 &&
                    (int)$test["passed"] === 1 &&
                    (int)$test["score"] >= 4 &&
                    (int)$test["total_questions"] === 5
                ) {

                    $final_level2_passed =
                        true;

                    $final_level2_score =
                        (int)$test["score"];
                }

            }


            mysqli_stmt_close(
                $test_stmt
            );
        }


        $has_test =
            count($test_results) > 0;


        $max_attempts_reached =
            $level1_attempts >= 5 &&
            !$final_level2_passed;

        ?>


        <!-- ==================================================
             APPLICATION CARD
        ================================================== -->

        <div class="application-card">


            <!-- HEADER -->

            <div class="application-header">

                <div>

                    <h2>
                        <?= htmlspecialchars(
                            $row["student_name"]
                        ) ?>
                    </h2>

                    <p>
                        Internship:
                        <strong>
                            <?= htmlspecialchars(
                                $row["internship_title"]
                            ) ?>
                        </strong>
                    </p>

                    <p style="margin-top:5px;">
                        Company:
                        <strong>
                            <?= htmlspecialchars(
                                $row["company_name"]
                            ) ?>
                        </strong>
                    </p>

                </div>


                <span
                    class="status <?= $status_class ?>"
                >
                    <?= htmlspecialchars(
                        $row["application_status"]
                    ) ?>
                </span>

            </div>


            <!-- ==================================================
                 STUDENT DETAILS
            ================================================== -->

            <div class="info-grid">


                <div class="info-box">

                    <strong>
                        👤 Student
                    </strong>

                    <?= htmlspecialchars(
                        $row["student_name"]
                    ) ?>

                </div>


                <div class="info-box">

                    <strong>
                        📧 Email
                    </strong>

                    <?= htmlspecialchars(
                        $row["student_email"]
                    ) ?>

                </div>


                <div class="info-box">

                    <strong>
                        🎓 Education
                    </strong>

                    <?= !empty(
                        $row["education"]
                    )

                        ? htmlspecialchars(
                            $row["education"]
                        )

                        : "Not provided"
                    ?>

                </div>


                <div class="info-box">

                    <strong>
                        📅 Applied On
                    </strong>

                    <?= date(
                        "d-m-Y h:i A",
                        strtotime(
                            $row["applied_at"]
                        )
                    ) ?>

                </div>


                <div class="info-box">

                    <strong>
                        🆔 Application ID
                    </strong>

                    #<?= $application_id ?>

                </div>


                <div class="info-box">

                    <strong>
                        🏢 Internship ID
                    </strong>

                    #<?= $internship_id ?>

                </div>


            </div>


            <!-- ==================================================
                 STUDENT SKILLS
            ================================================== -->

            <div class="skill-box">

                <strong>
                    💻 Student Skills
                </strong>

                <br>

                <?= !empty(
                    $row["student_skills"]
                )

                    ? nl2br(
                        htmlspecialchars(
                            $row["student_skills"]
                        )
                    )

                    : "No skills provided"
                ?>

            </div>


            <!-- ==================================================
                 REQUIRED SKILLS
            ================================================== -->

            <div class="skill-box">

                <strong>
                    🎯 Required Internship Skills
                </strong>

                <br>

                <?= !empty(
                    $row["internship_skills"]
                )

                    ? nl2br(
                        htmlspecialchars(
                            $row["internship_skills"]
                        )
                    )

                    : "No specific skills provided"
                ?>

            </div>


            <!-- ==================================================
                 SKILL MATCH
            ================================================== -->

            <div class="skill-match">

                <div class="skill-match-title">

                    🎯 Skill Match:
                    <?= $skill_match ?>%

                </div>


                <div class="progress">

                    <div
                        class="progress-bar"
                        style="
                            width:<?= $skill_match ?>%;
                        "
                    >

                        <?= $skill_match ?>%

                    </div>

                </div>

            </div>


            <!-- ==================================================
                 RESUME
            ================================================== -->

            <div class="resume-section">

                <div class="resume-title">
                    📄 Resume
                </div>


                <?php if (!empty($resume_file)): ?>


                    <?php

                    /*
                     * CORRECT ADMIN PATH
                     *
                     * admin/applications.php
                     *        ↓
                     * ../assets/uploads/
                     *
                     * basename() prevents:
                     * assets/uploads/resumes/file.pdf
                     *
                     * and creates:
                     * assets/uploads/file.pdf
                     */

                    $resume_url =
                        "../assets/uploads/" .
                        rawurlencode(
                            $resume_file
                        );

                    $resume_server_path =
                        __DIR__ .
                        "/../assets/uploads/" .
                        $resume_file;

                    ?>


                    <?php if (
                        file_exists(
                            $resume_server_path
                        )
                    ): ?>


                        <a
                            href="<?= htmlspecialchars(
                                $resume_url
                            ) ?>"
                            target="_blank"
                            rel="noopener"
                            class="resume-btn"
                        >
                            👁 View Resume
                        </a>


                        <a
                            href="<?= htmlspecialchars(
                                $resume_url
                            ) ?>"
                            download
                            class="resume-btn"
                        >
                            ⬇ Download Resume
                        </a>


                    <?php else: ?>


                        <span class="no-resume">

                            ⚠ Resume record exists,
                            but file was not found in
                            <strong>
                                assets/uploads
                            </strong>.

                        </span>


                    <?php endif; ?>


                <?php else: ?>


                    <span class="no-resume">

                        No resume uploaded.

                    </span>


                <?php endif; ?>

            </div>


            <!-- ==================================================
                 MOCK TEST
            ================================================== -->

            <div class="test-section">

                <h3>
                    📝 Mock Test Performance
                </h3>


                <div class="test-summary">


                    <div class="test-box">

                        Total L1 Attempts

                        <strong>
                            <?= $level1_attempts ?> / 5
                        </strong>

                    </div>


                    <div class="test-box">

                        Last Level

                        <strong>

                            <?= $has_test

                                ? "Level " .
                                  $last_level

                                : "-"
                            ?>

                        </strong>

                    </div>


                    <div class="test-box">

                        Last Score

                        <strong>

                            <?= $has_test

                                ? $last_score .
                                  "/" .
                                  ($test_results[
                                      count($test_results)-1
                                  ]["total_questions"])

                                : "-"
                            ?>

                        </strong>

                    </div>


                    <div class="test-box">

                        Last Percentage

                        <strong>

                            <?= $has_test

                                ? $last_percentage .
                                  "%"

                                : "-"
                            ?>

                        </strong>

                    </div>


                </div>


                <?php if (!$has_test): ?>


                    <div class="test-pending">

                        ⏳ Student has not attended
                        the mock test yet.

                    </div>


                <?php else: ?>


                    <strong>
                        Test History
                    </strong>

                    <br><br>


                    <?php

                    $attempt_number = 0;

                    ?>


                    <?php foreach (
                        $test_results
                        as $test
                    ): ?>


                        <?php

                        if (
                            (int)$test["level"] === 1
                        ) {

                            $attempt_number++;
                        }

                        ?>


                        <div
                            class="test-result-row"
                        >


                            <strong>

                                <?php if (
                                    (int)$test["level"] === 1
                                ): ?>

                                    L1 -
                                    Attempt
                                    <?= $attempt_number ?>

                                <?php else: ?>

                                    L2 -
                                    Final Level

                                <?php endif; ?>

                            </strong>


                            <div class="test-line">

                                Score:

                                <strong>
                                    <?= (int)$test["score"] ?>
                                </strong>

                                /

                                <?= (int)$test[
                                    "total_questions"
                                ] ?>

                                &nbsp; | &nbsp;

                                Percentage:

                                <strong>
                                    <?= (float)$test[
                                        "percentage"
                                    ] ?>%
                                </strong>

                                &nbsp; | &nbsp;

                                Result:

                                <?php if (
                                    (int)$test["passed"] === 1
                                ): ?>

                                    <span class="pass">
                                        PASS
                                    </span>

                                <?php else: ?>

                                    <span class="fail">
                                        FAIL
                                    </span>

                                <?php endif; ?>

                            </div>


                            <div class="test-line">

                                <strong>
                                    Time Limit:
                                </strong>

                                <?= htmlspecialchars(
                                    formatTestTime(
                                        $test[
                                            "time_limit_seconds"
                                        ]
                                    )
                                ) ?>

                                &nbsp;&nbsp;

                                <strong>
                                    Time Taken:
                                </strong>

                                <?= htmlspecialchars(
                                    formatTestTime(
                                        $test[
                                            "time_taken_seconds"
                                        ]
                                    )
                                ) ?>

                            </div>


                            <div class="test-line">

                                <small>

                                    Taken On:

                                    <?= date(
                                        "d-m-Y h:i A",
                                        strtotime(
                                            $test[
                                                "created_at"
                                            ]
                                        )
                                    ) ?>

                                </small>

                            </div>


                            <?php if (
                                !empty(
                                    $test["video_path"]
                                )
                            ): ?>


                                <?php

                                $video_path =
                                    $test[
                                        "video_path"
                                    ];

                                /*
                                 * If database already contains:
                                 * assets/uploads/videos/file.webm
                                 *
                                 * then admin page needs:
                                 * ../assets/uploads/videos/file.webm
                                 */

                                if (
                                    strpos(
                                        $video_path,
                                        "http://"
                                    ) === 0 ||

                                    strpos(
                                        $video_path,
                                        "https://"
                                    ) === 0
                                ) {

                                    $video_url =
                                        $video_path;

                                } else {

                                    $video_url =
                                        "../" .
                                        ltrim(
                                            $video_path,
                                            "/"
                                        );
                                }

                                ?>


                                <div
                                    class="test-line"
                                >

                                    <a
                                        href="<?= htmlspecialchars(
                                            $video_url
                                        ) ?>"
                                        target="_blank"
                                        class="video-btn"
                                    >
                                        🎥 View Test Video
                                    </a>

                                </div>


                            <?php endif; ?>


                        </div>


                    <?php endforeach; ?>


                    <?php if (
                        $final_level2_passed
                    ): ?>


                        <div class="final-pass">

                            ✅ Final Mock Test Passed

                            <br><br>

                            Level 2 Score:

                            <strong>
                                <?= $final_level2_score ?>
                                / 5
                            </strong>

                            <br>

                            Required:

                            <strong>
                                4 / 5
                            </strong>

                            or above

                        </div>


                    <?php elseif (
                        $max_attempts_reached
                    ): ?>


                        <div class="test-locked">

                            🔒 Maximum 5 Level 1
                            attempts completed.

                            <br><br>

                            Final mock test was not
                            successfully completed.

                        </div>


                    <?php else: ?>


                        <div class="test-pending">

                            ⏳ Mock test is still
                            in progress.

                            <br><br>

                            Level 1 requires
                            <strong>8 / 10</strong>.

                            <br>

                            Level 2 requires
                            <strong>4 / 5</strong>.

                        </div>


                    <?php endif; ?>


                <?php endif; ?>

            </div>


            <!-- ==================================================
                 DELETE APPLICATION
            ================================================== -->

            <div class="bottom-section">

                <form
                    method="POST"
                    onsubmit="
                        return confirm(
                            'Are you sure you want to delete this application? Test results and interview letter will also be deleted.'
                        );
                    "
                >

                    <input
                        type="hidden"
                        name="delete_application_id"
                        value="<?= $application_id ?>"
                    >


                    <button
                        type="submit"
                        class="delete-btn"
                    >
                        🗑 Delete Application
                    </button>

                </form>

            </div>


        </div>


    <?php endwhile; ?>


</div>


</body>

</html>

<?php

mysqli_stmt_close($stmt);

?>