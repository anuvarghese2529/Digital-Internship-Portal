<?php

session_start();

require_once '../config/db.php';


/* =========================================================
   CHECK STUDENT LOGIN
========================================================= */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'student'
) {
    header("Location: ../login.php");
    exit();
}

$student_id = (int)$_SESSION['user_id'];


/* =========================================================
   GET APPLICATION ID
========================================================= */

$application_id = isset($_GET['application_id'])
    ? (int)$_GET['application_id']
    : 0;


/* =========================================================
   START TEST PARAMETER
========================================================= */

$start_test =
    isset($_GET['start']) &&
    $_GET['start'] === '1';


/* =========================================================
   CAMERA READY PARAMETER
========================================================= */

$camera_ready =
    isset($_GET['camera_ready']) &&
    $_GET['camera_ready'] === '1';


/* =========================================================
   SAVE CAMERA PERMISSION INTO SESSION
========================================================= */

if (
    $camera_ready &&
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    if ($application_id <= 0) {

        header(
            'Content-Type: application/json'
        );

        echo json_encode([
            'success' => false,
            'message' => 'Invalid application.'
        ]);

        exit();
    }


    if (
        !isset(
            $_SESSION['camera_verified']
        ) ||
        !is_array(
            $_SESSION['camera_verified']
        )
    ) {

        $_SESSION['camera_verified'] = [];
    }


    $_SESSION['camera_verified']
        [$application_id] = true;


    header(
        'Content-Type: application/json'
    );


    echo json_encode([
        'success' => true
    ]);


    exit();
}


/* =========================================================
   IF APPLICATION ID IS NOT GIVEN
   SHOW STUDENT APPLICATIONS
========================================================= */

if ($application_id <= 0) {

    $sql = "
        SELECT
            a.id AS application_id,
            i.title,
            i.company_name,
            a.status,

            (
                SELECT tr.percentage
                FROM test_results tr
                WHERE tr.application_id = a.id
                ORDER BY tr.id DESC
                LIMIT 1
            ) AS last_percentage,

            (
                SELECT tr.level
                FROM test_results tr
                WHERE tr.application_id = a.id
                ORDER BY tr.id DESC
                LIMIT 1
            ) AS last_level,

            (
                SELECT tr.passed
                FROM test_results tr
                WHERE tr.application_id = a.id
                ORDER BY tr.id DESC
                LIMIT 1
            ) AS last_passed

        FROM applications a

        INNER JOIN internships i
            ON a.internship_id = i.id

        WHERE a.student_id = ?
          AND a.status != 'Cancelled'

        ORDER BY a.applied_at DESC
    ";


    $stmt =
        mysqli_prepare(
            $conn,
            $sql
        );


    if (!$stmt) {
        die("Database error.");
    }


    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $student_id
    );


    mysqli_stmt_execute($stmt);


    $result =
        mysqli_stmt_get_result(
            $stmt
        );

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
            Mock Test - Digital Internship
        </title>


        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
            rel="stylesheet"
        >

    </head>


    <body>

    <div class="container mt-4 mb-5">


        <a
            href="dashboard.php"
            class="btn btn-secondary mb-4"
        >
            ← Back to Dashboard
        </a>


        <div class="text-center mb-4">

            <h2>
                Mock Test
            </h2>

            <p class="text-muted">
                Select an internship to attend the mock test
            </p>

        </div>


        <?php if (mysqli_num_rows($result) > 0): ?>


            <?php while ($row = mysqli_fetch_assoc($result)): ?>


                <div class="card mb-3 shadow-sm">

                    <div class="card-body">


                        <h5 class="card-title">

                            <?php

                            echo htmlspecialchars(
                                $row['title']
                            );

                            ?>

                        </h5>


                        <p>

                            <strong>
                                Company:
                            </strong>

                            <?php

                            echo htmlspecialchars(
                                $row['company_name']
                            );

                            ?>

                        </p>


                        <p>

                            <strong>
                                Application Status:
                            </strong>

                            <?php

                            echo htmlspecialchars(
                                $row['status']
                            );

                            ?>

                        </p>


                        <?php if ($row['last_passed'] == 1): ?>


                            <div class="alert alert-success">

                                <strong>
                                    Mock Test Completed
                                </strong>

                                <br>

                                Final Level 2 Score:

                                <?php

                                echo htmlspecialchars(
                                    $row['last_percentage']
                                );

                                ?>%

                            </div>


                        <?php elseif ($row['last_level'] !== null): ?>


                            <div class="alert alert-info">

                                <strong>
                                    Current Level:
                                </strong>

                                Level

                                <?php

                                echo htmlspecialchars(
                                    $row['last_level']
                                );

                                ?>

                                <br>

                                Latest Score:

                                <?php

                                echo htmlspecialchars(
                                    $row['last_percentage']
                                );

                                ?>%

                            </div>


                            <a
                                href="mock_test.php?application_id=<?php
                                    echo (int)$row['application_id'];
                                ?>"
                                class="btn btn-warning"
                            >

                                Continue Mock Test

                            </a>


                        <?php else: ?>


                            <a
                                href="mock_test.php?application_id=<?php
                                    echo (int)$row['application_id'];
                                ?>"
                                class="btn btn-primary"
                            >

                                Attend Mock Test

                            </a>


                        <?php endif; ?>


                    </div>

                </div>


            <?php endwhile; ?>


        <?php else: ?>


            <div class="alert alert-info text-center">

                You have not applied for any internship yet.

            </div>


        <?php endif; ?>


    </div>


    </body>

    </html>

    <?php

    exit();
}


