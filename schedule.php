<?php
// schedule.php — OJT Schedule Setup
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

requireLogin();

$studentId = currentStudentId();
$student   = getStudent($pdo, $studentId);
$schedule  = getSchedule($pdo, $studentId);
$errors    = [];
$success   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Your session expired. Please refresh the page and try again.';
  }

    $vals = [
        'start_date'          => $_POST['start_date'] ?? '',
        'default_time_in'     => $_POST['default_time_in'] ?? '',
        'default_time_out'    => $_POST['default_time_out'] ?? '',
        'break_duration'      => (float)($_POST['break_duration'] ?? 1),
        'max_hours_per_day'   => (float)($_POST['max_hours_per_day'] ?? 8),
        'allow_overtime'      => isset($_POST['allow_overtime']) ? 1 : 0,
        'max_overtime_per_day'=> (float)($_POST['max_overtime_per_day'] ?? 2),
        'rest_days'           => implode(',', $_POST['rest_days'] ?? []),
    ];

    // Also update required hours if changed
    $required_hours = (int)($_POST['required_hours'] ?? $student['required_hours']);

    if (empty($vals['start_date']))       $errors[] = 'Start date is required.';
    if (empty($vals['default_time_in']))  $errors[] = 'Default time in is required.';
    if (empty($vals['default_time_out'])) $errors[] = 'Default time out is required.';
    if ($vals['max_hours_per_day'] < 1 || $vals['max_hours_per_day'] > 24)
                                          $errors[] = 'Max hours per day must be between 1 and 24.';
    if ($vals['break_duration'] < 0)      $errors[] = 'Break duration cannot be negative.';
    if ($required_hours < 1)              $errors[] = 'Required hours must be at least 1.';

    if (empty($errors)) {
        // Check if schedule exists
        $chk = $pdo->prepare("SELECT id FROM schedules WHERE student_id=?");
        $chk->execute([$studentId]);
        if ($chk->fetch()) {
            $stmt = $pdo->prepare(
                "UPDATE schedules SET start_date=?, default_time_in=?, default_time_out=?,
                 break_duration=?, max_hours_per_day=?, allow_overtime=?,
                 max_overtime_per_day=?, rest_days=? WHERE student_id=?"
            );
            $stmt->execute([
                $vals['start_date'], $vals['default_time_in'], $vals['default_time_out'],
                $vals['break_duration'], $vals['max_hours_per_day'], $vals['allow_overtime'],
                $vals['max_overtime_per_day'], $vals['rest_days'], $studentId
            ]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO schedules (student_id, start_date, default_time_in, default_time_out,
                 break_duration, max_hours_per_day, allow_overtime, max_overtime_per_day, rest_days)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $stmt->execute([
                $studentId, $vals['start_date'], $vals['default_time_in'], $vals['default_time_out'],
                $vals['break_duration'], $vals['max_hours_per_day'], $vals['allow_overtime'],
                $vals['max_overtime_per_day'], $vals['rest_days']
            ]);
        }

        // Update required hours in students table
        $pdo->prepare("UPDATE students SET required_hours=? WHERE id=?")
            ->execute([$required_hours, $studentId]);

        logActivity($pdo, $studentId, 'schedule.save',
          'start=' . $vals['start_date'] . '; required_hours=' . $required_hours);

        flash('success', 'Schedule saved successfully!');
        header('Location: schedule.php');
        exit;
    }
    $schedule = array_merge($schedule, $vals);
    $student['required_hours'] = $required_hours;
}

$currentRestDays = explode(',', $schedule['rest_days'] ?? '0,6');
$dayNames = ['0'=>'Sunday','1'=>'Monday','2'=>'Tuesday','3'=>'Wednesday',
             '4'=>'Thursday','5'=>'Friday','6'=>'Saturday'];

$pageTitle  = 'OJT Schedule';
$activePage = 'schedule';
include 'includes/header.php';
?>

<?php $msg = flash('success'); if ($msg): ?>
<div class="alert alert-success">✓ <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1>OJT Schedule Setup</h1>
  <p>Configure your default shift, rest days, and overtime settings.</p>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e): ?><div>⚠ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row justify-content-center">
