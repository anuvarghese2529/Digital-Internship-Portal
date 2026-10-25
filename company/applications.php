<?php

session_start();

require_once "../config/db.php";


/* ==================================================
   COMPANY LOGIN CHECK
================================================== */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "company"
) {
    header("Location: ../login.php");
    exit();
}

$company_id = (int)$_SESSION["user_id"];


/* ==================================================
   SKILL MATCH FUNCTION
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
   FORMAT TEST TIME
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

    $hours =
        floor($seconds / 3600);

    $minutes =
        floor(
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
   GET APPLICATIONS
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

    WHERE i.company_id = ?

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


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $company_id
);


mysqli_stmt_execute(
    $stmt
);


$result =
    mysqli_stmt_get_result(
        $stmt
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
    Applications - Company
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


.container {

    width: 95%;

    max-width: 1400px;

    margin: 30px auto;

}


.top-section {

    background: white;

    padding: 25px;

    border-radius: 12px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.07);

}


.top-section h1 {

    color: #667eea;

    margin-bottom: 8px;

}


.top-section p {

    color: #777;

}


.back-btn {

    display: inline-block;

    margin-top: 15px;

    padding: 10px 18px;

    background: #667eea;

    color: white;

    text-decoration: none;

    border-radius: 7px;

}


.back-btn:hover {

    background: #5568d9;

}


/* Clean table-first layout */
.applications-table-wrap{width:100%;overflow-x:auto;background:#fff;border:1px solid #e5e9f5;border-radius:14px;box-shadow:0 6px 22px rgba(30,50,100,.07);margin:22px 0}
.applications-table{width:100%;min-width:1000px;border-collapse:separate;border-spacing:0;font-size:14px}
.applications-table thead th{background:#3549a6;color:#fff;padding:15px 13px;text-align:left;white-space:nowrap;font-weight:700}
.applications-table tbody td{padding:15px 13px;border-bottom:1px solid #edf0f7;vertical-align:middle;background:#fff}
.applications-table tbody tr:hover td{background:#f8faff}
.applications-table tbody tr:last-child td{border-bottom:0}
.application-table-row .student-cell strong{font-size:15px;color:#202b48}
.application-table-row small{color:#69738b;line-height:1.5}
.table-actions{display:flex;flex-wrap:wrap;gap:7px;min-width:180px}
.table-action-btn{border:0;border-radius:8px;padding:9px 12px;font-weight:700;cursor:pointer;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:5px;white-space:nowrap}
.btn-view-details{background:#e9edff;color:#3549a6}
.btn-view-attempts{background:#242a35;color:#fff}
.application-details-inline{display:none;margin:12px 0 4px;padding:18px!important;border:1px solid #e4e9f5;box-shadow:none!important;grid-column:1/-1}
.application-details-inline.details-open{display:block}
.full-details-cell{min-width:220px}
.application-card {

    background: white;

    border-radius: 14px;

    margin-bottom: 25px;

    padding: 25px;

    box-shadow:
        0 5px 18px rgba(0,0,0,0.08);

}


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

    margin-bottom: 5px;

}


.application-header p {

    color: #777;

}


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


.skill-box {

    background: #f8f9ff;

    padding: 15px;

    border-radius: 9px;

    margin-bottom: 20px;

    line-height: 1.6;

}


.skill-box strong {

    color: #667eea;

}


.skill-match {

    margin: 20px 0;

    padding: 18px;

    background: #f3f4ff;

    border-radius: 10px;

}


.skill-match-title {

    font-weight: bold;

    margin-bottom: 10px;

}


.progress {

    width: 100%;

    height: 20px;

    background: #ddd;

    border-radius: 20px;

    overflow: hidden;

}


.progress-bar {

    height: 100%;

    background: #667eea;

    text-align: center;

    color: white;

    font-size: 12px;

    line-height: 20px;

}


.resume-section {

    margin: 20px 0;

    padding: 15px;

    background: #f8f9fa;

    border-radius: 10px;

}


.resume-btn {

    display: inline-block;

    padding: 9px 14px;

    background: #198754;

    color: white;

    text-decoration: none;

    border-radius: 7px;

    margin-right: 5px;

    margin-top: 8px;

}


.resume-btn:hover {

    background: #146c43;

}


.test-section {

    background: #f8f9fa;

    border-radius: 10px;

    padding: 20px;

    margin-top: 20px;

}


.test-section h3 {

    margin-bottom: 15px;

    color: #333;

}


.test-summary {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 12px;

    margin-bottom: 20px;

}


.test-box {

    background: white;

    padding: 15px;

    border-radius: 8px;

    border: 1px solid #eee;

    text-align: center;

}


.test-box strong {

    display: block;

    color: #667eea;

    font-size: 20px;

    margin-top: 5px;

}


.test-result-row {

    background: white;

    padding: 15px;

    margin-bottom: 10px;

    border-radius: 7px;

    border-left: 4px solid #667eea;

}


.test-line {

    margin-top: 6px;

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


.final-pass {

    background: #d1e7dd;

    color: #0f5132;

    padding: 15px;

    border-radius: 9px;

    font-weight: bold;

    margin-top: 15px;

}


.locked {

    background: #f8d7da;

    color: #842029;

    padding: 15px;

    border-radius: 9px;

    font-weight: bold;

    margin-top: 15px;

}


.progress-status {

    background: #fff3cd;

    color: #856404;

    padding: 15px;

    border-radius: 9px;

    margin-top: 15px;

}


.action-buttons {

    margin-top: 20px;

    padding-top: 20px;

    border-top: 1px solid #eee;

    display: flex;

    flex-wrap: wrap;

    gap: 10px;

    align-items: center;

}


.btn {

    display: inline-block;

    padding: 10px 16px;

    border-radius: 7px;

    text-decoration: none;

    border: none;

    cursor: pointer;

    font-size: 14px;

    font-family: Arial, sans-serif;

}


.btn-shortlist {

    background: #198754;

    color: white;

}


.btn-shortlist:hover {

    background: #146c43;

}


.btn-reject {

    background: #dc3545;

    color: white;

}


.btn-reject:hover {

    background: #bb2d3b;

}


.btn-letter {

    background: #6f42c1;

    color: white;

}


.btn-letter:hover {

    background: #59359c;

}


.btn-view-letter {

    background: #0d6efd;

    color: white;

}


.btn-view-letter:hover {

    background: #0b5ed7;

}


.btn-video {

    background: #212529;

    color: white;

    margin-top: 8px;

}


.btn-video:hover {

    background: #000;

}


.btn-locked {

    background: #6c757d;

    color: white;

    cursor: not-allowed;

}


.btn-rejected {

    background: #dc3545;

    color: white;

    cursor: default;

}


.no-applications {

    background: white;

    padding: 40px;

    text-align: center;

    border-radius: 12px;

    color: #777;

}


@media (max-width: 800px) {

    .info-grid {

        grid-template-columns: 1fr;

    }


    .test-summary {

        grid-template-columns: 1fr;

    }


    .application-header {

        flex-direction: column;

        align-items: flex-start;

    }

}


/* Application summary table */
.applications-table-wrap {
    width: 100%;
    overflow-x: auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 5px 18px rgba(0,0,0,0.08);
    margin: 22px 0 30px;
}
.applications-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}
.applications-table th, .applications-table td {
    padding: 13px 15px;
    text-align: left;
    border-bottom: 1px solid #e9edf5;
    font-size: 14px;
    vertical-align: middle;
}
.applications-table th {
    background: #3548a8;
    color: #fff;
    font-weight: 700;
    white-space: nowrap;
}
.applications-table tbody tr:hover { background: #f6f8ff; }
.applications-table tbody tr:last-child td { border-bottom: 0; }
.table-status { display: inline-block; padding: 6px 10px; border-radius: 18px; font-size: 12px; font-weight: 700; }
.table-status.pending { background: #fff3cd; color: #856404; }
.table-status.shortlisted { background: #d1e7dd; color: #0f5132; }
.table-status.rejected { background: #f8d7da; color: #842029; }
.view-application-btn { display: inline-block; padding: 7px 11px; border-radius: 6px; background: #3548a8; color: #fff; text-decoration: none; font-size: 12px; white-space: nowrap; }
.view-application-btn:hover { background: #253580; }


/* Keep every applicant's full information inside the applications table. */
.full-details-table { min-width: 1500px; }
.full-details-table th, .full-details-table td { vertical-align: top; }
.full-details-table .application-table-row > td { padding: 14px 12px; }
.full-details-cell { min-width: 520px; max-width: 760px; }
.application-details-inline { border: 0 !important; box-shadow: none !important; background: transparent !important; padding: 8px 0 !important; margin: 0 !important; width: auto !important; }
.application-details-inline .application-header { display: none; }

/* Modal popup for View Details / View Attempts */
body.application-modal-open { overflow: hidden; }
body.application-modal-open::before {
    content: ""; position: fixed; inset: 0; background: rgba(15,23,42,.62);
    z-index: 9998; backdrop-filter: blur(3px);
}
.application-details-inline.details-open {
    display: block !important; position: fixed !important; z-index: 9999 !important;
    top: 50% !important; left: 50% !important; transform: translate(-50%,-50%) !important;
    width: min(1000px, calc(100vw - 32px)) !important;
    max-width: calc(100vw - 32px) !important; max-height: 86vh !important;
    overflow: auto !important; box-sizing: border-box !important;
    margin: 0 !important; padding: 28px !important; background: #fff !important;
    border: 1px solid #dce3f3 !important; border-radius: 18px !important;
    box-shadow: 0 24px 80px rgba(0,0,0,.3) !important;
}
.application-modal-close {
    position: sticky; top: 0; float: right; z-index: 3; border: 0;
    border-radius: 9px; background: #242a35; color: #fff; font-weight: 700;
    padding: 10px 15px; cursor: pointer; margin: 0 0 14px 14px;
}
.application-details-inline.attempts-only > :not(.application-modal-close):not(.attempts-notice):not(.test-section) { display:none !important; }
.application-details-inline.attempts-only .test-section { display:block !important; }
.application-details-inline.attempts-only .attempts-notice { display:block !important; }
/* View Details popup should not include mock-test performance/history. */
.application-details-inline.details-only .test-section { display:none !important; }
.application-details-inline.details-only .attempts-notice { display:none !important; }
@media(max-width:600px) {
 .application-details-inline.details-open { width:calc(100vw - 18px)!important; max-width:calc(100vw - 18px)!important; max-height:92vh!important; padding:16px!important; }
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
            Review applicants, skill matching,
            mock test performance, webcam recordings
            and interview eligibility.
        </p>

        <a
            href="dashboard.php"
            class="back-btn"
        >
            ← Back to Dashboard
        </a>

    </div>


    <!-- ==================================================
         SUCCESS / ERROR
    ================================================== -->

    <?php if (
        isset($_GET["status_update"])
    ): ?>

        <?php if (
            $_GET["status_update"] === "shortlisted"
        ): ?>

            <div
                style="
                    background:#d1e7dd;
                    color:#0f5132;
                    padding:14px 18px;
                    border-radius:10px;
                    margin-bottom:20px;
                    font-weight:bold;
                "
            >
                ✅ Student shortlisted successfully.
            </div>

        <?php elseif (
            $_GET["status_update"] === "rejected"
        ): ?>

            <div
                style="
                    background:#f8d7da;
                    color:#842029;
                    padding:14px 18px;
                    border-radius:10px;
                    margin-bottom:20px;
                    font-weight:bold;
                "
            >
                ✅ Application rejected and student notified.
            </div>

        <?php elseif (
            $_GET["status_update"] === "error"
        ): ?>

            <div
                style="
                    background:#f8d7da;
                    color:#842029;
                    padding:14px 18px;
                    border-radius:10px;
                    margin-bottom:20px;
                    font-weight:bold;
                "
            >
                ❌ Unable to update application status.
            </div>

        <?php endif; ?>

    <?php endif; ?>


    <!-- ==================================================
         NO APPLICATIONS
    ================================================== -->

    <?php if (
        mysqli_num_rows($result) === 0
    ): ?>

        <div class="no-applications">

            <h2>
                No Applications Yet
            </h2>

            <p>
                Students who apply for your internships
                will appear here.
            </p>

        </div>

    <?php endif; ?>


    <!-- ALL APPLICATION INFORMATION IN ONE TABLE -->
    <?php if (mysqli_num_rows($result) > 0): ?>
        <div class="applications-table-wrap">
            <table class="applications-table full-details-table">
                <thead>
                    <tr>
                        <th># / Application ID</th>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Internship</th>
                        <th>Applied On</th>
                        <th>Skill Match</th>
                        <th>Status</th>
                        <th>Resume, Mock Test History & Actions</th>
                    </tr>
                </thead>
                <tbody>
    <?php endif; ?>

    <?php while (
        $row = mysqli_fetch_assoc($result)
    ): ?>


        <?php

        $application_id =
            (int)$row["application_id"];

        $student_id =
            (int)$row["student_id"];

        $internship_id =
            (int)$row["internship_id"];


        /* ==================================================
           STATUS CLASS
        ================================================== */

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


        /* ==================================================
           SKILL MATCH
        ================================================== */

        $skill_match =
            calculateSkillMatch(
                $row["student_skills"] ?? "",
                $row["internship_skills"] ?? ""
            );


        /* ==================================================
           RESUME
        ================================================== */

        $resume_file = "";

        if (
            !empty($row["resume"])
        ) {

            $resume_file =
                basename(
                    $row["resume"]
                );

        }


        /* ==================================================
           GET TEST RESULTS
        ================================================== */

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


        if (!$test_stmt) {

            die(
                "Unable to load test results."
            );

        }


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


        $test_results = [];

        $level1_attempts = 0;

        $level1_pass_count = 0;

        $level2_pass = false;

        $final_level2_score = 0;

        $last_level = 0;

        $last_percentage = 0;

        $last_passed = 0;

        $last_score = 0;


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


            $last_percentage =
                (float)$test["percentage"];


            $last_passed =
                (int)$test["passed"];


            $last_score =
                (int)$test["score"];


            if (
                (int)$test["level"] === 1
            ) {

                $level1_attempts++;


                if (
                    (int)$test["passed"] === 1
                ) {

                    $level1_pass_count++;

                }

            }


            /* FINAL LEVEL 2 PASS */

            if (
                (int)$test["level"] === 2 &&
                (int)$test["passed"] === 1 &&
                (int)$test["score"] >= 4 &&
                (int)$test["total_questions"] === 5
            ) {

                $level2_pass = true;

                $final_level2_score =
                    (int)$test["score"];

            }

        }


        mysqli_stmt_close(
            $test_stmt
        );
        
                $has_test =
            count($test_results) > 0;


        $final_test_passed =
            $level2_pass;


        $max_attempts_reached =
            ($level1_attempts >= 5) &&
            !$final_test_passed;

        

        /* ==================================================
           EXISTING INTERVIEW LETTER
        ================================================== */

        $existing_letter_id = 0;


        $letter_sql = "

            SELECT id

            FROM interview_letters

            WHERE application_id = ?

            LIMIT 1

        ";


        $letter_stmt =
            mysqli_prepare(
                $conn,
                $letter_sql
            );


        if ($letter_stmt) {

            mysqli_stmt_bind_param(
                $letter_stmt,
                "i",
                $application_id
            );


            mysqli_stmt_execute(
                $letter_stmt
            );


            $letter_result =
                mysqli_stmt_get_result(
                    $letter_stmt
                );


            if (
                $letter_row =
                mysqli_fetch_assoc(
                    $letter_result
                )
            ) {

                $existing_letter_id =
                    (int)$letter_row["id"];

            }


            mysqli_stmt_close(
                $letter_stmt
            );

        }

        ?>


        <?php
        $table_skill_match = calculateSkillMatch(
            $row["student_skills"] ?? "",
            $row["internship_skills"] ?? ""
        );
        $table_status = strtolower($row["application_status"] ?? "pending");
        $table_status_class = in_array($table_status, ["pending", "shortlisted", "rejected"], true) ? $table_status : "pending";
        ?>
        <tr class="application-table-row" id="application-<?= $application_id ?>">
            <td>#<?= $application_id ?></td>
            <td>
                <strong><?= htmlspecialchars($row["student_name"] ?? "") ?></strong><br>
                <small>Education: <?= htmlspecialchars($row["education"] ?: "Not provided") ?></small><br>
                <small>Skills: <?= htmlspecialchars($row["student_skills"] ?: "Not provided") ?></small>
            </td>
            <td><?= htmlspecialchars($row["student_email"] ?? "") ?></td>
            <td>
                <strong><?= htmlspecialchars($row["internship_title"] ?? "") ?></strong><br>
                <small>Required skills: <?= htmlspecialchars($row["internship_skills"] ?: "Not provided") ?></small>
            </td>
            <td><?= !empty($row["applied_at"]) ? date("d-m-Y h:i A", strtotime($row["applied_at"])) : "—" ?></td>
            <td><strong><?= (int)$table_skill_match ?>%</strong><div class="progress" style="min-width:100px"><div class="progress-bar" style="width:<?= (int)$table_skill_match ?>%"><?= (int)$table_skill_match ?>%</div></div></td>
            <td><span class="table-status <?= htmlspecialchars($table_status_class) ?>"><?= htmlspecialchars($row["application_status"] ?? "Pending") ?></span></td>
            <td class="full-details-cell">
        <div class="table-actions">
            <button type="button" class="table-action-btn btn-view-details" onclick="toggleApplicationDetails(<?= $application_id ?>)">👁 View Details</button>
            <button type="button" class="table-action-btn btn-view-attempts" onclick="toggleApplicationAttempts(<?= $application_id ?>)">🎥 View Attempts</button>
        </div>
        <!-- Expanded only when requested; keeps the main table compact and avoids repeated details on screen. -->
        <div class="application-card application-details-inline" id="application-details-<?= $application_id ?>">
            <div class="attempts-notice" id="attempts-notice-<?= $application_id ?>" style="display:none;color:#5367c9;font-weight:600;margin-bottom:12px">Mock test attempts and recordings are shown below.</div>


            <!-- ==================================================
                 HEADER
            ================================================== -->

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


            </div>


            <!-- ==================================================
                 STUDENT SKILLS
            ================================================== -->

            <div class="skill-box">

                <strong>
                    💻 Student Skills
                </strong>

                <br><br>

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

                <br><br>

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
                            width: <?= $skill_match ?>%;
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

                <strong>
                    📄 Resume
                </strong>


                <?php if (
                    !empty($resume_file)
                ): ?>

                    <br>


                    <a
                        href="../assets/uploads/<?= htmlspecialchars(
                            $resume_file
                        ) ?>"
                        target="_blank"
                        class="resume-btn"
                    >
                        👁 View Resume
                    </a>


                    <a
                        href="../assets/uploads/<?= htmlspecialchars(
                            $resume_file
                        ) ?>"
                        download
                        class="resume-btn"
                    >
                        ⬇ Download Resume
                    </a>


                <?php else: ?>

                    <br><br>

                    <span>
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

                        Total Attempts

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

                                ? $last_percentage . "%"

                                : "-"
                            ?>

                        </strong>

                    </div>


                </div>


                <?php if (
                    !$has_test
                ): ?>


                    <div class="progress-status">

                        ⏳ Student has not started
                        the mock test yet.

                    </div>


                <?php else: ?>


                    <strong>
                        Test History
                    </strong>

                    <br><br>


                    <?php

                    $display_attempt = 0;

                    ?>


                    <?php foreach (
                        $test_results as $test
                    ): ?>


                        <?php

                        if (
                            (int)$test["level"] === 1
                        ) {

                            $display_attempt++;

                        }

                        ?>


                        <div
                            class="test-result-row"
                        >


                            <?php if (
                                (int)$test["level"] === 1
                            ): ?>

                                <strong>
                                    Attempt
                                    <?= $display_attempt ?>
                                </strong>

                            <?php else: ?>

                                <strong>
                                    Level 2
                                </strong>

                            <?php endif; ?>


                            <div class="test-line">

                                Level
                                <?= (int)$test["level"] ?>

                                —

                                Score:

                                <?= (int)$test["score"] ?>

                                /

                                <?= (int)$test[
                                    "total_questions"
                                ] ?>

                                —

                                <?= (float)$test[
                                    "percentage"
                                ] ?>%

                                —

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


                            <!-- VIDEO -->

                            <?php if (
                                !empty(
                                    $test["video_path"]
                                )
                            ): ?>


                                <?php

                                $video_path =
                                    $test["video_path"];


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


                                <div class="test-line">

                                    <a
                                        href="<?= htmlspecialchars(
                                            $video_url
                                        ) ?>"
                                        target="_blank"
                                        class="btn btn-video"
                                    >
                                        🎥 View Test Video
                                    </a>

                                </div>


                            <?php else: ?>


                                <div class="test-line">

                                    <small>
                                        🎥 No webcam recording available.
                                    </small>

                                </div>


                            <?php endif; ?>


                        </div>


                    <?php endforeach; ?>


                <?php endif; ?>


                <!-- ==================================================
                     FINAL TEST STATUS
                ================================================== -->

                <?php if (
                    $final_test_passed
                ): ?>


                    <div class="final-pass">

                        ✅ Final Mock Test Passed!

                        <br><br>

                        Level 2 Score:

                        <strong>
                            <?= $final_level2_score ?> / 5
                        </strong>

                        <br>

                        Required:

                        <strong>
                            4 / 5 or above
                        </strong>

                        <br><br>

                        Student is eligible for
                        offline interview.

                    </div>


                <?php elseif (
                    $max_attempts_reached
                ): ?>


                    <div class="locked">

                        🔒 Maximum 5 attempts completed.

                        <br><br>

                        Student did not complete the
                        final test successfully.

                        <br>

                        Mock test is locked.

                    </div>


                <?php elseif (
                    $has_test
                ): ?>


                    <div class="progress-status">

                        ⏳ Mock test is still in progress.

                        <br><br>

                        Level 1 requires at least
                        <strong>8 / 10</strong>.

                        <br>

                        Level 2 requires at least
                        <strong>4 / 5</strong>.

                    </div>


                <?php endif; ?>


            </div>


            <!-- ==================================================
                 ACTION BUTTONS
            ================================================== -->

            <div class="action-buttons">


                <?php if (
                    $row["application_status"]
                    === "Rejected"
                ): ?>


                    <span class="btn btn-rejected">
                        ❌ Application Rejected
                    </span>


                <?php else: ?>


                    <!-- ==========================================
                         FINAL TEST PASSED
                    =========================================== -->

                    <?php if (
                        $final_test_passed
                    ): ?>


                        <!-- SHORTLIST -->

                        <?php if (
                            $row["application_status"]
                            !== "Shortlisted"
                        ): ?>

                            <a
                                href="update_application.php?id=<?= $application_id ?>&status=Shortlisted"
                                class="btn btn-shortlist"
                                onclick="
                                    return confirm(
                                        'Shortlist this student?'
                                    );
                                "
                            >
                                ✅ Shortlist Student
                            </a>

                        <?php else: ?>

                            <span
                                class="btn btn-shortlist"
                            >
                                ✅ Student Shortlisted
                            </span>

                        <?php endif; ?>


                        <!-- INTERVIEW LETTER -->

                        <?php if (
                            $row["application_status"]
                            === "Shortlisted"
                        ): ?>


                            <?php if (
                                $existing_letter_id > 0
                            ): ?>

                                <a
                                    href="issue_interview_letter.php?application_id=<?= $application_id ?>"
                                    class="btn btn-view-letter"
                                >
                                    👁 View Interview Letter
                                </a>

                            <?php else: ?>

                                <a
                                    href="issue_interview_letter.php?application_id=<?= $application_id ?>"
                                    class="btn btn-letter"
                                >
                                    📄 Issue Interview Letter
                                </a>

                            <?php endif; ?>


                        <?php endif; ?>


                        <!-- REJECT AFTER VIDEO REVIEW -->

                        <form
                            method="POST"
                            action="update_application.php"
                            style="display:inline;"
                            onsubmit="
                                return prepareReject(this);
                            "
                        >

                            <input
                                type="hidden"
                                name="application_id"
                                value="<?= $application_id ?>"
                            >

                            <input
                                type="hidden"
                                name="status"
                                value="Rejected"
                            >

                            <input
                                type="hidden"
                                name="reason"
                                value=""
                            >


                            <button
                                type="submit"
                                class="btn btn-reject"
                            >
                                ❌ Reject Student
                            </button>

                        </form>


                    <!-- ==========================================
                         MAX ATTEMPTS
                    =========================================== -->

                    <?php elseif (
                        $max_attempts_reached
                    ): ?>


                        <span
                            class="btn btn-locked"
                        >
                            🔒 Interview Letter Locked
                        </span>


                        <form
                            method="POST"
                            action="update_application.php"
                            style="display:inline;"
                            onsubmit="
                                return prepareReject(this);
                            "
                        >

                            <input
                                type="hidden"
                                name="application_id"
                                value="<?= $application_id ?>"
                            >

                            <input
                                type="hidden"
                                name="status"
                                value="Rejected"
                            >

                            <input
                                type="hidden"
                                name="reason"
                                value=""
                            >


                            <button
                                type="submit"
                                class="btn btn-reject"
                            >
                                ❌ Reject Application
                            </button>

                        </form>


                    <!-- ==========================================
                         TEST NOT COMPLETED
                    =========================================== -->

                    <?php else: ?>


                        <form
                            method="POST"
                            action="update_application.php"
                            style="display:inline;"
                            onsubmit="
                                return prepareReject(this);
                            "
                        >

                            <input
                                type="hidden"
                                name="application_id"
                                value="<?= $application_id ?>"
                            >

                            <input
                                type="hidden"
                                name="status"
                                value="Rejected"
                            >

                            <input
                                type="hidden"
                                name="reason"
                                value=""
                            >


                            <button
                                type="submit"
                                class="btn btn-reject"
                            >
                                ❌ Reject Application
                            </button>

                        </form>


                        <span
                            class="btn btn-locked"
                        >
                            ⏳ Waiting for Final Test
                        </span>


                    <?php endif; ?>


                <?php endif; ?>


            </div>

        </div>
            </td>
        </tr>

    <?php endwhile; ?>
    <?php if (isset($result) && mysqli_num_rows($result) > 0): ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>


<script>

/* ==================================================
   REJECTION FUNCTION
================================================== */

function prepareReject(form) {

    const defaultReason =
        "Webcam recording review indicated possible unauthorized assistance during the mock test.";


    const reason = prompt(
        "Enter the reason for rejecting this student:",
        defaultReason
    );


    /* Cancel */

    if (reason === null) {

        return false;

    }


    /* Empty reason */

    if (
        reason.trim() === ""
    ) {

        alert(
            "Please enter a rejection reason."
        );

        return false;

    }


    form.reason.value =
        reason.trim();


    return confirm(
        "Reject this application?\n\nReason:\n" +
        reason.trim()
    );

}

</script>



<script>
function closeApplicationModal() {
    document.querySelectorAll('.application-details-inline.details-open').forEach(function(panel) {
        panel.classList.remove('details-open', 'attempts-only', 'details-only');
        panel.style.display = '';
    });
    document.body.classList.remove('application-modal-open');
}
function openApplicationModal(id, mode) {
    closeApplicationModal();
    const panel = document.getElementById('application-details-' + id);
    if (!panel) { alert('Application details not found.'); return; }
    panel.classList.add('details-open');
    document.body.classList.add('application-modal-open');
    const testSection = panel.querySelector('.test-section');
    const notice = document.getElementById('attempts-notice-' + id);
    let closeBtn = panel.querySelector('.application-modal-close');
    if (!closeBtn) {
        closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'application-modal-close';
        closeBtn.textContent = '✕ Close';
        closeBtn.addEventListener('click', closeApplicationModal);
        panel.insertBefore(closeBtn, panel.firstChild);
    }
    if (mode === 'attempts') {
        if (!testSection) {
            panel.classList.remove('attempts-only');
            alert('Mock test history was not found for this application.');
            return;
        }
        panel.classList.remove('details-only');
        panel.classList.add('attempts-only');
        testSection.style.display = 'block';
        if (notice) notice.style.display = 'block';
    } else {
        panel.classList.remove('attempts-only');
        panel.classList.add('details-only');
        if (testSection) testSection.style.display = ''; 
        if (notice) notice.style.display = 'none';
    }
    panel.scrollTop = 0;
}
function toggleApplicationDetails(id) { openApplicationModal(id, 'details'); }
function toggleApplicationAttempts(id) { openApplicationModal(id, 'attempts'); }
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') closeApplicationModal();
});
document.addEventListener('click', function(event) {
    if (event.target === document.querySelector('body.application-modal-open::before')) closeApplicationModal();
});
</script>
</body>

</html>

<?php

mysqli_stmt_close(
    $stmt
);

?>