/* =========================================================
   VERIFY APPLICATION
========================================================= */

$sql = "

    SELECT
        a.id,
        a.internship_id,
        a.status,
        i.title,
        i.company_name

    FROM applications a

    INNER JOIN internships i
        ON a.internship_id = i.id

    WHERE a.id = ?
      AND a.student_id = ?
      AND a.status != 'Cancelled'

    LIMIT 1

";


$stmt =
    mysqli_prepare(
        $conn,
        $sql
    );


if (!$stmt) {
    die("Database error.");
}


mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $application_id,
    $student_id
);


mysqli_stmt_execute($stmt);


$result =
    mysqli_stmt_get_result(
        $stmt
    );


$application =
    mysqli_fetch_assoc(
        $result
    );


mysqli_stmt_close($stmt);


if (!$application) {
    die("Invalid application.");
}


$internship_id =
    (int)$application['internship_id'];


/* =========================================================
   CHECK REJECTED APPLICATION
========================================================= */

if (
    $application['status'] === 'Rejected'
) {

    die("

        <div style='
            font-family:Arial;
            text-align:center;
            margin-top:100px;
        '>

            <h2 style='color:red;'>
                Mock Test Locked
            </h2>

            <p>
                This application has been rejected.
            </p>

            <a href='dashboard.php'>
                Back to Dashboard
            </a>

        </div>

    ");
}


/* =========================================================
   CHECK FINAL TEST STATUS
   LEVEL 2 PASS = 4/5 OR ABOVE
========================================================= */

$sql = "

    SELECT *
    FROM test_results

    WHERE application_id = ?
      AND user_id = ?
      AND level = 2
      AND passed = 1
      AND score >= 4
      AND total_questions = 5

    ORDER BY id DESC
    LIMIT 1

";


$stmt =
    mysqli_prepare(
        $conn,
        $sql
    );


if (!$stmt) {
    die("Database error.");
}


mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $application_id,
    $student_id
);


mysqli_stmt_execute($stmt);


$final_result =
    mysqli_stmt_get_result(
        $stmt
    );


if (
    mysqli_num_rows(
        $final_result
    ) > 0
) {

    die("

        <div style='
            font-family:Arial;
            text-align:center;
            margin-top:100px;
        '>

            <h2 style='color:green;'>
                Mock Test Already Completed
            </h2>

            <p>
                You have successfully completed Level 2.
            </p>

            <a href='dashboard.php'>
                Back to Dashboard
            </a>

        </div>

    ");
}


/* =========================================================
   GET LAST TEST ATTEMPT
========================================================= */

$sql = "

    SELECT
        id,
        level,
        score,
        total_questions,
        percentage,
        passed,
        question_ids,
        time_limit_seconds,
        time_taken_seconds

    FROM test_results

    WHERE application_id = ?
      AND user_id = ?

    ORDER BY id DESC
    LIMIT 1

";


$stmt =
    mysqli_prepare(
        $conn,
        $sql
    );


if (!$stmt) {
    die("Database error.");
}


mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $application_id,
    $student_id
);


mysqli_stmt_execute($stmt);


$last_result =
    mysqli_stmt_get_result(
        $stmt
    );


$last_attempt =
    mysqli_fetch_assoc(
        $last_result
    );


/* =========================================================
   DETERMINE CURRENT LEVEL
========================================================= */

$current_level = 1;


/* No previous attempt */

if (!$last_attempt) {

    $current_level = 1;
}


/* Last test was Level 1 */

elseif (
    (int)$last_attempt['level'] === 1
) {

    if (
        (int)$last_attempt['score'] >= 8
    ) {

        $current_level = 2;

    } else {

        /*
           Level 1 failed.
           Retry Level 1 with a NEW set of questions.
        */

        $current_level = 1;
    }
}


/* Last test was Level 2 */

elseif (
    (int)$last_attempt['level'] === 2
) {

    if (
        (int)$last_attempt['passed'] === 0
    ) {

        $current_level = 1;
    }
}


/* =========================================================
   TIME SETTINGS
========================================================= */

$level_1_target_seconds =
    7 * 60;


$level_2_base_seconds =
    3 * 60;


$level_2_available_seconds =
    $level_2_base_seconds;


/* =========================================================
   CALCULATE LEVEL 2 TIME
========================================================= */

if ($current_level === 2) {

    $time_sql = "

        SELECT
            time_taken_seconds

        FROM test_results

        WHERE application_id = ?
          AND user_id = ?
          AND level = 1

        ORDER BY id DESC
        LIMIT 1

    ";


    $time_stmt =
        mysqli_prepare(
            $conn,
            $time_sql
        );


    if (!$time_stmt) {
        die(
            "Unable to calculate Level 2 time."
        );
    }


    mysqli_stmt_bind_param(
        $time_stmt,
        "ii",
        $application_id,
        $student_id
    );


    mysqli_stmt_execute(
        $time_stmt
    );


    $time_result =
        mysqli_stmt_get_result(
            $time_stmt
        );


    $level_1_result =
        mysqli_fetch_assoc(
            $time_result
        );


    mysqli_stmt_close(
        $time_stmt
    );


    if (
        $level_1_result &&
        $level_1_result[
            'time_taken_seconds'
        ] !== null
    ) {

        $level_1_time =
            (int)$level_1_result[
                'time_taken_seconds'
            ];


        $level_1_extra_time =
            max(
                0,
                $level_1_time
                - $level_1_target_seconds
            );


        $level_2_available_seconds =
            max(
                0,
                $level_2_base_seconds
                - $level_1_extra_time
            );
    }
}


