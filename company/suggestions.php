<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Check login and company role
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if (($_SESSION['user_role'] ?? '') !== 'company') {
    header('Location: ../login.php');
    exit();
}

// db.php should provide a MySQLi connection named $conn
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection error. Please check config/db.php and make sure it creates a MySQLi connection named $conn.');
}

mysqli_set_charset($conn, 'utf8mb4');
$company_id = (int) $_SESSION['user_id'];
$message = '';
$message_type = 'success';

// Ensure the table exists even if the admin suggestions page has not been opened yet.
$create_table_sql = "CREATE TABLE IF NOT EXISTS internship_suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    skills VARCHAR(500) NOT NULL,
    location VARCHAR(255) DEFAULT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('Pending','Accepted','Rejected') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!mysqli_query($conn, $create_table_sql)) {
    die('Unable to prepare internship suggestions table: ' . htmlspecialchars(mysqli_error($conn)));
}

// Process Accept / Reject actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $suggestion_id = (int) ($_POST['suggestion_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($suggestion_id <= 0 || !in_array($action, ['Accepted', 'Rejected'], true)) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'error';
    } else {
        $update_sql = "UPDATE internship_suggestions
                       SET status = ?
                       WHERE id = ? AND company_id = ? AND status = 'Pending'";
        $update_stmt = mysqli_prepare($conn, $update_sql);

        if (!$update_stmt) {
            $message = 'Database error while preparing update: ' . mysqli_error($conn);
            $message_type = 'error';
        } else {
            mysqli_stmt_bind_param($update_stmt, 'sii', $action, $suggestion_id, $company_id);

            if (mysqli_stmt_execute($update_stmt)) {
                if (mysqli_stmt_affected_rows($update_stmt) > 0) {
                    $message = ($action === 'Accepted')
                        ? 'Suggestion accepted successfully.'
                        : 'Suggestion rejected successfully.';
                } else {
                    $message = 'This suggestion was not found for your company or it has already been processed.';
                    $message_type = 'error';
                }
            } else {
                $message = 'Unable to update suggestion: ' . mysqli_stmt_error($update_stmt);
                $message_type = 'error';
            }
            mysqli_stmt_close($update_stmt);
        }
    }
}

// Load suggestions for the currently logged-in company only.
$select_sql = "SELECT id, title, description, skills, location, start_date, end_date, status, created_at
               FROM internship_suggestions
               WHERE company_id = ?
               ORDER BY id DESC";
$select_stmt = mysqli_prepare($conn, $select_sql);

if (!$select_stmt) {
    die('Database error while loading suggestions: ' . htmlspecialchars(mysqli_error($conn)));
}

mysqli_stmt_bind_param($select_stmt, 'i', $company_id);
if (!mysqli_stmt_execute($select_stmt)) {
    die('Unable to load suggestions: ' . htmlspecialchars(mysqli_stmt_error($select_stmt)));
}

$result = mysqli_stmt_get_result($select_stmt);
if ($result === false) {
    die('Unable to read suggestion results. Check that PHP MySQLnd support is enabled.');
}

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Internship Suggestions - Company Portal</title>
<style>
*{box-sizing:border-box}body{font-family:Arial,sans-serif;background:#f4f6fb;margin:0;color:#222}
.container{width:94%;max-width:1250px;margin:30px auto}.header,.panel{background:#fff;padding:22px;border-radius:12px;margin-bottom:20px;box-shadow:0 4px 14px rgba(0,0,0,.07)}
h1{color:#5865d8;margin:0 0 8px}.back{display:inline-block;margin-top:10px;background:#5865d8;color:#fff;padding:10px 15px;text-decoration:none;border-radius:7px}
.message{padding:12px 15px;border-radius:7px;margin-bottom:15px;overflow-wrap:anywhere}.success{background:#e5f8eb;color:#176534}.error{background:#ffe8e8;color:#a51d1d}
.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;background:white}th,td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}th{background:#5865d8;color:#fff;white-space:nowrap}tr:hover td{background:#fafaff}.desc{min-width:220px;max-width:330px;white-space:pre-wrap;overflow-wrap:anywhere}.skills{min-width:150px;white-space:pre-wrap;overflow-wrap:anywhere}.badge{display:inline-block;padding:6px 10px;border-radius:20px;font-size:12px;font-weight:bold}.Pending{background:#fff2cc;color:#855d00}.Accepted{background:#dff7e7;color:#176534}.Rejected{background:#ffe2e2;color:#a51d1d}.btn{border:0;border-radius:6px;padding:8px 11px;color:#fff;cursor:pointer;margin:2px}.accept{background:#198754}.reject{background:#dc3545}.muted{color:#777}.empty{text-align:center;padding:30px;color:#666}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>Internship Suggestions from Admin</h1>
    <p>Admin suggest cheytha internships ivide kaanam. Pending suggestions accept cheyyukayo reject cheyyukayo cheyyam.</p>
    <a class="back" href="dashboard.php">Back to Dashboard</a>
  </div>

  <?php if ($message !== ''): ?>
    <div class="message <?= e($message_type) ?>"><?= e($message) ?></div>
  <?php endif; ?>

  <div class="panel">
    <h2>Suggestions</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>ID</th><th>Internship Title</th><th>Description</th><th>Required Skills</th><th>Location</th><th>Start Date</th><th>End Date</th><th>Suggested On</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php if (mysqli_num_rows($result) > 0): ?>
          <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
              <td><?= (int)$row['id'] ?></td>
              <td><?= e($row['title']) ?></td>
              <td class="desc"><?= e($row['description']) ?></td>
              <td class="skills"><?= e($row['skills']) ?></td>
              <td><?= e($row['location'] ?: 'Not specified') ?></td>
              <td><?= e($row['start_date']) ?></td>
              <td><?= e($row['end_date']) ?></td>
              <td><?= e($row['created_at']) ?></td>
              <td><span class="badge <?= e($row['status']) ?>"><?= e($row['status']) ?></span></td>
              <td>
                <?php if ($row['status'] === 'Pending'): ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('Accept this internship suggestion?');">
                    <input type="hidden" name="suggestion_id" value="<?= (int)$row['id'] ?>">
                    <input type="hidden" name="action" value="Accepted">
                    <button class="btn accept" type="submit">Accept</button>
                  </form>
                  <form method="post" style="display:inline" onsubmit="return confirm('Reject this internship suggestion?');">
                    <input type="hidden" name="suggestion_id" value="<?= (int)$row['id'] ?>">
                    <input type="hidden" name="action" value="Rejected">
                    <button class="btn reject" type="submit">Reject</button>
                  </form>
                <?php else: ?>
                  <span class="muted">No action</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="10" class="empty">No internship suggestions from admin yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
<?php mysqli_stmt_close($select_stmt); ?>
