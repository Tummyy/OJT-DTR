<?php
// review-dtr.php — Supervisor DTR validation queue
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

requireLogin();

$reviewerId = currentStudentId();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }

    $action = empty($errors) ? ($_POST['form_action'] ?? '') : '';
    if ($action === 'review') {
        $recordId = (int)($_POST['record_id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        $reviewNote = trim($_POST['review_note'] ?? '');

        if (!in_array($decision, ['valid', 'invalid'], true)) {
            $errors[] = 'Invalid review decision.';
        }
        if (strlen($reviewNote) > 255) {
            $errors[] = 'Review note must be 255 characters or less.';
        }

        if (empty($errors) && $recordId > 0) {
          $ownershipSql =
            "SELECT r.id
             FROM dtr_records r
             INNER JOIN students s ON s.id = r.student_id
             WHERE r.id = ? AND s.supervisor_id = ?";
          $ownershipParams = [$recordId, $reviewerId];
          $ownershipSql .= " LIMIT 1";

          $ownStmt = $pdo->prepare($ownershipSql);
          $ownStmt->execute($ownershipParams);
          $target = $ownStmt->fetch();

          if (!$target) {
            $errors[] = 'You are not allowed to review this record.';
          } else {
            $stmt = $pdo->prepare(
              "UPDATE dtr_records
               SET status = ?, reviewed_by = ?, reviewed_at = NOW(), reviewed_note = ?
               WHERE id = ?"
            );
            $stmt->execute([$decision, $reviewerId, $reviewNote !== '' ? $reviewNote : null, $recordId]);

            logActivity($pdo, $reviewerId, 'review.dtr_decision', 'record=' . $recordId . '; decision=' . $decision);
            flash('success', 'DTR review decision saved.');
            header('Location: review-dtr.php?' . http_build_query($_GET));
            exit;
          }
        }
    }
}

    $studentsSql =
      "SELECT s.id, s.name, s.email, s.required_hours,
          COALESCE(SUM(CASE WHEN r.status='valid' THEN r.total_hours ELSE 0 END), 0) AS rendered_hours
       FROM students s
       LEFT JOIN dtr_records r ON r.student_id = s.id
       WHERE s.role='student' AND s.supervisor_id = ?";

    $studentsParams = [$reviewerId];

    $studentsSql .= " GROUP BY s.id, s.name, s.email, s.required_hours ORDER BY s.name ASC";
    $studentsStmt = $pdo->prepare($studentsSql);
    $studentsStmt->execute($studentsParams);
    $students = $studentsStmt->fetchAll();

$studentId = (int)($_GET['student_id'] ?? 0);
if ($studentId <= 0 && !empty($students)) {
    $studentId = (int)$students[0]['id'];
}

$month = normalizeMonthInput($_GET['month'] ?? date('Y-m'));
if ($month === '') {
    $month = date('Y-m');
}

$statusFilter = $_GET['status'] ?? 'incomplete';
if (!in_array($statusFilter, ['all', 'incomplete', 'valid', 'invalid'], true)) {
    $statusFilter = 'incomplete';
}

$startDate = $month . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

$where = 'WHERE r.record_date BETWEEN ? AND ?';
$params = [$startDate, $endDate];

$where .= ' AND s.supervisor_id = ?';
$params[] = $reviewerId;

if ($studentId > 0) {
    $where .= ' AND r.student_id = ?';
    $params[] = $studentId;
}

if ($statusFilter !== 'all') {
    $where .= ' AND r.status = ?';
    $params[] = $statusFilter;
}

$query =
    "SELECT r.*, s.name AS student_name, s.email AS student_email,
            reviewer.name AS reviewer_name
     FROM dtr_records r
     INNER JOIN students s ON s.id = r.student_id
     LEFT JOIN students reviewer ON reviewer.id = r.reviewed_by
     $where
     ORDER BY r.record_date DESC, r.id DESC
     LIMIT 400";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

$statsStmt = $pdo->prepare(
  "SELECT
    SUM(CASE WHEN r.status='incomplete' THEN 1 ELSE 0 END) AS pending_count,
    SUM(CASE WHEN r.status='valid' THEN 1 ELSE 0 END) AS approved_count,
    SUM(CASE WHEN r.status='invalid' THEN 1 ELSE 0 END) AS rejected_count
   FROM dtr_records r
   INNER JOIN students s ON s.id = r.student_id
   WHERE r.record_date BETWEEN ? AND ? AND s.supervisor_id = ?"
);
$statsParams = [$startDate, $endDate, $reviewerId];
$statsStmt->execute($statsParams);
$stats = $statsStmt->fetch() ?: ['pending_count' => 0, 'approved_count' => 0, 'rejected_count' => 0];

$pageTitle = 'Review DTR';
$activePage = 'review-dtr';
include 'includes/header.php';
?>

<?php $msg = flash('success'); if ($msg): ?>
<div class="alert alert-success">✓ <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div>
    <h1>DTR Validation Queue</h1>
    <p>Review, approve, or reject student time records.</p>
  </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e): ?><div>⚠ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="stats-grid" style="margin-bottom:1.5rem;">
  <div class="stat-card warning">
    <div class="stat-label">Pending Review</div>
    <div class="stat-value"><?= (int)$stats['pending_count'] ?></div>
    <div class="stat-sub">Needs supervisor validation</div>
  </div>
  <div class="stat-card success">
    <div class="stat-label">Approved</div>
    <div class="stat-value"><?= (int)$stats['approved_count'] ?></div>
    <div class="stat-sub">Counted toward OJT totals</div>
  </div>
  <div class="stat-card accent">
    <div class="stat-label">Rejected</div>
    <div class="stat-value"><?= (int)$stats['rejected_count'] ?></div>
    <div class="stat-sub">Sent back for correction</div>
  </div>
