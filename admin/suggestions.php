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


/* =========================================================
   CREATE TABLE IF NOT EXISTS
========================================================= */

$create_table_sql = "

CREATE TABLE IF NOT EXISTS internship_suggestions (

    id INT AUTO_INCREMENT PRIMARY KEY,

    company_id INT NOT NULL,

    title VARCHAR(255) NOT NULL,

    description TEXT NOT NULL,

    skills VARCHAR(500) NOT NULL,

    location VARCHAR(255) DEFAULT NULL,

    start_date DATE NOT NULL,

    end_date DATE NOT NULL,

    status ENUM('Pending','Accepted','Rejected')
    NOT NULL DEFAULT 'Pending',

    created_at TIMESTAMP
    DEFAULT CURRENT_TIMESTAMP

)

";

mysqli_query(
    $conn,
    $create_table_sql
);


$message = "";
$message_type = "";


/* =========================================================
   CREATE SUGGESTION
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $company_id =
        (int)($_POST["company_id"] ?? 0);

    $title =
        trim($_POST["title"] ?? "");

    $description =
        trim($_POST["description"] ?? "");

    $skills =
        trim($_POST["skills"] ?? "");

    $location =
        trim($_POST["location"] ?? "");

    $start_date =
        $_POST["start_date"] ?? "";

    $end_date =
        $_POST["end_date"] ?? "";


    if (
        $company_id <= 0
        ||
        $title === ""
        ||
        $description === ""
        ||
        $skills === ""
        ||
        $start_date === ""
        ||
        $end_date === ""
    ) {

        $message =
            "Please fill all required fields.";

        $message_type =
            "error";

    } elseif ($end_date < $start_date) {

        $message =
            "End date must be after start date.";

        $message_type =
            "error";

    } else {


        /* Check approved company */

        $stmt = mysqli_prepare(
            $conn,

            "SELECT
                u.name,
                u.email

             FROM users u

             INNER JOIN company_verifications cv
                ON cv.user_id = u.id

             WHERE u.id = ?
             AND u.role = 'company'
             AND cv.admin_status = 'Approved'

             LIMIT 1"
        );


        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $company_id
        );

        mysqli_stmt_execute($stmt);

        $company_result =
            mysqli_stmt_get_result($stmt);

        $company =
            mysqli_fetch_assoc($company_result);

        mysqli_stmt_close($stmt);


        if (!$company) {

            $message =
                "Only approved companies can receive suggestions.";

            $message_type =
                "error";

        } else {


            /* Insert */

            $stmt = mysqli_prepare(
                $conn,

                "INSERT INTO internship_suggestions
                (
                    company_id,
                    title,
                    description,
                    skills,
                    location,
                    start_date,
                    end_date
                )

                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );


            mysqli_stmt_bind_param(
                $stmt,
                "issssss",

                $company_id,
                $title,
                $description,
                $skills,
                $location,
                $start_date,
                $end_date
            );


            if(mysqli_stmt_execute($stmt)){

                mysqli_stmt_close($stmt);


                /* Notification */

                $notification_message =
                    "Admin has suggested a new internship: "
                    . $title
                    . ". Please review the suggestion.";

                $notification_link =
                    "suggestions.php";


                $notification_stmt =
                    mysqli_prepare(

                        $conn,

                        "INSERT INTO notifications
                        (
                            user_id,
                            message,
                            link,
                            is_read
                        )

                        VALUES (?, ?, ?, 0)"
                    );


                if($notification_stmt){

                    mysqli_stmt_bind_param(
                        $notification_stmt,
                        "iss",
                        $company_id,
                        $notification_message,
                        $notification_link
                    );

                    mysqli_stmt_execute(
                        $notification_stmt
                    );

                    mysqli_stmt_close(
                        $notification_stmt
                    );
                }


                $message =
                    "Internship suggestion sent successfully.";

                $message_type =
                    "success";


            } else {

                mysqli_stmt_close($stmt);

                $message =
                    "Unable to create internship suggestion.";

                $message_type =
                    "error";
            }
        }
    }
}


/* =========================================================
   GET APPROVED COMPANIES
========================================================= */

$company_sql = "

    SELECT
        u.id,
        u.name,
        u.email

    FROM users u

    INNER JOIN company_verifications cv
        ON cv.user_id = u.id

    WHERE u.role = 'company'

    AND cv.admin_status = 'Approved'

    ORDER BY u.name ASC

";

$company_result =
    mysqli_query(
        $conn,
        $company_sql
    );


/* =========================================================
   GET SUGGESTIONS
========================================================= */

$suggestion_sql = "

    SELECT

        s.*,

        u.name AS company_name,
        u.email AS company_email

    FROM internship_suggestions s

    INNER JOIN users u
        ON s.company_id = u.id

    ORDER BY s.id DESC

";

$suggestion_result =
    mysqli_query(
        $conn,
        $suggestion_sql
    );

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Internship Suggestions</title>

<style>

body{
    font-family:Arial,sans-serif;
    background:#f4f6fb;
    margin:0;
}

.container{
    width:94%;
    max-width:1200px;
    margin:30px auto;
}