<div class="col-12 col-lg-10 col-xl-8">
<div class="card">
  <div class="card-header">
    <span class="card-title">Schedule Configuration</span>
  </div>
  <div class="card-body">
    <form method="POST" action="schedule.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

      <div class="form-section-title">Basic OJT Info</div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="start_date">OJT Start Date <span>*</span></label>
          <input type="date" id="start_date" name="start_date" class="form-control"
                 value="<?= htmlspecialchars($schedule['start_date'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="required_hours">Required OJT Hours <span>*</span></label>
          <input type="number" id="required_hours" name="required_hours" class="form-control"
                 min="1" max="9999"
                 value="<?= htmlspecialchars($student['required_hours']) ?>" required>
        </div>
      </div>

      <div class="form-section-title">Default Shift</div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="default_time_in">Default Time In <span>*</span></label>
          <input type="time" id="default_time_in" name="default_time_in" class="form-control"
                 value="<?= htmlspecialchars(substr($schedule['default_time_in'] ?? '08:00', 0, 5)) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="default_time_out">Default Time Out <span>*</span></label>
          <input type="time" id="default_time_out" name="default_time_out" class="form-control"
                 value="<?= htmlspecialchars(substr($schedule['default_time_out'] ?? '17:00', 0, 5)) ?>" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="break_duration">Break Duration (hours)</label>
          <input type="number" id="break_duration" name="break_duration" class="form-control"
                 min="0" max="8" step="0.25"
                 value="<?= htmlspecialchars($schedule['break_duration'] ?? 1) ?>">
          <p class="form-hint">Time deducted from raw hours (e.g., 1 for 1 hour lunch break)</p>
        </div>
        <div class="form-group">
          <label class="form-label" for="max_hours_per_day">Max Regular Hours/Day <span>*</span></label>
          <input type="number" id="max_hours_per_day" name="max_hours_per_day" class="form-control"
                 min="1" max="24" step="0.5"
                 value="<?= htmlspecialchars($schedule['max_hours_per_day'] ?? 8) ?>" required>
          <p class="form-hint">Hours beyond this cap become overtime</p>
        </div>
      </div>

      <div class="form-section-title">Rest Days</div>

      <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach ($dayNames as $num => $name): ?>
        <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;
                      background:var(--slate-50);border:1.5px solid var(--slate-200);
                      border-radius:var(--radius-sm);padding:.4rem .85rem;font-size:.87rem;
                      transition:.15s ease;"
               class="rest-day-label <?= in_array($num, $currentRestDays) ? 'rest-active' : '' ?>"
               id="lbl_<?= $num ?>">
          <input type="checkbox" name="rest_days[]" value="<?= $num ?>"
                 <?= in_array($num, $currentRestDays) ? 'checked' : '' ?>
                 onchange="toggleRestLabel(this)">
          <?= $name ?>
        </label>
        <?php endforeach; ?>
      </div>
      <p class="form-hint" style="margin-top:-.75rem;margin-bottom:1.25rem;">
        Checked days are treated as rest days and excluded from end date computation.
      </p>

      <div class="form-section-title">Overtime Settings</div>

      <div class="toggle-group" style="margin-bottom:1rem;">
        <span class="toggle-label">Allow Overtime Hours</span>
        <label class="toggle-switch">
          <input type="checkbox" id="allow_overtime" name="allow_overtime"
                 <?= $schedule['allow_overtime'] ? 'checked' : '' ?>>
          <span class="toggle-slider"></span>
        </label>
      </div>

      <div id="overtime-fields">
        <div class="form-group">
          <label class="form-label" for="max_overtime_per_day">Max Overtime Per Day (hours)</label>
          <input type="number" id="max_overtime_per_day" name="max_overtime_per_day"
                 class="form-control"
                 min="0.5" max="12" step="0.5"
                 value="<?= htmlspecialchars($schedule['max_overtime_per_day'] ?? 2) ?>">
          <p class="form-hint">Maximum overtime hours counted per day</p>
        </div>
      </div>

      <div class="btn-group" style="margin-top:1.5rem;">
        <button type="submit" class="btn btn-primary btn-lg">Save Schedule</button>
        <a href="dashboard.php" class="btn btn-outline btn-lg">Cancel</a>
      </div>

    </form>
  </div>
</div>
</div>
</div>

<style>
.rest-day-label {
  color: var(--slate-700);
}

.rest-active {
  background: var(--navy-100) !important;
  border-color: var(--navy-500) !important;
  color: var(--navy-700);
  font-weight: 600;
}

html[data-theme='dark'] .rest-day-label {
  background: #0d182b !important;
  border-color: #2f4363 !important;
  color: #dce8fb !important;
}

html[data-theme='dark'] .rest-day-label input {
  accent-color: #60a5fa;
}

html[data-theme='dark'] .rest-day-label.rest-active {
  background: #22508a !important;
  border-color: #6ea9e3 !important;
  color: #f8fbff !important;
}
</style>
<script>
function toggleRestLabel(cb) {
  const lbl = cb.closest('label');
  if (cb.checked) lbl.classList.add('rest-active');
  else lbl.classList.remove('rest-active');
}
</script>

<?php include 'includes/footer.php'; ?>
