<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/db.php";

$student_id = (int)$_SESSION["user_id"];

$message = "";
$message_type = "";


/* ==================================================
   CANCEL APPLICATION
================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["cancel_application"])
) {

    $application_id = (int)($_POST["application_id"] ?? 0);

    if ($application_id <= 0) {

        $message = "Invalid application.";
        $message_type = "error";

    } else {

        $check_sql = "
            SELECT
                applications.id,
                applications.status,
                internships.title
            FROM applications

            INNER JOIN internships
                ON applications.internship_id = internships.id

            WHERE applications.id = ?
            AND applications.student_id = ?
        ";

        $check_stmt = mysqli_prepare($conn, $check_sql);

        if (!$check_stmt) {
            die("Database Error: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param(
            $check_stmt,
            "ii",
            $application_id,
            $student_id
        );

        mysqli_stmt_execute($check_stmt);

        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) === 1) {

            $application = mysqli_fetch_assoc($check_result);

            /* Only Pending applications can be cancelled */

            if (
                strtolower(trim($application["status"])) === "pending"
            ) {

                $update_sql = "
                    UPDATE applications
                    SET status = 'Cancelled'
                    WHERE id = ?
                    AND student_id = ?
                ";

                $update_stmt = mysqli_prepare(
                    $conn,
                    $update_sql
                );

                if (!$update_stmt) {
                    die("Database Error: " . mysqli_error($conn));
                }

                mysqli_stmt_bind_param(
                    $update_stmt,
                    "ii",
                    $application_id,
                    $student_id
                );

                if (mysqli_stmt_execute($update_stmt)) {

                    $message =
                        "Application cancelled successfully.";

                    $message_type = "success";

                    /* Create notification */

                    $notification_message =
                        "Your application for " .
                        $application["title"] .
                        " has been cancelled.";

                    $notification_sql = "
                        INSERT INTO notifications
                        (user_id, message, is_read)
                        VALUES (?, ?, 0)
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

                } else {

                    $message =
                        "Unable to cancel application.";

                    $message_type = "error";
                }

                mysqli_stmt_close($update_stmt);

            } else {

                $message =
                    "Only pending applications can be cancelled.";

                $message_type = "error";
            }

        } else {

            $message =
                "Application not found.";

            $message_type = "error";
        }

        mysqli_stmt_close($check_stmt);
    }
}


/* ==================================================
   GET STUDENT APPLICATIONS
================================================== */

$sql = "
    SELECT
        applications.id,
        applications.status,
        applications.applied_at,
        applications.internship_id,

        internships.title,
        internships.company_name,
        internships.location

    FROM applications

    INNER JOIN internships
        ON applications.internship_id = internships.id

    WHERE applications.student_id = ?

    AND applications.status != 'Cancelled'

    ORDER BY applications.id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $student_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

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
    My Applications - Digital Internship
</title>


<style>

/* ==================================================
   RESET
================================================== */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}


/* ==================================================
   BODY
================================================== */

body {
    font-family: Arial, sans-serif;
    background: #f4f6fb;
    color: #333;
}


/* ==================================================
   CONTAINER
================================================== */

.container {
    width: 90%;
    max-width: 1100px;
    margin: 40px auto;
}


/* ==================================================
   HEADER
================================================== */

