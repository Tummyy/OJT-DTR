<?php
// dashboard.php — Student Dashboard
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

requireLogin();

$studentId = currentStudentId();
$student   = getStudent($pdo, $studentId);
$schedule  = getSchedule($pdo, $studentId);
$holidays  = getHolidaysMap($pdo, $studentId);

$holidayCountStmt = $pdo->prepare("SELECT COUNT(*) FROM holidays WHERE student_id=?");
$holidayCountStmt->execute([$studentId]);
$holidayCount = (int)$holidayCountStmt->fetchColumn();

$logCountStmt = $pdo->prepare("SELECT COUNT(*) FROM dtr_records WHERE student_id=?");
$logCountStmt->execute([$studentId]);
$logCount = (int)$logCountStmt->fetchColumn();

// ── Core stats ────────────────────────────────────
$required = (float)$student['required_hours'];
$rendered = getTotalRendered($pdo, $studentId);
$remaining = max(0, $required - $rendered);
$pct = $required > 0 ? min(100, round(($rendered / $required) * 100, 1)) : 0;

// Expected end date
$hasSchedule = isset($schedule['_is_default']) ? ((int)$schedule['_is_default'] === 0) : !empty($schedule['id']);
$endDate = $hasSchedule ? calculateEndDate($pdo, $studentId, $schedule, $holidays) : 'Set up your schedule first';

// Today's status
$todayStr = date('Y-m-d');
$todayStmt = $pdo->prepare("SELECT * FROM dtr_records WHERE student_id=? AND record_date=?");
$todayStmt->execute([$studentId, $todayStr]);
$todayLog = $todayStmt->fetch();

// Total overtime
$otStmt = $pdo->prepare("SELECT COALESCE(SUM(overtime_hours),0) FROM dtr_records WHERE student_id=? AND status='valid'");
$otStmt->execute([$studentId]);
$totalOT = (float)$otStmt->fetchColumn();

