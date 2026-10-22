<?php
// dtr-log.php — Daily Time Record: List + Add/Edit
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

requireLogin();

$studentId = currentStudentId();
$schedule  = getSchedule($pdo, $studentId);
$holidays  = getHolidaysMap($pdo, $studentId);
$action    = $_GET['action'] ?? 'list';
$editId    = (int)($_GET['id'] ?? 0);
$errors    = [];
$vals      = [];

// ── Handle POST ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $editId = (int)($_POST['form_edit_id'] ?? $editId);

  if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Your session expired. Please refresh the page and try again.';
  }

  $postAction = empty($errors) ? ($_POST['form_action'] ?? '') : '';

    if ($postAction === 'save') {
        $vals = [
            'record_date'    => $_POST['record_date'] ?? '',
            'time_in'        => $_POST['time_in'] ?? '',
            'time_out'       => $_POST['time_out'] ?? '',
            'break_duration' => (float)($_POST['break_duration'] ?? $schedule['break_duration']),
            'notes'          => trim($_POST['notes'] ?? ''),
        ];

        if (empty($vals['record_date']))  $errors[] = 'Date is required.';
        if (empty($vals['time_in']))      $errors[] = 'Time In is required.';
        if (empty($vals['time_out']))     $errors[] = 'Time Out is required.';

        // Validate date format
        if (!empty($vals['record_date']) && !isValidDateYmd($vals['record_date'])) {
            $errors[] = 'Invalid date format.';
        }

        // Check if holiday (skip if special_working)
        if (empty($errors)) {
            $dayType = getDayType($vals['record_date'], $holidays, array_map('intval', explode(',', $schedule['rest_days'] ?? '0,6')));
            if ($dayType === 'holiday_regular' || $dayType === 'holiday_special_non_working') {
                $errors[] = 'This date is a holiday (' . ($holidays[$vals['record_date']]['name'] ?? '') . '). DTR cannot be logged on this day.';
            }
        }

        if (empty($errors)) {
            // Compute hours
            $computed = computeDTR(
                $vals['time_in'],
                $vals['time_out'],
                $vals['break_duration'],
                (float)$schedule['max_hours_per_day'],
                (bool)$schedule['allow_overtime'],
                (float)$schedule['max_overtime_per_day']
            );

            if ($editId) {
                // Update existing
                $stmt = $pdo->prepare(
                    "UPDATE dtr_records SET
                        record_date=?, time_in=?, time_out=?, break_duration=?,
                        raw_hours=?, regular_hours=?, overtime_hours=?, total_hours=?,
                        status=?, notes=?, updated_at=NOW()
                     WHERE id=? AND student_id=?"
                );
                $stmt->execute([
                    $vals['record_date'], $vals['time_in'], $vals['time_out'], $vals['break_duration'],
                    $computed['raw_hours'], $computed['regular_hours'],
                    $computed['overtime_hours'], $computed['total_hours'],
                    $computed['status'], $vals['notes'],
                    $editId, $studentId
                ]);
                logActivity($pdo, $studentId, 'dtr.update', 'date=' . $vals['record_date']);
                flash('success', 'Time record updated successfully.');
            } else {
                // Insert new
                $stmt = $pdo->prepare(
                    "INSERT INTO dtr_records
                        (student_id, record_date, time_in, time_out, break_duration,
                         raw_hours, regular_hours, overtime_hours, total_hours, status, notes)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE
                        time_in=VALUES(time_in), time_out=VALUES(time_out),
                        break_duration=VALUES(break_duration), raw_hours=VALUES(raw_hours),
                        regular_hours=VALUES(regular_hours), overtime_hours=VALUES(overtime_hours),
                        total_hours=VALUES(total_hours), status=VALUES(status),
                        notes=VALUES(notes), updated_at=NOW()"
                );
                $stmt->execute([
                    $studentId, $vals['record_date'], $vals['time_in'], $vals['time_out'],
                    $vals['break_duration'], $computed['raw_hours'], $computed['regular_hours'],
                    $computed['overtime_hours'], $computed['total_hours'],
                    $computed['status'], $vals['notes']
                ]);
                logActivity($pdo, $studentId, 'dtr.create_or_replace', 'date=' . $vals['record_date']);
                flash('success', 'Time record logged successfully.');
            }
            header('Location: dtr-log.php');
            exit;
        }

    } elseif ($postAction === 'delete') {
        $delId = (int)($_POST['delete_id'] ?? 0);
        $pdo->prepare("DELETE FROM dtr_records WHERE id=? AND student_id=?")->execute([$delId, $studentId]);
      logActivity($pdo, $studentId, 'dtr.delete', 'record_id=' . $delId);
        flash('success', 'Record deleted.');
        header('Location: dtr-log.php');
        exit;
    }
}

// ── Load existing record for edit ─────────────────
$editRecord = null;
if ($action === 'edit' && $editId) {
    $stmt = $pdo->prepare("SELECT * FROM dtr_records WHERE id=? AND student_id=?");
    $stmt->execute([$editId, $studentId]);
    $editRecord = $stmt->fetch();
    if (!$editRecord) {
        header('Location: dtr-log.php');
        exit;
    }
    $vals = $editRecord;
}