.header {
    background: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.header h1 {
    color: #667eea;
    margin-bottom: 8px;
}

.header p {
    color: #777;
}

.back-btn {
    display: inline-block;
    margin-top: 15px;
    padding: 10px 18px;
    background: #667eea;
    color: white;
    text-decoration: none;
    border-radius: 7px;
}

.back-btn:hover {
    background: #5568d8;
}


/* ==================================================
   MESSAGE
================================================== */

.message {
    padding: 13px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
    font-weight: bold;
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
   APPLICATION CARD
================================================== */

.application-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.application-card h2 {
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

.info strong {
    color: #333;
}


/* ==================================================
   STATUS
================================================== */

.status {
    display: inline-block;
    margin-top: 15px;
    padding: 8px 15px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 14px;
}

.pending {
    background: #fff3cd;
    color: #856404;
}

.shortlisted {
    background: #d4edda;
    color: #155724;
}

.rejected {
    background: #f8d7da;
    color: #721c24;
}


/* ==================================================
   MOCK TEST SECTION
================================================== */

.mock-test-box {
    margin-top: 22px;
    padding: 20px;
    background: #f8f9ff;
    border-left: 5px solid #667eea;
    border-radius: 10px;
}

.mock-test-box h3 {
    color: #667eea;
    margin-bottom: 15px;
}

.mock-status {
    margin-bottom: 10px;
    line-height: 1.6;
}

.mock-result {
    margin-top: 12px;
    padding: 12px;
    background: white;
    border-radius: 8px;
}

.mock-pass {
    color: #198754;
    font-weight: bold;
}

.mock-fail {
    color: #dc3545;
    font-weight: bold;
}

.mock-pending {
    color: #856404;
    font-weight: bold;
}


/* ==================================================
   MOCK TEST BUTTON
================================================== */

.mock-btn {
    display: inline-block;
    margin-top: 12px;
    padding: 10px 18px;
    background: #667eea;
    color: white;
    text-decoration: none;
    border-radius: 7px;
    font-weight: bold;
}

.mock-btn:hover {
    background: #5568d8;
}


/* ==================================================
   FINAL PASS
================================================== */

.final-pass {
    margin-top: 12px;
    padding: 14px;
    background: #d4edda;
    color: #155724;
    border-radius: 8px;
    font-weight: bold;
}


/* ==================================================
   CANCEL BUTTON
================================================== */

.cancel-form {
    margin-top: 18px;
}

.cancel-btn {
    padding: 10px 18px;
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 7px;
    font-weight: bold;
    cursor: pointer;
}

.cancel-btn:hover {
    background: #c82333;
}


/* ==================================================
   EMPTY
================================================== */

.empty {
    background: white;
    padding: 45px;
    text-align: center;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.empty h2 {
    margin-bottom: 10px;
    color: #555;
}

.empty p {
    color: #777;
    margin-bottom: 20px;
}

.browse-btn {
    display: inline-block;
    background: #667eea;
    color: white;
    text-decoration: none;
    padding: 11px 20px;
    border-radius: 7px;
}

.browse-btn:hover {
    background: #5568d8;
}


/* ==================================================
   RESPONSIVE
================================================== */

@media (max-width: 600px) {

    .container {
        width: 94%;
        margin: 20px auto;
    }

    .application-card {
        padding: 20px;
    }

}

</style>

</head>


<body>


<div class="container">


    <!-- ==================================================
         HEADER
    ================================================== -->

    <div class="header">

        <h1>
            My Applications
        </h1>

        <p>
            Track the internships you have applied for
            and check your application status.
        </p>

        <a
            href="dashboard.php"
            class="back-btn"
        >
            ← Back to Dashboard
        </a>

    </div>


    <!-- ==================================================
         MESSAGE
    ================================================== -->

    <?php if ($message !== ""): ?>

        <div class="message <?php echo htmlspecialchars($message_type); ?>">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>


    <!-- ==================================================
         APPLICATIONS
    ================================================== -->

    <?php if (mysqli_num_rows($result) > 0): ?>


        <?php while ($row = mysqli_fetch_assoc($result)): ?>


            <?php

            /* ==================================================
               APPLICATION STATUS
            ================================================== */

            $status = strtolower(
                trim(
                    $row["status"] ?? "Pending"
                )
            );


            if ($status === "shortlisted") {

                $status_class = "shortlisted";
                $status_text = "Shortlisted";

            } elseif ($status === "rejected") {

                $status_class = "rejected";
                $status_text = "Rejected";

            } else {

                $status_class = "pending";
                $status_text = "Pending";
            }


            /* ==================================================
               GET MOCK TEST RESULTS FOR THIS APPLICATION
            ================================================== */

            $application_id =
                (int)$row["id"];


            $test_sql = "
                SELECT
                    id,
                    level,
                    score,
                    total_questions,
                    percentage,
                    passed,
                    created_at

                FROM test_results

                WHERE application_id = ?

                ORDER BY id DESC
            ";


            $test_stmt =
                mysqli_prepare(
                    $conn,
                    $test_sql
                );


            $test_results = [];

            $final_level2_passed = false;


            if ($test_stmt) {

                mysqli_stmt_bind_param(
                    $test_stmt,
                    "i",
                    $application_id
                );

                mysqli_stmt_execute(
                    $test_stmt
                );

                $test_result =
                    mysqli_stmt_get_result(
                        $test_stmt
                    );


                while (
                    $test_row =
                    mysqli_fetch_assoc(
                        $test_result
                    )
                ) {

                    $test_results[] =
                        $test_row;


                    /* Final Level 2 pass */

                    if (
                        (int)$test_row["level"] === 2 &&
                        (int)$test_row["passed"] === 1 &&
                        (int)$test_row["score"] >= 4 &&
                        (int)$test_row["total_questions"] === 5
                    ) {

                        $final_level2_passed = true;
                    }
                }


                mysqli_stmt_close(
                    $test_stmt
                );
            }


            $has_test =
                count($test_results) > 0;


            /* Latest test */

            $latest_test =
                $has_test
                    ? $test_results[0]
                    : null;

            ?>


            <!-- ==================================================
                 APPLICATION CARD
            ================================================== -->

            <div class="application-card">


                <h2>

                    📋

                    <?php
                    echo htmlspecialchars(
                        $row["title"]
                    );
                    ?>

                </h2>


                <p class="company">

                    🏢

                    <?php
                    echo htmlspecialchars(
                        $row["company_name"]
                    );
                    ?>

                </p>


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


                <p class="info">

                    📅

                    <strong>
                        Applied On:
                    </strong>

                    <?php

                    if (!empty($row["applied_at"])) {

                        echo date(
                            "d M Y",
                            strtotime(
                                $row["applied_at"]
                            )
                        );

                    } else {

                        echo "Not available";

                    }

                    ?>

                </p>


                <!-- ==================================================
                     APPLICATION STATUS
                ================================================== -->

                <span
                    class="status <?php echo $status_class; ?>"
                >

                    <?php if ($status === "shortlisted"): ?>

                        ⭐

                    <?php elseif ($status === "rejected"): ?>

                        ❌

                    <?php else: ?>

                        ⏳

                    <?php endif; ?>


                    Status:

                    <?php
                    echo htmlspecialchars(
                        $status_text
                    );
                    ?>

                </span>


                <!-- ==================================================
                     MOCK TEST
                ================================================== -->

                <div class="mock-test-box">

                    <h3>
                        📝 Mock Test
                    </h3>


                    <?php if (!$has_test): ?>


                        <div class="mock-status">

                            <span class="mock-pending">
                                ⏳ Not Attended Yet
                            </span>

                            <br>

                            You have not attended the mock test
                            for this internship.

                        </div>


                        <a
                            href="mock_test.php?application_id=<?php echo $application_id; ?>"
                            class="mock-btn"
                        >
                            📝 Attend Mock Test
                        </a>


                    <?php else: ?>


                        <div class="mock-status">

                            <strong>
                                ✅ Mock Test Attended
                            </strong>

                        </div>


                        <!-- LATEST RESULT -->

                        <?php if ($latest_test): ?>


                            <div class="mock-result">

                                <p class="info">

                                    <strong>
                                        Latest Level:
                                    </strong>

                                    Level
                                    <?php
                                    echo (int)$latest_test["level"];
                                    ?>

                                </p>


                                <p class="info">

                                    <strong>
                                        Score:
                                    </strong>

                                    <?php
                                    echo (int)$latest_test["score"];
                                    ?>

                                    /

                                    <?php
                                    echo (int)$latest_test["total_questions"];
                                    ?>

                                </p>


                                <p class="info">

                                    <strong>
                                        Percentage:
                                    </strong>

                                    <?php
                                    echo (float)$latest_test["percentage"];
                                    ?>%

                                </p>


                                <p class="info">

                                    <strong>
                                        Result:
                                    </strong>


                                    <?php if (
                                        (int)$latest_test["passed"] === 1
                                    ): ?>

                                        <span class="mock-pass">
                                            ✅ PASS
                                        </span>

                                    <?php else: ?>

                                        <span class="mock-fail">
                                            ❌ FAIL
                                        </span>

                                    <?php endif; ?>

                                </p>


                                <p class="info">

                                    <strong>
                                        Taken On:
                                    </strong>

                                    <?php
                                    echo date(
                                        "d M Y, h:i A",
                                        strtotime(
                                            $latest_test["created_at"]
                                        )
                                    );
                                    ?>

                                </p>

                            </div>


                        <?php endif; ?>


                        <!-- FINAL LEVEL 2 PASSED -->

                        <?php if ($final_level2_passed): ?>


                            <div class="final-pass">

                                ✅ Final Mock Test Passed

                                <br><br>

                                You have successfully completed
                                the required mock test.

                                <br><br>

                                The company can now review
                                your application.

                            </div>


                        <?php else: ?>


                            <a
                                href="mock_test.php?application_id=<?php echo $application_id; ?>"
                                class="mock-btn"
                            >
                                📝 Continue Mock Test
                            </a>


                        <?php endif; ?>


                    <?php endif; ?>


                </div>


                <!-- ==================================================
                     CANCEL ONLY FOR PENDING
                ================================================== -->

                <?php if ($status === "pending"): ?>

                    <form
                        method="POST"
                        class="cancel-form"
                        onsubmit="return confirm(
                            'Are you sure you want to cancel this application?'
                        );"
                    >

                        <input
                            type="hidden"
                            name="application_id"
                            value="<?php
                            echo $application_id;
                            ?>"
                        >


                        <button
                            type="submit"
                            name="cancel_application"
                            class="cancel-btn"
                        >

                            ✕ Cancel Application

                        </button>

                    </form>

                <?php endif; ?>


            </div>


        <?php endwhile; ?>


    <?php else: ?>


        <!-- ==================================================
             NO APPLICATIONS
        ================================================== -->

        <div class="empty">

            <h2>
                No Active Applications
            </h2>

            <p>
                You haven't applied for any internship yet.
            </p>

            <a
                href="internships.php"
                class="browse-btn"
            >

                🔍 Browse Internships

            </a>

        </div>


    <?php endif; ?>


</div>


</body>

</html>

<?php

mysqli_stmt_close($stmt);

?>