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
   REMOVE INTERNSHIP
========================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $internship_id = (int)($_POST["internship_id"] ?? 0);

    if (
        ($_POST["action"] ?? "") === "remove"
        &&
        $internship_id > 0
    ) {

        mysqli_begin_transaction($conn);

        try {

            /* Delete test results */

            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM test_results
                 WHERE application_id IN
                 (
                    SELECT id
                    FROM applications
                    WHERE internship_id = ?
                 )"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $internship_id
            );

            mysqli_stmt_execute($stmt);

            mysqli_stmt_close($stmt);



            /* Delete interview letters */

            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM interview_letters
                 WHERE internship_id = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $internship_id
            );

            mysqli_stmt_execute($stmt);

            mysqli_stmt_close($stmt);



            /* Delete applications */

            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM applications
                 WHERE internship_id = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $internship_id
            );

            mysqli_stmt_execute($stmt);

            mysqli_stmt_close($stmt);



            /* Delete internship */

            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM internships
                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $internship_id
            );

            mysqli_stmt_execute($stmt);

            mysqli_stmt_close($stmt);


            mysqli_commit($conn);


            $message =
                "Internship removed successfully.";

            $message_type = "success";


        } catch(Throwable $e) {

            mysqli_rollback($conn);

            $message =
                "Unable to remove internship.";

            $message_type = "error";
        }
    }
}


/* =========================================
   GET INTERNSHIPS
========================================= */

$sql = "
    SELECT
        i.id,
        i.title,
        i.company_name,
        i.description,
        i.skills,
        i.location,
        i.duration,
        i.deadline,
        i.company_id,

        (
            SELECT COUNT(*)
            FROM applications a
            WHERE a.internship_id = i.id
        ) AS application_count

    FROM internships i

    ORDER BY i.id DESC
";

$result = mysqli_query($conn, $sql);

?>


<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Internships</title>

<style>

body{
    font-family:Arial,sans-serif;
    background:#f4f6fb;
    margin:0;
}

.container{
    width:92%;
    max-width:1200px;
    margin:30px auto;
}

.top,
.internship{
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

.internship h2{
    color:#667eea;
    margin-bottom:8px;
}

.info{
    margin:8px 0;
    color:#555;
}

.description{
    background:#f8f9ff;
    padding:12px;
    border-radius:8px;
    line-height:1.5;
    margin:12px 0;
}

.skills{
    background:#eef2ff;
    padding:12px;
    border-radius:8px;
    color:#4f46e5;
}

.remove{
    border:none;
    background:#dc3545;
    color:white;
    padding:10px 15px;
    border-radius:7px;
    font-weight:bold;
    cursor:pointer;
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
    padding:30px;
    text-align:center;
    border-radius:12px;
    color:#777;
}

</style>

</head>


<body>


<div class="container">


<div class="top">

    <h1>
        💼 Internships
    </h1>

    <p>
        View and manage all internship postings.
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


<div class="internship">


    <h2>
        <?= htmlspecialchars($row["title"]) ?>
    </h2>


    <div class="info">

        🏢
        <strong>
            Company:
        </strong>

        <?= htmlspecialchars($row["company_name"]) ?>

    </div>


    <div class="info">

        📍
        <strong>
            Location:
        </strong>

        <?= htmlspecialchars($row["location"]) ?>

    </div>


    <div class="info">

        ⏳
        <strong>
            Duration:
        </strong>

        <?= htmlspecialchars($row["duration"]) ?>

    </div>


    <div class="info">

        📅
        <strong>
            Deadline:
        </strong>

        <?= htmlspecialchars($row["deadline"]) ?>

    </div>


    <div class="info">

        📄
        <strong>
            Applications:
        </strong>

        <?= (int)$row["application_count"] ?>

    </div>


    <div class="description">

        <?= nl2br(
            htmlspecialchars(
                $row["description"]
            )
        ) ?>

    </div>


    <div class="skills">

        <strong>
            Required Skills:
        </strong>

        <?= htmlspecialchars($row["skills"]) ?>

    </div>


    <form method="POST">

        <input
            type="hidden"
            name="internship_id"
            value="<?= (int)$row["id"] ?>"
        >


        <button
            type="submit"
            name="action"
            value="remove"
            class="remove"
            onclick="return confirm('Remove this internship and all its applications/test results?');"
        >

            🗑 Remove Internship

        </button>

    </form>


</div>


<?php endwhile; ?>


<?php else: ?>


<div class="no-data">

    No internships found.

</div>


<?php endif; ?>


</div>


</body>
</html>