// ── Pre-fill from schedule defaults ───────────────
if ($action === 'add' && empty($vals)) {
    $vals = [
        'record_date'    => date('Y-m-d'),
        'time_in'        => substr($schedule['default_time_in'], 0, 5),
        'time_out'       => substr($schedule['default_time_out'], 0, 5),
        'break_duration' => $schedule['break_duration'],
        'notes'          => '',
    ];
}

// ── Fetch paginated list ───────────────────────────
$page    = max(1, (int)($_GET['pg'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$filterMonth = trim($_GET['month'] ?? '');
$filterStatus = $_GET['status'] ?? '';

$filterMonth = normalizeMonthInput($filterMonth);

$where = 'WHERE student_id = ?';
$params = [$studentId];

if ($filterMonth) {
    $where .= ' AND DATE_FORMAT(record_date, "%Y-%m") = ?';
    $params[] = $filterMonth;
}
if ($filterStatus) {
    $where .= ' AND status = ?';
    $params[] = $filterStatus;
}

$totalCount = $pdo->prepare("SELECT COUNT(*) FROM dtr_records $where");
$totalCount->execute($params);
$total = (int)$totalCount->fetchColumn();
$pages = (int)ceil($total / $perPage);

$listStmt = $pdo->prepare("SELECT * FROM dtr_records $where ORDER BY record_date DESC LIMIT $perPage OFFSET $offset");
$listStmt->execute($params);
$records = $listStmt->fetchAll();

$pageTitle  = 'Time Records';
$activePage = 'dtr';
include 'includes/header.php';
?>

<?php $msg = flash('success'); if ($msg): ?>
<div class="alert alert-success">✓ <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- ── ADD / EDIT FORM ──────────────────────────── -->
<div class="page-header">
  <h1><?= $action === 'edit' ? 'Edit Time Record' : 'Log Daily Time Record' ?></h1>
  <p><?= $action === 'edit' ? 'Update your time log entry.' : 'Record your time in and time out for today or a past date.' ?></p>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e): ?><div>⚠ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row justify-content-center">
<div class="col-12 col-lg-9 col-xl-7">
<div class="card">
  <div class="card-header">
    <span class="card-title"><?= $action === 'edit' ? 'Edit Record' : 'New DTR Entry' ?></span>
    <a href="dtr-log.php" class="btn btn-outline btn-sm">← Back to List</a>
  </div>
  <div class="card-body">
    <form method="POST" action="dtr-log.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="form_action" value="save">
      <?php if ($editId): ?>
      <input type="hidden" name="form_edit_id" value="<?= $editId ?>">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label" for="record_date">Date <span>*</span></label>
        <input type="date" id="record_date" name="record_date" class="form-control"
               value="<?= htmlspecialchars($vals['record_date'] ?? date('Y-m-d')) ?>"
               max="<?= date('Y-m-d') ?>" required>
        <p class="form-hint">Cannot log future dates.</p>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="time_in">Time In <span>*</span></label>
          <input type="time" id="time_in" name="time_in" class="form-control"
                 value="<?= htmlspecialchars(substr($vals['time_in'] ?? '', 0, 5)) ?>"
                 required>
        </div>
        <div class="form-group">
          <label class="form-label" for="time_out">Time Out <span>*</span></label>
          <input type="time" id="time_out" name="time_out" class="form-control"
                 value="<?= htmlspecialchars(substr($vals['time_out'] ?? '', 0, 5)) ?>"
                 required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="break_duration">Break Duration (hours)</label>
        <input type="number" id="break_duration" name="break_duration" class="form-control"
               min="0" max="8" step="0.25"
               value="<?= htmlspecialchars($vals['break_duration'] ?? $schedule['break_duration']) ?>">
        <p class="form-hint">Default from your schedule: <?= number_format($schedule['break_duration'], 2) ?> hours</p>
      </div>

      <div class="form-group">
        <label class="form-label" for="notes">Notes (optional)</label>
        <input type="text" id="notes" name="notes" class="form-control"
               placeholder="e.g., half day, late, etc."
               value="<?= htmlspecialchars($vals['notes'] ?? '') ?>">
      </div>

      <!-- Live preview -->
      <div style="margin-bottom:1.25rem;">
        <div class="form-section-title">Hours Preview</div>
        <div id="hours-preview" style="display:none;background:var(--slate-50);border:1px solid var(--slate-200);border-radius:var(--radius-md);padding:.9rem 1.1rem;">
        </div>
        <style>
          .preview-table { width:100%; font-size:.87rem; }
          .preview-table td { padding:.25rem 0; color:var(--slate-500); }
          .preview-table .td-val { text-align:right; color:var(--navy-700); }
          html[data-theme='dark'] #hours-preview { background:#0d182b !important; border-color:#2f4363 !important; }
          html[data-theme='dark'] .preview-table td { color:#c9d8ef !important; }
          html[data-theme='dark'] .preview-table .td-val { color:#e5efff !important; }
        </style>
        <input type="hidden" id="max_hours" value="<?= $schedule['max_hours_per_day'] ?>">
        <input type="checkbox" id="allow_ot_check" style="display:none;" <?= $schedule['allow_overtime'] ? 'checked' : '' ?>>
        <input type="hidden" id="max_ot_hours" value="<?= $schedule['max_overtime_per_day'] ?>">
      </div>

      <div class="btn-group">
        <button type="submit" class="btn btn-primary btn-lg">
          <?= $action === 'edit' ? 'Update Record' : 'Save Time Record' ?>
        </button>
        <a href="dtr-log.php" class="btn btn-outline btn-lg">Cancel</a>
      </div>
    </form>
  </div>
</div>
</div>
</div>

<?php else: ?>
<!-- ── LIST VIEW ────────────────────────────────── -->
<div class="page-header">
  <h1>Time Records</h1>
  <p>All your logged DTR entries</p>
</div>

<!-- Filters -->
<div class="report-filters row g-3">
  <div class="form-group col-12 col-md-6 col-lg-3">
    <label class="form-label">Filter by Month</label>
    <input type="month" class="form-control" id="filterMonth"
           value="<?= htmlspecialchars($filterMonth) ?>">
  </div>
  <div class="form-group col-12 col-md-6 col-lg-3">
    <label class="form-label">Status</label>
    <select class="form-control" id="filterStatus">
      <option value="">All Status</option>
      <option value="valid"      <?= $filterStatus==='valid'?'selected':'' ?>>Valid</option>
      <option value="incomplete" <?= $filterStatus==='incomplete'?'selected':'' ?>>Incomplete</option>
      <option value="invalid"    <?= $filterStatus==='invalid'?'selected':'' ?>>Invalid</option>
    </select>
  </div>
  <div class="form-group col-12 col-md-6 col-lg-3 d-flex align-items-end">
    <button onclick="applyFilters()" class="btn btn-primary">Filter</button>
    <a href="dtr-log.php" class="btn btn-outline" style="margin-left:.5rem;">Clear</a>
  </div>
  <div class="form-group col-12 col-md-6 col-lg-3 d-flex align-items-end ms-lg-auto">
    <a href="dtr-log.php?action=add" class="btn btn-accent">+ Add Time Log</a>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <?php if (empty($records)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">📋</div>
      <h3>No records found</h3>
      <p>Start logging your daily time to track your OJT hours.</p>
      <a href="dtr-log.php?action=add" class="btn btn-primary mt-2">+ Log Time</a>
    </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Time In</th>
          <th>Time Out</th>
          <th>Break</th>
          <th>Raw Hrs</th>
          <th>Regular</th>
          <th>Overtime</th>
          <th>Total</th>
          <th>Status</th>
          <th>Notes</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $r): ?>
        <tr>
          <td><strong><?= fmtDate($r['record_date']) ?></strong><br><small class="text-muted"><?= date('D', strtotime($r['record_date'])) ?></small></td>
          <td class="td-mono"><?= fmtTime($r['time_in']) ?></td>
          <td class="td-mono"><?= fmtTime($r['time_out']) ?></td>
          <td><?= number_format($r['break_duration'], 1) ?>h</td>
          <td class="td-mono text-muted"><?= hoursToHM($r['raw_hours']) ?></td>
          <td class="td-mono text-navy fw-600"><?= hoursToHM($r['regular_hours']) ?></td>
          <td class="td-mono <?= $r['overtime_hours'] > 0 ? 'text-amber fw-600' : 'text-muted' ?>">
            <?= $r['overtime_hours'] > 0 ? hoursToHM($r['overtime_hours']) : '—' ?>
          </td>
          <td class="td-mono fw-bold"><?= hoursToHM($r['total_hours']) ?></td>
          <td><?= statusBadge($r['status']) ?></td>
          <td style="max-width:120px;font-size:.82rem;color:var(--slate-500);">
            <?= $r['notes'] ? htmlspecialchars($r['notes']) : '<span class="text-muted">—</span>' ?>
          </td>
          <td>
            <div class="btn-group">
              <a href="dtr-log.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="form_action" value="delete">
                <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"
                        data-confirm="Delete this record for <?= fmtDate($r['record_date']) ?>? This cannot be undone.">
                  Del
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div style="padding:1rem 1.5rem;display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
      <a href="?pg=<?= $p ?>&month=<?= urlencode($filterMonth) ?>&status=<?= urlencode($filterStatus) ?>"
         class="btn <?= $p === $page ? 'btn-primary' : 'btn-outline' ?> btn-sm">
        <?= $p ?>
      </a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
  </div>
</div>

<script>
function applyFilters() {
  const m = document.getElementById('filterMonth').value;
  const s = document.getElementById('filterStatus').value;
  window.location = 'dtr-log.php?month=' + encodeURIComponent(m) + '&status=' + encodeURIComponent(s);
}
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