// Total absences (days since start with no valid log, weekdays only, excluding holidays)
$absences = 0;
if ($hasSchedule) {
    $start = new DateTime($schedule['start_date']);
    $today = new DateTime('today');
    $restDays = array_map('intval', explode(',', $schedule['rest_days'] ?? '0,6'));

    // Get all valid log dates
    $logStmt = $pdo->prepare("SELECT record_date FROM dtr_records WHERE student_id=? AND status='valid'");
    $logStmt->execute([$studentId]);
    $logDates = array_column($logStmt->fetchAll(), 'record_date');

    $cursor = clone $start;
    while ($cursor <= $today) {
        $ds  = $cursor->format('Y-m-d');
        $dow = (int)$cursor->format('w');
        $dayType = getDayType($ds, $holidays, $restDays);
        if (($dayType === 'work' || $dayType === 'holiday_special_working')
            && !in_array($ds, $logDates)) {
            $absences++;
        }
        $cursor->modify('+1 day');
    }
}

    // Late statistics (record-date based)
    $lateGraceMinutes = 10;
    $lateStats = getLateStats($pdo, $studentId, $schedule, $holidays, $lateGraceMinutes);
    $todayDayType = getDayType($todayStr, $holidays, array_map('intval', explode(',', $schedule['rest_days'] ?? '0,6')));
    $todayIsWorkingDay = ($todayDayType === 'work' || $todayDayType === 'holiday_special_working');

    $onboardingSteps = [
      [
        'done' => $hasSchedule,
        'title' => 'Set up your schedule',
        'desc' => 'Configure your start date, daily shift, and rest days first.',
        'href' => 'schedule.php',
        'cta' => $hasSchedule ? 'Review Schedule' : 'Set Schedule',
      ],
      [
        'done' => $holidayCount > 0,
        'title' => 'Add your holidays',
        'desc' => 'Import or add holidays so completion date and reports stay accurate.',
        'href' => 'holidays.php',
        'cta' => $holidayCount > 0 ? 'Manage Holidays' : 'Add Holidays',
      ],
      [
        'done' => $logCount > 0,
        'title' => 'Log your first DTR entry',
        'desc' => 'Start recording time in/out so hours and progress can be tracked.',
        'href' => 'dtr-log.php?action=add',
        'cta' => $logCount > 0 ? 'Add Another Log' : 'Log First Day',
      ],
      [
        'done' => $rendered > 0,
        'title' => 'Review your progress',
        'desc' => 'Check reports and export your records when needed.',
        'href' => 'reports.php',
        'cta' => 'Open Reports',
      ],
    ];

    $completedOnboardingSteps = count(array_filter($onboardingSteps, static fn($s) => !empty($s['done'])));
    $totalOnboardingSteps = count($onboardingSteps);
    $showOnboarding = $completedOnboardingSteps < $totalOnboardingSteps;

    // Pace insight (rendered vs expected by now)
    $paceInsight = [
      'has_schedule' => $hasSchedule,
      'headline' => 'Set your schedule to unlock pace insights.',
      'detail' => 'Once your schedule is configured, OJTSync can compare your current pace versus target hours.',
      'status_class' => 'neutral',
      'status_icon' => 'ti ti-bulb',
      'elapsed_workdays' => 0,
      'avg_per_workday' => 0.0,
      'target_by_now' => 0.0,
      'pace_delta' => 0.0,
      'pace_ratio' => 0.0,
      'catchup_daily_10' => 0.0,
    ];

    if ($hasSchedule) {
      $restDays = array_map('intval', explode(',', $schedule['rest_days'] ?? '0,6'));
      $start = new DateTime($schedule['start_date']);
      $today = new DateTime('today');
      $projectedDaily = getProjectedDailyHours($pdo, $studentId, $schedule, $holidays);
      $elapsedWorkdays = 0;

      if ($start <= $today) {
        $cursor = clone $start;
        while ($cursor <= $today) {
          $ds = $cursor->format('Y-m-d');
          $dt = getDayType($ds, $holidays, $restDays);
          if ($dt === 'work' || $dt === 'holiday_special_working') {
            $elapsedWorkdays++;
          }
          $cursor->modify('+1 day');
        }
      }

      $targetByNow = min($required, max(0, $elapsedWorkdays * $projectedDaily));
      $paceDelta = $rendered - $targetByNow;
      $avgPerWorkday = $elapsedWorkdays > 0 ? ($rendered / $elapsedWorkdays) : 0.0;
      $paceRatio = $targetByNow > 0 ? (($rendered / $targetByNow) * 100) : 100.0;
      $paceRatio = max(0, min(200, $paceRatio));
      $catchupDaily10 = $paceDelta < 0 ? (abs($paceDelta) / 10) : 0.0;

      $paceInsight['elapsed_workdays'] = $elapsedWorkdays;
      $paceInsight['avg_per_workday'] = $avgPerWorkday;
      $paceInsight['target_by_now'] = $targetByNow;
      $paceInsight['pace_delta'] = $paceDelta;
      $paceInsight['pace_ratio'] = $paceRatio;
      $paceInsight['catchup_daily_10'] = $catchupDaily10;

      if ($remaining <= 0) {
        $paceInsight['headline'] = 'You have completed your required OJT hours.';
        $paceInsight['detail'] = 'Great work staying consistent. Keep your logs updated for final documentation.';
        $paceInsight['status_class'] = 'ahead';
        $paceInsight['status_icon'] = 'ti ti-rosette-discount-check';
      } elseif ($paceDelta >= 0) {
        $paceInsight['headline'] = 'You are currently ahead of your expected pace.';
        $paceInsight['detail'] = 'Keep your routine steady to maintain this momentum.';
        $paceInsight['status_class'] = 'ahead';
        $paceInsight['status_icon'] = 'ti ti-trending-up';
      } else {
        $paceInsight['headline'] = 'You are behind your expected pace right now.';
        $paceInsight['detail'] = 'To catch up in the next 10 workdays, aim for about ' . hoursToHM($catchupDaily10) . ' extra per day.';
        $paceInsight['status_class'] = 'behind';
        $paceInsight['status_icon'] = 'ti ti-alert-triangle';
      }
    }

// Recent DTR records (last 10)
$recentStmt = $pdo->prepare(
    "SELECT * FROM dtr_records WHERE student_id=? ORDER BY record_date DESC LIMIT 10"
);
$recentStmt->execute([$studentId]);
$recentRecords = $recentStmt->fetchAll();

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
include 'includes/header.php';
?>

<div class="page-header">
  <h1>Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= htmlspecialchars(explode(' ', $student['name'])[0]) ?> !</h1>
  <p>Here's your OJT progress as of <?= date('F j, Y') ?></p>
</div>

<?php if (!$hasSchedule): ?>
<div class="alert alert-warning">
  ⚠ You haven't set up your OJT schedule yet.
  <a href="schedule.php" class="auth-link">Set up now →</a>
</div>
<?php endif; ?>

