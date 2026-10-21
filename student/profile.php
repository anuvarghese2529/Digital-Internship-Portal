<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/db.php";

$user_id = (int)$_SESSION["user_id"];

$message = "";
$message_type = "";


/* ==================================================
   GET CURRENT USER DETAILS
================================================== */

$sql = "SELECT
            name,
            email,
            education,
            previous_internship,
            skills,
            resume
        FROM users
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) !== 1) {
    die("User not found.");
}

$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/* ==================================================
   PREDEFINED SKILL LIST
   Students can select only from these skills.
================================================== */

$allowed_skills = [
    "HTML",
    "CSS",
    "JavaScript",
    "Bootstrap",
    "PHP",
    "MySQL",
    "Python",
    "Django",
    "Java",
    "C",
    "C++",
    "C#",
    "SQL",
    "Git",
    "WAMP",
    "Flask",
    "Laravel",
    "React",
    "Node.js",
    "Data Structures",
    "DBMS",
    "OOP",
    "Machine Learning"
];


/* Convert saved skills into valid selected skills */

$current_skills = [];

if (!empty($user["skills"])) {

    $saved_skills = preg_split(
        '/[,;]+/',
        $user["skills"]
    );

    $saved_skills = array_values(
        array_filter(
            array_map(
                'trim',
                $saved_skills
            )
        )
    );

    foreach ($allowed_skills as $allowed_skill) {

        foreach ($saved_skills as $saved_skill) {

            if (strcasecmp(
                $allowed_skill,
                $saved_skill
            ) === 0) {

                $current_skills[] = $allowed_skill;
                break;
            }
        }
    }
}


