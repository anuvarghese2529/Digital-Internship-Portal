<?php

session_start();

require_once '../config/db.php';


/* =========================
   COMPANY LOGIN CHECK
========================= */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'company'
) {
    header("Location: ../login.php");
    exit();
}

$company_id = (int)$_SESSION['user_id'];

$message = "";
$message_type = "info";


/* =========================
   DELETE QUESTION
========================= */

if (isset($_GET['delete'])) {

    $question_id = (int)$_GET['delete'];

    /*
       Delete only if the question belongs
       to an internship posted by this company
    */

    $sql = "
        DELETE q
        FROM questions q
        INNER JOIN internships i
        ON q.internship_id = i.id
        WHERE q.id = ?
        AND i.company_id = ?
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {

        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $question_id,
            $company_id
        );

        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {

            $message = "Question deleted successfully.";
            $message_type = "success";

        } else {

            $message = "Question could not be deleted.";
            $message_type = "danger";
        }

        mysqli_stmt_close($stmt);
    }
}


/* =========================
   ADD QUESTION
========================= */

if (isset($_POST['add_question'])) {

    $internship_id = (int)($_POST['internship_id'] ?? 0);

    $question = trim($_POST['question'] ?? "");
    $option_a = trim($_POST['option_a'] ?? "");
    $option_b = trim($_POST['option_b'] ?? "");
    $option_c = trim($_POST['option_c'] ?? "");
    $option_d = trim($_POST['option_d'] ?? "");

    $correct_answer = strtoupper(
        trim($_POST['correct_answer'] ?? "")
    );


    /* Check all fields */

    if (
        $internship_id <= 0 ||
        $question === "" ||
        $option_a === "" ||
        $option_b === "" ||
        $option_c === "" ||
        $option_d === "" ||
        !in_array(
            $correct_answer,
            ["A", "B", "C", "D"]
        )
    ) {

        $message = "Please fill all fields correctly.";
        $message_type = "danger";

    } else {


        /* =========================
           CHECK INTERNSHIP
        ========================= */

        $sql = "
            SELECT id, title
            FROM internships
            WHERE id = ?
            AND company_id = ?
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $internship_id,
                $company_id
            );

            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);


            if (!$result || mysqli_num_rows($result) == 0) {

                $message = "Invalid internship.";
                $message_type = "danger";

            } else {


                /* =========================
                   INSERT QUESTION
                ========================= */

                $sql = "
                    INSERT INTO questions
                    (
                        internship_id,
                        question,
                        option_a,
                        option_b,
                        option_c,
                        option_d,
                        correct_answer
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ";

                $insert_stmt = mysqli_prepare(
                    $conn,
                    $sql
                );


                if ($insert_stmt) {

                    mysqli_stmt_bind_param(
                        $insert_stmt,
                        "issssss",
                        $internship_id,
                        $question,
                        $option_a,
                        $option_b,
                        $option_c,
                        $option_d,
                        $correct_answer
                    );


                    if (
                        mysqli_stmt_execute(
                            $insert_stmt
                        )
                    ) {

                        $message =
                            "Question added successfully!";

                        $message_type =
                            "success";

                    } else {

                        $message =
                            "Failed to add question.";

                        $message_type =
                            "danger";
                    }


                    mysqli_stmt_close(
                        $insert_stmt
                    );

                } else {

                    $message =
                        "Database error.";

                    $message_type =
                        "danger";
                }
            }


            mysqli_stmt_close($stmt);
        }
    }
}


/* =========================
   GET COMPANY INTERNSHIPS
========================= */

$sql = "
    SELECT id, title
    FROM internships
    WHERE company_id = ?
    ORDER BY id DESC
";

$stmt = mysqli_prepare($conn, $sql);

$internships = null;

if ($stmt) {

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $company_id
    );

    mysqli_stmt_execute($stmt);

    $internships =
        mysqli_stmt_get_result($stmt);
}


/* =========================
   SELECTED INTERNSHIP
========================= */

$selected_internship =
    (int)(
        $_GET['internship_id']
        ?? $_POST['internship_id']
        ?? 0
    );


/* =========================
   GET QUESTIONS
========================= */

$questions = [];

