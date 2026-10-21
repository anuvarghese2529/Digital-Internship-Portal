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


/* ==========================================
   UPDATE COMPANY PROFILE
========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");

    if ($name === "" || $email === "") {

        $message = "Please fill all required fields.";
        $message_type = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "error";

    } else {

        /* Check whether email already belongs to another user */

        $check_sql = "
            SELECT id
            FROM users
            WHERE email = ?
            AND id != ?
        ";

        $check_stmt = mysqli_prepare(
            $conn,
            $check_sql
        );

        if (!$check_stmt) {
            die("Database Error: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param(
            $check_stmt,
            "si",
            $email,
            $user_id
        );

        mysqli_stmt_execute($check_stmt);

        $check_result = mysqli_stmt_get_result($check_stmt);


        if (mysqli_num_rows($check_result) > 0) {

            $message = "This email is already used by another account.";
            $message_type = "error";

        } else {

            /* Update company details */

            $update_sql = "
                UPDATE users
                SET name = ?,
                    email = ?
                WHERE id = ?
                AND role = 'company'
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
                "ssi",
                $name,
                $email,
                $user_id
            );


            if (mysqli_stmt_execute($update_stmt)) {

                /* Update session values */

                $_SESSION["user_name"] = $name;
                $_SESSION["user_email"] = $email;

                $message = "Company profile updated successfully.";
                $message_type = "success";

            } else {

                $message = "Unable to update profile.";
                $message_type = "error";
            }

            mysqli_stmt_close($update_stmt);
        }

        mysqli_stmt_close($check_stmt);
    }
}


/* ==========================================
   GET COMPANY DETAILS
========================================== */

$sql = "
    SELECT
        id,
        name,
        email,
        role,
        created_at
    FROM users
    WHERE id = ?
    AND role = 'company'
";

$stmt = mysqli_prepare(
    $conn,
    $sql
);

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

    mysqli_stmt_close($stmt);

    session_destroy();

    header("Location: ../login.php");
    exit();
}

$company = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/* ==========================================
   GET COMPANY VERIFICATION DOCUMENTS
========================================== */

$verification = null;

$verification_sql = "
    SELECT
        gstin,
        pan,
        gst_status,
        pan_status,
        admin_status,
        admin_remark,
        gst_certificate,
        company_registration_document,
        pan_document
    FROM company_verifications
    WHERE user_id = ?
    LIMIT 1
";

$verification_stmt = mysqli_prepare(
    $conn,
    $verification_sql
);

if ($verification_stmt) {

    mysqli_stmt_bind_param(
        $verification_stmt,
        "i",
        $user_id
    );

    mysqli_stmt_execute($verification_stmt);

    $verification_result =
        mysqli_stmt_get_result($verification_stmt);

    if (mysqli_num_rows($verification_result) === 1) {

        $verification =
            mysqli_fetch_assoc($verification_result);
    }

    mysqli_stmt_close($verification_stmt);
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Company Profile - Digital Internship</title>


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


.dashboard {
    display: flex;
    min-height: 100vh;
}


/* ==========================================
   SIDEBAR
========================================== */

.sidebar {
    width: 250px;
    min-height: 100vh;
    background: white;
    padding: 25px 15px;
    position: fixed;
    left: 0;
    top: 0;
    box-shadow: 3px 0 15px rgba(0,0,0,0.10);
}


.sidebar-header {
    text-align: center;
    margin-bottom: 30px;
}


.sidebar-header h2 {
    color: #667eea;
    font-size: 22px;
    margin-bottom: 5px;
}


.sidebar-header p {
    color: #777;
    font-size: 13px;
}


.sidebar nav {
    display: flex;
    flex-direction: column;
    gap: 8px;
}


.sidebar nav a {
    text-decoration: none;
    color: #444;
    padding: 13px 15px;
    border-radius: 8px;
    font-size: 15px;
    display: block;
    transition: 0.3s;
}


.sidebar nav a:hover,
.sidebar nav a.active {
    background: #667eea;
    color: white;
}


/* ==========================================
   MAIN CONTENT
========================================== */

.main-content {
    margin-left: 250px;
    width: calc(100% - 250px);
    padding: 30px;
}


/* ==========================================
   HEADER
========================================== */

.page-header {
    background: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.06);
}