<?php if ($showOnboarding): ?>
<section class="onboarding-guide card">
  <div class="card-header">
    <span class="card-title">Quick Start Guide</span>
    <span class="badge badge-info"><?= $completedOnboardingSteps ?>/<?= $totalOnboardingSteps ?> completed</span>
  </div>
  <div class="card-body">
    <p class="onboarding-intro">Complete these steps to get familiar with OJTSync and keep your records accurate.</p>
    <div class="onboarding-steps">
      <?php foreach ($onboardingSteps as $step): ?>
      <div class="onboarding-step <?= $step['done'] ? 'done' : '' ?>">
        <div class="onboarding-mark" aria-hidden="true"><?= $step['done'] ? '✓' : '•' ?></div>
        <div class="onboarding-copy">
          <div class="onboarding-title"><?= htmlspecialchars($step['title']) ?></div>
          <div class="onboarding-desc"><?= htmlspecialchars($step['desc']) ?></div>
        </div>
        <a href="<?= htmlspecialchars($step['href']) ?>" class="btn btn-outline btn-sm"><?= htmlspecialchars($step['cta']) ?></a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="insight-strip <?= htmlspecialchars($paceInsight['status_class']) ?>">
  <div class="insight-main">
    <div class="insight-pill">
      <i class="<?= htmlspecialchars($paceInsight['status_icon']) ?>" aria-hidden="true"></i>
      <span>Pace Insight</span>
    </div>
    <h2><?= htmlspecialchars($paceInsight['headline']) ?></h2>
    <p><?= htmlspecialchars($paceInsight['detail']) ?></p>
  </div>
  <div class="insight-kpis">
    <div class="insight-kpi">
      <div class="insight-kpi-head">
        <span class="insight-kpi-label">Workdays Elapsed</span>
        <details class="kpi-help">
          <summary aria-label="What does Workdays Elapsed mean?">
            <i class="ti ti-help-circle" aria-hidden="true"></i>
          </summary>
          <div class="kpi-tooltip" role="note">
            Count of working days from your OJT start date up to today. Rest days and non-working holidays are excluded.
          </div>
        </details>
      </div>
      <strong class="insight-kpi-value"><?= (int)$paceInsight['elapsed_workdays'] ?></strong>
    </div>
    <div class="insight-kpi">
      <div class="insight-kpi-head">
        <span class="insight-kpi-label">Avg Logged / Day</span>
        <details class="kpi-help">
          <summary aria-label="What does Avg Logged per Day mean?">
            <i class="ti ti-help-circle" aria-hidden="true"></i>
          </summary>
          <div class="kpi-tooltip" role="note">
            Average valid hours logged per counted workday. Formula: rendered valid hours divided by workdays elapsed.
          </div>
        </details>
      </div>
      <strong class="insight-kpi-value"><?= hoursToHM((float)$paceInsight['avg_per_workday']) ?></strong>
    </div>
    <div class="insight-kpi">
      <div class="insight-kpi-head">
        <span class="insight-kpi-label">Target By Now</span>
        <details class="kpi-help">
          <summary aria-label="What does Target By Now mean?">
            <i class="ti ti-help-circle" aria-hidden="true"></i>
          </summary>
          <div class="kpi-tooltip" role="note">
            Expected rendered hours by today based on your configured daily regular hours and eligible workdays.
          </div>
        </details>
      </div>
      <strong class="insight-kpi-value"><?= hoursToHM((float)$paceInsight['target_by_now']) ?></strong>
    </div>
    <div class="insight-kpi <?= (float)$paceInsight['pace_delta'] < 0 ? 'warn' : 'good' ?>">
      <div class="insight-kpi-head">
        <span class="insight-kpi-label">Pace Delta</span>
        <details class="kpi-help">
          <summary aria-label="What does Pace Delta mean?">
            <i class="ti ti-help-circle" aria-hidden="true"></i>
          </summary>
          <div class="kpi-tooltip" role="note">
            Difference between your rendered valid hours and target by now. Positive means ahead; negative means behind.
          </div>
        </details>
      </div>
      <strong class="insight-kpi-value">
        <?= (float)$paceInsight['pace_delta'] >= 0 ? '+' : '−' ?><?= hoursToHM(abs((float)$paceInsight['pace_delta'])) ?>
      </strong>
    </div>
  </div>
</section>

<!-- Progress Bar -->
<div class="progress-section">
  <div class="progress-header">
    <div class="progress-title">OJT Completion Progress</div>
    <div class="progress-pct"><?= $pct ?>%</div>
  </div>
  <div class="progress-bar-track">
    <div class="progress-bar-fill" data-width="<?= $pct ?>%" style="width:<?= $pct ?>%"></div>
  </div>
  <div class="progress-detail">
    <span><?= hoursToHM($rendered) ?> rendered of <?= number_format($required) ?> required hours</span>
    <span><?= hoursToHM($remaining) ?> remaining</span>
  </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
  <div class="stat-card success">
    <div class="stat-label">Hours Rendered</div>
    <div class="stat-value"><?= hoursToHM($rendered) ?></div>
    <div class="stat-sub">of <?= number_format($required) ?> required</div>
  </div>
  <div class="stat-card accent">
    <div class="stat-label">Hours Remaining</div>
    <div class="stat-value"><?= hoursToHM($remaining) ?></div>
    <div class="stat-sub"><?= $pct ?>% completed</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Total Overtime</div>
    <div class="stat-value"><?= hoursToHM($totalOT) ?></div>
    <div class="stat-sub">Overtime hours logged</div>
  </div>
  <div class="stat-card warning">
    <div class="stat-label">Total Absences</div>
    <div class="stat-value"><?= $absences ?></div>
    <div class="stat-sub">Working days missed</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Late Days</div>
    <div class="stat-value"><?= (int)$lateStats['late_days'] ?></div>
    <div class="stat-sub">Late time: <?= minutesToHM((int)$lateStats['total_late_minutes']) ?> (<?= (int)$lateStats['grace_minutes'] ?>m grace)</div>
  </div>