.top,
.form-card,
.suggestion{
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

.form-card h2,
.suggestion h2{
    margin-bottom:18px;
}

.form-group{
    margin-bottom:16px;
}

label{
    display:block;
    font-weight:bold;
    margin-bottom:7px;
}

input,
textarea,
select{
    width:100%;
    padding:12px;
    border:1px solid #d5d8df;
    border-radius:8px;
    box-sizing:border-box;
    font-size:15px;
}

textarea{
    min-height:100px;
    resize:vertical;
}

.submit{
    border:none;
    background:#667eea;
    color:white;
    padding:12px 18px;
    border-radius:8px;
    font-weight:bold;
    cursor:pointer;
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

.info{
    margin:8px 0;
    color:#555;
}

.badge{
    display:inline-block;
    padding:7px 12px;
    border-radius:20px;
    font-weight:bold;
    font-size:13px;
    margin-top:10px;
}

.pending{
    background:#fff3cd;
    color:#856404;
}

.accepted{
    background:#d1e7dd;
    color:#0f5132;
}

.rejected{
    background:#f8d7da;
    color:#842029;
}

.skills{
    margin-top:12px;
    background:#eef2ff;
    color:#4f46e5;
    padding:12px;
    border-radius:8px;
}

.empty{
    background:white;
    padding:30px;
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
        💡 Internship Suggestions
    </h1>

    <p>
        Suggest suitable internship opportunities to approved companies.
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



<div class="form-card">

    <h2>
        ➕ Create New Internship Suggestion
    </h2>


    <form method="POST">


        <div class="form-group">

            <label>
                Select Approved Company
            </label>

            <select
                name="company_id"
                required
            >

                <option value="">
                    -- Select Company --
                </option>


                <?php while($company = mysqli_fetch_assoc($company_result)): ?>

                    <option
                        value="<?= (int)$company["id"] ?>"
                    >

                        <?= htmlspecialchars(
                            $company["name"]
                        ) ?>

                        -
                        <?= htmlspecialchars(
                            $company["email"]
                        ) ?>

                    </option>

                <?php endwhile; ?>

            </select>

        </div>



        <div class="form-group">

            <label>
                Internship Title
            </label>

            <input
                type="text"
                name="title"
                placeholder="Example: Python Django Intern"
                required
            >

        </div>



        <div class="form-group">

            <label>
                Description
            </label>

            <textarea
                name="description"
                placeholder="Enter internship description"
                required
            ></textarea>

        </div>



        <div class="form-group">

            <label>
                Required Skills
            </label>

            <input
                type="text"
                name="skills"
                placeholder="Python, Django, MySQL, HTML, CSS"
                required
            >

        </div>



        <div class="form-group">

            <label>
                Location
            </label>

            <input
                type="text"
                name="location"
                placeholder="Kochi"
            >

        </div>



        <div class="form-group">

            <label>
                Start Date
            </label>

            <input
                type="date"
                name="start_date"
                required
            >

        </div>



        <div class="form-group">

            <label>
                End Date
            </label>

            <input
                type="date"
                name="end_date"
                required
            >

        </div>



        <button
            type="submit"
            class="submit"
        >

            📤 Send Suggestion

        </button>


    </form>

</div>



<h2 style="margin-bottom:15px;">
    Sent Suggestions
</h2>



<?php if(
    $suggestion_result
    &&
    mysqli_num_rows($suggestion_result) > 0
): ?>


<?php while(
    $suggestion =
    mysqli_fetch_assoc(
        $suggestion_result
    )
): ?>


<div class="suggestion">


    <h2>

        <?= htmlspecialchars(
            $suggestion["title"]
        ) ?>

    </h2>


    <div class="info">

        🏢
        <strong>
            Company:
        </strong>

        <?= htmlspecialchars(
            $suggestion["company_name"]
        ) ?>

    </div>


    <div class="info">

        📅
        <strong>
            Period:
        </strong>

        <?= htmlspecialchars(
            $suggestion["start_date"]
        ) ?>

        →

        <?= htmlspecialchars(
            $suggestion["end_date"]
        ) ?>

    </div>


    <div class="info">

        📍
        <strong>
            Location:
        </strong>

        <?= htmlspecialchars(
            $suggestion["location"]
        ) ?>

    </div>


    <div class="skills">

        <strong>
            Required Skills:
        </strong>

        <?= htmlspecialchars(
            $suggestion["skills"]
        ) ?>

    </div>


    <div
        style="
        margin-top:12px;
        line-height:1.6;
        "
    >

        <?= nl2br(
            htmlspecialchars(
                $suggestion["description"]
            )
        ) ?>

    </div>


    <?php

    $status =
        $suggestion["status"];

    $status_class = "pending";

    if($status === "Accepted"){
        $status_class = "accepted";
    }

    if($status === "Rejected"){
        $status_class = "rejected";
    }

    ?>


    <span class="badge <?= $status_class ?>">

        <?= htmlspecialchars($status) ?>

    </span>


</div>


<?php endwhile; ?>


<?php else: ?>


<div class="empty">

    No internship suggestions sent yet.

</div>


<?php endif; ?>


</div>


</body>
</html>