/* =========================================================
   RULES PAGE
   TIMER DOES NOT START HERE
========================================================= */

if (!$start_test) {

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
            Mock Test Rules
        </title>


        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
            rel="stylesheet"
        >


        <style>

            body {
                background: #f5f7fb;
            }

            .rules-card {
                max-width: 850px;
                margin: 40px auto;
                border: none;
                border-radius: 15px;
            }

            .camera-box {
                background: #111;
                border-radius: 12px;
                padding: 10px;
            }

            #permissionVideo {
                width: 100%;
                max-height: 280px;
                object-fit: cover;
                border-radius: 10px;
                background: #000;
            }

        </style>

    </head>


    <body>


    <div class="container">


        <div class="card shadow rules-card">


            <div class="card-body p-4 p-md-5">


                <div class="text-center mb-4">

                    <h2>
                        Mock Test Rules & Instructions
                    </h2>

                    <p class="text-muted">
                        Please read all rules carefully before starting.
                    </p>

                </div>


                <!-- INTERNSHIP -->

                <div class="alert alert-primary">

                    <strong>
                        Internship:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $application['title']
                    );

                    ?>

                    <br>

                    <strong>
                        Company:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $application['company_name']
                    );

                    ?>

                </div>


                <!-- LEVEL 1 -->

                <div class="card mb-4 border-primary">

                    <div class="card-body">

                        <h4 class="text-primary">
                            Level 1
                        </h4>

                        <ul>

                            <li>
                                10 questions.
                            </li>

                            <li>
                                Target time:
                                <strong>
                                    7 minutes
                                </strong>.
                            </li>

                            <li>
                                You may continue after
                                7 minutes.
                            </li>

                            <li>
                                Extra time after 7 minutes
                                will be deducted from Level 2.
                            </li>

                            <li>
                                Minimum score:
                                <strong>
                                    8/10
                                </strong>.
                            </li>

                            <li>
                                Below 8/10 means Level 1
                                must be attempted again.
                            </li>

                            <li>
                                When a level is attempted again,
                                a new set of questions will be provided.
                            </li>

                        </ul>

                    </div>

                </div>


                <!-- LEVEL 2 -->

                <div class="card mb-4 border-warning">

                    <div class="card-body">

                        <h4 class="text-warning">
                            Level 2
                        </h4>

                        <ul>

                            <li>
                                5 questions.
                            </li>

                            <li>
                                Base time:
                                <strong>
                                    3 minutes
                                </strong>.
                            </li>

                            <li>
                                Level 1 extra time is
                                deducted from these 3 minutes.
                            </li>

                            <li>
                                The remaining Level 2 time
                                is strict.
                            </li>

                            <li>
                                At 0 seconds, the test is
                                automatically submitted.
                            </li>

                            <li>
                                Required score:
                                <strong>
                                    4/5 or above
                                </strong>.
                            </li>

                            <li>
                                Below 4/5 means restart from
                                Level 1 with a new question set.
                            </li>

                        </ul>

                    </div>

                </div>


                <!-- ATTEMPTS -->

                <div class="card mb-4 border-danger">

                    <div class="card-body">

                        <h4 class="text-danger">
                            Attempts
                        </h4>

                        <ul>

                            <li>
                                Maximum 5 complete attempts.
                            </li>

                            <li>
                                Every attempt starts from
                                Level 1.
                            </li>

                            <li>
                                If final Level 2 is not passed
                                within 5 attempts, the
                                application will be rejected.
                            </li>

                        </ul>

                    </div>

                </div>


                <!-- WEBCAM -->

                <div class="card mb-4 border-dark">

                    <div class="card-body">

                        <h4>
                            📷 Webcam Verification
                        </h4>


                        <p>
                            Webcam access is mandatory
                            to attend the mock test.
                        </p>


                        <p class="text-muted">
                            Please click
                            <strong>
                                Allow
                            </strong>
                            when your browser asks for
                            camera permission.
                        </p>


                        <div
                            id="permissionMessage"
                            class="alert alert-warning"
                        >

                            Camera permission is required
                            before you can start the test.

                        </div>


                        <div
                            id="permissionSuccess"
                            class="alert alert-success"
                            style="display:none;"
                        >

                            ✅ Camera access granted.
                            You can start the test.

                        </div>


                        <button
                            type="button"
                            id="enableCameraButton"
                            class="btn btn-dark"
                        >

                            📷 Allow Webcam Access

                        </button>


                        <div
                            class="camera-box mt-3"
                            id="permissionCameraBox"
                            style="display:none;"
                        >

                            <video
                                id="permissionVideo"
                                autoplay
                                muted
                                playsinline
                            ></video>

                        </div>

                    </div>

                </div>


                <!-- BUTTONS -->

                <div class="text-center mt-4">


                    <a
                        href="dashboard.php"
                        class="btn btn-secondary me-2"
                    >
                        Cancel
                    </a>


                    <a
                        href="mock_test.php?application_id=<?php
                        echo $application_id;
                        ?>&start=1"
                        id="startTestButton"
                        class="btn btn-success btn-lg disabled"
                        aria-disabled="true"
                    >

                        Start Level
                        <?php
                        echo $current_level;
                        ?>

                    </a>

                </div>


            </div>

        </div>

    </div>


    <script>

    const enableCameraButton =
        document.getElementById(
            "enableCameraButton"
        );


    const startTestButton =
        document.getElementById(
            "startTestButton"
        );


    const permissionMessage =
        document.getElementById(
            "permissionMessage"
        );


    const permissionSuccess =
        document.getElementById(
            "permissionSuccess"
        );


    const permissionVideo =
        document.getElementById(
            "permissionVideo"
        );


    const permissionCameraBox =
        document.getElementById(
            "permissionCameraBox"
        );


    let permissionStream = null;


    enableCameraButton.addEventListener(
        "click",
        async function () {

            try {

                permissionStream =
                    await navigator.mediaDevices.getUserMedia({
                        video: {
                            width: {
                                ideal: 640
                            },
                            height: {
                                ideal: 480
                            },
                            facingMode: "user"
                        },
                        audio: false
                    });


                permissionVideo.srcObject =
                    permissionStream;


                permissionCameraBox.style.display =
                    "block";


                permissionMessage.style.display =
                    "none";


                permissionSuccess.style.display =
                    "block";


                enableCameraButton.textContent =
                    "✅ Webcam Enabled";


                enableCameraButton.disabled =
                    true;


                const response =
                    await fetch(
                        "mock_test.php?application_id=<?php
                        echo $application_id;
                        ?>&camera_ready=1",
                        {
                            method: "POST"
                        }
                    );


                const data =
                    await response.json();


                if (data.success) {

                    startTestButton.classList.remove(
                        "disabled"
                    );


                    startTestButton.removeAttribute(
                        "aria-disabled"
                    );


                } else {

                    permissionSuccess.classList.remove(
                        "alert-success"
                    );


                    permissionSuccess.classList.add(
                        "alert-danger"
                    );


                    permissionSuccess.textContent =
                        "Unable to verify camera access.";
                }


            }
            catch (error) {

                permissionMessage.classList.remove(
                    "alert-warning"
                );


                permissionMessage.classList.add(
                    "alert-danger"
                );


                permissionMessage.innerHTML =
                    "<strong>Webcam access denied.</strong><br>" +
                    "You must allow camera access to attend the mock test.";


                startTestButton.classList.add(
                    "disabled"
                );


                startTestButton.setAttribute(
                    "aria-disabled",
                    "true"
                );

            }

        }
    );


    startTestButton.addEventListener(
        "click",
        function (event) {

            if (
                startTestButton.classList.contains(
                    "disabled"
                )
            ) {

                event.preventDefault();

                alert(
                    "Please allow webcam access before starting the mock test."
                );

                return;
            }


            if (permissionStream) {

                permissionStream
                    .getTracks()
                    .forEach(
                        track => track.stop()
                    );
            }

        }
    );

    </script>


    </body>

    </html>

    <?php

    exit();
}


