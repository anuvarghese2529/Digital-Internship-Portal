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
   HELPER
   ========================================================= */

function tableExists(mysqli $conn, string $table): bool
{
    $table = mysqli_real_escape_string($conn, $table);

    $result = mysqli_query(
        $conn,
        "SHOW TABLES LIKE '$table'"
    );

    return ($result && mysqli_num_rows($result) > 0);
}


/* =========================================================
   APPROVE / REJECT
   ========================================================= */

if (
    isset($_GET['action']) &&
    isset($_GET['id'])
) {

    $action = $_GET['action'];
    $user_id = (int)$_GET['id'];


    /* =========================
       APPROVE COMPANY
       ========================= */

    if ($action === 'approve') {

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE company_verifications
             SET admin_status = 'Approved',
                 reviewed_at = NOW()
             WHERE user_id = ?
             AND admin_status = 'Pending'"
        );

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $user_id
            );

            mysqli_stmt_execute($stmt);

            mysqli_stmt_close($stmt);
        }


        /* Notification */

        if (tableExists($conn, 'notifications')) {

            $message = "Your company verification has been approved by the administrator.";

            $link = "../company/dashboard.php";

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO notifications
                (user_id, message, link)
                VALUES (?, ?, ?)"
            );

            if ($stmt) {

                mysqli_stmt_bind_param(
                    $stmt,
                    "iss",
                    $user_id,
                    $message,
                    $link
                );

                mysqli_stmt_execute($stmt);

                mysqli_stmt_close($stmt);
            }
        }
    }


    /* =========================
       REJECT COMPANY
       ========================= */

    elseif ($action === 'reject') {

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE company_verifications
             SET admin_status = 'Rejected',
                 reviewed_at = NOW()
             WHERE user_id = ?
             AND admin_status = 'Pending'"
        );

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $user_id
            );

            mysqli_stmt_execute($stmt);

            mysqli_stmt_close($stmt);
        }


        /* Notification */

        if (tableExists($conn, 'notifications')) {

            $message = "Your company verification has been rejected by the administrator.";

            $link = "../company/dashboard.php";

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO notifications
                (user_id, message, link)
                VALUES (?, ?, ?)"
            );

            if ($stmt) {

                mysqli_stmt_bind_param(
                    $stmt,
                    "iss",
                    $user_id,
                    $message,
                    $link
                );

                mysqli_stmt_execute($stmt);

                mysqli_stmt_close($stmt);
            }
        }
    }


    /* =========================
       REMOVE COMPANY
       ONLY APPROVED COMPANY
       ========================= */

    elseif ($action === 'remove') {

        $status = null;


        $stmt = mysqli_prepare(
            $conn,
            "SELECT admin_status
             FROM company_verifications
             WHERE user_id = ?"
        );

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $user_id
            );

            mysqli_stmt_execute($stmt);

            mysqli_stmt_bind_result(
                $stmt,
                $status
            );

            mysqli_stmt_fetch($stmt);

            mysqli_stmt_close($stmt);
        }


        /* Remove ONLY approved company */

        if ($status === 'Approved') {

            /* Get internships */

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
                    $user_id
                );

                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);

                while ($row = mysqli_fetch_assoc($result)) {
                    $internship_ids[] = (int)$row['id'];
                }

                mysqli_stmt_close($stmt);
            }


            /* Delete internship related data */

            foreach ($internship_ids as $internship_id) {

                /* Applications */

                if (tableExists($conn, 'applications')) {

                    mysqli_query(
                        $conn,
                        "DELETE FROM applications
                         WHERE internship_id = $internship_id"
                    );
                }


                /* Test results */

                if (tableExists($conn, 'test_results')) {

                    mysqli_query(
                        $conn,
                        "DELETE FROM test_results
                         WHERE internship_id = $internship_id"
                    );
                }


                /* Interview letters */

                if (tableExists($conn, 'interview_letters')) {

                    mysqli_query(
                        $conn,
                        "DELETE FROM interview_letters
                         WHERE internship_id = $internship_id"
                    );
                }


                /* Questions */

                if (tableExists($conn, 'questions')) {

                    mysqli_query(
                        $conn,
                        "DELETE FROM questions
                         WHERE internship_id = $internship_id"
                    );
                }


                /* Internship */

                mysqli_query(
                    $conn,
                    "DELETE FROM internships
                     WHERE id = $internship_id"
                );
            }


            /* Suggestions */

            if (tableExists($conn, 'internship_suggestions')) {

                mysqli_query(
                    $conn,
                    "DELETE FROM internship_suggestions
                     WHERE company_id = $user_id"
                );
            }


            /* Company verification documents */

            $documents = [];

            $stmt = mysqli_prepare(
                $conn,
                "SELECT gst_certificate,
                        company_registration_document,
                        pan_document
                 FROM company_verifications
                 WHERE user_id = ?"
            );

            if ($stmt) {

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $user_id
                );

                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);

                if ($row = mysqli_fetch_assoc($result)) {

                    $documents[] = $row['gst_certificate'];
                    $documents[] = $row['company_registration_document'];
                    $documents[] = $row['pan_document'];
                }

                mysqli_stmt_close($stmt);
            }


            /* Delete company documents */

            $upload_folder =
                __DIR__ . '/../assets/uploads/company_verification/';


            foreach ($documents as $document) {

                if (!empty($document)) {

                    $file = $upload_folder . $document;

                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }


            /* Delete verification */

            mysqli_query(
                $conn,
                "DELETE FROM company_verifications
                 WHERE user_id = $user_id"
            );


            /* Delete notifications */

            if (tableExists($conn, 'notifications')) {

                mysqli_query(
                    $conn,
                    "DELETE FROM notifications
                     WHERE user_id = $user_id"
                );
            }


            /* Delete company user */

            mysqli_query(
                $conn,
                "DELETE FROM users
                 WHERE id = $user_id
                 AND role = 'company'"
            );
        }
    }


    /* Go back */

    header("Location: companies.php");
    exit();
}