.page-header h1 {
    margin-bottom: 8px;
}


.page-header p {
    color: #777;
}


/* ==========================================
   MESSAGE
========================================== */

.message {
    padding: 13px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: bold;
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


/* ==========================================
   PROFILE
========================================== */

.profile-container {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.06);
    max-width: 950px;
}


.profile-icon {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: #667eea;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 42px;
    margin-bottom: 20px;
}


.profile-container h2 {
    margin-bottom: 25px;
    color: #333;
}


/* ==========================================
   FORM
========================================== */

.form-group {
    margin-bottom: 20px;
}


.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    color: #444;
}


.form-group input {
    width: 100%;
    padding: 13px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 15px;
    outline: none;
}


.form-group input:focus {
    border-color: #667eea;
}


.role-box {
    background: #f3f4ff;
    padding: 13px 15px;
    border-radius: 8px;
    color: #667eea;
    font-weight: bold;
}


/* ==========================================
   BUTTONS
========================================== */

.button-group {
    display: flex;
    gap: 12px;
    margin-top: 25px;
}


.update-btn {
    background: #667eea;
    color: white;
    border: none;
    padding: 12px 22px;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
}


.update-btn:hover {
    background: #5568d8;
}


.back-btn {
    display: inline-block;
    background: #eee;
    color: #444;
    text-decoration: none;
    padding: 12px 22px;
    border-radius: 8px;
    font-weight: bold;
}


.back-btn:hover {
    background: #ddd;
}


/* ==========================================
   ACCOUNT INFO
========================================== */

.account-info {
    margin-top: 30px;
    padding-top: 25px;
    border-top: 1px solid #eee;
}


.account-info h3 {
    margin-bottom: 15px;
}


.account-info p {
    color: #666;
    margin-bottom: 8px;
}


/* ==========================================
   VERIFICATION SECTION
========================================== */

.verification-section {
    margin-top: 35px;
    padding-top: 30px;
    border-top: 1px solid #eee;
}


.verification-section h3 {
    margin-bottom: 18px;
    color: #333;
}


.verification-box {
    background: #f8f9ff;
    border: 1px solid #e2e5ff;
    border-radius: 10px;
    padding: 20px;
}


.verification-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}


.info-item {
    background: white;
    padding: 14px;
    border-radius: 8px;
    border: 1px solid #eee;
}


.info-item label {
    display: block;
    font-size: 12px;
    color: #777;
    margin-bottom: 5px;
    font-weight: bold;
}


.info-item strong {
    color: #333;
    word-break: break-word;
}


.status-badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}


.status-pending {
    background: #fff3cd;
    color: #856404;
}


.status-approved {
    background: #d4edda;
    color: #155724;
}


.status-rejected {
    background: #f8d7da;
    color: #721c24;
}


/* ==========================================
   DOCUMENTS
========================================== */

.documents-title {
    margin-top: 10px;
    margin-bottom: 12px;
    font-size: 16px;
}


.documents-list {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}


.document-card {
    background: white;
    border: 1px solid #e5e5e5;
    border-radius: 10px;
    padding: 15px;
}


.document-card h4 {
    font-size: 14px;
    margin-bottom: 6px;
}


.document-card p {
    color: #777;
    font-size: 12px;
    margin-bottom: 12px;
    word-break: break-word;
}


.document-card a {
    display: inline-block;
    text-decoration: none;
    background: #667eea;
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: bold;
}


.document-card a:hover {
    background: #5568d8;
}


.no-document {
    background: #fff7e6;
    color: #856404;
    padding: 14px;
    border-radius: 8px;
    font-size: 13px;
}


/* ==========================================
   RESPONSIVE
========================================== */

