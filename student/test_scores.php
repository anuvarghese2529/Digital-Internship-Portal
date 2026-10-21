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
   FORMAT TIME FUNCTION
========================================================= */

function formatTestTime($seconds)
{
    if ($seconds === null || $seconds === '') {
        return "Not Available";
    }

    $seconds = max(0, (int)$seconds);

    $hours = floor($seconds / 3600);

    $minutes = floor(
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


/* =========================================================
   GET TEST SCORES
========================================================= */

$sql = "
    SELECT 
        tr.id,
        tr.level,
        tr.score,
        tr.total_questions,
        tr.percentage,
        tr.passed,
        tr.time_limit_seconds,
        tr.time_taken_seconds,
        tr.created_at,

        i.title AS internship_title,
        i.company_name

    FROM test_results tr

    INNER JOIN applications a
        ON tr.application_id = a.id

    INNER JOIN internships i
        ON a.internship_id = i.id

    WHERE tr.user_id = ?

    ORDER BY tr.created_at DESC
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
    "i",
    $student_id
);


mysqli_stmt_execute($stmt);


$result =
    mysqli_stmt_get_result($stmt);

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
        Test Scores
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <style>

        body {
            background: #f5f7fb;
        }

        .score-card {
            border: none;
            border-radius: 15px;
            transition: 0.2s;
        }

        .score-card:hover {
            transform: translateY(-2px);
        }

        .info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 12px;
        }

    </style>

</head>


<body>


<div class="container mt-5 mb-5">


    <!-- PAGE HEADER -->

    <div
        class="d-flex justify-content-between align-items-center mb-4"
    >

        <div>

            <h2>
                My Test Scores
            </h2>

            <p class="text-muted mb-0">
                View your mock test performance and time details.
            </p>

        </div>


        <a
            href="dashboard.php"
            class="btn btn-secondary"
        >

            ← Back to Dashboard

        </a>

    </div>


    <!-- TEST SCORES -->

    <?php if (mysqli_num_rows($result) > 0): ?>


        <div class="row">


            <?php while ($row = mysqli_fetch_assoc($result)): ?>


                <?php

                $time_limit =
                    formatTestTime(
                        $row['time_limit_seconds']
                    );


                $time_taken =
                    formatTestTime(
                        $row['time_taken_seconds']
                    );

                ?>


                <div class="col-md-6 mb-4">


                    <div
                        class="card score-card shadow-sm h-100"
                    >

                        <div class="card-body">


                            <!-- INTERNSHIP -->

                            <h4>

                                <?php

                                echo htmlspecialchars(
                                    $row['internship_title']
                                );

                                ?>

                            </h4>


                            <p class="text-muted">

                                Company:

                                <strong>

                                    <?php

                                    echo htmlspecialchars(
                                        $row['company_name']
                                    );

                                    ?>

                                </strong>

                            </p>


                            <hr>


                            <!-- LEVEL -->

                            <div class="info-box mb-3">

                                <strong>
                                    Test Level:
                                </strong>

                                Level
                                <?php
                                echo (int)$row['level'];
                                ?>

                            </div>


                            <!-- SCORE -->

                            <p>

                                <strong>
                                    Score:
                                </strong>

                                <?php
                                echo (int)$row['score'];
                                ?>

                                /

                                <?php
                                echo (int)$row['total_questions'];
                                ?>

                            </p>


                            <!-- PERCENTAGE -->

                            <p>

                                <strong>
                                    Percentage:
                                </strong>

                                <?php
                                echo (int)$row['percentage'];
                                ?>%

                            </p>


                            <!-- TIME LIMIT -->

                            <p>

                                <strong>
                                    Time Limit:
                                </strong>

                                <?php
                                echo htmlspecialchars(
                                    $time_limit
                                );
                                ?>

                            </p>


                            <!-- TIME TAKEN -->

                            <p>

                                <strong>
                                    Time Taken:
                                </strong>

                                <span class="text-primary fw-bold">

                                    <?php

                                    echo htmlspecialchars(
                                        $time_taken
                                    );

                                    ?>

                                </span>

                            </p>


                            <!-- RESULT -->

                            <p>

                                <strong>
                                    Result:
                                </strong>


                                <?php if ((int)$row['passed'] === 1): ?>

                                    <span
                                        class="badge bg-success"
                                    >
                                        Passed
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="badge bg-danger"
                                    >
                                        Failed
                                    </span>

                                <?php endif; ?>

                            </p>


                            <!-- SCORE CATEGORY -->

                            <?php if (
                                (int)$row['percentage'] >= 70
                            ): ?>

                                <span
                                    class="badge bg-success"
                                >
                                    High Score
                                </span>


                            <?php elseif (
                                (int)$row['percentage'] >= 50
                            ): ?>

                                <span
                                    class="badge bg-warning text-dark"
                                >
                                    Average Score
                                </span>


                            <?php else: ?>

                                <span
                                    class="badge bg-danger"
                                >
                                    Low Score
                                </span>

                            <?php endif; ?>


                            <!-- TEST DATE -->

                            <p
                                class="text-muted mt-3 mb-0"
                            >

                                Test Taken:

                                <?php

                                echo date(
                                    "d-m-Y h:i A",
                                    strtotime(
                                        $row['created_at']
                                    )
                                );

                                ?>

                            </p>


                        </div>

                    </div>


                </div>


            <?php endwhile; ?>


        </div>


    <?php else: ?>


        <div
            class="alert alert-info text-center"
        >

            No mock test scores available yet.

        </div>


    <?php endif; ?>


</div>


</body>

</html>