/* =========================================================
   CHECK CAMERA WAS VERIFIED
========================================================= */

if (
    !isset(
        $_SESSION['camera_verified']
    ) ||
    !isset(
        $_SESSION['camera_verified']
            [$application_id]
    )
) {

    die("

        <div style='
            font-family:Arial;
            text-align:center;
            margin-top:100px;
        '>

            <h2 style='color:red;'>
                Webcam Access Required
            </h2>

            <p>
                You must allow webcam access
                before attending the mock test.
            </p>

            <a href='mock_test.php?application_id=<?php
                echo $application_id;
            ?>'>
                Go Back to Rules
            </a>

        </div>

    ");
}


/* =========================================================
   GET QUESTIONS

   IMPORTANT:
   Every time the same level is attempted again,
   previously used questions for that level are excluded.

   Level 1 -> 10 questions
   Level 2 -> 5 questions
========================================================= */

$questions = [];

$required_questions =
    ($current_level === 1)
    ? 10
    : 5;


/* ---------------------------------------------------------
   KEEP THE CURRENT IN-PROGRESS QUESTION SET ON REFRESH
--------------------------------------------------------- */

$active_question_ids = [];
$use_active_question_set =
    $start_test &&
    isset($_SESSION['mock_test_questions']) &&
    isset($_SESSION['mock_test_application_id']) &&
    isset($_SESSION['mock_test_level']) &&
    isset($_SESSION['mock_test_started_at']) &&
    (int)$_SESSION['mock_test_application_id'] === $application_id &&
    (int)$_SESSION['mock_test_level'] === $current_level;

if ($use_active_question_set) {

    $active_question_ids = array_values(
        array_unique(
            array_map(
                'intval',
                $_SESSION['mock_test_questions']
            )
        )
    );

    if (count($active_question_ids) !== $required_questions) {
        $use_active_question_set = false;
    }
}


/* ---------------------------------------------------------
   GET QUESTION IDS ALREADY USED FOR THIS LEVEL
--------------------------------------------------------- */

$used_question_ids = [];


if (!$use_active_question_set) {

$used_sql = "
    SELECT question_ids
    FROM test_results
    WHERE application_id = ?
      AND user_id = ?
      AND level = ?
      AND question_ids IS NOT NULL
      AND question_ids <> ''
";

$used_stmt = mysqli_prepare(
    $conn,
    $used_sql
);

if (!$used_stmt) {
    die("Unable to check previous test questions.");
}

mysqli_stmt_bind_param(
    $used_stmt,
    "iii",
    $application_id,
    $student_id,
    $current_level
);

mysqli_stmt_execute($used_stmt);

$used_result = mysqli_stmt_get_result($used_stmt);

while ($used_row = mysqli_fetch_assoc($used_result)) {

    $stored_ids = array_map(
        'intval',
        explode(',', (string)$used_row['question_ids'])
    );

    foreach ($stored_ids as $stored_id) {

        if ($stored_id > 0) {
            $used_question_ids[] = $stored_id;
        }
    }
}

mysqli_stmt_close($used_stmt);

$used_question_ids = array_values(
    array_unique($used_question_ids)
);


/* ---------------------------------------------------------
   BUILD EXCLUSION CLAUSE
--------------------------------------------------------- */

$exclude_sql = '';

if (count($used_question_ids) > 0) {

    $safe_used_ids = implode(
        ',',
        array_map('intval', $used_question_ids)
    );

    $exclude_sql =
        " AND id NOT IN ($safe_used_ids) ";
}


/* ---------------------------------------------------------
   GET A NEW RANDOM QUESTION SET
--------------------------------------------------------- */

$sql = "
    SELECT
        id,
        question,
        option_a,
        option_b,
        option_c,
        option_d

    FROM questions

    WHERE internship_id = ?
    $exclude_sql

    ORDER BY RAND()

    LIMIT $required_questions
";

$stmt = mysqli_prepare(
    $conn,
    $sql
);

if (!$stmt) {
    die("Database error while loading questions.");
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $internship_id
);

mysqli_stmt_execute($stmt);

$questions_result =
    mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($questions_result)) {
    $questions[] = $row;
}

mysqli_stmt_close($stmt);


/* ---------------------------------------------------------
   SAFETY FALLBACK
   If all unused questions are exhausted, allow random
   questions again so the mock test never gets stuck.
--------------------------------------------------------- */

if (count($questions) < $required_questions) {

    $sql = "
        SELECT
            id,
            question,
            option_a,
            option_b,
            option_c,
            option_d

        FROM questions

        WHERE internship_id = ?

        ORDER BY RAND()

        LIMIT $required_questions
    ";

    $stmt = mysqli_prepare(
        $conn,
        $sql
    );

    if (!$stmt) {
        die("Database error while loading fallback questions.");
    }

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $internship_id
    );

    mysqli_stmt_execute($stmt);

    $questions_result =
        mysqli_stmt_get_result($stmt);

    $questions = [];

    while ($row = mysqli_fetch_assoc($questions_result)) {
        $questions[] = $row;
    }

    mysqli_stmt_close($stmt);
}

} // End: !$use_active_question_set


