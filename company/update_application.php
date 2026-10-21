<?php

session_start();

require_once "../config/db.php";


/* ==================================================
   COMPANY LOGIN CHECK
================================================== */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "company"
) {

    header("Location: ../login.php");
    exit();

}


$company_id =
    (int)$_SESSION["user_id"];


/* ==================================================
   GET REQUEST
================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $application_id =
        (int)($_POST["application_id"] ?? 0);

    $status =
        trim(
            $_POST["status"] ?? ""
        );

    $reason =
        trim(
            $_POST["reason"] ?? ""
        );

} else {

    $application_id =
        (int)($_GET["id"] ?? 0);

    $status =
        $_GET["status"] ?? "";

    $reason =
        trim(
            $_GET["reason"] ?? ""
        );

}


/* ==================================================
   VALIDATION
================================================== */

if (
    $application_id <= 0 ||
    !in_array(
        $status,
        [
            "Shortlisted",
            "Rejected"
        ],
        true
    )
) {

    die(
        "Invalid application request."
    );

}


/* ==================================================
   GET APPLICATION
================================================== */

$sql = "

    SELECT

        a.id,
        a.student_id,
        a.internship_id,
        a.status,

        i.company_id,
        i.title,
        i.company_name

    FROM applications a

    INNER JOIN internships i
        ON a.internship_id = i.id

    WHERE a.id = ?

      AND i.company_id = ?

    LIMIT 1

";


$stmt =
    mysqli_prepare(
        $conn,
        $sql
    );


if (!$stmt) {

    die(
        "Database Error: " .
        mysqli_error($conn)
    );

}


mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $application_id,
    $company_id
);


mysqli_stmt_execute(
    $stmt
);


$result =
    mysqli_stmt_get_result(
        $stmt
    );


$application =
    mysqli_fetch_assoc(
        $result
    );


mysqli_stmt_close(
    $stmt
);


if (!$application) {

    die(
        "Application not found or this application does not belong to your company."
    );

}


$student_id =
    (int)$application["student_id"];

$internship_id =
    (int)$application["internship_id"];

$current_status =
    $application["status"];


$already_done =
    (
        $current_status === $status
    );


/* ==================================================
   FINAL LEVEL 2 CHECK BEFORE SHORTLISTING
   Required: 4/5 or 5/5
================================================== */

if (
    $status === "Shortlisted" &&
    !$already_done
) {

    $test_sql = "

        SELECT
            id,
            score,
            total_questions,
            passed

        FROM test_results

        WHERE application_id = ?
          AND user_id = ?
          AND internship_id = ?
          AND level = 2
          AND passed = 1
          AND score >= 4
          AND total_questions = 5

        ORDER BY id DESC

        LIMIT 1

    ";


    $test_stmt =
        mysqli_prepare(
            $conn,
            $test_sql
        );


    if (!$test_stmt) {

        die(
            "Database Error: " .
            mysqli_error($conn)
        );

    }


    mysqli_stmt_bind_param(
        $test_stmt,
        "iii",
        $application_id,
        $student_id,
        $internship_id
    );


    mysqli_stmt_execute(
        $test_stmt
    );


    $test_result =
        mysqli_stmt_get_result(
            $test_stmt
        );


    $final_test =
        mysqli_fetch_assoc(
            $test_result
        );


    mysqli_stmt_close(
        $test_stmt
    );


    if (!$final_test) {

        die(
            "Cannot shortlist this student. Final Level 2 mock test must be passed with 4/5 or above."
        );

    }

}


/* ==================================================
   DEFAULT REJECTION REASON
================================================== */

if (
    $status === "Rejected" &&
    $reason === ""
) {

    $reason =
        "The application was rejected by the company after reviewing the student's application and mock-test performance.";

}


/* ==================================================
   START TRANSACTION
================================================== */

mysqli_begin_transaction(
    $conn
);


