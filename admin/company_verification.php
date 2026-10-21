<?php

session_start();

require_once __DIR__ . '/../config/db.php';


/* =========================================================
   ADMIN CHECK
   ========================================================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}


/* =========================================================
   ACTION
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);


    if ($id <= 0) {
        header("Location: company_verification.php");
        exit();
    }


    /* =====================================================
       GET CURRENT STATUS
       ===================================================== */

    $current_status = '';

    $stmt = mysqli_prepare(
        $conn,
        "SELECT admin_status
         FROM company_verifications
         WHERE id = ?"
    );

    if ($stmt) {

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $id
        );

        mysqli_stmt_execute($stmt);

        mysqli_stmt_bind_result(
            $stmt,
            $current_status
        );

        mysqli_stmt_fetch($stmt);

        mysqli_stmt_close($stmt);
    }


    /* =====================================================
       APPROVE
       ===================================================== */

    if ($action === 'approve') {

        /*
         * Approve ONLY if currently Pending
         */

        if ($current_status === 'Pending') {

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE company_verifications
                 SET admin_status = 'Approved',
                     reviewed_at = NOW()
                 WHERE id = ?
                 AND admin_status = 'Pending'"
            );

            if ($stmt) {

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $id
                );

                mysqli_stmt_execute($stmt);

                mysqli_stmt_close($stmt);
            }
        }
    }


    /* =====================================================
       REJECT
       ===================================================== */

    elseif ($action === 'reject') {

        /*
         * Reject ONLY if currently Pending
         */

        if ($current_status === 'Pending') {

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE company_verifications
                 SET admin_status = 'Rejected',
                     reviewed_at = NOW()
                 WHERE id = ?
                 AND admin_status = 'Pending'"
            );

            if ($stmt) {

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $id
                );

                mysqli_stmt_execute($stmt);

                mysqli_stmt_close($stmt);
            }
        }
    }


    /* =====================================================
       REMOVE
       ===================================================== */

    elseif ($action === 'remove') {

        /*
         * Remove ONLY Approved company
         */

        if ($current_status === 'Approved') {

            /* Get company user id */

            $company_user_id = 0;

            $stmt = mysqli_prepare(
                $conn,
                "SELECT user_id
                 FROM company_verifications
                 WHERE id = ?"
            );

            if ($stmt) {

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $id
                );

                mysqli_stmt_execute($stmt);

                mysqli_stmt_bind_result(
                    $stmt,
                    $company_user_id
                );

                mysqli_stmt_fetch($stmt);

                mysqli_stmt_close($stmt);
            }


            /* =================================================
               GET COMPANY INTERNSHIPS
               ================================================= */

            $internship_ids = [];

            $stmt = mysqli_prepare(
                $conn,
                "SELECT id
                 FROM internships
                 WHERE company_id = ?"
            );

            if ($stmt) {

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $company_user_id
                );

                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);

                while ($row = mysqli_fetch_assoc($result)) {

                    $internship_ids[] =
                        (int)$row['id'];
                }

                mysqli_stmt_close($stmt);
            }


            /* =================================================
               DELETE RELATED DATA
               ================================================= */

            foreach ($internship_ids as $internship_id) {

                mysqli_query(
                    $conn,
                    "DELETE FROM applications
                     WHERE internship_id = $internship_id"
                );


                mysqli_query(
                    $conn,
                    "DELETE FROM test_results
                     WHERE internship_id = $internship_id"
                );


                if (
                    mysqli_query(
                        $conn,
                        "SHOW TABLES LIKE 'interview_letters'"
                    )
                ) {

                    $check =
                        mysqli_query(
                            $conn,
                            "SHOW TABLES LIKE 'interview_letters'"
                        );

                    if (
                        $check &&
                        mysqli_num_rows($check) > 0
                    ) {

                        mysqli_query(
                            $conn,
                            "DELETE FROM interview_letters
                             WHERE internship_id = $internship_id"
                        );
                    }
                }


                mysqli_query(
                    $conn,
                    "DELETE FROM questions
                     WHERE internship_id = $internship_id"
                );
            }


            mysqli_query(
                $conn,
                "DELETE FROM internships
                 WHERE company_id = $company_user_id"
            );


            /* Delete suggestions */

            $check =
                mysqli_query(
                    $conn,
                    "SHOW TABLES LIKE 'internship_suggestions'"
                );

            if (
                $check &&
                mysqli_num_rows($check) > 0
            ) {

                mysqli_query(
                    $conn,
                    "DELETE FROM internship_suggestions
                     WHERE company_id = $company_user_id"
                );
            }


            /* Delete notifications */

            $check =
                mysqli_query(
                    $conn,
                    "SHOW TABLES LIKE 'notifications'"
                );

            if (
                $check &&
                mysqli_num_rows($check) > 0
            ) {

                mysqli_query(
                    $conn,
                    "DELETE FROM notifications
                     WHERE user_id = $company_user_id"
                );
            }


            /* Delete verification */

            mysqli_query(
                $conn,
                "DELETE FROM company_verifications
                 WHERE id = $id"
            );


            /* Delete company user */

            mysqli_query(
                $conn,
                "DELETE FROM users
                 WHERE id = $company_user_id
                 AND role = 'company'"
            );
        }
    }


    header("Location: company_verification.php");
    exit();
}


