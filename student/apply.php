<?php

session_start();

date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/db.php";

$student_id = (int)$_SESSION["user_id"];

$internship_id = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

if ($internship_id <= 0) {
    die("Invalid internship ID.");
}


/* =========================================================
   GET INTERNSHIP DETAILS
   ========================================================= */

$sql = "
    SELECT
        id,
        title,
        company_name,
        skills,
        location,
        duration,
        start_date,
        end_date,
        deadline
    FROM internships
    WHERE id = ?
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $internship_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    die("Internship not found.");
}

$internship = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/* =========================================================
   DEADLINE CHECK
   ========================================================= */

$today = date("Y-m-d");

$application_closed = false;

if (!empty($internship["deadline"])) {

    if ($today > $internship["deadline"]) {

        $application_closed = true;
    }
}


/* =========================================================
   GET STUDENT SKILLS
   ========================================================= */

$user_sql = "
    SELECT skills
    FROM users
    WHERE id = ?
";

$user_stmt = mysqli_prepare(
    $conn,
    $user_sql
);

if (!$user_stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $user_stmt,
    "i",
    $student_id
);

mysqli_stmt_execute($user_stmt);

$user_result =
    mysqli_stmt_get_result($user_stmt);

$user_data =
    mysqli_fetch_assoc($user_result);

mysqli_stmt_close($user_stmt);


$student_skills = [];

if (!empty($user_data["skills"])) {

    $student_skills = preg_split(
        "/[,;]+/",
        strtolower($user_data["skills"])
    );

    $student_skills = array_values(
        array_unique(
            array_filter(
                array_map(
                    "trim",
                    $student_skills
                )
            )
        )
    );
}


/* =========================================================
   CALCULATE SKILL MATCH
   ========================================================= */

$required_skills = [];

if (!empty($internship["skills"])) {

    $required_skills = preg_split(
        "/[,;]+/",
        strtolower($internship["skills"])
    );

    $required_skills = array_values(
        array_unique(
            array_filter(
                array_map(
                    "trim",
                    $required_skills
                )
            )
        )
    );
}


$matched_skills = [];

foreach ($required_skills as $required_skill) {

    if (in_array(
        $required_skill,
        $student_skills,
        true
    )) {

        $matched_skills[] =
            $required_skill;
    }
}


$skill_match =
    count($required_skills) > 0
    ? round(
        (
            count($matched_skills) /
            count($required_skills)
        ) * 100
    )
    : 0;


$can_apply =
    $skill_match >= 60;


/* =========================================================
   CHECK ALREADY APPLIED
   ========================================================= */

$check_sql = "
    SELECT id
    FROM applications
    WHERE student_id = ?
    AND internship_id = ?
    AND status != 'Cancelled'
";

$check_stmt = mysqli_prepare(
    $conn,
    $check_sql
);

if (!$check_stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $check_stmt,
    "ii",
    $student_id,
    $internship_id
);

mysqli_stmt_execute($check_stmt);

$check_result =
    mysqli_stmt_get_result(
        $check_stmt
    );

$already_applied =
    mysqli_num_rows(
        $check_result
    ) > 0;

mysqli_stmt_close($check_stmt);


/* =========================================================
   GET EXISTING APPLICATION ID
   ========================================================= */

$application_id = 0;

if ($already_applied) {

    $existing_sql = "
        SELECT id
        FROM applications
        WHERE student_id = ?
        AND internship_id = ?
        AND status != 'Cancelled'
        LIMIT 1
    ";

    $existing_stmt =
        mysqli_prepare(
            $conn,
            $existing_sql
        );

    if ($existing_stmt) {

        mysqli_stmt_bind_param(
            $existing_stmt,
            "ii",
            $student_id,
            $internship_id
        );

        mysqli_stmt_execute(
            $existing_stmt
        );

        $existing_result =
            mysqli_stmt_get_result(
                $existing_stmt
            );

        if (
            mysqli_num_rows(
                $existing_result
            ) > 0
        ) {

            $existing_application =
                mysqli_fetch_assoc(
                    $existing_result
                );

            $application_id =
                (int)
                $existing_application["id"];
        }

        mysqli_stmt_close(
            $existing_stmt
        );
    }
}


/* =========================================================
   VARIABLES
   ========================================================= */

$message = "";

$message_type = "";

$application_submitted = false;