/* ---------------------------------------------------------
   LOAD ACTIVE QUESTIONS WHEN THE TEST PAGE IS REFRESHED
--------------------------------------------------------- */

if ($use_active_question_set) {

    $safe_active_ids = implode(
        ',',
        array_map('intval', $active_question_ids)
    );

    $sql = "
        SELECT
            id,
            question,
            option_a,
            option_b,
            option_c,
            option_d

        FROM questions

        WHERE internship_id = ?
          AND id IN ($safe_active_ids)

        ORDER BY FIELD(
            id,
            $safe_active_ids
        )
    ";

    $stmt = mysqli_prepare(
        $conn,
        $sql
    );

    if (!$stmt) {
        die("Database error while restoring the current test.");
    }

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $internship_id
    );

    mysqli_stmt_execute($stmt);

    $questions_result = mysqli_stmt_get_result($stmt);

    $questions = [];

    while ($row = mysqli_fetch_assoc($questions_result)) {
        $questions[] = $row;
    }

    mysqli_stmt_close($stmt);
}


/* =========================================================
   CHECK QUESTION COUNT
========================================================= */

$required_questions =
    ($current_level === 1)
    ? 10
    : 5;


if (
    count($questions) <
    $required_questions
) {

    die(
        "Not enough questions are available for this internship."
    );
}


/* =========================================================
   STORE TEST INFORMATION
========================================================= */

$_SESSION['mock_test_questions'] =
    array_column(
        $questions,
        'id'
    );


$_SESSION['mock_test_application_id'] =
    $application_id;


$_SESSION['mock_test_level'] =
    $current_level;


/* =========================================================
   CREATE ATTEMPT TOKEN
========================================================= */

$question_token =
    implode(
        ',',
        array_column(
            $questions,
            'id'
        )
    );


$last_result_id =
    $last_attempt
    ? (int)$last_attempt['id']
    : 0;


$attempt_token =
    $application_id
    . '_'
    . $current_level
    . '_'
    . $last_result_id
    . '_'
    . md5(
        $question_token
    );


/* =========================================================
   START TIMER NOW
========================================================= */

