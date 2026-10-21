<?php

require_once 'config/db.php';

$message = "";
$message_type = "";

$name = "";
$email = "";
$role = "student";

/* =========================================================
   ACCOUNT ROLE
   register.php              -> Student
   register.php?role=company -> Company
========================================================= */

if (isset($_GET['role']) && $_GET['role'] === 'company') {
    $role = 'company';
}

/* =========================================================
   SUCCESS MESSAGE
========================================================= */

if (isset($_GET['registered']) && $_GET['registered'] === '1') {

    $role = $_GET['role'] ?? 'student';

    if ($role === 'company') {
        $message = "Company registration submitted successfully. Your account is pending admin verification.";
    } else {
        $message = "Account created successfully! You can login now.";
    }

    $message_type = "success";
}

/* =========================================================
   HELPERS
========================================================= */

function gstCharValue(string $char): int
{
    $char = strtoupper($char);

    if (ctype_digit($char)) {
        return (int)$char;
    }

    return ord($char) - ord('A') + 10;
}


function gstValueToChar(int $value): string
{
    $value = $value % 36;

    if ($value < 10) {
        return (string)$value;
    }

    return chr(ord('A') + ($value - 10));
}


/*
 * GSTIN structure check + checksum check.
 * Example structure:
 * 2 state chars + 10 PAN chars + entity + Z + checksum
 */
function validateGSTIN(string $gstin): bool
{
    $gstin = strtoupper(trim($gstin));

    if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin)) {
        return false;
    }

    /* State code should be in the valid 01-38 range. */
    $stateCode = (int)substr($gstin, 0, 2);

    if ($stateCode < 1 || $stateCode > 38) {
        return false;
    }

    $base = substr($gstin, 0, 14);
    $factor = 1;
    $sum = 0;

    for ($i = 0; $i < strlen($base); $i++) {

        $code = gstCharValue($base[$i]);
        $product = $code * $factor;

        $sum += intdiv($product, 36) + ($product % 36);

        $factor = ($factor === 1) ? 2 : 1;
    }

    $checkValue = (36 - ($sum % 36)) % 36;
    $calculatedCheck = gstValueToChar($checkValue);
    $givenCheck = strtoupper($gstin[14]);

    return $calculatedCheck === $givenCheck;
}


function isFreeEmailDomain(string $email): bool
{
    $parts = explode('@', strtolower($email));
    $domain = end($parts);

    $freeDomains = [
        'gmail.com',
        'yahoo.com',
        'yahoo.in',
        'outlook.com',
        'hotmail.com',
        'live.com',
        'rediffmail.com',
        'protonmail.com',
        'icloud.com'
    ];

    return in_array($domain, $freeDomains, true);
}


function saveUploadedFile(
    array $file,
    string $uploadDir,
    int $userId,
    string $prefix,
    array $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png']
): string|false
{
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        return false;
    }

    $extension = strtolower(
        pathinfo($file['name'], PATHINFO_EXTENSION)
    );

    if (!in_array($extension, $allowedExtensions, true)) {
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowedMime = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];

    if (!isset($allowedMime[$mime])) {
        return false;
    }

    if ($allowedMime[$mime] !== ($extension === 'jpeg' ? 'jpg' : $extension)) {
        return false;
    }

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            return false;
        }
    }

    $safeExtension = $extension === 'jpeg' ? 'jpg' : $extension;

    $filename =
        $prefix . '_' .
        $userId . '_' .
        time() . '_' .
        bin2hex(random_bytes(4)) .
        '.' .
        $safeExtension;

    $destination = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return false;
    }

    return $filename;
}


