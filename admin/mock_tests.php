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


/* =========================================================
   DELETE TEST RESULT
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $test_id = (int)($_POST["test_id"] ?? 0);

    if (
        ($_POST["action"] ?? "") === "remove"
        &&
        $test_id > 0
    ) {

        $video_path = "";


        /* Get video */

        $stmt = mysqli_prepare(
            $conn,
            "SELECT video_path
             FROM test_results
             WHERE id = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $test_id
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $row = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);


        if($row){

            $video_path =
                $row["video_path"] ?? "";


            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM test_results
                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $test_id
            );

            if(mysqli_stmt_execute($stmt)){

                $message =
                    "Mock test result removed successfully.";

                $message_type =
                    "success";

            } else {

                $message =
                    "Unable to remove mock result.";

                $message_type =
                    "error";
            }

            mysqli_stmt_close($stmt);


            /* Delete video */

            if(!empty($video_path)){

                $full_path =
                    __DIR__ .
                    "/../" .
                    ltrim($video_path, "/\\");

                if(is_file($full_path)){
                    @unlink($full_path);
                }
            }

        } else {

            $message =
                "Mock test result not found.";

            $message_type =
                "error";
        }
    }
}


/* =========================================================
   GET TEST RESULTS
========================================================= */

$sql = "

    SELECT

        tr.id AS test_id,
        tr.level,
        tr.score,
        tr.total_questions,
        tr.percentage,
        tr.passed,
        tr.time_limit_seconds,
        tr.time_taken_seconds,
        tr.video_path,
        tr.created_at,

        u.name AS student_name,
        u.email AS student_email,

        i.title AS internship_title,
        i.company_name

    FROM test_results tr

    INNER JOIN users u
        ON tr.user_id = u.id

    LEFT JOIN internships i
        ON tr.internship_id = i.id

    ORDER BY tr.id DESC

";

$result = mysqli_query($conn, $sql);

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Mock Tests - Admin</title>

<style>

body{
    font-family:Arial,sans-serif;
    background:#f4f6fb;
    margin:0;
}

.container{
    width:94%;
    max-width:1250px;
    margin:30px auto;
}

.top,
.test{
    background:white;
    padding:25px;
    border-radius:12px;
    margin-bottom:20px;
    box-shadow:0 5px 15px rgba(0,0,0,.07);
}

.top h1{
    color:#667eea;
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

.test h2{
    margin-bottom:6px;
}

.internship{
    color:#667eea;
    font-weight:bold;
    margin-bottom:20px;
}

.grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:12px;
}

.box{
    background:#f8f9ff;
    padding:14px;
    border-radius:8px;
}

.box strong{
    color:#667eea;
}

.pass{
    color:#0f5132;
    font-weight:bold;
}

.fail{
    color:#842029;
    font-weight:bold;
}

.video{
    display:inline-block;
    margin-top:15px;
    padding:9px 13px;
    background:#212529;
    color:white;
    text-decoration:none;
    border-radius:7px;
}

.remove{
    display:inline-block;
    border:none;
    background:#dc3545;
    color:white;
    padding:10px 15px;
    border-radius:7px;
    cursor:pointer;
    font-weight:bold;
    margin-top:15px;
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

.no-data{
    background:white;
    padding:35px;
    border-radius:12px;
    text-align:center;
    color:#777;
}

@media(max-width:800px){

    .grid{
        grid-template-columns:1fr;
    }

}

</style>

</head>


<body>


<div class="container">


<div class="top">

    <h1>
        📝 Mock Test Results
    </h1>

    <p>
        View all Level 1 and Level 2 mock-test attempts.
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


<div class="test">


    <h2>

        🎓
        <?= htmlspecialchars(
            $row["student_name"]
        ) ?>

    </h2>


    <div class="internship">

        💼
        <?= htmlspecialchars(
            $row["internship_title"] ?? "Unknown Internship"
        ) ?>

        —

        <?= htmlspecialchars(
            $row["company_name"] ?? "Unknown Company"
        ) ?>

    </div>



    <div class="grid">


        <div class="box">

            <strong>
                Student Email
            </strong>

            <br>

            <?= htmlspecialchars(
                $row["student_email"]
            ) ?>

        </div>



        <div class="box">

            <strong>
                Test Level
            </strong>

            <br>

            Level <?= (int)$row["level"] ?>

        </div>



        <div class="box">

            <strong>
                Score
            </strong>

            <br>

            <?= (int)$row["score"] ?>
            /
            <?= (int)$row["total_questions"] ?>

        </div>



        <div class="box">

            <strong>
                Percentage
            </strong>

            <br>

            <?= (int)$row["percentage"] ?>%

        </div>



        <div class="box">

            <strong>
                Result
            </strong>

            <br>

            <?php if((int)$row["passed"] === 1): ?>

                <span class="pass">
                    ✅ PASS
                </span>

            <?php else: ?>

                <span class="fail">
                    ❌ FAIL
                </span>

            <?php endif; ?>

        </div>



        <div class="box">

            <strong>
                Time Limit
            </strong>

            <br>

            <?= (int)$row["time_limit_seconds"] ?>
            seconds

        </div>



        <div class="box">

            <strong>
                Time Taken
            </strong>

            <br>

            <?= (int)$row["time_taken_seconds"] ?>
            seconds

        </div>



        <div class="box">

            <strong>
                Attempt Date
            </strong>

            <br>

            <?= htmlspecialchars(
                $row["created_at"]
            ) ?>

        </div>


    </div>



    <?php if(!empty($row["video_path"])): ?>


        <a
            class="video"
            href="../<?= htmlspecialchars(
                ltrim(
                    $row["video_path"],
                    "/\\"
                )
            ) ?>"
            target="_blank"
        >

            🎥 View Webcam Recording

        </a>


    <?php endif; ?>



    <form method="POST">

        <input
            type="hidden"
            name="test_id"
            value="<?= (int)$row["test_id"] ?>"
        >


        <button
            type="submit"
            name="action"
            value="remove"
            class="remove"
            onclick="return confirm('Delete this mock test result?');"
        >

            🗑 Remove Result

        </button>

    </form>


</div>


<?php endwhile; ?>


<?php else: ?>


<div class="no-data">

    No mock-test results found.

</div>


<?php endif; ?>


</div>


</body>
</html>