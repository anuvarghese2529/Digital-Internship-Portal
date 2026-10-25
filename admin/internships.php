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
*{box-sizing:border-box}
body{font-family:Arial,sans-serif;background:#f4f6fb;margin:0;color:#222}
.container{width:95%;max-width:1500px;margin:30px auto}
.top{background:#fff;padding:25px;border-radius:12px;margin-bottom:20px;box-shadow:0 5px 15px rgba(0,0,0,.07)}
.top h1{color:#667eea;margin-top:0}
.back{display:inline-block;margin-top:10px;background:#667eea;color:#fff;text-decoration:none;padding:10px 16px;border-radius:7px}
.message{padding:12px;border-radius:8px;margin-bottom:20px;font-weight:bold}
.success{background:#d1e7dd;color:#0f5132}.error{background:#f8d7da;color:#842029}
.table-wrap{overflow-x:auto;background:#fff;border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,.07)}
table{width:100%;border-collapse:collapse;min-width:1050px}
th,td{padding:14px 12px;text-align:left;border-bottom:1px solid #e7eaf1;vertical-align:top}
th{background:#667eea;color:#fff;white-space:nowrap}
tr:hover td{background:#f8f9ff}.title{font-weight:bold;color:#4f46e5;min-width:150px}
.small{font-size:13px;color:#555;line-height:1.5;max-width:300px;white-space:normal;overflow-wrap:anywhere}
.skills{font-size:13px;color:#4f46e5;max-width:220px;overflow-wrap:anywhere}
.count{font-weight:bold;text-align:center}.remove{border:none;background:#dc3545;color:#fff;padding:9px 12px;border-radius:7px;font-weight:bold;cursor:pointer;white-space:nowrap}
.details summary{cursor:pointer;color:#4f46e5;font-weight:bold}.details{min-width:180px}.details p{max-width:300px;white-space:normal;overflow-wrap:anywhere;line-height:1.5}
.no-data{background:#fff;padding:30px;text-align:center;border-radius:12px;color:#777}
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
<div class="table-wrap">
<table>
<thead>
<tr>
<th>ID</th>
<th>Internship</th>
<th>Company</th>
<th>Location</th>
<th>Duration</th>
<th>Deadline</th>
<th>Applications</th>
<th>Skills / Description</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php while($row = mysqli_fetch_assoc($result)): ?>
<tr>
<td><?= (int)$row["id"] ?></td>
<td class="title"><?= htmlspecialchars($row["title"] ?? "") ?></td>
<td><?= htmlspecialchars($row["company_name"] ?? "") ?></td>
<td><?= htmlspecialchars($row["location"] ?? "") ?></td>
<td><?= htmlspecialchars($row["duration"] ?? "") ?></td>
<td><?= htmlspecialchars($row["deadline"] ?? "") ?></td>
<td class="count"><?= (int)$row["application_count"] ?></td>
<td class="details">
<strong>Skills:</strong>
<div class="skills"><?= htmlspecialchars($row["skills"] ?? "Not specified") ?></div>
<details>
<summary>View description</summary>
<p class="small"><?= nl2br(htmlspecialchars($row["description"] ?? "No description")) ?></p>
</details>
</td>
<td>
<form method="POST" onsubmit="return confirm('Remove this internship and all its applications/test results?');">
<input type="hidden" name="internship_id" value="<?= (int)$row["id"] ?>">
<button type="submit" name="action" value="remove" class="remove">🗑 Remove</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="no-data">No internships found.</div>
<?php endif; ?>

</div>


</body>
</html>