try {


    /* ==================================================
       UPDATE STATUS
    ================================================== */

    if (!$already_done) {

        $update_sql = "

            UPDATE applications

            SET status = ?

            WHERE id = ?

              AND student_id = ?

              AND internship_id = ?

        ";


        $update_stmt =
            mysqli_prepare(
                $conn,
                $update_sql
            );


        if (!$update_stmt) {

            throw new Exception(
                "Unable to prepare application update."
            );

        }


        mysqli_stmt_bind_param(
            $update_stmt,
            "siii",
            $status,
            $application_id,
            $student_id,
            $internship_id
        );


        if (
            !mysqli_stmt_execute(
                $update_stmt
            )
        ) {

            throw new Exception(
                "Unable to update application."
            );

        }


        mysqli_stmt_close(
            $update_stmt
        );

    }


    /* ==================================================
       REJECTION
    ================================================== */

    if (
        $status === "Rejected" &&
        !$already_done
    ) {


        /* ---------------------------------------------
           DELETE EXISTING INTERVIEW LETTER
        --------------------------------------------- */

        $delete_letter_sql = "

            DELETE FROM interview_letters

            WHERE application_id = ?

        ";


        $delete_letter_stmt =
            mysqli_prepare(
                $conn,
                $delete_letter_sql
            );


        if ($delete_letter_stmt) {

            mysqli_stmt_bind_param(
                $delete_letter_stmt,
                "i",
                $application_id
            );


            mysqli_stmt_execute(
                $delete_letter_stmt
            );


            mysqli_stmt_close(
                $delete_letter_stmt
            );

        }


        /* ---------------------------------------------
           STUDENT NOTIFICATION
        --------------------------------------------- */

        $notification_message =

            "Your application for " .

            $application["title"] .

            " has been rejected by " .

            $application["company_name"] .

            ". Reason: " .

            $reason;


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


        if (!$notification_stmt) {

            throw new Exception(
                "Unable to prepare notification."
            );

        }


        mysqli_stmt_bind_param(
            $notification_stmt,
            "iss",
            $student_id,
            $notification_message,
            $notification_link
        );


        if (
            !mysqli_stmt_execute(
                $notification_stmt
            )
        ) {

            throw new Exception(
                "Unable to send rejection notification."
            );

        }


        mysqli_stmt_close(
            $notification_stmt
        );

    }


    /* ==================================================
       SHORTLIST NOTIFICATION
    ================================================== */

    if (
        $status === "Shortlisted" &&
        !$already_done
    ) {

        $notification_message =

            "Congratulations! Your application for " .

            $application["title"] .

            " has been shortlisted by " .

            $application["company_name"] .
            ".";


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


        if (!$notification_stmt) {

            throw new Exception(
                "Unable to prepare notification."
            );

        }


        mysqli_stmt_bind_param(
            $notification_stmt,
            "iss",
            $student_id,
            $notification_message,
            $notification_link
        );


        if (
            !mysqli_stmt_execute(
                $notification_stmt
            )
        ) {

            throw new Exception(
                "Unable to send shortlist notification."
            );

        }


        mysqli_stmt_close(
            $notification_stmt
        );

    }


    /* ==================================================
       COMMIT
    ================================================== */

    mysqli_commit(
        $conn
    );

}

catch (Exception $e) {

    mysqli_rollback(
        $conn
    );

    die(
        "Unable to update application: " .
        htmlspecialchars(
            $e->getMessage()
        )
    );

}


/* ==================================================
   SUCCESS PAGE
================================================== */

if (
    $status === "Rejected"
) {

    $heading =
        $already_done
        ? "Application Already Rejected"
        : "Application Rejected Successfully";


    $message =
        $already_done
        ? "This application was already rejected."
        : "The application has been rejected and the student has been notified.";


    $color =
        "#dc3545";

}
else {

    $heading =
        $already_done
        ? "Student Already Shortlisted"
        : "Student Shortlisted Successfully";


    $message =
        $already_done
        ? "This student was already shortlisted."
        : "The student has been shortlisted successfully.";


    $color =
        "#198754";

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
    <?= htmlspecialchars($heading) ?>
</title>


<style>

body {

    font-family: Arial, sans-serif;

    background: #f4f6fb;

    display: flex;

    justify-content: center;

    align-items: center;

    min-height: 100vh;

    margin: 0;

}


.box {

    width: 90%;

    max-width: 600px;

    background: white;

    padding: 40px;

    border-radius: 16px;

    text-align: center;

    box-shadow:
        0 10px 30px
        rgba(0,0,0,.10);

}


.icon {

    font-size: 60px;

    margin-bottom: 12px;

}


h2 {

    color: <?= $color ?>;

    margin-bottom: 15px;

}


p {

    color: #555;

    line-height: 1.6;

}


.reason-box {

    margin-top: 18px;

    background: #f8f9fa;

    padding: 15px;

    border-radius: 9px;

    text-align: left;

}


.reason-box strong {

    display: block;

    margin-bottom: 6px;

    color: #444;

}


.btn {

    display: inline-block;

    margin-top: 22px;

    padding: 12px 22px;

    background: #667eea;

    color: white;

    text-decoration: none;

    border-radius: 8px;

    font-weight: bold;

}


.btn:hover {

    background: #5568d8;

}

</style>

</head>


<body>


<div class="box">


    <div class="icon">

        <?= $status === "Rejected"
            ? "❌"
            : "✅"
        ?>

    </div>


    <h2>

        <?= htmlspecialchars(
            $heading
        ) ?>

    </h2>


    <p>

        <?= htmlspecialchars(
            $message
        ) ?>

    </p>


    <?php if (
        $status === "Rejected"
    ): ?>

        <div class="reason-box">

            <strong>
                Rejection Reason
            </strong>

            <?= nl2br(
                htmlspecialchars(
                    $reason
                )
            ) ?>

        </div>

    <?php endif; ?>


    <div
        class="reason-box"
        style="text-align:center;"
    >

        <strong>
            Application ID
        </strong>

        #<?= $application_id ?>

    </div>


    <a
        href="applications.php"
        class="btn"
    >
        ← Back to Applications
    </a>


</div>


</body>

</html>