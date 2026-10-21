<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/db.php";

date_default_timezone_set("Asia/Kolkata");

$user_id = (int) $_SESSION["user_id"];
$user_name = $_SESSION["user_name"] ?? "Company";
$user_role = $_SESSION["user_role"] ?? "";


/* =========================================================
   ONLY COMPANY CAN ACCESS
========================================================= */

if ($user_role !== "company") {
    header("Location: ../login.php");
    exit();
}


/* =========================================================
   GET INTERNSHIPS POSTED BY THIS COMPANY
========================================================= */

$sql = "SELECT
            id,
            title,
            company_name,
            description,
            skills,
            location,
            start_date,
            end_date,
            duration,
            deadline,
            created_at
        FROM internships
        WHERE company_id = ?
        ORDER BY id DESC";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("SQL Prepare Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);

if (!mysqli_stmt_execute($stmt)) {
    die("SQL Execute Error: " . mysqli_stmt_error($stmt));
}

$result = mysqli_stmt_get_result($stmt);

if (!$result) {
    die("Result Error: " . mysqli_error($conn));
}


/* Today's date */

$today = date("Y-m-d");

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>My Internships</title>


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
    width: 90%;
    max-width: 1100px;
    margin: 40px auto;
}


/* =========================================================
   HEADER
========================================================= */

.header {
    background: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.08);
}

.header h1 {
    color: #667eea;
    margin-bottom: 8px;
}

.header p {
    color: #777;
}


/* =========================================================
   TOP BUTTONS
========================================================= */

.buttons {
    margin-top: 15px;
}

.back-btn,
.post-btn {

    display: inline-block;

    padding: 10px 18px;

    color: white;

    text-decoration: none;

    border-radius: 7px;

    margin-right: 8px;
}

.back-btn {
    background: #667eea;
}

.post-btn {
    background: #28a745;
}

.back-btn:hover {
    background: #5568d8;
}

.post-btn:hover {
    background: #218838;
}


/* =========================================================
   INTERNSHIP CARD
========================================================= */

.internship-card {

    background: white;

    padding: 25px;

    border-radius: 12px;

    margin-bottom: 20px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.08);
}

.internship-card h2 {

    color: #333;

    margin-bottom: 8px;
}

.company {

    color: #667eea;

    font-weight: bold;

    margin-bottom: 15px;
}

.info {

    color: #666;

    margin-bottom: 8px;

    line-height: 1.5;
}

.description {

    color: #555;

    margin: 15px 0;

    line-height: 1.6;
}

.skills {

    background: #f3f4ff;

    color: #667eea;

    padding: 10px;

    border-radius: 8px;

    margin-top: 12px;
}


/* =========================================================
   DATES
========================================================= */

.date-box {

    background: #f8f9fa;

    padding: 15px;

    margin-top: 15px;

    border-radius: 9px;

    border-left: 4px solid #667eea;
}

.date-item {

    margin-bottom: 7px;

    color: #555;
}

.date-item:last-child {
    margin-bottom: 0;
}


/* =========================================================
   STATUS
========================================================= */

.status-open {

    display: inline-block;

    background: #d1e7dd;

    color: #0f5132;

    padding: 8px 14px;

    border-radius: 20px;

    font-weight: bold;

    margin-top: 15px;
}

.status-closed {

    display: inline-block;

    background: #f8d7da;

    color: #842029;

    padding: 8px 14px;

    border-radius: 20px;

    font-weight: bold;

    margin-top: 15px;
}


/* =========================================================
   CREATED
========================================================= */

.created {

    margin-top: 12px;

    color: #888;

    font-size: 13px;
}


/* =========================================================
   ACTIONS
========================================================= */

.actions {

    margin-top: 20px;

    display: flex;

    flex-wrap: wrap;

    gap: 8px;
}

.edit-btn,
.delete-btn {

    display: inline-block;

    padding: 9px 16px;

    color: white;

    text-decoration: none;

    border-radius: 6px;
}

.edit-btn {

    background: #667eea;
}

.delete-btn {

    background: #dc3545;
}

.edit-btn:hover {

    background: #5568d8;
}

.delete-btn:hover {

    background: #c82333;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    background: white;

    padding: 40px;

    text-align: center;

    border-radius: 12px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.08);
}

.empty h2 {

    margin-bottom: 10px;
}

.empty p {

    color: #777;

    margin-bottom: 20px;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 700px) {

    .container {

        width: 94%;
    }

    .actions {

        flex-direction: column;
    }

    .edit-btn,
    .delete-btn {

        width: 100%;

        text-align: center;
    }

}

</style>

</head>


<body>

