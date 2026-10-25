<?php

session_start();

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}


$message = "";
$message_type = "";



/* =========================================
   REMOVE STUDENT
========================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $student_id = (int)($_POST["student_id"] ?? 0);

    $action = $_POST["action"] ?? "";


    if (
        $action === "remove"
        &&
        $student_id > 0
    ) {

        /* Get resume */

        $stmt = mysqli_prepare(
            $conn,
            "SELECT resume
             FROM users
             WHERE id = ?
             AND role = 'student'
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $student_id
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $student = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);


        if (!$student) {

            $message = "Student not found.";
            $message_type = "error";

        } else {

            mysqli_begin_transaction($conn);


            try {

                /* Delete test results */

                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM test_results
                     WHERE user_id = ?"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $student_id
                );

                mysqli_stmt_execute($stmt);

                mysqli_stmt_close($stmt);



                /* Delete interview letters */

                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM interview_letters
                     WHERE student_id = ?"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $student_id
                );

                mysqli_stmt_execute($stmt);

                mysqli_stmt_close($stmt);



                /* Delete applications */

                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM applications
                     WHERE student_id = ?"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $student_id
                );

                mysqli_stmt_execute($stmt);

                mysqli_stmt_close($stmt);



                /* Delete notifications */

                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM notifications
                     WHERE user_id = ?"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $student_id
                );

                mysqli_stmt_execute($stmt);

                mysqli_stmt_close($stmt);



                /* Delete user */

                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM users
                     WHERE id = ?
                     AND role = 'student'"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $student_id
                );

                mysqli_stmt_execute($stmt);

                mysqli_stmt_close($stmt);


                mysqli_commit($conn);


                /* Delete resume */

                if (!empty($student["resume"])) {

                    $resume_name = basename(
                        $student["resume"]
                    );

                    $resume_path =
                        __DIR__ .
                        "/../assets/uploads/" .
                        $resume_name;

                    if (is_file($resume_path)) {
                        @unlink($resume_path);
                    }
                }


                $message = "Student removed successfully.";
                $message_type = "success";

            } catch (Throwable $e) {

                mysqli_rollback($conn);

                $message = "Unable to remove student.";
                $message_type = "error";
            }
        }
    }
}



/* =========================================
   GET STUDENTS
========================================= */

$sql = "
    SELECT
        id,
        name,
        email,
        education,
        skills,
        resume
    FROM users
    WHERE role = 'student'
    ORDER BY id DESC
";

$result = mysqli_query($conn, $sql);

?>


<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Students - Admin</title>

<style>

body{
    font-family:Arial,sans-serif;
    background:#f4f6fb;
    margin:0;
    color:#333;
}

.container{
    width:92%;
    max-width:1200px;
    margin:30px auto;
}

.top{
    background:white;
    padding:25px;
    border-radius:12px;
    margin-bottom:20px;
    box-shadow:0 5px 15px rgba(0,0,0,.07);
}

.top h1{
    color:#667eea;
    margin-bottom:8px;
}

.back{
    display:inline-block;
    margin-top:15px;
    background:#667eea;
    color:white;
    text-decoration:none;
    padding:10px 16px;
    border-radius:7px;
}

.message{
    padding:12px;
    border-radius:8px;
    margin-bottom:20px;
    font-weight:bold;
}

.success{
    background:#d1e7dd;
    color:#0f5132;
}

.error{
    background:#f8d7da;
    color:#842029;
}

.student{
    background:white;
    padding:22px;
    border-radius:12px;
    margin-bottom:18px;
    box-shadow:0 5px 15px rgba(0,0,0,.07);
}

.student h2{
    margin-bottom:8px;
}

.info{
    margin:7px 0;
}

.skills{
    background:#f2f4ff;
    padding:12px;
    border-radius:8px;
    margin-top:12px;
}

.actions{
    margin-top:15px;
}

.btn{
    border:none;
    padding:10px 15px;
    border-radius:7px;
    cursor:pointer;
    font-weight:bold;
}

.remove{
    background:#dc3545;
    color:white;
}

.resume{
    display:inline-block;
    margin-top:10px;
    padding:9px 13px;
    background:#eef2ff;
    color:#4f46e5;
    text-decoration:none;
    border-radius:7px;
}