/* =========================================================
   SUBMIT APPLICATION
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /* DEADLINE FIRST */

    if ($application_closed) {

        $message =
            "Application is closed. The application deadline has passed.";

        $message_type = "error";


    } elseif (!$can_apply) {

        $message =
            "You cannot apply because your Skill Match is " .
            $skill_match .
            "%. Minimum 60% is required.";

        $message_type = "error";


    } elseif ($already_applied) {

        $message =
            "You have already applied for this internship.";

        $message_type = "error";


    } else {


        /* =================================================
           INSERT APPLICATION
           ================================================= */

        $insert_sql = "
            INSERT INTO applications
            (
                student_id,
                internship_id,
                status
            )
            VALUES
            (
                ?,
                ?,
                'Pending'
            )
        ";

        $insert_stmt =
            mysqli_prepare(
                $conn,
                $insert_sql
            );

        if (!$insert_stmt) {

            die(
                "Database Error: " .
                mysqli_error($conn)
            );
        }


        mysqli_stmt_bind_param(
            $insert_stmt,
            "ii",
            $student_id,
            $internship_id
        );


        if (
            mysqli_stmt_execute(
                $insert_stmt
            )
        ) {


            /* New Application ID */

            $application_id =
                mysqli_insert_id($conn);


            /* =================================================
               NOTIFICATION
               ================================================= */

            $notification_message =
                "Your application for " .
                $internship["title"] .
                " has been submitted successfully.";


            $notification_sql = "
                INSERT INTO notifications
                (
                    user_id,
                    message,
                    is_read
                )
                VALUES
                (
                    ?,
                    ?,
                    0
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
                    "is",
                    $student_id,
                    $notification_message
                );

                mysqli_stmt_execute(
                    $notification_stmt
                );

                mysqli_stmt_close(
                    $notification_stmt
                );
            }


            $message =
                "Application submitted successfully!";

            $message_type =
                "success";

            $already_applied =
                true;

            $application_submitted =
                true;


        } else {

            $message =
                "Application failed: " .
                mysqli_stmt_error(
                    $insert_stmt
                );

            $message_type =
                "error";
        }


        mysqli_stmt_close(
            $insert_stmt
        );
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
    Apply Internship
</title>


<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}


body {

    font-family: Arial, sans-serif;

    background:
        linear-gradient(
            135deg,
            #667eea,
            #764ba2
        );

    min-height: 100vh;

    display: flex;

    justify-content: center;

    align-items: center;

    padding: 20px;
}


.box {

    width: 500px;

    max-width: 100%;

    background: white;

    padding: 35px;

    border-radius: 15px;

    box-shadow:
        0 10px 30px
        rgba(0,0,0,0.2);

    text-align: center;
}


h1 {

    color: #667eea;

    margin-bottom: 20px;
}


h2 {

    color: #333;

    margin-bottom: 10px;
}


.company {

    color: #667eea;

    font-weight: bold;

    margin-bottom: 20px;
}


.message {

    padding: 12px;

    border-radius: 8px;

    margin-bottom: 20px;
}


.success {

    background: #d4edda;

    color: #155724;
}


.error {

    background: #f8d7da;

    color: #721c24;
}


.info-box {

    background: #f8f9ff;

    padding: 15px;

    border-radius: 10px;

    margin-bottom: 15px;

    text-align: left;

    line-height: 1.7;

    color: #555;
}


.skill-box {

    background: #f3f4ff;

    padding: 15px;

    border-radius: 10px;

    margin: 15px 0;

    color: #555;

    line-height: 1.6;
}


.skill-match-number {

    font-size: 22px;

    font-weight: bold;

    color: #667eea;
}


.apply-btn {

    width: 100%;

    padding: 13px;

    border: none;

    border-radius: 8px;

    background: #667eea;

    color: white;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;
}


.apply-btn:hover {

    background: #5568d8;
}


.closed-btn {

    width: 100%;

    padding: 13px;

    border-radius: 8px;

    background: #6c757d;

    color: white;

    font-size: 16px;

    font-weight: bold;

    cursor: not-allowed;

}


.closed-note {

    background: #f8d7da;

    color: #721c24;

    padding: 13px;

    border-radius: 8px;

    margin-top: 10px;

    line-height: 1.6;
}


.mock-test-btn {

    display: block;

    width: 100%;

    padding: 14px;

    margin-top: 15px;

    border-radius: 8px;

    background: #28a745;

    color: white;

    text-decoration: none;

    font-size: 16px;

    font-weight: bold;
}


.mock-test-btn:hover {

    background: #218838;
}


.dashboard-btn {

    display: block;

    width: 100%;

    padding: 13px;

    margin-top: 10px;

    border-radius: 8px;

    background: #f1f3f5;

    color: #667eea;

    text-decoration: none;

    font-weight: bold;
}


.back {

    display: block;

    margin-top: 20px;

    color: #667eea;

    text-decoration: none;
}


.already {

    color: #28a745;

    font-weight: bold;

    margin-bottom: 15px;
}