/* =========================================================
   GET COMPANY VERIFICATION RECORDS
   ========================================================= */

$sql = "
    SELECT
        cv.id AS verification_id,
        cv.user_id,
        u.name,
        u.email,
        cv.gstin,
        cv.pan,
        cv.gst_status,
        cv.pan_status,
        cv.admin_status,
        cv.admin_remark,
        cv.gst_certificate,
        cv.company_registration_document,
        cv.pan_document
    FROM company_verifications cv

    INNER JOIN users u
        ON u.id = cv.user_id

    WHERE u.role = 'company'

    ORDER BY
        CASE cv.admin_status
            WHEN 'Pending' THEN 1
            WHEN 'Rejected' THEN 2
            WHEN 'Approved' THEN 3
            ELSE 4
        END,

        cv.id DESC
";


$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Company Verification</title>


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


.container {
    width: 95%;
    max-width: 1400px;
    margin: 30px auto;
}


.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}


.header h1 {
    color: #667eea;
}


.back {
    text-decoration: none;
    background: #667eea;
    color: white;
    padding: 10px 16px;
    border-radius: 8px;
}


.table-box {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,.08);
    overflow-x: auto;
}


table {
    width: 100%;
    border-collapse: collapse;
}


th {
    background: #667eea;
    color: white;
    padding: 13px;
    text-align: left;
}


td {
    padding: 13px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}


tr:hover {
    background: #f8f9ff;
}


.status {
    display: inline-block;
    padding: 7px 11px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
}


.pending {
    background: #fff3cd;
    color: #856404;
}


.approved {
    background: #d4edda;
    color: #155724;
}


.rejected {
    background: #f8d7da;
    color: #721c24;
}


.btn {
    display: inline-block;
    border: none;
    padding: 8px 11px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: bold;
    margin: 2px;
    cursor: pointer;
}


.approve {
    background: #28a745;
    color: white;
}


.reject {
    background: #dc3545;
    color: white;
}


.remove {
    background: #343a40;
    color: white;
}


.documents a {
    display: block;
    margin: 3px 0;
    color: #667eea;
    font-size: 12px;
    text-decoration: none;
}


.no-data {
    text-align: center;
    padding: 30px;
    color: #777;
}

</style>

</head>


<body>


<div class="container">


    <div class="header">

        <h1>
            🏢 Company Verification
        </h1>

        <a
            href="dashboard.php"
            class="back"
        >
            ← Dashboard
        </a>

    </div>


    <div class="table-box">


        <table>


            <thead>

                <tr>

                    <th>ID</th>

                    <th>Company</th>

                    <th>Email</th>

                    <th>GSTIN</th>

                    <th>PAN</th>

                    <th>GST Status</th>

                    <th>PAN Status</th>

                    <th>Documents</th>

                    <th>Admin Status</th>

                    <th>Action</th>

                </tr>

            </thead>


            <tbody>


<?php if ($result && mysqli_num_rows($result) > 0): ?>


