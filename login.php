<?php

session_start();

require_once 'config/db.php';

$message = "";
$message_type = "";

$email = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";


    /* Check empty fields */

    if ($email === "" || $password === "") {

        $message = "Please enter email and password.";
        $message_type = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "error";

    } else {


        /* Find user */

        $sql = "SELECT id, name, email, password, role
                FROM users
                WHERE email = ?";

        $stmt = mysqli_prepare($conn, $sql);


        if (!$stmt) {

            $message = "Database error.";
            $message_type = "error";

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "s",
                $email
            );

            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);


            if (mysqli_num_rows($result) === 1) {

                $user = mysqli_fetch_assoc($result);


                /* Verify password */

                if (password_verify(
                    $password,
                    $user["password"]
                )) {


                    /* Store session */

                    $_SESSION["user_id"] =
                        $user["id"];

                    $_SESSION["user_name"] =
                        $user["name"];

                    $_SESSION["user_email"] =
                        $user["email"];

                    $_SESSION["user_role"] =
                        $user["role"];


                    /* Redirect according to role */

                    if ($user["role"] === "admin") {

                        header(
                            "Location: admin/dashboard.php"
                        );

                    } elseif ($user["role"] === "company") {

                        header(
                            "Location: company/dashboard.php"
                        );

                    } elseif ($user["role"] === "student") {

                        header(
                            "Location: student/dashboard.php"
                        );

                    } else {

                        $message =
                            "Invalid account role.";

                        $message_type = "error";

                        mysqli_stmt_close($stmt);

                        exit();
                    }


                    exit();


                } else {

                    $message =
                        "Incorrect email or password.";

                    $message_type = "error";
                }


            } else {

                $message =
                    "Incorrect email or password.";

                $message_type = "error";
            }


            mysqli_stmt_close($stmt);
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

    <title>Login - Digital Internship</title>

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

</head>


<body>


<div class="register-container">


    <div class="register-card">


        <h1>
            Digital Internship
        </h1>


        <h2>
            Login
        </h2>


        <p class="subtitle">
            Login to access your account
        </p>


        <?php if ($message !== ""): ?>

            <div
                class="message
                <?php
                echo htmlspecialchars($message_type);
                ?>"
            >

                <?php
                echo htmlspecialchars($message);
                ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action="login.php"
            autocomplete="off"
        >


            <!-- EMAIL -->

            <div class="form-group">

                <label for="email">
                    Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?php
                    echo htmlspecialchars($email);
                    ?>"
                    autocomplete="off"
                    required
                >

            </div>


            <!-- PASSWORD -->

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >

            </div>


            <!-- LOGIN -->

            <button
                type="submit"
                class="register-btn"
            >
                Login
            </button>


        </form>


        <p class="login-link">

            Don't have an account?

            <a href="register.php">
                Register
            </a>

        </p>


    </div>

</div>


</body>

</html>