if (
    !isset(
        $_SESSION['mock_test_attempt_token']
    ) ||
    $_SESSION['mock_test_attempt_token']
    !== $attempt_token
) {

    $_SESSION['mock_test_attempt_token'] =
        $attempt_token;


    $_SESSION['mock_test_started_at'] =
        time();
}


$test_started_at =
    (int)$_SESSION['mock_test_started_at'];


/* =========================================================
   HTML TEST PAGE
========================================================= */

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
        Mock Test
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <style>

        body {
            background: #f5f7fb;
        }


        #timerBox {
            position: sticky;
            top: 10px;
            z-index: 1000;
        }


        #timer {
            font-size: 28px;
            font-weight: bold;
        }


        .timer-warning {
            color: red !important;
        }


        .timer-danger {
            animation: pulse 1s infinite;
        }


        @keyframes pulse {

            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }

            100% {
                opacity: 1;
            }

        }


        #cameraBox {
            background: #111;
            border-radius: 12px;
            padding: 8px;
        }


        #webcamPreview {
            width: 100%;
            max-height: 280px;
            object-fit: cover;
            border-radius: 8px;
            background: #000;
        }


        #cameraStatus {
            font-weight: bold;
        }


        #cameraBlocking {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.92);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }


        #cameraBlocking .card {
            max-width: 500px;
            width: 100%;
        }

    </style>

</head>


<body>


<!-- ======================================================
     CAMERA BLOCKING OVERLAY
======================================================= -->

<div id="cameraBlocking">

    <div class="card shadow-lg">

        <div class="card-body text-center p-4">

            <h3 class="text-danger">
                📷 Webcam Access Required
            </h3>

            <p class="mt-3">
                Your webcam must be enabled to attend
                this mock test.
            </p>


            <button
                type="button"
                id="allowCameraButton"
                class="btn btn-primary btn-lg"
            >

                Allow Webcam

            </button>


            <p
                id="cameraError"
                class="text-danger mt-3"
            ></p>

        </div>

    </div>

</div>


<div class="container mt-4 mb-5">


    <a
        href="dashboard.php"
        class="btn btn-secondary mb-4"
    >

        ← Back to Dashboard

    </a>


    <!-- TITLE -->

    <div class="text-center mb-4">

        <h2>
            Mock Test
        </h2>

        <p class="text-muted">
            Digital Internship Portal
        </p>

    </div>


    <!-- INTERNSHIP -->

    <div class="card mb-4 shadow-sm">

        <div class="card-body">

            <h5>

                Internship:

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $application['title']
                    );

                    ?>

                </strong>

            </h5>


            <p class="mb-0">

                <strong>
                    Company:
                </strong>

                <?php

                echo htmlspecialchars(
                    $application['company_name']
                );

                ?>

            </p>

        </div>

    </div>


    <!-- WEBCAM -->

    <div class="card mb-4 shadow-sm">

        <div class="card-body">


            <div
                class="d-flex justify-content-between align-items-center mb-2"
            >

                <h5 class="mb-0">
                    📷 Webcam
                </h5>


                <span
                    id="cameraStatus"
                    class="badge bg-secondary"
                >
                    Checking...
                </span>

            </div>


            <div id="cameraBox">

                <video
                    id="webcamPreview"
                    autoplay
                    muted
                    playsinline
                ></video>

            </div>


            <div class="text-muted small mt-2">

                Your webcam video is being recorded
                during the mock test.

            </div>


        </div>

    </div>


    <!-- TIMER -->

    <div
        id="timerBox"
        class="alert alert-warning text-center shadow-sm"
    >

        <?php if ($current_level === 1): ?>

            <strong>
                Level 1 Target Time
            </strong>

            <div id="timer">
                07:00
            </div>


            <div
                id="extraTimeMessage"
                style="display:none;"
            >

                Extra Time Used:

                <strong id="extraTime">
                    00:00
                </strong>

                <br>

                Extra time will be deducted
                from Level 2.

            </div>


        <?php else: ?>

            <strong>
                Level 2 Remaining Time
            </strong>


            <div id="timer">

                <?php

                printf(
                    "%02d:%02d",
                    floor(
                        $level_2_available_seconds / 60
                    ),
                    $level_2_available_seconds % 60
                );

                ?>

            </div>


        <?php endif; ?>

    </div>


    <!-- LEVEL -->

    <div
        class="alert alert-primary text-center"
    >

        <h4>

            Level
            <?php
            echo $current_level;
            ?>

        </h4>


        <?php if ($current_level === 1): ?>

            <p class="mb-0">

                10 Questions

                <br>

                Minimum Score:
                <strong>
                    8/10
                </strong>

            </p>


        <?php else: ?>

            <p class="mb-0">

                5 Questions

                <br>

                Required:
                <strong>
                    4/5
                </strong>

            </p>


        <?php endif; ?>

    </div>


    <!-- TEST FORM -->

    <form
        method="POST"
        action="test_result.php"
        id="mockTestForm"
    >


        <?php

        $number = 1;

        ?>


        <?php foreach ($questions as $q): ?>


            <div
                class="card mb-4 shadow-sm"
            >

                <div class="card-body">


                    <h5 class="mb-3">

                        <?php

                        echo $number . ". ";

                        echo htmlspecialchars(
                            $q['question']
                        );

                        ?>

                    </h5>


                    <!-- OPTION A -->

                    <div class="form-check mb-2">

                        <input
                            class="form-check-input"
                            type="radio"
                            name="answer[<?php
                            echo (int)$q['id'];
                            ?>]"
                            value="A"
                            required
                        >

                        <label class="form-check-label">

                            <strong>
                                A.
                            </strong>

                            <?php

                            echo htmlspecialchars(
                                $q['option_a']
                            );

                            ?>

                        </label>

                    </div>


                    <!-- OPTION B -->

                    <div class="form-check mb-2">

                        <input
                            class="form-check-input"
                            type="radio"
                            name="answer[<?php
                            echo (int)$q['id'];
                            ?>]"
                            value="B"
                            required
                        >

                        <label class="form-check-label">

                            <strong>
                                B.
                            </strong>

                            <?php

                            echo htmlspecialchars(
                                $q['option_b']
                            );

                            ?>

                        </label>

                    </div>


                    <!-- OPTION C -->

                    <div class="form-check mb-2">

                        <input
                            class="form-check-input"
                            type="radio"
                            name="answer[<?php
                            echo (int)$q['id'];
                            ?>]"
                            value="C"
                            required
                        >

                        <label class="form-check-label">

                            <strong>
                                C.
                            </strong>

                            <?php

                            echo htmlspecialchars(
                                $q['option_c']
                            );

                            ?>

                        </label>

                    </div>


                    <!-- OPTION D -->

                    <div class="form-check">

                        <input
                            class="form-check-input"
                            type="radio"
                            name="answer[<?php
                            echo (int)$q['id'];
                            ?>]"
                            value="D"
                            required
                        >

                        <label class="form-check-label">

                            <strong>
                                D.
                            </strong>

                            <?php

                            echo htmlspecialchars(
                                $q['option_d']
                            );

                            ?>

                        </label>

                    </div>


                </div>

            </div>


            <?php

            $number++;

            ?>


        <?php endforeach; ?>


        <div class="text-center mt-4">

            <button
                type="submit"
                class="btn btn-success btn-lg px-5"
                id="submitTestButton"
            >

                Submit Level
                <?php
                echo $current_level;
                ?>

            </button>

        </div>


    </form>