/* ==================================================
   UPDATE PROFILE
================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");

    $education = trim(
        $_POST["education"] ?? ""
    );

    $has_previous_internship =
        $_POST["has_previous_internship"] ?? "No";

    $previous_internship =
        trim($_POST["previous_internship"] ?? "");

    $selected_skills = $_POST["skills"] ?? [];

    if (!is_array($selected_skills)) {
        $selected_skills = [];
    }

    /* Keep only skills from the predefined list */
    $selected_skills = array_values(
        array_intersect(
            $allowed_skills,
            array_map('trim', $selected_skills)
        )
    );

    $skills = implode(
        ", ",
        $selected_skills
    );


    /* --------------------------------------------------
       VALIDATE NAME
    -------------------------------------------------- */

    if ($name === "") {

        $message = "Please enter your name.";
        $message_type = "error";

    }


    /* --------------------------------------------------
       PREVIOUS INTERNSHIP
    -------------------------------------------------- */

    elseif (
        $has_previous_internship !== "Yes" &&
        $has_previous_internship !== "No"
    ) {

        $message = "Please select Yes or No for previous internship.";
        $message_type = "error";

    }

    elseif (
        $has_previous_internship === "Yes" &&
        $previous_internship === ""
    ) {

        $message = "Please enter your previous internship name.";
        $message_type = "error";

    }

    else {

        /*
         * If student selected No,
         * don't store any internship name.
         */

        if ($has_previous_internship === "No") {

            $previous_internship = "";

        }


        /* --------------------------------------------------
           KEEP OLD RESUME
        -------------------------------------------------- */

        $resume_name = $user["resume"];


        /* --------------------------------------------------
           RESUME UPLOAD
        -------------------------------------------------- */

        if (
            isset($_FILES["resume"]) &&
            $_FILES["resume"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            if (
                $_FILES["resume"]["error"] ===
                UPLOAD_ERR_OK
            ) {

                $file_name =
                    $_FILES["resume"]["name"];

                $file_tmp =
                    $_FILES["resume"]["tmp_name"];

                $file_size =
                    $_FILES["resume"]["size"];


                $extension = strtolower(
                    pathinfo(
                        $file_name,
                        PATHINFO_EXTENSION
                    )
                );


                $allowed = [
                    "pdf",
                    "doc",
                    "docx"
                ];


                /* Check file type */

                if (!in_array(
                    $extension,
                    $allowed
                )) {

                    $message =
                        "Only PDF, DOC and DOCX files are allowed.";

                    $message_type = "error";

                }


                /* Check file size */

                elseif (
                    $file_size >
                    5 * 1024 * 1024
                ) {

                    $message =
                        "Resume size must be less than 5 MB.";

                    $message_type = "error";

                }


                else {

                    $upload_folder =
                        "../assets/uploads/";


                    /* Create folder if not exists */

                    if (!is_dir(
                        $upload_folder
                    )) {

                        mkdir(
                            $upload_folder,
                            0777,
                            true
                        );
                    }


                    /* New unique filename */

                    $new_file_name =
                        "resume_" .
                        $user_id .
                        "_" .
                        time() .
                        "." .
                        $extension;


                    $upload_path =
                        $upload_folder .
                        $new_file_name;


                    /* Move uploaded file */

                    if (
                        move_uploaded_file(
                            $file_tmp,
                            $upload_path
                        )
                    ) {

                        $resume_name =
                            $new_file_name;

                    }

                    else {

                        $message =
                            "Resume upload failed.";

                        $message_type =
                            "error";
                    }
                }

            }

            else {

                $message =
                    "Error while uploading resume.";

                $message_type =
                    "error";
            }
        }


        /* --------------------------------------------------
           UPDATE DATABASE
        -------------------------------------------------- */

        if ($message === "") {

            $update_sql =
                "UPDATE users
                 SET
                    name = ?,
                    education = ?,
                    previous_internship = ?,
                    skills = ?,
                    resume = ?
                 WHERE id = ?";


            $update_stmt =
                mysqli_prepare(
                    $conn,
                    $update_sql
                );


            if (!$update_stmt) {

                die(
                    "Database Error: " .
                    mysqli_error($conn)
                );
            }


            mysqli_stmt_bind_param(
                $update_stmt,
                "sssssi",
                $name,
                $education,
                $previous_internship,
                $skills,
                $resume_name,
                $user_id
            );


            if (
                mysqli_stmt_execute(
                    $update_stmt
                )
            ) {

                /* Update session name */

                $_SESSION["user_name"] =
                    $name;


                /* Success message */

                $message =
                    "Profile updated successfully!";

                $message_type =
                    "success";


                /* Update displayed values */

                $user["name"] =
                    $name;

                $user["education"] =
                    $education;

                $user["previous_internship"] =
                    $previous_internship;

                $user["skills"] =
                    $skills;

                $user["resume"] =
                    $resume_name;

            }

            else {

                $message =
                    "Profile update failed.";

                $message_type =
                    "error";
            }


            mysqli_stmt_close(
                $update_stmt
            );
        }
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
    My Profile - Digital Internship
</title>


<style>

/* ==================================================
   GENERAL
================================================== */

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

    padding: 30px;
}


/* ==================================================
   CONTAINER
================================================== */

.container {

    width: 100%;

    max-width: 650px;

    margin: auto;
}


/* ==================================================
   CARD
================================================== */

.card {

    background: white;

    padding: 35px;

    border-radius: 15px;

    box-shadow:
        0 10px 30px
        rgba(0,0,0,0.20);
}


/* ==================================================
   HEADING
================================================== */

h1 {

    text-align: center;

    color: #667eea;

    margin-bottom: 8px;
}


.subtitle {

    text-align: center;

    color: #777;

    margin-bottom: 25px;

    font-size: 14px;
}


/* ==================================================
   FORM
================================================== */

.form-group {

    margin-bottom: 20px;
}


label {

    display: block;

    margin-bottom: 7px;

    font-weight: bold;

    color: #333;
}


input,
textarea {

    width: 100%;

    padding: 12px;

    border: 1px solid #ddd;

    border-radius: 8px;

    font-size: 15px;

    outline: none;
}


input:focus,
textarea:focus {

    border-color: #667eea;
}


textarea {

    height: 100px;

    resize: vertical;
}


/* ==================================================
   EMAIL
================================================== */

.email-box {

    background: #f3f4ff;

    color: #555;
}


/* ==================================================
   YES / NO OPTIONS
================================================== */

.internship-options {

    display: flex;

    gap: 30px;

    margin-top: 10px;
}


.radio-option {

    display: flex;

    align-items: center;

    gap: 7px;

    font-weight: normal;

    cursor: pointer;

    margin: 0;
}


.radio-option input {

    width: auto;

    cursor: pointer;
}


/* ==================================================
   PREDEFINED SKILLS
================================================== */

.skills-grid {

    display: grid;

    grid-template-columns: repeat(2, 1fr);

    gap: 10px;

    padding: 12px;

    background: #f8f9ff;

    border: 1px solid #e1e4f5;

    border-radius: 10px;
}


.skill-option {

    display: flex;

    align-items: center;

    gap: 8px;

    padding: 9px 10px;

    margin: 0;

    font-weight: normal;

    background: white;

    border-radius: 7px;

    cursor: pointer;
}


.skill-option input {

    width: auto;

    margin: 0;

    cursor: pointer;
}


.skill-option span {

    color: #444;

    font-size: 14px;

}


/* ==================================================
   INTERNSHIP NAME BOX
================================================== */

#internshipNameBox {

    margin-top: 12px;
}


