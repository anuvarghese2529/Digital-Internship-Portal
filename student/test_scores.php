<?php
session_start();
require_once '../config/db.php';

/* STUDENT LOGIN CHECK */
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'student'
) {
    header('Location: ../login.php');
    exit();
}

$student_id = (int) $_SESSION['user_id'];

/* FORMAT SECONDS AS MM:SS OR HH:MM:SS */
function formatTestTime($seconds)
{
    if ($seconds === null || $seconds === '') {
        return 'Not Available';
    }

    $seconds = max(0, (int) $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remainingSeconds = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }

    return sprintf('%02d:%02d', $minutes, $remainingSeconds);
}

/* GET SCORES: NEWEST DATE/TIME FIRST; ID BREAKS SAME-TIME TIES */
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
    INNER JOIN applications a ON tr.application_id = a.id
    INNER JOIN internships i ON a.internship_id = i.id
    WHERE tr.user_id = ?
    ORDER BY tr.created_at DESC, tr.id DESC
";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    die('Unable to load test scores. Please check the database query.');
}

mysqli_stmt_bind_param($stmt, 'i', $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Test Scores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6fb; color: #202938; }
        .page-header { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 5px 20px rgba(20,35,70,.07); }
        .page-title { color: #526fe5; font-weight: 750; margin-bottom: 6px; }
        .table-card { background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 5px 20px rgba(20,35,70,.07); }
        .scores-table { margin: 0; min-width: 1050px; vertical-align: middle; }
        .scores-table thead th { background: #3549a5; color: #fff; border: 0; padding: 15px 13px; white-space: nowrap; }
        .scores-table tbody td { padding: 14px 13px; border-color: #e9edf5; }
        .scores-table tbody tr:hover { background: #f5f7ff; }
        .internship-name { font-weight: 700; color: #26366f; }
        .company-name { color: #687386; font-size: .9rem; margin-top: 4px; }
        .score-value { font-weight: 750; white-space: nowrap; }
        .percentage-pill { display: inline-block; min-width: 58px; text-align: center; border-radius: 20px; padding: 5px 10px; font-weight: 700; }
        .percentage-high { color: #146c43; background: #d1e7dd; }
        .percentage-medium { color: #664d03; background: #fff3cd; }
        .percentage-low { color: #b02a37; background: #f8d7da; }
        .result-badge { display: inline-block; border-radius: 20px; padding: 6px 11px; font-size: .85rem; font-weight: 700; }
        .passed { background: #d1e7dd; color: #146c43; }
        .failed { background: #f8d7da; color: #b02a37; }
        .time-cell { white-space: nowrap; font-variant-numeric: tabular-nums; }
        .date-cell { white-space: nowrap; }
        @media (max-width: 768px) { .page-header { padding: 18px; } .page-header .top-actions { margin-top: 16px; } }
    </style>
</head>
<body>
<div class="container-fluid px-3 px-lg-4 py-4">
    <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h1 class="page-title">📝 My Test Scores</h1>
            <p class="text-muted mb-0">Your mock-test attempts, scores and time details, newest first.</p>
        </div>
        <div class="top-actions">
            <a href="dashboard.php" class="btn btn-primary">← Back to Dashboard</a>
        </div>
    </div>

    <div class="table-card">
        <?php if ($result && mysqli_num_rows($result) > 0): ?>
            <div class="table-responsive">
                <table class="table scores-table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Test Taken</th>
                        <th>Internship / Company</th>
                        <th>Level</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th>Time Limit</th>
                        <th>Time Taken</th>
                        <th>Result</th>
                        <th>Score Category</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php $rowNumber = 1; ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php
                            $percentage = (int) $row['percentage'];
                            $passed = (int) $row['passed'] === 1;
                            $category = $percentage >= 70 ? 'High Score' : ($percentage >= 50 ? 'Average Score' : 'Low Score');
                            $percentageClass = $percentage >= 70 ? 'percentage-high' : ($percentage >= 50 ? 'percentage-medium' : 'percentage-low');
                        ?>
                        <tr>
                            <td><?= $rowNumber++ ?></td>
                            <td class="date-cell">
                                <?php
                                    $dateValue = strtotime($row['created_at'] ?? '');
                                    echo $dateValue ? htmlspecialchars(date('d-m-Y h:i A', $dateValue)) : 'Not Available';
                                ?>
                            </td>
                            <td>
                                <div class="internship-name"><?= htmlspecialchars($row['internship_title'] ?? 'Internship') ?></div>
                                <div class="company-name"><?= htmlspecialchars($row['company_name'] ?? 'Company not available') ?></div>
                            </td>
                            <td><span class="badge text-bg-primary">Level <?= (int) $row['level'] ?></span></td>
                            <td class="score-value"><?= (int) $row['score'] ?> / <?= (int) $row['total_questions'] ?></td>
                            <td><span class="percentage-pill <?= $percentageClass ?>"><?= $percentage ?>%</span></td>
                            <td class="time-cell"><?= htmlspecialchars(formatTestTime($row['time_limit_seconds'])) ?></td>
                            <td class="time-cell"><?= htmlspecialchars(formatTestTime($row['time_taken_seconds'])) ?></td>
                            <td><span class="result-badge <?= $passed ? 'passed' : 'failed' ?>"><?= $passed ? 'Passed' : 'Failed' ?></span></td>
                            <td><span class="percentage-pill <?= $percentageClass ?>"><?= htmlspecialchars($category) ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center p-5">
                <h5>No mock test scores available yet.</h5>
                <p class="text-muted mb-0">Your completed mock tests will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
<?php
mysqli_stmt_close($stmt);
?>