/* =========================================================
   GET COMPANIES
   ========================================================= */

$sql = "
    SELECT
        u.id,
        u.name,
        u.email,
        cv.gstin,
        cv.pan,
        cv.gst_status,
        cv.pan_status,
        cv.admin_status,
        cv.admin_remark
    FROM users u

    LEFT JOIN company_verifications cv
        ON cv.user_id = u.id

    WHERE u.role = 'company'

    ORDER BY u.id DESC
";


$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Manage Companies</title>


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

    max-width: 1300px;

    margin: 30px auto;

}

.top {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 20px;

}

.top h1 {

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

    border-radius: 12px;

    padding: 20px;

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

    padding: 6px 10px;

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

    text-decoration: none;

    padding: 7px 10px;

    border-radius: 6px;

    font-size: 12px;

    font-weight: bold;

    margin: 2px;

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

.info {

    color: #777;

    font-size: 12px;

}

.no-data {

    text-align: center;

    color: #777;

    padding: 30px;

}

</style>

</head>


<body>


<div class="container">


    <div class="top">

        <h1>
            🏢 Manage Companies
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

                    <th>Admin Status</th>

                    <th>Action</th>

                </tr>

            </thead>


            <tbody>


<?php if ($result && mysqli_num_rows($result) > 0): ?>


<?php while ($row = mysqli_fetch_assoc($result)): ?>


                <tr>


                    <td>
                        <?= (int)$row['id'] ?>
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


                    <!-- ADMIN STATUS -->

                    <td>


<?php
$status = $row['admin_status'] ?? 'Pending';


if ($status === 'Approved'):

?>

                        <span class="status approved">
                            ✅ Approved
                        </span>


<?php elseif ($status === 'Rejected'): ?>

                        <span class="status rejected">
                            ❌ Rejected
                        </span>


<?php else: ?>

                        <span class="status pending">
                            ⏳ Pending
                        </span>


<?php endif; ?>


                    </td>


                    <!-- ACTION -->

                    <td>


<?php if ($status === 'Pending'): ?>


                        <!-- ONLY PENDING GETS APPROVE / REJECT -->

                        <a
                            href="companies.php?action=approve&id=<?= (int)$row['id'] ?>"
                            class="btn approve"
                            onclick="return confirm('Approve this company?');"
                        >
                            ✅ Approve
                        </a>


                        <a
                            href="companies.php?action=reject&id=<?= (int)$row['id'] ?>"
                            class="btn reject"
                            onclick="return confirm('Reject this company?');"
                        >
                            ❌ Reject
                        </a>


<?php elseif ($status === 'Approved'): ?>


                        <!-- APPROVED GETS REMOVE -->

                        <a
                            href="companies.php?action=remove&id=<?= (int)$row['id'] ?>"
                            class="btn remove"
                            onclick="return confirm('Remove this approved company permanently?');"
                        >
                            🗑 Remove Company
                        </a>


<?php elseif ($status === 'Rejected'): ?>


                        <!-- REJECTED GETS NO APPROVE/REJECT -->

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
                        colspan="9"
                        class="no-data"
                    >
                        No companies found.
                    </td>

                </tr>


<?php endif; ?>


            </tbody>


        </table>


    </div>


</div>


</body>

</html>