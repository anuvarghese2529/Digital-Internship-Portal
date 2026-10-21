<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/db.php";

$user_id = (int)$_SESSION["user_id"];
$user_role = $_SESSION["user_role"] ?? "";

if ($user_role !== "company") {
    header("Location: ../login.php");
    exit();
}

$message = "";
$message_type = "";

/* Get internship ID */
$internship_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($internship_id <= 0) {
    header("Location: my_internships.php");
    exit();
}


/* Update internship */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $company_name = trim($_POST["company_name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $skills = trim($_POST["skills"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $duration = trim($_POST["duration"] ?? "");
    $deadline = $_POST["deadline"] ?? "";

    if (
        $title === "" ||
        $company_name === "" ||
        $description === "" ||
        $skills === "" ||
        $location === "" ||
        $duration === "" ||
        $deadline === ""
    ) {

        $message = "Please fill all fields.";
        $message_type = "error";

    } else {

        /*
         * Update only the internship belonging
         * to the logged-in company
         */

        $sql = "UPDATE internships
                SET title = ?,
                    company_name = ?,
                    description = ?,
                    skills = ?,
                    location = ?,
                    duration = ?,
                    deadline = ?
                WHERE id = ?
                AND company_id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {

            $message = "Database Error: " . mysqli_error($conn);
            $message_type = "error";

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "sssssssii",
                $title,
                $company_name,
                $description,
                $skills,
                $location,
                $duration,
                $deadline,
                $internship_id,
                $user_id
            );

            if (mysqli_stmt_execute($stmt)) {

                header("Location: my_internships.php?updated=1");
                exit();

            } else {

                $message = "Failed to update internship.";
                $message_type = "error";
            }

            mysqli_stmt_close($stmt);
        }
    }

} else {

    /* Get existing internship */

    $sql = "SELECT
                title,
                company_name,
                description,
                skills,
                location,
                duration,
                deadline
            FROM internships
            WHERE id = ?
            AND company_id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        die("Database Error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $internship_id,
        $user_id
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) !== 1) {

        mysqli_stmt_close($stmt);

        header("Location: my_internships.php");
        exit();
    }

    $row = mysqli_fetch_assoc($result);

    $title = $row["title"];
    $company_name = $row["company_name"];
    $description = $row["description"];
    $skills = $row["skills"];
    $location = $row["location"];
    $duration = $row["duration"];
    $deadline = $row["deadline"];

    mysqli_stmt_close($stmt);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Edit Internship</title>

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
    max-width: 800px;
    margin: 40px auto;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

h1 {
    color: #667eea;
    margin-bottom: 8px;
}

.subtitle {
    color: #777;
    margin-bottom: 25px;
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 7px;
    color: #444;
}

input,
textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 7px;
    font-size: 15px;
    font-family: Arial, sans-serif;
}

input:focus,
textarea:focus {
    outline: none;
    border-color: #667eea;
}

textarea {
    min-height: 120px;
    resize: vertical;
}

.message {
    padding: 12px;
    border-radius: 7px;
    margin-bottom: 20px;
    font-weight: bold;
}

.error {
    background: #f8d7da;
    color: #721c24;
}

.buttons {
    margin-top: 25px;
}

.update-btn,
.cancel-btn {
    display: inline-block;
    padding: 11px 20px;
    border: none;
    border-radius: 7px;
    color: white;
    text-decoration: none;
    font-size: 15px;
    cursor: pointer;
}

.update-btn {
    background: #667eea;
}

.update-btn:hover {
    background: #5568d8;
}

.cancel-btn {
    background: #6c757d;
    margin-left: 8px;
}

.cancel-btn:hover {
    background: #5a6268;
}

</style>

</head>

<body>

<div class="container">

    <div class="card">

        <h1>✏️ Edit Internship</h1>

        <p class="subtitle">
            Update your internship details
        </p>


        <?php if ($message !== ""): ?>

            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>


        <form method="POST">


            <!-- TITLE -->

            <div class="form-group">

                <label for="title">
                    Internship Title
                </label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    value="<?php echo htmlspecialchars($title); ?>"
                    required
                >

            </div>


            <!-- COMPANY NAME -->

            <div class="form-group">

                <label for="company_name">
                    Company Name
                </label>

                <input
                    type="text"
                    id="company_name"
                    name="company_name"
                    value="<?php echo htmlspecialchars($company_name); ?>"
                    required
                >

            </div>


            <!-- DESCRIPTION -->

            <div class="form-group">

                <label for="description">
                    Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    required
                ><?php echo htmlspecialchars($description); ?></textarea>

            </div>


            <!-- SKILLS -->

            <div class="form-group">

                <label for="skills">
                    Required Skills
                </label>

                <input
                    type="text"
                    id="skills"
                    name="skills"
                    value="<?php echo htmlspecialchars($skills); ?>"
                    placeholder="Example: PHP, MySQL, HTML, CSS"
                    required
                >

            </div>


            <!-- LOCATION -->

            <div class="form-group">

                <label for="location">
                    Location
                </label>

                <input
                    type="text"
                    id="location"
                    name="location"
                    value="<?php echo htmlspecialchars($location); ?>"
                    required
                >

            </div>


            <!-- DURATION -->

            <div class="form-group">

                <label for="duration">
                    Duration
                </label>

                <input
                    type="text"
                    id="duration"
                    name="duration"
                    value="<?php echo htmlspecialchars($duration); ?>"
                    placeholder="Example: 3 Months"
                    required
                >

            </div>


            <!-- DEADLINE -->

            <div class="form-group">

                <label for="deadline">
                    Application Deadline
                </label>

                <input
                    type="date"
                    id="deadline"
                    name="deadline"
                    value="<?php echo htmlspecialchars($deadline); ?>"
                    required
                >

            </div>


            <!-- BUTTONS -->

            <div class="buttons">

                <button
                    type="submit"
                    class="update-btn">
                    💾 Update Internship
                </button>


                <a
                    href="my_internships.php"
                    class="cancel-btn">
                    Cancel
                </a>

            </div>


        </form>

    </div>

</div>

</body>

</html>