if ($selected_internship > 0) {

    $sql = "
        SELECT
            q.id,
            q.question,
            q.option_a,
            q.option_b,
            q.option_c,
            q.option_d,
            q.correct_answer
        FROM questions q

        INNER JOIN internships i
        ON q.internship_id = i.id

        WHERE q.internship_id = ?
        AND i.company_id = ?

        ORDER BY q.id ASC
    ";


    $stmt = mysqli_prepare(
        $conn,
        $sql
    );


    if ($stmt) {

        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $selected_internship,
            $company_id
        );

        mysqli_stmt_execute($stmt);

        $result =
            mysqli_stmt_get_result($stmt);


        if ($result) {

            while (
                $row =
                mysqli_fetch_assoc($result)
            ) {

                $questions[] = $row;
            }
        }


        mysqli_stmt_close($stmt);
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>
    Manage Mock Questions
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<style>

body {
    background: #f4f6fb;
}

.page-title {
    color: #667eea;
}

.question-card {
    border-left: 5px solid #667eea;
}

.correct-answer {
    color: #198754;
    font-weight: bold;
}

.question-number {
    color: #667eea;
    font-weight: bold;
}

</style>

</head>


<body>


<div class="container mt-5 mb-5">


    <!-- HEADER -->

    <div
        class="d-flex justify-content-between align-items-center mb-4"
    >

        <div>

            <h2 class="page-title">
                📝 Manage Mock Questions
            </h2>

            <p class="text-muted mb-0">
                Create and manage questions for your internships.
            </p>

        </div>


        <a
            href="dashboard.php"
            class="btn btn-secondary"
        >
            ← Back to Dashboard
        </a>

    </div>


    <!-- MESSAGE -->

    <?php if ($message != ""): ?>

        <div
            class="alert alert-<?php
                echo $message_type;
            ?>"
        >

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>


    <!-- SELECT INTERNSHIP -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-primary text-white">

            <h4 class="mb-0">
                1️⃣ Select Internship
            </h4>

        </div>


        <div class="card-body">

            <?php if (
                $internships &&
                mysqli_num_rows($internships) > 0
            ): ?>

                <form method="GET">

                    <div class="mb-3">

                        <label class="form-label fw-bold">

                            Internship

                        </label>


                        <select
                            name="internship_id"
                            class="form-select"
                            onchange="this.form.submit()"
                            required
                        >

                            <option value="">
                                -- Select Internship --
                            </option>


                            <?php
                            while (
                                $internship =
                                mysqli_fetch_assoc(
                                    $internships
                                )
                            ):
                            ?>

                                <option
                                    value="<?php
                                        echo $internship['id'];
                                    ?>"
                                    <?php
                                    if (
                                        $selected_internship ==
                                        $internship['id']
                                    ) {
                                        echo "selected";
                                    }
                                    ?>
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $internship['title']
                                    );
                                    ?>

                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>

                </form>

            <?php else: ?>

                <div class="alert alert-warning mb-0">

                    You have not posted any internships yet.

                </div>

            <?php endif; ?>

        </div>

    </div>


    <?php if ($selected_internship > 0): ?>


    <!-- ADD QUESTION -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-warning">

            <h4 class="mb-0">
                2️⃣ Add Mock Test Question
            </h4>

        </div>


        <div class="card-body">

            <form method="POST">

                <input
                    type="hidden"
                    name="internship_id"
                    value="<?php
                        echo $selected_internship;
                    ?>"
                >


                <!-- QUESTION -->

                <div class="mb-3">

                    <label class="form-label fw-bold">

                        Question

                    </label>


                    <textarea
                        name="question"
                        class="form-control"
                        rows="3"
                        placeholder="Enter your question"
                        required
                    ></textarea>

                </div>


                <!-- OPTIONS -->

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label class="form-label fw-bold">
                            Option A
                        </label>

                        <input
                            type="text"
                            name="option_a"
                            class="form-control"
                            placeholder="Enter option A"
                            required
                        >

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label fw-bold">
                            Option B
                        </label>

                        <input
                            type="text"
                            name="option_b"
                            class="form-control"
                            placeholder="Enter option B"
                            required
                        >

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label fw-bold">
                            Option C
                        </label>

                        <input
                            type="text"
                            name="option_c"
                            class="form-control"
                            placeholder="Enter option C"
                            required
                        >

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label fw-bold">
                            Option D
                        </label>

                        <input
                            type="text"
                            name="option_d"
                            class="form-control"
                            placeholder="Enter option D"
                            required
                        >

                    </div>

                </div>


                <!-- CORRECT ANSWER -->

                <div class="mb-3">

                    <label class="form-label fw-bold">

                        Correct Answer

                    </label>


                    <select
                        name="correct_answer"
                        class="form-select"
                        required
                    >

                        <option value="">
                            -- Select Correct Answer --
                        </option>

                        <option value="A">
                            A
                        </option>

                        <option value="B">
                            B
                        </option>

                        <option value="C">
                            C
                        </option>

                        <option value="D">
                            D
                        </option>

                    </select>

                </div>


                <button
                    type="submit"
                    name="add_question"
                    class="btn btn-success"
                >

                    ➕ Add Question

                </button>

            </form>

        </div>

    </div>


    <!-- EXISTING QUESTIONS -->

    <div class="card shadow-sm">

        <div class="card-header bg-info text-white">

            <h4 class="mb-0">
                3️⃣ Questions for this Internship
            </h4>

        </div>


        <div class="card-body">


            <?php if (
                count($questions) > 0
            ): ?>


                <div class="alert alert-primary">

                    <strong>
                        Total Questions:
                    </strong>

                    <?php
                    echo count($questions);
                    ?>

                </div>


                <?php
                foreach (
                    $questions
                    as $index => $q
                ):
                ?>


                    <div
                        class="card question-card mb-3"
                    >

                        <div class="card-body">


                            <!-- QUESTION NUMBER -->

                            <div
                                class="question-number mb-2"
                            >

                                Question
                                <?php
                                echo $index + 1;
                                ?>

                            </div>


                            <!-- QUESTION -->

                            <h5>

                                <?php
                                echo htmlspecialchars(
                                    $q['question']
                                );
                                ?>

                            </h5>


                            <hr>


                            <!-- OPTION A -->

                            <p class="mb-2">

                                <strong>A.</strong>

                                <?php
                                echo htmlspecialchars(
                                    $q['option_a']
                                );
                                ?>

                                <?php
                                if (
                                    $q['correct_answer']
                                    === "A"
                                ):
                                ?>

                                    <span
                                        class="correct-answer"
                                    >
                                        ✓ Correct Answer
                                    </span>

                                <?php endif; ?>

                            </p>


                            <!-- OPTION B -->

                            <p class="mb-2">

                                <strong>B.</strong>

                                <?php
                                echo htmlspecialchars(
                                    $q['option_b']
                                );
                                ?>

                                <?php
                                if (
                                    $q['correct_answer']
                                    === "B"
                                ):
                                ?>

                                    <span
                                        class="correct-answer"
                                    >
                                        ✓ Correct Answer
                                    </span>

                                <?php endif; ?>

                            </p>


                            <!-- OPTION C -->

                            <p class="mb-2">

                                <strong>C.</strong>

                                <?php
                                echo htmlspecialchars(
                                    $q['option_c']
                                );
                                ?>

                                <?php
                                if (
                                    $q['correct_answer']
                                    === "C"
                                ):
                                ?>

                                    <span
                                        class="correct-answer"
                                    >
                                        ✓ Correct Answer
                                    </span>

                                <?php endif; ?>

                            </p>


                            <!-- OPTION D -->

                            <p class="mb-3">

                                <strong>D.</strong>

                                <?php
                                echo htmlspecialchars(
                                    $q['option_d']
                                );
                                ?>

                                <?php
                                if (
                                    $q['correct_answer']
                                    === "D"
                                ):
                                ?>

                                    <span
                                        class="correct-answer"
                                    >
                                        ✓ Correct Answer
                                    </span>

                                <?php endif; ?>

                            </p>


                            <!-- DELETE -->

                            <a
                                href="manage_questions.php?delete=<?php
                                    echo $q['id'];
                                ?>&internship_id=<?php
                                    echo $selected_internship;
                                ?>"
                                class="btn btn-danger btn-sm"
                                onclick="
                                    return confirm(
                                        'Are you sure you want to delete this question?'
                                    );
                                "
                            >

                                🗑 Delete Question

                            </a>


                        </div>

                    </div>


                <?php endforeach; ?>


            <?php else: ?>


                <div
                    class="alert alert-secondary text-center"
                >

                    No questions have been added
                    for this internship yet.

                    <br>

                    Add questions using the form above.

                </div>


            <?php endif; ?>


        </div>

    </div>


    <?php endif; ?>


</div>


</body>

</html>