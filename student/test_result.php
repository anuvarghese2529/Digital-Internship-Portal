<?php

session_start();

require_once "../config/db.php";


/* =========================================================
   CHECK STUDENT LOGIN
========================================================= */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "student"
) {
    header("Location: ../login.php");
    exit();
}

$student_id = (int)$_SESSION["user_id"];


/* =========================================================
   CHECK TEST SESSION
========================================================= */

if (
    !isset($_SESSION["mock_test_questions"]) ||
    !isset($_SESSION["mock_test_application_id"]) ||
    !isset($_SESSION["mock_test_level"]) ||
    !isset($_SESSION["mock_test_started_at"])
) {
    die("Test session expired. Please start the test again.");
}


$question_ids =
    $_SESSION["mock_test_questions"];

$application_id =
    (int)$_SESSION["mock_test_application_id"];

$level =
    (int)$_SESSION["mock_test_level"];

$test_started_at =
    (int)$_SESSION["mock_test_started_at"];


/* =========================================================
   VALID LEVEL
========================================================= */

if ($level !== 1 && $level !== 2) {
    die("Invalid test level.");
}


/* =========================================================
   EXPECTED QUESTION COUNT
========================================================= */

$expected_questions =
    ($level === 1) ? 10 : 5;


if (count($question_ids) !== $expected_questions) {
    die("Invalid test questions.");
}


/* =========================================================
   CHECK ANSWERS
========================================================= */

/*
   Level 2 may be automatically submitted when
   the timer reaches zero.

   Therefore empty answers are allowed.
*/

$answers = $_POST["answer"] ?? [];

if (!is_array($answers)) {
    $answers = [];
}


/* =========================================================
   CHECK WEBCAM RECORDING
========================================================= */

$webcam_recorded =
    isset($_POST["webcam_recorded"]) &&
    $_POST["webcam_recorded"] === "1";