<div class="container">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="header">

        <h1>
            My Internships
        </h1>

        <p>
            View and manage the internships you have posted.
        </p>


        <div class="buttons">

            <a
                href="dashboard.php"
                class="back-btn"
            >
                ← Back to Dashboard
            </a>


            <a
                href="post_internship.php"
                class="post-btn"
            >
                + Post New Internship
            </a>

        </div>

    </div>


    <!-- =====================================================
         INTERNSHIPS
    ====================================================== -->

    <?php if (mysqli_num_rows($result) > 0): ?>


        <?php while ($row = mysqli_fetch_assoc($result)): ?>


            <?php

            /* -----------------------------------------------
               CHECK APPLICATION DEADLINE
            ------------------------------------------------ */

            $application_closed = false;

            if (!empty($row["deadline"])) {

                if ($today > $row["deadline"]) {

                    $application_closed = true;

                }

            }

            ?>


            <div class="internship-card">


                <!-- TITLE -->

                <h2>

                    <?php

                    echo htmlspecialchars(
                        $row["title"]
                    );

                    ?>

                </h2>


                <!-- COMPANY -->

                <p class="company">

                    🏢

                    <?php

                    echo htmlspecialchars(
                        $row["company_name"]
                    );

                    ?>

                </p>


                <!-- LOCATION -->

                <p class="info">

                    📍

                    <strong>
                        Location:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $row["location"]
                    );

                    ?>

                </p>


                <!-- DURATION -->

                <p class="info">

                    ⏱️

                    <strong>
                        Duration:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $row["duration"]
                    );

                    ?>

                </p>


                <!-- DESCRIPTION -->

                <p class="description">

                    <strong>
                        Description:
                    </strong>

                    <br>

                    <?php

                    echo nl2br(
                        htmlspecialchars(
                            $row["description"]
                        )
                    );

                    ?>

                </p>


                <!-- SKILLS -->

                <div class="skills">

                    <strong>
                        Skills:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $row["skills"]
                    );

                    ?>

                </div>


                <!-- =================================================
                     DATES
                ================================================== -->

                <div class="date-box">


                    <div class="date-item">

                        📅

                        <strong>
                            Internship Start:
                        </strong>

                        <?php

                        if (!empty($row["start_date"])) {

                            echo date(
                                "d M Y",
                                strtotime(
                                    $row["start_date"]
                                )
                            );

                        } else {

                            echo "Not specified";

                        }

                        ?>

                    </div>


                    <div class="date-item">

                        📅

                        <strong>
                            Internship End:
                        </strong>

                        <?php

                        if (!empty($row["end_date"])) {

                            echo date(
                                "d M Y",
                                strtotime(
                                    $row["end_date"]
                                )
                            );

                        } else {

                            echo "Not specified";

                        }

                        ?>

                    </div>


                    <div class="date-item">

                        ⏳

                        <strong>
                            Application Deadline:
                        </strong>

                        <?php

                        if (!empty($row["deadline"])) {

                            echo date(
                                "d M Y",
                                strtotime(
                                    $row["deadline"]
                                )
                            );

                        } else {

                            echo "Not specified";

                        }

                        ?>

                    </div>


                </div>


                <!-- =================================================
                     APPLICATION STATUS
                ================================================== -->

                <?php if ($application_closed): ?>


                    <div class="status-closed">

                        ❌ Application Closed

                    </div>


                <?php else: ?>


                    <div class="status-open">

                        ✅ Applications Open

                    </div>


                <?php endif; ?>


                <!-- POSTED DATE -->

                <p class="created">

                    Posted on:

                    <?php

                    if (!empty($row["created_at"])) {

                        echo date(
                            "d M Y",
                            strtotime(
                                $row["created_at"]
                            )
                        );

                    } else {

                        echo "Not available";

                    }

                    ?>

                </p>


                <!-- =================================================
                     ACTION BUTTONS
                ================================================== -->

                <div class="actions">


                    <a
                        href="edit_internship.php?id=<?php echo (int)$row["id"]; ?>"
                        class="edit-btn"
                    >

                        ✏️ Edit

                    </a>


                    <a
                        href="delete_internship.php?id=<?php echo (int)$row["id"]; ?>"
                        class="delete-btn"

                        onclick="return confirm(
                            'Are you sure you want to delete this internship?'
                        );"
                    >

                        🗑️ Delete

                    </a>


                    <a
                        href="manage_questions.php?internship_id=<?php echo (int)$row["id"]; ?>"
                        class="edit-btn"
                    >

                        📝 Manage Mock Questions

                    </a>


                </div>


            </div>


        <?php endwhile; ?>


    <?php else: ?>


        <div class="empty">

            <h2>
                No Internships Posted
            </h2>

            <p>
                You have not posted any internships yet.
            </p>

            <a
                href="post_internship.php"
                class="post-btn"
            >
                + Post Your First Internship
            </a>

        </div>


    <?php endif; ?>


</div>

</body>

</html>