</div>


<script>


/* =========================================================
   CAMERA + RECORDING
========================================================= */

const cameraBlocking =
    document.getElementById(
        "cameraBlocking"
    );


const allowCameraButton =
    document.getElementById(
        "allowCameraButton"
    );


const cameraError =
    document.getElementById(
        "cameraError"
    );


const webcamPreview =
    document.getElementById(
        "webcamPreview"
    );


const cameraStatus =
    document.getElementById(
        "cameraStatus"
    );


const form =
    document.getElementById(
        "mockTestForm"
    );


let cameraStream = null;

let mediaRecorder = null;

let recordedChunks = [];

let recordingStarted = false;

let submissionStarted = false;


/* =========================================================
   START CAMERA
========================================================= */

async function startCameraAndRecording() {

    try {

        if (
            !navigator.mediaDevices ||
            !navigator.mediaDevices.getUserMedia
        ) {

            throw new Error(
                "Your browser does not support webcam access."
            );
        }


        cameraStream =
            await navigator.mediaDevices.getUserMedia({

                video: {
                    width: {
                        ideal: 640
                    },

                    height: {
                        ideal: 480
                    },

                    frameRate: {
                        ideal: 20
                    },

                    facingMode: "user"
                },

                audio: false

            });


        webcamPreview.srcObject =
            cameraStream;


        if (
            !window.MediaRecorder
        ) {

            throw new Error(
                "Your browser does not support video recording."
            );
        }


        let recorderOptions = {};


        if (
            MediaRecorder.isTypeSupported(
                "video/webm;codecs=vp9"
            )
        ) {

            recorderOptions.mimeType =
                "video/webm;codecs=vp9";

        }
        else if (
            MediaRecorder.isTypeSupported(
                "video/webm"
            )
        ) {

            recorderOptions.mimeType =
                "video/webm";
        }


        recorderOptions.videoBitsPerSecond =
            250000;


        mediaRecorder =
            new MediaRecorder(
                cameraStream,
                recorderOptions
            );


        recordedChunks = [];


        mediaRecorder.ondataavailable =
            function(event) {

                if (
                    event.data &&
                    event.data.size > 0
                ) {

                    recordedChunks.push(
                        event.data
                    );
                }

            };


        mediaRecorder.start(1000);


        recordingStarted = true;


        cameraStatus.textContent =
            "● Recording";


        cameraStatus.className =
            "badge bg-danger";


        cameraBlocking.style.display =
            "none";

    }

    catch (error) {

        cameraError.textContent =
            error.message ||
            "Camera access is required.";


        cameraStatus.textContent =
            "Camera Required";


        cameraStatus.className =
            "badge bg-danger";

    }

}


/* =========================================================
   ALLOW CAMERA BUTTON
========================================================= */

allowCameraButton.addEventListener(
    "click",
    function() {

        startCameraAndRecording();

    }
);


/* =========================================================
   STOP RECORDING
========================================================= */

function stopRecording() {

    return new Promise(
        function(resolve) {

            if (
                !mediaRecorder ||
                mediaRecorder.state === "inactive"
            ) {

                resolve();

                return;
            }


            mediaRecorder.onstop =
                function() {

                    resolve();

                };


            mediaRecorder.stop();

        }
    );

}