.mock-tests{
    margin-top:18px;
    border:1px solid #e2e6f0;
    border-radius:9px;
    overflow:hidden;
    background:#fbfcff;
}
.mock-tests summary{
    cursor:pointer;
    padding:14px 16px;
    background:#eef2ff;
    color:#4f46e5;
    font-weight:bold;
}
.table-wrap{width:100%;overflow-x:auto;padding:12px;}
table{width:100%;border-collapse:collapse;min-width:750px;background:white;}
th,td{padding:11px 12px;border:1px solid #e5e7eb;text-align:left;font-size:13px;}
th{background:#667eea;color:white;white-space:nowrap;}
.pass{color:#198754;font-weight:bold;}
.fail{color:#dc3545;font-weight:bold;}
.no-tests{padding:16px;color:#777;}

.no-data{
    background:white;
    padding:35px;
    border-radius:12px;
    text-align:center;
    color:#777;
}

</style>

</head>


<body>


<div class="container">


<div class="top">

    <h1>
        🎓 Students
    </h1>

    <p>
        All registered students are active automatically.
        Click Mock Tests under each student to view their scores, results, date/time and video on this same page. Admin can remove students from here.
    </p>

    <a href="dashboard.php"
       class="back">

        ← Back to Dashboard

    </a>

</div>



<?php if($message !== ""): ?>

    <div class="message <?= $message_type ?>">

        <?= htmlspecialchars($message) ?>

    </div>

<?php endif; ?>



<?php if($result && mysqli_num_rows($result) > 0): ?>


    <?php while($row = mysqli_fetch_assoc($result)): ?>

        <?php
        $student_test_sql = "
            SELECT tr.id AS test_id, tr.level, tr.score, tr.total_questions,
                   tr.percentage, tr.passed, tr.time_limit_seconds,
                   tr.time_taken_seconds, tr.video_path, tr.created_at,
                   i.title AS internship_title, i.company_name
            FROM test_results tr
            LEFT JOIN internships i ON tr.internship_id = i.id
            WHERE tr.user_id = ?
            ORDER BY tr.created_at DESC, tr.id DESC
        ";
        $student_test_stmt = mysqli_prepare($conn, $student_test_sql);
        $student_id_for_tests = (int)$row["id"];
        mysqli_stmt_bind_param($student_test_stmt, "i", $student_id_for_tests);
        mysqli_stmt_execute($student_test_stmt);
        $student_tests_result = mysqli_stmt_get_result($student_test_stmt);
        $student_test_count = $student_tests_result ? mysqli_num_rows($student_tests_result) : 0;
        ?>

        <div class="student" id="student-<?= (int)$row["id"] ?>">

            <h2>
                <?= htmlspecialchars($row["name"]) ?>
            </h2>


            <div class="info">
                📧
                <?= htmlspecialchars($row["email"]) ?>
            </div>


            <div class="info">
                🎓
                <?= htmlspecialchars($row["education"] ?? "Not specified") ?>
            </div>


            <div class="skills">

                <strong>
                    Skills:
                </strong>

                <?= htmlspecialchars(
                    $row["skills"] ?? "No skills added"
                ) ?>

            </div>


            <details class="mock-tests">
                <summary>📝 Mock Tests (<?= (int)$student_test_count ?>)</summary>
                <?php if($student_test_count > 0): ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr>
                                <th>Date & Time</th><th>Internship / Company</th>
                                <th>Level</th><th>Score</th><th>Result</th>
                                <th>Time Taken</th><th>Video</th>
                            </tr></thead>
                            <tbody>
                            <?php while($test = mysqli_fetch_assoc($student_tests_result)): ?>
                                <tr>
                                    <td><?= htmlspecialchars($test["created_at"] ?? "—") ?></td>
                                    <td>
                                        <?= htmlspecialchars($test["internship_title"] ?? "General Mock Test") ?>
                                        <?php if(!empty($test["company_name"])): ?>
                                            <br><small><?= htmlspecialchars($test["company_name"]) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($test["level"] ?? "—") ?></td>
                                    <td><?= (int)($test["score"] ?? 0) ?> / <?= (int)($test["total_questions"] ?? 0) ?> (<?= htmlspecialchars((string)($test["percentage"] ?? 0)) ?>%)</td>
                                    <td class="<?= !empty($test["passed"]) ? 'pass' : 'fail' ?>"><?= !empty($test["passed"]) ? 'Passed' : 'Failed' ?></td>
                                    <td><?php $taken = (int)($test["time_taken_seconds"] ?? 0); echo $taken > 0 ? floor($taken / 60) . "m " . ($taken % 60) . "s" : "—"; ?></td>
                                    <td>
                                        <?php if(!empty($test["video_path"])): ?>
                                            <a href="../<?= htmlspecialchars(ltrim($test["video_path"], "/\\")) ?>" target="_blank" rel="noopener">View Video</a>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="no-tests">This student has not attempted any mock tests.</p>
                <?php endif; ?>
            </details>
            <?php if ($student_test_stmt) { mysqli_stmt_close($student_test_stmt); } ?>

            <?php if(!empty($row["resume"])): ?>

                <a
                    class="resume"
                    href="../assets/uploads/<?= rawurlencode(basename($row["resume"])) ?>"
                    target="_blank"
                >
                    📄 View Resume
                </a>

            <?php endif; ?>


            <div class="actions">

                <form method="POST">

                    <input
                        type="hidden"
                        name="student_id"
                        value="<?= (int)$row["id"] ?>"
                    >

                    <button
                        type="submit"
                        name="action"
                        value="remove"
                        class="btn remove"
                        onclick="return confirm('Remove this student permanently?');"
                    >
                        🗑 Remove Student
                    </button>

                </form>

            </div>

        </div>


    <?php endwhile; ?>


<?php else: ?>


    <div class="no-data">

        No students registered yet.

    </div>


<?php endif; ?>


</div>


</body>
</html>