.mock-info {

    background: #f3f4ff;

    padding: 15px;

    border-radius: 10px;

    margin-top: 15px;

    margin-bottom: 10px;

    color: #555;

    font-size: 14px;

    line-height: 1.6;
}

</style>

</head>


<body>


<div class="box">


    <h1>
        Digital Internship
    </h1>


    <h2>

        <?= htmlspecialchars(
            $internship["title"]
        ) ?>

    </h2>


    <p class="company">

        🏢

        <?= htmlspecialchars(
            $internship["company_name"]
        ) ?>

    </p>



    <!-- INTERNSHIP DETAILS -->

    <div class="info-box">

        <strong>
            📍 Location:
        </strong>

        <?= htmlspecialchars(
            $internship["location"]
            ?? "Not specified"
        ) ?>

        <br>


        <strong>
            ⏳ Duration:
        </strong>

        <?= htmlspecialchars(
            $internship["duration"]
            ?? "Not specified"
        ) ?>

        <br>


        <strong>
            📅 Start Date:
        </strong>

        <?php

        if (!empty($internship["start_date"])) {

            echo date(
                "d M Y",
                strtotime(
                    $internship["start_date"]
                )
            );

        } else {

            echo "Not specified";

        }

        ?>

        <br>


        <strong>
            🏁 End Date:
        </strong>

        <?php

        if (!empty($internship["end_date"])) {

            echo date(
                "d M Y",
                strtotime(
                    $internship["end_date"]
                )
            );

        } else {

            echo "Not specified";

        }

        ?>

        <br>


        <strong>
            ⏰ Application Deadline:
        </strong>

        <?php

        if (!empty($internship["deadline"])) {

            echo date(
                "d M Y",
                strtotime(
                    $internship["deadline"]
                )
            );

        } else {

            echo "Not specified";

        }

        ?>

    </div>



    <!-- SKILL MATCH -->

    <div class="skill-box">

        <strong>
            🎯 Skill Match
        </strong>


        <div class="skill-match-number">

            <?= $skill_match ?>%

        </div>


        Minimum required:
        <strong>60%</strong>


<?php if (!empty($matched_skills)): ?>

        <br><br>

        <strong>
            Matched Skills:
        </strong>

        <?= htmlspecialchars(
            implode(
                ", ",
                $matched_skills
            )
        ) ?>

<?php endif; ?>


    </div>



    <!-- MESSAGE -->

<?php if ($message !== ""): ?>

    <div class="message <?= htmlspecialchars($message_type) ?>">

        <?= htmlspecialchars($message) ?>

    </div>

<?php endif; ?>



    <!-- APPLICATION SUCCESS -->

<?php if ($application_submitted): ?>


    <div class="mock-info">

        🎉
        <strong>
            Application Submitted!
        </strong>

        <br><br>

        You can now attend the mock test.

        <br>

        <strong>
            Level 1 – 10 Questions
        </strong>

        <br>

        Minimum score:
        <strong>8/10</strong>

    </div>


    <a
        href="mock_test.php?application_id=<?= $application_id ?>"
        class="mock-test-btn"
    >

        📝 Attend Mock Test

    </a>


    <a
        href="dashboard.php"
        class="dashboard-btn"
    >

        ← Back to Dashboard

    </a>



<?php elseif ($already_applied): ?>


    <p class="already">

        ✓ You have already applied
        for this internship.

    </p>


<?php if ($application_id > 0): ?>

    <a
        href="mock_test.php?application_id=<?= $application_id ?>"
        class="mock-test-btn"
    >

        📝 Attend Mock Test

    </a>

<?php endif; ?>



<?php else: ?>


    <!-- DEADLINE CLOSED -->

<?php if ($application_closed): ?>


    <div class="closed-btn">

        ❌ Application Closed

    </div>


    <div class="closed-note">

        The application deadline has passed.

        <br>

        Application closed after:

        <strong>

            <?= date(
                "d M Y",
                strtotime(
                    $internship["deadline"]
                )
            ) ?>

        </strong>

    </div>



<?php elseif ($can_apply): ?>


    <form method="POST">

        <button
            type="submit"
            class="apply-btn"
        >

            Confirm Application

        </button>

    </form>



<?php else: ?>


    <div
        class="apply-btn"
        style="
            background:#adb5bd;
            cursor:not-allowed;
        "
    >

        Apply Not Available

    </div>


    <div
        class="closed-note"
        style="
            background:#f8d7da;
        "
    >

        ❌ Your Skill Match is
        <strong>
            <?= $skill_match ?>%
        </strong>.

        <br>

        Minimum 60% is required.

    </div>


<?php endif; ?>


<?php endif; ?>


    <a
        href="browse.php"
        class="back"
    >

        ← Back to Internships

    </a>


</div>


</body>

</html>