</div>

<div class="report-filters row g-3">
  <div class="form-group col-12 col-md-6 col-lg-3">
    <label class="form-label">Student</label>
    <select id="filterStudent" class="form-control">
      <?php foreach ($students as $s): ?>
      <option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === $studentId ? 'selected' : '' ?>>
        <?= htmlspecialchars($s['name']) ?>
        <?php if ((float)$s['rendered_hours'] >= (float)$s['required_hours']): ?>
          (Completed)
        <?php endif; ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-group col-12 col-md-6 col-lg-3">
    <label class="form-label">Month</label>
    <input type="month" id="filterMonth" class="form-control" value="<?= htmlspecialchars($month) ?>">
  </div>

  <div class="form-group col-12 col-md-6 col-lg-3">
    <label class="form-label">Status</label>
    <select id="filterStatus" class="form-control">
      <option value="incomplete" <?= $statusFilter==='incomplete' ? 'selected' : '' ?>>Pending</option>
      <option value="valid" <?= $statusFilter==='valid' ? 'selected' : '' ?>>Approved</option>
      <option value="invalid" <?= $statusFilter==='invalid' ? 'selected' : '' ?>>Rejected</option>
      <option value="all" <?= $statusFilter==='all' ? 'selected' : '' ?>>All</option>
    </select>
  </div>

  <div class="form-group col-12 col-md-6 col-lg-3 d-flex align-items-end">
    <button type="button" class="btn btn-primary" onclick="applyReviewFilters()">Apply Filters</button>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <?php if (empty($records)): ?>
      <div class="empty-state">
        <div class="empty-state-icon">✅</div>
        <h3>No records found</h3>
        <p>No entries match the current filters.</p>
      </div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Student</th>
            <th>Time In</th>
            <th>Time Out</th>
            <th>Regular</th>
            <th>Overtime</th>
            <th>Total</th>
            <th>Status</th>
            <th>Review Note</th>
            <th>Decision</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $r): ?>
          <tr>
            <td><strong><?= fmtDate($r['record_date']) ?></strong></td>
            <td>
              <div><?= htmlspecialchars($r['student_name']) ?></div>
              <small class="text-muted"><?= htmlspecialchars($r['student_email']) ?></small>
            </td>
            <td class="td-mono"><?= fmtTime((string)$r['time_in']) ?></td>
            <td class="td-mono"><?= fmtTime((string)$r['time_out']) ?></td>
            <td class="td-mono text-navy fw-600"><?= hoursToHM((float)$r['regular_hours']) ?></td>
            <td class="td-mono"><?= (float)$r['overtime_hours'] > 0 ? hoursToHM((float)$r['overtime_hours']) : '—' ?></td>
            <td class="td-mono fw-bold"><?= hoursToHM((float)$r['total_hours']) ?></td>
            <td>
              <?= statusBadge((string)$r['status']) ?>
              <?php if (!empty($r['reviewed_by'])): ?>
                <div class="text-muted" style="font-size:.76rem;margin-top:.2rem;">
                  by <?= htmlspecialchars((string)($r['reviewer_name'] ?? 'Reviewer')) ?>
                </div>
              <?php endif; ?>
            </td>
            <td style="max-width:220px;">
              <?= htmlspecialchars((string)($r['reviewed_note'] ?? '—')) ?>
            </td>
            <td>
              <form method="POST" action="review-dtr.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" class="d-flex gap-1 flex-wrap" style="min-width:220px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="form_action" value="review">
                <input type="hidden" name="record_id" value="<?= (int)$r['id'] ?>">
                <input type="text" name="review_note" class="form-control" placeholder="Optional note" maxlength="255" style="min-width:170px;padding:.35rem .55rem;">
                <button type="submit" name="decision" value="valid" class="btn btn-success btn-sm">Approve</button>
                <button type="submit" name="decision" value="invalid" class="btn btn-danger btn-sm">Reject</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<script>
function applyReviewFilters() {
  const studentId = document.getElementById('filterStudent').value;
  const month = document.getElementById('filterMonth').value;
  const status = document.getElementById('filterStatus').value;

  let url = 'review-dtr.php?student_id=' + encodeURIComponent(studentId);
  if (month) url += '&month=' + encodeURIComponent(month);
  if (status) url += '&status=' + encodeURIComponent(status);
  window.location = url;
}
</script>

<?php include 'includes/footer.php'; ?>