@media (max-width: 850px) {

    .verification-grid {
        grid-template-columns: 1fr;
    }

    .documents-list {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 700px) {

    .sidebar {
        width: 200px;
    }

    .main-content {
        margin-left: 200px;
        width: calc(100% - 200px);
        padding: 20px;
    }

    .button-group {
        flex-direction: column;
    }

}

</style>

</head>


<body>


<div class="dashboard">


<!-- ==========================================
     SIDEBAR
========================================== -->

<aside class="sidebar">

    <div class="sidebar-header">

        <h2>
            Digital Internship
        </h2>

        <p>
            Company Portal
        </p>

    </div>


    <nav>

        <a href="dashboard.php">
            🏠 Dashboard
        </a>

        <a href="profile.php" class="active">
            👤 Company Profile
        </a>

        <a href="post_internship.php">
            ➕ Post Internship
        </a>

        <a href="my_internships.php">
            📋 My Internships
        </a>

        <a href="applications.php">
            📄 Applications
        </a>

        <a href="../logout.php">
            🚪 Logout
        </a>

    </nav>

</aside>


<!-- ==========================================
     MAIN
========================================== -->

<main class="main-content">


    <!-- HEADER -->

    <div class="page-header">

        <h1>
            Company Profile
        </h1>

        <p>
            View and update your company account information.
        </p>

    </div>


    <!-- MESSAGE -->

    <?php if ($message !== ""): ?>

        <div class="message <?php echo $message_type; ?>">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>


    <!-- PROFILE -->

    <div class="profile-container">


        <div class="profile-icon">
            🏢
        </div>


        <h2>
            Company Information
        </h2>


        <form method="POST">


            <!-- COMPANY NAME -->

            <div class="form-group">

                <label for="name">
                    Company Name
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?php
                    echo htmlspecialchars(
                        $company["name"]
                    );
                    ?>"
                    required
                >

            </div>


            <!-- EMAIL -->

            <div class="form-group">

                <label for="email">
                    Company Email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?php
                    echo htmlspecialchars(
                        $company["email"]
                    );
                    ?>"
                    required
                >

            </div>


            <!-- ROLE -->

            <div class="form-group">

                <label>
                    Account Type
                </label>

                <div class="role-box">

                    🏢 Company Account

                </div>

            </div>


            <!-- BUTTONS -->

           


        </form>


        <!-- ======================================
             ACCOUNT INFORMATION
        ======================================= -->

        <div class="account-info">

            <h3>
                Account Information
            </h3>


            <p>

                <strong>
                    User ID:
                </strong>

                <?php
                echo (int)$company["id"];
                ?>

            </p>


            <p>

                <strong>
                    Account Role:
                </strong>

                Company

            </p>


            <p>

                <strong>
                    Created At:
                </strong>

                <?php

                if (
                    !empty($company["created_at"]) &&
                    $company["created_at"] !== "0000-00-00 00:00:00"
                ) {

                    echo date(
                        "d M Y, h:i A",
                        strtotime(
                            $company["created_at"]
                        )
                    );

                } else {

                    echo "Not available";

                }

                ?>

            </p>

        </div>


        <!-- ======================================
             COMPANY VERIFICATION
        ======================================= -->

        <div class="verification-section">

            <h3>
                📄 Company Verification & Documents
            </h3>


<?php if ($verification): ?>


            <div class="verification-box">


                <!-- VERIFICATION INFORMATION -->

                <div class="verification-grid">


                    <div class="info-item">

                        <label>
                            GSTIN
                        </label>

                        <strong>
                            <?= htmlspecialchars(
                                $verification["gstin"] ?? "-"
                            ) ?>
                        </strong>

                    </div>


                    <div class="info-item">

                        <label>
                            PAN
                        </label>

                        <strong>
                            <?= htmlspecialchars(
                                $verification["pan"] ?? "-"
                            ) ?>
                        </strong>

                    </div>


                    <div class="info-item">

                        <label>
                            GST Status
                        </label>

                        <?php
                        $gst_status =
                            $verification["gst_status"] ?? "Pending";
                        ?>

                        <?php if ($gst_status === "Verified"): ?>

                            <span class="status-badge status-approved">
                                ✅ Verified
                            </span>

                        <?php elseif ($gst_status === "Rejected"): ?>

                            <span class="status-badge status-rejected">
                                ❌ Rejected
                            </span>

                        <?php else: ?>

                            <span class="status-badge status-pending">
                                ⏳ Pending
                            </span>

                        <?php endif; ?>

                    </div>


                    <div class="info-item">

                        <label>
                            PAN Status
                        </label>

                        <?php
                        $pan_status =
                            $verification["pan_status"] ?? "Pending";
                        ?>

                        <?php if ($pan_status === "Matched"): ?>

                            <span class="status-badge status-approved">
                                ✅ Matched
                            </span>

                        <?php elseif ($pan_status === "Mismatch"): ?>

                            <span class="status-badge status-rejected">
                                ❌ Mismatch
                            </span>

                        <?php else: ?>

                            <span class="status-badge status-pending">
                                ⏳ Pending
                            </span>

                        <?php endif; ?>

                    </div>


                    <div class="info-item">

                        <label>
                            Admin Verification
                        </label>

                        <?php
                        $admin_status =
                            $verification["admin_status"] ?? "Pending";
                        ?>


                        <?php if ($admin_status === "Approved"): ?>

                            <span class="status-badge status-approved">
                                ✅ Approved
                            </span>

                        <?php elseif ($admin_status === "Rejected"): ?>

                            <span class="status-badge status-rejected">
                                ❌ Rejected
                            </span>

                        <?php else: ?>

                            <span class="status-badge status-pending">
                                ⏳ Pending
                            </span>

                        <?php endif; ?>

                    </div>


                    <?php if (
                        !empty($verification["admin_remark"])
                    ): ?>

                        <div class="info-item">

                            <label>
                                Admin Remark
                            </label>

                            <strong>
                                <?= htmlspecialchars(
                                    $verification["admin_remark"]
                                ) ?>
                            </strong>

                        </div>

                    <?php endif; ?>


                </div>


                <!-- DOCUMENTS -->

                <h4 class="documents-title">
                    Uploaded Documents
                </h4>


                <div class="documents-list">


                    <!-- GST -->

                    <div class="document-card">

                        <h4>
                            📄 GST Certificate
                        </h4>


                        <?php if (
                            !empty(
                                $verification["gst_certificate"]
                            )
                        ): ?>

                            <p>
                                <?= htmlspecialchars(
                                    $verification["gst_certificate"]
                                ) ?>
                            </p>

                            <a
                                href="../assets/uploads/company_verification/<?= rawurlencode(
                                    $verification["gst_certificate"]
                                ) ?>"
                                target="_blank"
                            >
                                👁 View Document
                            </a>

                        <?php else: ?>

                            <div class="no-document">
                                No GST document uploaded.
                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- COMPANY REGISTRATION -->

                    <div class="document-card">

                        <h4>
                            📄 Company Registration
                        </h4>


                        <?php if (
                            !empty(
                                $verification[
                                    "company_registration_document"
                                ]
                            )
                        ): ?>

                            <p>
                                <?= htmlspecialchars(
                                    $verification[
                                        "company_registration_document"
                                    ]
                                ) ?>
                            </p>

                            <a
                                href="../assets/uploads/company_verification/<?= rawurlencode(
                                    $verification[
                                        "company_registration_document"
                                    ]
                                ) ?>"
                                target="_blank"
                            >
                                👁 View Document
                            </a>

                        <?php else: ?>

                            <div class="no-document">
                                No registration document uploaded.
                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- PAN -->

                    <div class="document-card">

                        <h4>
                            📄 PAN Document
                        </h4>


                        <?php if (
                            !empty(
                                $verification["pan_document"]
                            )
                        ): ?>

                            <p>
                                <?= htmlspecialchars(
                                    $verification["pan_document"]
                                ) ?>
                            </p>

                            <a
                                href="../assets/uploads/company_verification/<?= rawurlencode(
                                    $verification["pan_document"]
                                ) ?>"
                                target="_blank"
                            >
                                👁 View Document
                            </a>

                        <?php else: ?>

                            <div class="no-document">
                                No PAN document uploaded.
                            </div>

                        <?php endif; ?>

                    </div>


                </div>


            </div>


<?php else: ?>


            <div class="no-document">

                No company verification details found.

            </div>


<?php endif; ?>


        </div>


    </div>
     <div class="button-group">

                <button
                    type="submit"
                    class="update-btn"
                >

                    💾 Update Profile

                </button>


                <a
                    href="dashboard.php"
                    class="back-btn"
                >

                    ← Back to Dashboard

                </a>

            </div>

</main>


</div>


</body>

</html>