#internshipNameBox input {

    width: 100%;
}


/* ==================================================
   SMALL INFO TEXT
================================================== */

.info-text {

    margin-top: 8px;

    font-size: 13px;

    color: #777;

    line-height: 1.5;
}


/* ==================================================
   UPDATE BUTTON
================================================== */

.update-btn {

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


.update-btn:hover {

    background: #5568d8;
}


/* ==================================================
   MESSAGE
================================================== */

.message {

    padding: 12px;

    border-radius: 8px;

    margin-bottom: 20px;

    text-align: center;
}


.success {

    background: #d4edda;

    color: #155724;
}


.error {

    background: #f8d7da;

    color: #721c24;
}


/* ==================================================
   RESUME INFO
================================================== */

.resume-info {

    margin-top: 10px;

    font-size: 14px;

    color: #555;
}


/* ==================================================
   BACK BUTTON
================================================== */

.back {

    display: block;

    text-align: center;

    margin-top: 20px;

    color: #667eea;

    text-decoration: none;

    font-weight: bold;
}


.back:hover {

    text-decoration: underline;
}


/* ==================================================
   RESPONSIVE
================================================== */

@media (max-width: 600px) {

    body {
        padding: 15px;
    }

    .card {
        padding: 25px;
    }

    .skills-grid {
        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<div class="container">

    <div class="card">


        <!-- ==================================================
             TITLE
        ================================================== -->

        <h1>
            My Profile
        </h1>


        <p class="subtitle">

            Update your profile and upload your resume

        </p>


        <!-- ==================================================
             MESSAGE
        ================================================== -->

        <?php if ($message !== ""): ?>

            <div
                class="message
                <?php echo $message_type; ?>"
            >

                <?php

                echo htmlspecialchars(
                    $message
                );

                ?>

            </div>

        <?php endif; ?>


        <!-- ==================================================
             FORM
        ================================================== -->

        <form
            method="POST"
            enctype="multipart/form-data"
        >


            <!-- NAME -->

            <div class="form-group">

                <label for="name">

                    Full Name

                </label>


                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?php

                    echo htmlspecialchars(
                        $user["name"]
                    );

                    ?>"
                    required
                >

            </div>


            <!-- EMAIL -->

            <div class="form-group">

                <label for="email">

                    Email Address

                </label>


                <input
                    type="email"
                    id="email"
                    class="email-box"
                    value="<?php

                    echo htmlspecialchars(
                        $user["email"]
                    );

                    ?>"
                    readonly
                >

            </div>


            <!-- EDUCATION -->

            <div class="form-group">

                <label for="education">

                    Higher Education

                </label>


                <input
                    type="text"
                    id="education"
                    name="education"
                    placeholder="Example: MCA Computer Applications"
                    value="<?php

                    echo htmlspecialchars(
                        $user["education"] ?? ""
                    );

                    ?>"
                >

            </div>


            <!-- ==================================================
                 PREVIOUS INTERNSHIP
            ================================================== -->

            <div class="form-group">


                <label>

                    Previous Internship?

                </label>


                <div class="internship-options">


                    <!-- YES -->

                    <label class="radio-option">

                        <input
                            type="radio"
                            name="has_previous_internship"
                            value="Yes"
                            onclick="showInternshipBox()"

                            <?php

                            if (
                                !empty(
                                    $user["previous_internship"]
                                )
                            ) {

                                echo "checked";

                            }

                            ?>
                        >

                        Yes

                    </label>


                    <!-- NO -->

                    <label class="radio-option">

                        <input
                            type="radio"
                            name="has_previous_internship"
                            value="No"
                            onclick="hideInternshipBox()"

                            <?php

                            if (
                                empty(
                                    $user["previous_internship"]
                                )
                            ) {

                                echo "checked";

                            }

                            ?>
                        >

                        No

                    </label>


                </div>


                <!-- ==================================================
                     INTERNSHIP NAME
                     SHOWN ONLY FOR YES
                ================================================== -->

                <div
                    id="internshipNameBox"

                    style="<?php

                    if (
                        empty(
                            $user["previous_internship"]
                        )
                    ) {

                        echo "display:none;";

                    } else {

                        echo "display:block;";

                    }

                    ?>"
                >


                    <input
                        type="text"
                        id="previous_internship"
                        name="previous_internship"
                        placeholder="Enter previous internship name"
                        value="<?php

                        echo htmlspecialchars(
                            $user["previous_internship"] ?? ""
                        );

                        ?>"
                    >


                    <p class="info-text">

                        Enter the name/type of your previous
                        internship.

                    </p>


                </div>


            </div>


            <!-- SKILLS -->

            <div class="form-group">

                <label>

                    Skills

                </label>


                <div class="skills-grid">

                    <?php foreach ($allowed_skills as $skill): ?>

                        <label class="skill-option">

                            <input
                                type="checkbox"
                                name="skills[]"
                                value="<?= htmlspecialchars($skill) ?>"
                                <?= in_array(
                                    $skill,
                                    $current_skills,
                                    true
                                ) ? 'checked' : '' ?>
                            >

                            <span>
                                <?= htmlspecialchars($skill) ?>
                            </span>

                        </label>

                    <?php endforeach; ?>

                </div>

                <p class="info-text">

                    Select only the skills you actually know.
                    These skills are used to calculate your
                    internship Skill Match Percentage.

                </p>

            </div>


            <!-- RESUME -->

            <div class="form-group">

                <label for="resume">

                    Upload Resume

                </label>


                <input
                    type="file"
                    id="resume"
                    name="resume"
                    accept=".pdf,.doc,.docx"
                >


                <p class="resume-info">


                    <?php if (
                        !empty(
                            $user["resume"]
                        )
                    ): ?>


                        Current Resume:

                        <?php

                        echo htmlspecialchars(
                            $user["resume"]
                        );

                        ?>


                    <?php else: ?>


                        No resume uploaded yet.


                    <?php endif; ?>


                </p>

            </div>


            <!-- UPDATE -->

            <button
                type="submit"
                class="update-btn"
            >

                Update Profile

            </button>


        </form>


        <!-- BACK -->

        <a
            href="dashboard.php"
            class="back"
        >

            ← Back to Dashboard

        </a>


    </div>

</div>


<!-- ==================================================
     JAVASCRIPT
================================================== -->

<script>

function showInternshipBox() {

    document.getElementById(
        "internshipNameBox"
    ).style.display = "block";

}


function hideInternshipBox() {

    document.getElementById(
        "internshipNameBox"
    ).style.display = "none";


    /*
     * Clear the internship name
     * when No is selected.
     */

    document.getElementById(
        "previous_internship"
    ).value = "";

}

</script>


</body>

</html>