if (
    !$webcam_recorded ||
    !isset($_FILES["webcam_video"]) ||
    $_FILES["webcam_video"]["error"] !== UPLOAD_ERR_OK
) {

    die(
        "Webcam recording is required to submit the mock test. " .
        "Please allow webcam access and try again."
    );
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


$stmt = mysqli_prepare(
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
    mysqli_stmt_get_result($stmt);


$application =
    mysqli_fetch_assoc($result);


mysqli_stmt_close($stmt);


if (!$application) {
    die("Invalid application.");
}


/* =========================================================
   IF APPLICATION ALREADY REJECTED
========================================================= */

if ($application["status"] === "Rejected") {

    die(
        "This application has been rejected. " .
        "The mock test is permanently locked."
    );
}


/* =========================================================
   MAKE QUESTION ID LIST SAFE
========================================================= */

$question_ids = array_map(
    "intval",
    $question_ids
);


$question_ids = array_values(
    array_unique($question_ids)
);


if (count($question_ids) !== $expected_questions) {
    die("Invalid question list.");
}


/* =========================================================
   CALCULATE TIME LIMIT
========================================================= */

/*
   LEVEL 1 = 7 MINUTES
*/

$level_1_time_limit_seconds =
    7 * 60;


/*
   LEVEL 2 BASE = 3 MINUTES
*/

$level_2_base_seconds =
    3 * 60;


/*
   CURRENT TEST TIME LIMIT
*/

if ($level === 1) {

    $time_limit_seconds =
        $level_1_time_limit_seconds;
}


/*
   LEVEL 2
   Extra Level 1 time is deducted from
   the 3-minute Level 2 time.
*/

else {

    $level_1_time_taken = 0;


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


    $time_row =
        mysqli_fetch_assoc(
            $time_result
        );


    mysqli_stmt_close(
        $time_stmt
    );


    if (
        $time_row &&
        $time_row["time_taken_seconds"] !== null
    ) {

        $level_1_time_taken =
            max(
                0,
                (int)$time_row["time_taken_seconds"]
            );
    }


    /*
       Extra time =
       Actual Level 1 time - 7 minutes
    */

    $level_1_extra_time =
        max(
            0,
            $level_1_time_taken
            - $level_1_time_limit_seconds
        );


    /*
       Remaining Level 2 time
    */

    $time_limit_seconds =
        max(
            0,
            $level_2_base_seconds
            - $level_1_extra_time
        );
}


/* =========================================================
   CALCULATE ACTUAL TIME TAKEN
========================================================= */

$current_time =
    time();


$elapsed_seconds =
    max(
        0,
        $current_time - $test_started_at
    );


/*
   LEVEL 1
   No strict upper limit.
*/

if ($level === 1) {

    $time_taken_seconds =
        $elapsed_seconds;
}


/*
   LEVEL 2
   Strict remaining time.
*/

else {

    $time_taken_seconds =
        min(
            $elapsed_seconds,
            $time_limit_seconds
        );
}


/*
   SAFETY
*/

$time_taken_seconds =
    max(
        0,
        (int)$time_taken_seconds
    );


$time_limit_seconds =
    max(
        0,
        (int)$time_limit_seconds
    );


/* =========================================================
   CHECK PREVIOUS TEST HISTORY
========================================================= */

$history_sql = "
    SELECT
        level,
        score,
        total_questions,
        passed

    FROM test_results

    WHERE application_id = ?
      AND user_id = ?

    ORDER BY created_at ASC, id ASC
";


$history_stmt =
    mysqli_prepare(
        $conn,
        $history_sql
    );


if (!$history_stmt) {

    die(
        "Unable to check test history."
    );
}


mysqli_stmt_bind_param(
    $history_stmt,
    "ii",
    $application_id,
    $student_id
);


mysqli_stmt_execute(
    $history_stmt
);


$history_result =
    mysqli_stmt_get_result(
        $history_stmt
    );


$level1_attempts = 0;

$final_level2_passed = false;


while (
    $history_row =
    mysqli_fetch_assoc(
        $history_result
    )
) {

    $history_level =
        (int)$history_row["level"];

    $history_score =
        (int)$history_row["score"];

    $history_total =
        (int)$history_row["total_questions"];

    $history_passed =
        (int)$history_row["passed"];


    /*
       COUNT ONLY LEVEL 1 AS ATTEMPTS
    */

    if ($history_level === 1) {

        $level1_attempts++;
    }


    /*
       LEVEL 2 PASS:
       4/5 OR 5/5
    */

    if (
        $history_level === 2 &&
        $history_passed === 1 &&
        $history_score >= 4 &&
        $history_total === 5
    ) {

        $final_level2_passed = true;
    }
}


mysqli_stmt_close(
    $history_stmt
);


/* =========================================================
   IF FINAL TEST ALREADY PASSED
========================================================= */

if ($final_level2_passed) {

    die(
        "You have already completed the mock test successfully."
    );
}


/* =========================================================
   MAXIMUM ATTEMPT CHECK
========================================================= */

if (
    $level === 1 &&
    $level1_attempts >= 5
) {

    die(
        "Maximum 5 mock test attempts have already been completed. " .
        "Your application is locked."
    );
}


/* =========================================================
   GET CORRECT ANSWERS
========================================================= */

$id_list =
    implode(
        ",",
        $question_ids
    );


$sql = "
    SELECT
        id,
        question,
        option_a,
        option_b,
        option_c,
        option_d,
        correct_answer

    FROM questions

    WHERE id IN ($id_list)
      AND internship_id = ?

    ORDER BY FIELD(
        id,
        $id_list
    )
";


$stmt =
    mysqli_prepare(
        $conn,
        $sql
    );


if (!$stmt) {

    die(
        "Database error while checking answers."
    );
}


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $application["internship_id"]
);


mysqli_stmt_execute($stmt);


$result =
    mysqli_stmt_get_result(
        $stmt
    );


/* =========================================================
   CALCULATE SCORE
========================================================= */

$correct = 0;

$wrong_questions = [];


while (
    $row =
    mysqli_fetch_assoc(
        $result
    )
) {

    $question_id =
        (int)$row["id"];


    $correct_answer =
        strtoupper(
            trim(
                $row["correct_answer"]
            )
        );


    $student_answer = "";


    if (
        isset(
            $answers[$question_id]
        )
    ) {

        $student_answer =
            strtoupper(
                trim(
                    (string)$answers[$question_id]
                )
            );
    }


    if (
        $student_answer ===
        $correct_answer
    ) {

        $correct++;

    } else {

        $option_text = [
            "A" => $row["option_a"],
            "B" => $row["option_b"],
            "C" => $row["option_c"],
            "D" => $row["option_d"]
        ];

        $wrong_questions[] = [
            "question" => $row["question"],
            "student_answer" =>
                $student_answer !== ""
                    ? ($student_answer . ". " . ($option_text[$student_answer] ?? ""))
                    : "Not Answered",
            "correct_answer" =>
                $correct_answer . ". " . ($option_text[$correct_answer] ?? "")
        ];
    }
}


mysqli_stmt_close(
    $stmt
);


/* =========================================================
   CALCULATE PERCENTAGE
========================================================= */

$total_questions =
    $expected_questions;


$percentage =
    round(
        ($correct / $total_questions) * 100
    );


/* =========================================================
   DETERMINE PASS / FAIL
========================================================= */

$passed = 0;


/*
   LEVEL 1
   Need 8/10 or above
*/

if ($level === 1) {

    $passed =
        ($correct >= 8)
        ? 1
        : 0;
}


/*
   LEVEL 2
   Need 4/5 or above
*/

elseif ($level === 2) {

    $passed =
        ($correct >= 4)
        ? 1
        : 0;
}


/* =========================================================
   SAVE QUESTION IDS
========================================================= */

$question_ids_text =
    implode(
        ",",
        $question_ids
    );


/* =========================================================
   DETERMINE CURRENT ATTEMPT
========================================================= */

$current_attempt =
    $level1_attempts;


if ($level === 1) {

    $current_attempt =
        $level1_attempts + 1;
}


/* =========================================================
   DETERMINE FINAL FAILURE
========================================================= */

$final_attempt_failed = false;


/*
   LEVEL 1 FAILED ON ATTEMPT 5
*/

if (
    $level === 1 &&
    $passed === 0 &&
    $current_attempt >= 5
) {

    $final_attempt_failed = true;
}


/*
   LEVEL 2 FAILED AFTER 5 LEVEL 1 ATTEMPTS
*/

if (
    $level === 2 &&
    $passed === 0 &&
    $level1_attempts >= 5
) {

    $final_attempt_failed = true;
}


/* =========================================================
   UPLOAD WEBCAM VIDEO
========================================================= */

$video_file =
    $_FILES["webcam_video"];


/*
   MAXIMUM 50 MB
*/

$max_video_size =
    50 * 1024 * 1024;


if (
    !isset($video_file) ||
    $video_file["error"] !== UPLOAD_ERR_OK
) {

    die(
        "Unable to receive webcam recording."
    );
}


if (
    $video_file["size"] <= 0
) {

    die(
        "The webcam recording is empty."
    );
}


if (
    $video_file["size"] > $max_video_size
) {

    die(
        "The webcam recording is too large. " .
        "Maximum allowed size is 50 MB."
    );
}


/* =========================================================
   CREATE UPLOAD DIRECTORY
========================================================= */

$upload_directory =
    "../assets/uploads/mock_tests";


if (!is_dir($upload_directory)) {

    if (
        !mkdir(
            $upload_directory,
            0777,
            true
        )
    ) {

        die(
            "Unable to create webcam upload directory."
        );
    }
}


/* =========================================================
   VALIDATE VIDEO TYPE
========================================================= */

$finfo =
    finfo_open(
        FILEINFO_MIME_TYPE
    );


if (!$finfo) {

    die(
        "Unable to validate webcam video."
    );
}


$video_mime =
    finfo_file(
        $finfo,
        $video_file["tmp_name"]
    );


finfo_close(
    $finfo
);


$allowed_video_types = [

    "video/webm" => "webm",

    "video/mp4" => "mp4",

    "video/x-matroska" => "webm"
];


if (
    !isset(
        $allowed_video_types[
            $video_mime
        ]
    )
) {

    die(
        "Invalid webcam video format."
    );
}


$video_extension =
    $allowed_video_types[
        $video_mime
    ];


/* =========================================================
   GENERATE UNIQUE VIDEO NAME
========================================================= */

try {

    $random_name =
        bin2hex(
            random_bytes(5)
        );

}
catch (Exception $e) {

    $random_name =
        uniqid();
}


$video_filename =
    "mock_test_"
    . $application_id
    . "_level_"
    . $level
    . "_"
    . time()
    . "_"
    . $random_name
    . "."
    . $video_extension;


/* =========================================================
   FULL VIDEO PATH
========================================================= */

$video_full_path =
    $upload_directory
    . "/"
    . $video_filename;


/* =========================================================
   MOVE VIDEO TO SERVER
========================================================= */

if (
    !move_uploaded_file(
        $video_file["tmp_name"],
        $video_full_path
    )
) {

    die(
        "Unable to save webcam recording."
    );
}


/*
   PATH STORED IN DATABASE
*/

$video_relative_path =
    "assets/uploads/mock_tests/"
    . $video_filename;


/* =========================================================
   START DATABASE TRANSACTION
========================================================= */

mysqli_begin_transaction(
    $conn
);


try {


    /* =====================================================
       SAVE TEST RESULT INCLUDING VIDEO
    ===================================================== */

    $sql = "
        INSERT INTO test_results
        (
            user_id,
            application_id,
            internship_id,
            level,
            passed,
            question_ids,
            score,
            total_questions,
            percentage,
            time_limit_seconds,
            time_taken_seconds,
            video_path
        )

        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";


    $stmt =
        mysqli_prepare(
            $conn,
            $sql
        );


    if (!$stmt) {

        throw new Exception(
            "Unable to prepare test result."
        );
    }


    /*
       12 PARAMETERS

       5 integers
       1 string
       5 integers
       1 string

       "iiiiisiiiiis"
    */

    mysqli_stmt_bind_param(
        $stmt,
        "iiiiisiiiiis",
        $student_id,
        $application_id,
        $application["internship_id"],
        $level,
        $passed,
        $question_ids_text,
        $correct,
        $total_questions,
        $percentage,
        $time_limit_seconds,
        $time_taken_seconds,
        $video_relative_path
    );


    if (
        !mysqli_stmt_execute(
            $stmt
        )
    ) {

        throw new Exception(
            "Failed to save test result. "
            . mysqli_error($conn)
        );
    }


    mysqli_stmt_close(
        $stmt
    );


    /* =====================================================
       REJECT AFTER MAXIMUM ATTEMPTS
    ===================================================== */

    if ($final_attempt_failed) {


        $reject_sql = "
            UPDATE applications

            SET status = 'Rejected'

            WHERE id = ?
              AND student_id = ?
        ";


        $reject_stmt =
            mysqli_prepare(
                $conn,
                $reject_sql
            );


        if (!$reject_stmt) {

            throw new Exception(
                "Unable to reject application."
            );
        }


        mysqli_stmt_bind_param(
            $reject_stmt,
            "ii",
            $application_id,
            $student_id
        );


        if (
            !mysqli_stmt_execute(
                $reject_stmt
            )
        ) {

            throw new Exception(
                "Unable to update application."
            );
        }


        mysqli_stmt_close(
            $reject_stmt
        );


        /* =================================================
           CREATE NOTIFICATION
        ================================================= */

        $notification_message =
            "Your application for "
            . $application["title"]
            . " has been rejected because the maximum 5 mock test attempts were completed without successfully passing the final test.";


        $notification_link =
            "applications.php";


        $notification_sql = "
            INSERT INTO notifications
            (
                user_id,
                message,
                link,
                is_read
            )

            VALUES (?, ?, ?, 0)
        ";


        $notification_stmt =
            mysqli_prepare(
                $conn,
                $notification_sql
            );


        if ($notification_stmt) {

            mysqli_stmt_bind_param(
                $notification_stmt,
                "iss",
                $student_id,
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
    }


    /* =====================================================
       COMMIT
    ===================================================== */

    mysqli_commit(
        $conn
    );

}
catch (Exception $e) {

    mysqli_rollback(
        $conn
    );


    /*
       DELETE UPLOADED VIDEO IF DB SAVE FAILS
    */

    if (
        file_exists(
            $video_full_path
        )
    ) {

        unlink(
            $video_full_path
        );
    }


    die(
        "Unable to save test result. "
        . htmlspecialchars(
            $e->getMessage()
        )
    );
}


/* =========================================================
   FORMAT TIME
========================================================= */

function formatTestTime($seconds)
{
    $seconds =
        max(
            0,
            (int)$seconds
        );


    $hours =
        floor(
            $seconds / 3600
        );


    $minutes =
        floor(
            ($seconds % 3600) / 60
        );


    $remaining_seconds =
        $seconds % 60;


    if ($hours > 0) {

        return sprintf(
            "%02d:%02d:%02d",
            $hours,
            $minutes,
            $remaining_seconds
        );
    }


    return sprintf(
        "%02d:%02d",
        $minutes,
        $remaining_seconds
    );
}


$time_limit_display =
    formatTestTime(
        $time_limit_seconds
    );


$time_taken_display =
    formatTestTime(
        $time_taken_seconds
    );


/* =========================================================
   CLEAR TEST SESSION
========================================================= */

unset(
    $_SESSION["mock_test_questions"]
);

unset(
    $_SESSION["mock_test_application_id"]
);

unset(
    $_SESSION["mock_test_level"]
);

unset(
    $_SESSION["mock_test_started_at"]
);

unset(
    $_SESSION["mock_test_attempt_token"]
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
        Test Result - Digital Internship
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>


<body>


<div class="container mt-5">


    <div class="card shadow-sm">


        <div class="card-body text-center">


            <h2 class="mb-4">
                Mock Test Result
            </h2>


            <h5>

                Internship:

                <?php

                echo htmlspecialchars(
                    $application["title"]
                );

                ?>

            </h5>


            <p>

                Company:

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $application["company_name"]
                    );

                    ?>

                </strong>

            </p>


            <hr>


            <!-- LEVEL -->

            <h4 class="mt-4">

                Level
                <?php echo $level; ?>

            </h4>


            <!-- SCORE -->

            <h4 class="mt-3">
                Your Score
            </h4>


            <h1 class="display-4">

                <?php echo $correct; ?>

                /

                <?php echo $total_questions; ?>

            </h1>


            <h3>

                <?php echo $percentage; ?>%

            </h3>


            <!-- TIME DETAILS -->

            <div class="row justify-content-center mt-4">


                <div class="col-md-4 mb-3">

                    <div class="card bg-light">

                        <div class="card-body">

                            <h6 class="text-muted">
                                Time Limit
                            </h6>

                            <h4>

                                <?php

                                echo htmlspecialchars(
                                    $time_limit_display
                                );

                                ?>

                            </h4>

                        </div>

                    </div>

                </div>


                <div class="col-md-4 mb-3">

                    <div class="card bg-light">

                        <div class="card-body">

                            <h6 class="text-muted">
                                Time Taken
                            </h6>

                            <h4 class="text-primary">

                                <?php

                                echo htmlspecialchars(
                                    $time_taken_display
                                );

                                ?>

                            </h4>

                        </div>

                    </div>

                </div>


            </div>


            <!-- VIDEO -->

            <div class="alert alert-info mt-3">

                📷 Webcam recording has been saved
                successfully.

            </div>


            <!-- =========================================
                 QUESTIONS TO REVIEW
            ========================================== -->

            <?php if (!empty($wrong_questions)): ?>

                <div
                    class="card border-danger mt-4 text-start"
                >

                    <div class="card-body">

                        <h4 class="text-danger mb-3">
                            ❌ Questions You Got Wrong
                        </h4>

                        <p class="text-muted">
                            Review the questions you answered incorrectly
                            and the correct answers.
                        </p>


                        <?php foreach (
                            $wrong_questions as $index => $wrong
                        ): ?>

                            <div
                                class="border rounded p-3 mb-3 bg-light"
                            >

                                <strong>
                                    Question <?= $index + 1 ?>
                                </strong>

                                <p class="mt-2 mb-2">
                                    <?= htmlspecialchars(
                                        $wrong["question"]
                                    ) ?>
                                </p>

                                <p class="mb-1 text-danger">
                                    <strong>
                                        Your Answer:
                                    </strong>
                                    <?= htmlspecialchars(
                                        $wrong["student_answer"]
                                    ) ?>
                                </p>

                                <p class="mb-0 text-success">
                                    <strong>
                                        Correct Answer:
                                    </strong>
                                    <?= htmlspecialchars(
                                        $wrong["correct_answer"]
                                    ) ?>
                                </p>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php else: ?>

                <div class="alert alert-success mt-4 text-start">
                    ✅ You answered all questions correctly in this level.
                </div>

            <?php endif; ?>


            <!-- =========================================
                 LEVEL 1
            ========================================== -->

            <?php if ($level === 1): ?>


                <?php if ($passed === 1): ?>


                    <div
                        class="alert alert-success mt-4"
                    >

                        <strong>
                            Level 1 Passed! 🎉
                        </strong>

                        <br><br>

                        You scored

                        <strong>
                            <?php echo $correct; ?>/10
                        </strong>.

                        <br>

                        Attempt:

                        <strong>
                            <?php echo $current_attempt; ?>/5
                        </strong>

                        <br>

                        You can now proceed to
                        <strong>
                            Level 2
                        </strong>.

                    </div>


                    <a
                        href="mock_test.php?application_id=<?php
                            echo $application_id;
                        ?>"
                        class="btn btn-primary btn-lg"
                    >

                        Start Level 2

                    </a>


                <?php else: ?>


                    <?php if ($final_attempt_failed): ?>


                        <div
                            class="alert alert-danger mt-4"
                        >

                            <strong>
                                ❌ Maximum 5 Attempts Completed
                            </strong>

                            <br><br>

                            You scored

                            <strong>
                                <?php echo $correct; ?>/10
                            </strong>.

                            <br><br>

                            You did not achieve the required
                            <strong>
                                8/10
                            </strong>
                            in your 5th attempt.

                            <br><br>

                            Your application has been
                            <strong>
                                Rejected
                            </strong>.

                            <br>

                            The mock test is permanently locked.

                        </div>


                        <a
                            href="applications.php"
                            class="btn btn-danger btn-lg"
                        >

                            ← Back to My Applications

                        </a>


                    <?php else: ?>


                        <div
                            class="alert alert-warning mt-4"
                        >

                            <strong>
                                Level 1 Not Passed
                            </strong>

                            <br><br>

                            You scored

                            <strong>
                                <?php echo $correct; ?>/10
                            </strong>.

                            <br>

                            You need at least
                            <strong>
                                8/10
                            </strong>
                            to proceed.

                            <br><br>

                            Attempt:

                            <strong>
                                <?php echo $current_attempt; ?>/5
                            </strong>

                        </div>


                        <a
                            href="mock_test.php?application_id=<?php
                                echo $application_id;
                            ?>"
                            class="btn btn-warning btn-lg"
                        >

                            Retry Level 1

                        </a>


                    <?php endif; ?>


                <?php endif; ?>


            <!-- =========================================
                 LEVEL 2
            ========================================== -->

            <?php else: ?>


                <?php if ($passed === 1): ?>


                    <div
                        class="alert alert-success mt-4"
                    >

                        <strong>
                            🎉 Mock Test Completed Successfully!
                        </strong>

                        <br><br>

                        You scored

                        <strong>
                            <?php echo $correct; ?>/5
                        </strong>

                        in Level 2.

                        <br><br>

                        You needed at least
                        <strong>
                            4/5
                        </strong>
                        to pass.

                        <br><br>

                        Time Taken:

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $time_taken_display
                            );
                            ?>
                        </strong>

                        <br><br>

                        You have successfully completed
                        the mock test.

                        <br>

                        You are now eligible for the
                        <strong>
                            Offline Interview
                        </strong>.

                    </div>


                    <a
                        href="dashboard.php"
                        class="btn btn-success btn-lg"
                    >

                        ← Back to Dashboard

                    </a>


                <?php else: ?>


                    <?php if ($final_attempt_failed): ?>


                        <div
                            class="alert alert-danger mt-4"
                        >

                            <strong>
                                ❌ Maximum 5 Attempts Completed
                            </strong>

                            <br><br>

                            You scored

                            <strong>
                                <?php echo $correct; ?>/5
                            </strong>

                            in Level 2.

                            <br><br>

                            Level 2 requires at least
                            <strong>
                                4/5
                            </strong>.

                            <br><br>

                            Your application has been
                            <strong>
                                Rejected
                            </strong>.

                            <br>

                            The mock test is permanently locked.

                        </div>


                        <a
                            href="applications.php"
                            class="btn btn-danger btn-lg"
                        >

                            ← Back to My Applications

                        </a>


                    <?php else: ?>


                        <div
                            class="alert alert-danger mt-4"
                        >

                            <strong>
                                Level 2 Not Passed
                            </strong>

                            <br><br>

                            You scored

                            <strong>
                                <?php echo $correct; ?>/5
                            </strong>.

                            <br>

                            You need at least
                            <strong>
                                4/5
                            </strong>
                            to complete the mock test.

                            <br><br>

                            You must restart from
                            <strong>
                                Level 1
                            </strong>.

                            <br><br>

                            Attempts used:

                            <strong>
                                <?php echo $level1_attempts; ?>/5
                            </strong>

                        </div>


                        <a
                            href="mock_test.php?application_id=<?php
                                echo $application_id;
                            ?>"
                            class="btn btn-warning btn-lg"
                        >

                            Restart Level 1

                        </a>


                    <?php endif; ?>


                <?php endif; ?>


            <?php endif; ?>


        </div>

    </div>

</div>


</body>

</html>