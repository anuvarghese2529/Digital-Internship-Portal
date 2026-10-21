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
                        "/../assets/uploads/resumes/" .
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
        Admin can remove students from here.
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


        <div class="student">

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


            <?php if(!empty($row["resume"])): ?>

                <a
                    class="resume"
                    href="../assets/uploads/resumes/<?= htmlspecialchars(basename($row["resume"])) ?>"
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