</div>

<!-- Today & End Date Cards -->
<div class="dashboard-grid">
  <div class="today-card">
    <div class="today-card-label">TODAY</div>
    <div class="today-date"><?= date('l, F j, Y') ?></div>
    <?php if ($todayLog): ?>
      <div class="today-status">
        Status: <?= ucfirst($todayLog['status']) ?> —
        In: <?= fmtTime($todayLog['time_in']) ?> /
        Out: <?= fmtTime($todayLog['time_out']) ?>
      </div>
      <div class="today-status" style="margin-top:.35rem;">
        <?php if (!$todayIsWorkingDay): ?>
          Late: Not required today
        <?php elseif ($lateStats['today_has_record']): ?>
          <?php if ($lateStats['today_is_late']): ?>
            Late today: <?= (int)$lateStats['today_late_minutes'] ?> minute<?= (int)$lateStats['today_late_minutes'] === 1 ? '' : 's' ?>
          <?php else: ?>
            Late today: On time
          <?php endif; ?>
        <?php else: ?>
          Late: Waiting for time in
        <?php endif; ?>
      </div>
      <div style="margin-top:.75rem;">
        <?= statusBadge($todayLog['status']) ?>
        <?php if ($todayLog['status'] === 'valid'): ?>
          <span class="badge badge-success" style="margin-left:.5rem;"><?= hoursToHM($todayLog['total_hours']) ?> hrs</span>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="today-status">No time log yet for today</div>
      <div style="margin-top:.75rem;">
        <a href="dtr-log.php?action=add" class="btn btn-accent btn-sm">+ Log Time</a>
      </div>
    <?php endif; ?>
  </div>

  <div class="end-date-card">
    <div class="today-card-label">EXPECTED COMPLETION DATE</div>
    <div class="today-date" style="font-size:1.1rem;">
      <?= htmlspecialchars($endDate) ?>
    </div>
    <div class="today-status">
      <?php if ($remaining <= 0): ?>
        🎉 You have completed your OJT hours!
      <?php elseif ($hasSchedule): ?>
        Based on your current schedule and <?= hoursToHM($remaining) ?> remaining
      <?php else: ?>
        Configure your schedule to see this
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Recent DTR Records -->
<div class="card">
  <div class="card-header">
    <span class="card-title">Recent Time Records</span>
    <div class="btn-group d-flex flex-wrap justify-content-start">
      <a href="dtr-log.php" class="btn btn-outline btn-sm">View All</a>
      <a href="dtr-log.php?action=add" class="btn btn-primary btn-sm">+ Add Log</a>
    </div>
  </div>
  <div class="table-wrap">
    <?php if (empty($recentRecords)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">📋</div>
      <h3>No time records yet</h3>
      <p>Start logging your daily time records to track your OJT progress.</p>
      <a href="dtr-log.php?action=add" class="btn btn-primary mt-2">Log Your First Day</a>
    </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Time In</th>
          <th>Time Out</th>
          <th>Break</th>
          <th>Regular</th>
          <th>Overtime</th>
          <th>Total</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentRecords as $r): ?>
        <tr>
          <td><strong><?= fmtDate($r['record_date']) ?></strong></td>
          <td class="td-mono"><?= fmtTime($r['time_in']) ?></td>
          <td class="td-mono"><?= fmtTime($r['time_out']) ?></td>
          <td><?= number_format($r['break_duration'], 1) ?>h</td>
          <td class="td-mono text-navy fw-600"><?= hoursToHM($r['regular_hours']) ?></td>
          <td class="td-mono <?= $r['overtime_hours'] > 0 ? 'text-amber' : 'text-muted' ?>">
            <?= $r['overtime_hours'] > 0 ? hoursToHM($r['overtime_hours']) : '—' ?>
          </td>
          <td class="td-mono fw-bold"><?= hoursToHM($r['total_hours']) ?></td>
          <td><?= statusBadge($r['status']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