/* =========================================================
   SUBMIT TEST WITH VIDEO
========================================================= */

async function submitTestWithVideo() {

    if (submissionStarted) {
        return;
    }


    submissionStarted = true;


    const submitButton =
        document.getElementById(
            "submitTestButton"
        );


    if (submitButton) {

        submitButton.disabled = true;

        submitButton.textContent =
            "Submitting...";

    }


    await stopRecording();


    if (cameraStream) {

        cameraStream
            .getTracks()
            .forEach(
                function(track) {

                    track.stop();

                }
            );

    }


    const formData =
        new FormData(form);


    if (recordedChunks.length > 0) {

        const videoBlob =
            new Blob(
                recordedChunks,
                {
                    type:
                        mediaRecorder &&
                        mediaRecorder.mimeType
                            ? mediaRecorder.mimeType
                            : "video/webm"
                }
            );


        formData.append(
            "webcam_video",
            videoBlob,
            "mock_test_video.webm"
        );

    }


    formData.append(
        "webcam_recorded",
        recordingStarted
            ? "1"
            : "0"
    );


    try {

        const response =
            await fetch(
                form.action,
                {
                    method: "POST",
                    body: formData
                }
            );


        const html =
            await response.text();


        document.open();

        document.write(html);

        document.close();

    }

    catch (error) {

        alert(
            "Unable to submit the test. Please try again."
        );


        submissionStarted =
            false;


        if (submitButton) {

            submitButton.disabled =
                false;


            submitButton.textContent =
                "Submit Level " +
                <?php echo $current_level; ?>;

        }


        startCameraAndRecording();

    }

}


/* =========================================================
   MANUAL SUBMISSION
========================================================= */

form.addEventListener(
    "submit",
    function(event) {

        event.preventDefault();


        if (!recordingStarted) {

            alert(
                "Webcam recording is required to submit the test."
            );

            return;
        }


        submitTestWithVideo();

    }
);


/* =========================================================
   TIMER
========================================================= */

const currentLevel =
    <?php echo $current_level; ?>;


const startedAt =
    <?php echo $test_started_at; ?> * 1000;


const timerElement =
    document.getElementById(
        "timer"
    );


/* =========================================================
   LEVEL 1
   7 MINUTES TARGET
   EXTRA TIME ALLOWED
========================================================= */

if (currentLevel === 1) {

    const targetSeconds =
        7 * 60;


    function updateLevel1Timer() {

        if (submissionStarted) {
            return;
        }


        const now =
            Math.floor(
                Date.now() / 1000
            );


        const start =
            Math.floor(
                startedAt / 1000
            );


        const elapsed =
            Math.max(
                0,
                now - start
            );


        if (
            elapsed <=
            targetSeconds
        ) {

            const remaining =
                targetSeconds -
                elapsed;


            const minutes =
                Math.floor(
                    remaining / 60
                );


            const seconds =
                remaining % 60;


            timerElement.textContent =
                String(minutes)
                    .padStart(2, "0")
                + ":"
                + String(seconds)
                    .padStart(2, "0");


        }

        else {

            const extra =
                elapsed -
                targetSeconds;


            const extraMinutes =
                Math.floor(
                    extra / 60
                );


            const extraSeconds =
                extra % 60;


            timerElement.textContent =
                "07:00 + Extra";


            timerElement.classList.add(
                "timer-warning"
            );


            const extraMessage =
                document.getElementById(
                    "extraTimeMessage"
                );


            const extraTime =
                document.getElementById(
                    "extraTime"
                );


            if (extraMessage) {

                extraMessage.style.display =
                    "block";

            }


            if (extraTime) {

                extraTime.textContent =
                    String(
                        extraMinutes
                    ).padStart(
                        2,
                        "0"
                    )
                    + ":"
                    + String(
                        extraSeconds
                    ).padStart(
                        2,
                        "0"
                    );

            }

        }

    }


    updateLevel1Timer();


    setInterval(
        updateLevel1Timer,
        1000
    );

}


/* =========================================================
   LEVEL 2
   STRICT REMAINING TIME
========================================================= */

else {

    const allowedSeconds =
        <?php echo (int)$level_2_available_seconds; ?>;


    let submitted =
        false;


    function updateLevel2Timer() {

        if (
            submitted ||
            submissionStarted
        ) {

            return;
        }


        const now =
            Math.floor(
                Date.now() / 1000
            );


        const start =
            Math.floor(
                startedAt / 1000
            );


        const elapsed =
            Math.max(
                0,
                now - start
            );


        const remaining =
            Math.max(
                0,
                allowedSeconds -
                elapsed
            );


        const minutes =
            Math.floor(
                remaining / 60
            );


        const seconds =
            remaining % 60;


        timerElement.textContent =
            String(minutes)
                .padStart(2, "0")
            + ":"
            + String(seconds)
                .padStart(2, "0");


        if (remaining <= 30) {

            timerElement.classList.add(
                "timer-warning"
            );

        }


        if (remaining <= 10) {

            timerElement.classList.add(
                "timer-danger"
            );

        }


        /* AUTO SUBMIT AT ZERO */

        if (remaining <= 0) {

            submitted = true;

            submitTestWithVideo();

        }

    }


    updateLevel2Timer();


    setInterval(
        updateLevel2Timer,
        1000
    );

}

</script>


</body>

</html>