function deleteUploadedFile(string $folder, string $filename): void
{
    if ($filename === '') {
        return;
    }

    $path = rtrim($folder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($filename);

    if (is_file($path)) {
        @unlink($path);
    }
}

/* =========================================================
   REGISTRATION
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'student';

    if ($role !== 'student' && $role !== 'company') {
        $role = 'student';
    }

    /* Company-only fields */
    $gstin = strtoupper(trim($_POST['gstin'] ?? ''));
    $pan = strtoupper(trim($_POST['pan'] ?? ''));

    /* -----------------------------------------------------
       BASIC VALIDATION
    ----------------------------------------------------- */

    if ($name === '' || $email === '' || $password === '') {

        $message = 'Please fill all fields.';
        $message_type = 'error';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = 'Please enter a valid email address.';
        $message_type = 'error';

    } elseif (strlen($password) < 6) {

        $message = 'Password must contain at least 6 characters.';
        $message_type = 'error';

    } elseif (
        $role === 'company' &&
        isFreeEmailDomain($email)
    ) {

        $message = 'Company registration requires an official company-domain email such as hr@company.com.';
        $message_type = 'error';

    } elseif (
        $role === 'company' &&
        !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)
    ) {

        $message = 'Please enter a valid 10-character PAN.';
        $message_type = 'error';

    } elseif (
        $role === 'company' &&
        !validateGSTIN($gstin)
    ) {

        $message = 'Please enter a valid GSTIN. Check the 15-character format and checksum.';
        $message_type = 'error';

    } elseif (
        $role === 'company' &&
        substr($gstin, 2, 10) !== $pan
    ) {

        $message = 'PAN does not match the PAN embedded in the GSTIN.';
        $message_type = 'error';

    } elseif (
        $role === 'company' &&
        empty($_FILES['gst_certificate']['name'])
    ) {

        $message = 'Please upload the GST certificate.';
        $message_type = 'error';

    } elseif (
        $role === 'company' &&
        empty($_FILES['company_registration_document']['name'])
    ) {

        $message = 'Please upload the company registration / CIN document.';
        $message_type = 'error';

    } elseif (
        $role === 'company' &&
        empty($_FILES['pan_document']['name'])
    ) {

        $message = 'Please upload the PAN document.';
        $message_type = 'error';

    } else {

        /* -------------------------------------------------
           CHECK EMAIL / GSTIN / PAN DUPLICATES
        ------------------------------------------------- */

        $check_sql = "
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ";

        $check_stmt = mysqli_prepare($conn, $check_sql);

        if (!$check_stmt) {

            $message = 'Database error while checking email.';
            $message_type = 'error';

        } else {

            mysqli_stmt_bind_param(
                $check_stmt,
                's',
                $email
            );

            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);

            $email_exists = mysqli_stmt_num_rows($check_stmt) > 0;

            mysqli_stmt_close($check_stmt);

            if ($email_exists) {

                $message = 'This email is already registered.';
                $message_type = 'error';

            } else {

                $duplicate_company = false;

                if ($role === 'company') {

                    $dup_sql = "
                        SELECT id
                        FROM company_verifications
                        WHERE gstin = ?
                           OR pan = ?
                        LIMIT 1
                    ";

                    $dup_stmt = mysqli_prepare($conn, $dup_sql);

                    if (!$dup_stmt) {
                        $message = 'Database error while checking company details.';
                        $message_type = 'error';
                    } else {

                        mysqli_stmt_bind_param(
                            $dup_stmt,
                            'ss',
                            $gstin,
                            $pan
                        );

                        mysqli_stmt_execute($dup_stmt);
                        mysqli_stmt_store_result($dup_stmt);

                        $duplicate_company =
                            mysqli_stmt_num_rows($dup_stmt) > 0;

                        mysqli_stmt_close($dup_stmt);
                    }

                    if ($duplicate_company) {
                        $message = 'This GSTIN or PAN is already registered for another company.';
                        $message_type = 'error';
                    }
                }

                if ($message === '') {

                    /* ---------------------------------------------
                       PASSWORD HASH
                    --------------------------------------------- */

                    $hashed_password = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    /* ---------------------------------------------
                       START TRANSACTION
                    --------------------------------------------- */

                    mysqli_begin_transaction($conn);

                    $savedFiles = [];
                    $uploadDir = __DIR__ . '/assets/uploads/company_verification';

                    try {

                        /* -----------------------------------------
                           INSERT USER
                        ----------------------------------------- */

                        $insert_sql = "
                            INSERT INTO users
                            (name, email, password, role)
                            VALUES (?, ?, ?, ?)
                        ";

                        $stmt = mysqli_prepare($conn, $insert_sql);

                        if (!$stmt) {
                            throw new Exception('Unable to create user account.');
                        }

                        mysqli_stmt_bind_param(
                            $stmt,
                            'ssss',
                            $name,
                            $email,
                            $hashed_password,
                            $role
                        );

                        if (!mysqli_stmt_execute($stmt)) {
                            mysqli_stmt_close($stmt);
                            throw new Exception('Registration failed. Please try again.');
                        }

                        $new_user_id = mysqli_insert_id($conn);

                        mysqli_stmt_close($stmt);

                        /* -----------------------------------------
                           COMPANY VERIFICATION DATA
                        ----------------------------------------- */

                        if ($role === 'company') {

                            $gstCertificate = saveUploadedFile(
                                $_FILES['gst_certificate'],
                                $uploadDir,
                                $new_user_id,
                                'gst'
                            );

                            if ($gstCertificate === false || $gstCertificate === '') {
                                throw new Exception('GST certificate upload failed. Use PDF/JPG/PNG up to 5 MB.');
                            }

                            $savedFiles[] = $gstCertificate;


                            $companyRegistration = saveUploadedFile(
                                $_FILES['company_registration_document'],
                                $uploadDir,
                                $new_user_id,
                                'registration'
                            );

                            if ($companyRegistration === false || $companyRegistration === '') {
                                throw new Exception('Company registration / CIN document upload failed. Use PDF/JPG/PNG up to 5 MB.');
                            }

                            $savedFiles[] = $companyRegistration;


                            $panDocument = saveUploadedFile(
                                $_FILES['pan_document'],
                                $uploadDir,
                                $new_user_id,
                                'pan'
                            );

                            if ($panDocument === false || $panDocument === '') {
                                throw new Exception('PAN document upload failed. Use PDF/JPG/PNG up to 5 MB.');
                            }

                            $savedFiles[] = $panDocument;


                            /*
                             * Store only relative filenames in DB.
                             * The company remains Pending until admin review.
                             */

                            $verify_sql = "
                                INSERT INTO company_verifications
                                (
                                    user_id,
                                    gstin,
                                    pan,
                                    gst_certificate,
                                    company_registration_document,
                                    pan_document,
                                    gst_status,
                                    pan_status,
                                    email_verified,
                                    admin_status,
                                    admin_remark
                                )

                                VALUES
                                (
                                    ?, ?, ?, ?, ?, ?,
                                    'Pending',
                                    'Matched',
                                    0,
                                    'Pending',
                                    NULL
                                )
                            ";

                            $verify_stmt = mysqli_prepare(
                                $conn,
                                $verify_sql
                            );

                            if (!$verify_stmt) {
                                throw new Exception('Unable to save company verification details.');
                            }

                            mysqli_stmt_bind_param(
                                $verify_stmt,
                                'isssss',
                                $new_user_id,
                                $gstin,
                                $pan,
                                $gstCertificate,
                                $companyRegistration,
                                $panDocument
                            );

                            if (!mysqli_stmt_execute($verify_stmt)) {
                                mysqli_stmt_close($verify_stmt);
                                throw new Exception('Unable to save company verification details.');
                            }

                            mysqli_stmt_close($verify_stmt);
                        }

                        mysqli_commit($conn);

                        if ($role === 'company') {
                            header(
                                'Location: register.php?registered=1&role=company'
                            );
                        } else {
                            header(
                                'Location: register.php?registered=1&role=student'
                            );
                        }

                        exit();

                    } catch (Throwable $e) {

                        mysqli_rollback($conn);

                        foreach ($savedFiles as $savedFile) {
                            deleteUploadedFile($uploadDir, $savedFile);
                        }

                        $message = $e->getMessage();
                        $message_type = 'error';
                    }
                }
            }
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
        Register - Digital Internship
    </title>

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

    <style>

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        .register-container {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 35px 15px;
        }

        .register-card {
            width: 620px;
            max-width: 95%;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-sizing: border-box;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .register-card h1 {
            text-align: center;
            margin-bottom: 5px;
        }

        .register-card h2 {
            text-align: center;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            margin-bottom: 25px;
            color: #666;
        }

        .company-notice {
            background: #eef3ff;
            border-left: 4px solid #667eea;
            color: #334155;
            padding: 14px 16px;
            border-radius: 9px;
            margin-bottom: 25px;
            line-height: 1.6;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .required {
            color: #dc3545;
        }

        .form-group input {
            width: 100%;
            padding: 14px;
            box-sizing: border-box;
            border: 1px solid #d5d5d5;
            border-radius: 10px;
            font-size: 15px;
            outline: none;
        }

        .form-group input:focus {
            border-color: #667eea;
        }

        .help-text {
            display: block;
            margin-top: 6px;
            color: #777;
            font-size: 12px;
            line-height: 1.5;
        }

        .file-note {
            background: #fff8e1;
            color: #7a5b00;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 13px;
        }

        .register-btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            background: #667eea;
            color: white;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
        }

        .register-btn:hover {
            opacity: 0.92;
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
        }

        .login-link a {
            text-decoration: none;
            font-weight: bold;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            line-height: 1.5;
        }

        .message.success {
            background: #dff5e3;
            color: #176b2c;
        }

        .message.error {
            background: #ffe1e1;
            color: #a40000;
        }

        .verification-flow {
            background: #f7f8ff;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 22px;
            color: #4b5563;
            font-size: 13px;
            line-height: 1.7;
        }

        .verification-flow strong {
            color: #667eea;
        }

        @media (max-width: 650px) {
            .register-card {
                padding: 25px;
            }
        }

    </style>

</head>

<body>

<div class="register-container">

    <div class="register-card">

        <h1>
            Digital Internship
        </h1>

        <h2>
            Create Account
        </h2>

        <?php if ($role === 'company'): ?>

            <p class="subtitle">
                Register your company for verification
            </p>

            <div class="company-notice">
                <strong>Company verification:</strong>
                GSTIN, PAN and company documents are checked first.
                After registration, the company remains <strong>Pending</strong>
                until an administrator reviews and approves it.
            </div>

            <div class="verification-flow">
                <strong>Verification flow:</strong><br>
                GSTIN format + checksum → GSTIN/PAN match → document upload → Admin approval
            </div>

        <?php else: ?>

            <p class="subtitle">
                Create your student account
            </p>

        <?php endif; ?>


        <?php if ($message !== ''): ?>

            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>


        <form
            method="POST"
            action="register.php<?php echo $role === 'company' ? '?role=company' : ''; ?>"
            enctype="multipart/form-data"
            autocomplete="off"
        >

            <input
                type="hidden"
                name="role"
                value="<?php echo htmlspecialchars($role); ?>"
            >


            <!-- Full Name / Company Name -->

            <div class="form-group">

                <label for="name">
                    <?php echo $role === 'company' ? 'Company Name' : 'Full Name'; ?>
                    <span class="required">*</span>
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="<?php echo $role === 'company' ? 'Enter company name' : 'Enter your full name'; ?>"
                    value="<?php echo htmlspecialchars($name); ?>"
                    required
                >

            </div>


            <!-- Email -->

            <div class="form-group">

                <label for="email">
                    <?php echo $role === 'company' ? 'Official Company Email' : 'Email Address'; ?>
                    <span class="required">*</span>
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="<?php echo $role === 'company' ? 'hr@company.com' : 'Enter your email'; ?>"
                    value="<?php echo htmlspecialchars($email); ?>"
                    required
                >

                <?php if ($role === 'company'): ?>
                    <small class="help-text">
                        Company registration uses an official company-domain email.
                        Gmail/Yahoo/Outlook-type free email addresses are not accepted.
                    </small>
                <?php endif; ?>

            </div>


            <!-- Password -->

            <div class="form-group">

                <label for="password">
                    Password
                    <span class="required">*</span>
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    minlength="6"
                    required
                >

            </div>


            <?php if ($role === 'company'): ?>

                <!-- GSTIN -->

                <div class="form-group">

                    <label for="gstin">
                        GSTIN
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="gstin"
                        name="gstin"
                        maxlength="15"
                        minlength="15"
                        pattern="[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][1-9A-Za-z]Z[0-9A-Za-z]"
                        placeholder="15-character GSTIN"
                        value="<?php echo htmlspecialchars($_POST['gstin'] ?? ''); ?>"
                        required
                        style="text-transform:uppercase;"
                    >

                    <small class="help-text">
                        Example format: 15-character GSTIN. The system also checks the GSTIN checksum.
                    </small>

                </div>


                <!-- PAN -->

                <div class="form-group">

                    <label for="pan">
                        PAN
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="pan"
                        name="pan"
                        maxlength="10"
                        minlength="10"
                        pattern="[A-Za-z]{5}[0-9]{4}[A-Za-z]"
                        placeholder="10-character PAN"
                        value="<?php echo htmlspecialchars($_POST['pan'] ?? ''); ?>"
                        required
                        style="text-transform:uppercase;"
                    >

                    <small class="help-text">
                        PAN is compared with the PAN embedded inside the GSTIN.
                    </small>

                </div>


                <div class="file-note">
                    <strong>Document rules:</strong>
                    PDF/JPG/PNG only, maximum 5 MB per file.
                </div>


                <!-- GST Certificate -->

                <div class="form-group">

                    <label for="gst_certificate">
                        GST Certificate
                        <span class="required">*</span>
                    </label>

                    <input
                        type="file"
                        id="gst_certificate"
                        name="gst_certificate"
                        accept=".pdf,.jpg,.jpeg,.png"
                        required
                    >

                </div>


                <!-- Company Registration / CIN -->

                <div class="form-group">

                    <label for="company_registration_document">
                        Company Registration / CIN Document
                        <span class="required">*</span>
                    </label>

                    <input
                        type="file"
                        id="company_registration_document"
                        name="company_registration_document"
                        accept=".pdf,.jpg,.jpeg,.png"
                        required
                    >

                </div>


                <!-- PAN Document -->

                <div class="form-group">

                    <label for="pan_document">
                        PAN Document
                        <span class="required">*</span>
                    </label>

                    <input
                        type="file"
                        id="pan_document"
                        name="pan_document"
                        accept=".pdf,.jpg,.jpeg,.png"
                        required
                    >

                </div>

            <?php endif; ?>


            <!-- Create Account -->

            <button
                type="submit"
                class="register-btn"
            >
                <?php echo $role === 'company' ? 'Submit Company Registration' : 'Create Account'; ?>
            </button>

        </form>


        <p class="login-link">

            Already have an account?

            <a href="login.php">
                Login
            </a>

        </p>


        <?php if ($role === "student"): ?>

            <p class="login-link" style="margin-top: 12px;">

                Are you a company?

                <a href="register.php?role=company">
                    Register as Company
                </a>

            </p>

        <?php else: ?>

            <p class="login-link" style="margin-top: 12px;">

                Are you a student?

                <a href="register.php">
                    Register as Student
                </a>

            </p>

        <?php endif; ?>

    </div>

</div>


<script>

/* Automatically convert GSTIN/PAN input to uppercase. */

const gstinInput = document.getElementById('gstin');
const panInput = document.getElementById('pan');

if (gstinInput) {
    gstinInput.addEventListener('input', function () {
        this.value = this.value.toUpperCase();
    });
}

if (panInput) {
    panInput.addEventListener('input', function () {
        this.value = this.value.toUpperCase();
    });
}

</script>

</body>
</html>