<?php while ($row = mysqli_fetch_assoc($result)): ?>


<?php

$status = $row['admin_status'] ?? 'Pending';

$verification_id =
    (int)$row['verification_id'];

?>


                <tr>


                    <td>
                        <?= $verification_id ?>
                    </td>


                    <td>
                        <strong>
                            <?= htmlspecialchars($row['name']) ?>
                        </strong>
                    </td>


                    <td>
                        <?= htmlspecialchars($row['email']) ?>
                    </td>


                    <td>
                        <?= htmlspecialchars($row['gstin'] ?? '-') ?>
                    </td>


                    <td>
                        <?= htmlspecialchars($row['pan'] ?? '-') ?>
                    </td>


                    <td>
                        <?= htmlspecialchars($row['gst_status'] ?? '-') ?>
                    </td>


                    <td>
                        <?= htmlspecialchars($row['pan_status'] ?? '-') ?>
                    </td>


                    <td class="documents">


<?php if (!empty($row['gst_certificate'])): ?>

                        <a
                            href="../assets/uploads/company_verification/<?= urlencode($row['gst_certificate']) ?>"
                            target="_blank"
                        >
                            📄 GST Certificate
                        </a>

<?php endif; ?>


<?php if (!empty($row['company_registration_document'])): ?>

                        <a
                            href="../assets/uploads/company_verification/<?= urlencode($row['company_registration_document']) ?>"
                            target="_blank"
                        >
                            📄 Company Registration
                        </a>

<?php endif; ?>


<?php if (!empty($row['pan_document'])): ?>

                        <a
                            href="../assets/uploads/company_verification/<?= urlencode($row['pan_document']) ?>"
                            target="_blank"
                        >
                            📄 PAN Document
                        </a>

<?php endif; ?>


<?php if (
    empty($row['gst_certificate']) &&
    empty($row['company_registration_document']) &&
    empty($row['pan_document'])
): ?>

                        -

<?php endif; ?>


                    </td>


                    <td>


<?php if ($status === 'Pending'): ?>

                        <span class="status pending">
                            ⏳ Pending
                        </span>


<?php elseif ($status === 'Approved'): ?>

                        <span class="status approved">
                            ✅ Approved
                        </span>


<?php elseif ($status === 'Rejected'): ?>

                        <span class="status rejected">
                            ❌ Rejected
                        </span>


<?php endif; ?>


                    </td>


                    <td>


<?php if ($status === 'Pending'): ?>


                        <!-- APPROVE -->

                        <form
                            method="POST"
                            style="display:inline;"
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="approve"
                            >

                            <input
                                type="hidden"
                                name="id"
                                value="<?= $verification_id ?>"
                            >

                            <button
                                type="submit"
                                class="btn approve"
                                onclick="return confirm('Approve this company?');"
                            >
                                ✅ Approve
                            </button>

                        </form>


                        <!-- REJECT -->

                        <form
                            method="POST"
                            style="display:inline;"
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="reject"
                            >

                            <input
                                type="hidden"
                                name="id"
                                value="<?= $verification_id ?>"
                            >

                            <button
                                type="submit"
                                class="btn reject"
                                onclick="return confirm('Reject this company?');"
                            >
                                ❌ Reject
                            </button>

                        </form>


<?php elseif ($status === 'Approved'): ?>


                        <!-- REMOVE -->

                        <form
                            method="POST"
                            style="display:inline;"
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="remove"
                            >

                            <input
                                type="hidden"
                                name="id"
                                value="<?= $verification_id ?>"
                            >

                            <button
                                type="submit"
                                class="btn remove"
                                onclick="return confirm('Remove this approved company permanently?');"
                            >
                                🗑 Remove Company
                            </button>

                        </form>


<?php elseif ($status === 'Rejected'): ?>


                        <span class="status rejected">
                            ❌ Rejected
                        </span>


<?php endif; ?>


                    </td>


                </tr>


<?php endwhile; ?>


<?php else: ?>


                <tr>

                    <td
                        colspan="10"
                        class="no-data"
                    >
                        No company verification records found.
                    </td>

                </tr>


<?php endif; ?>


            </tbody>


        </table>


    </div>


</div>


</body>

</html>