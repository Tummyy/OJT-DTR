<?php
// reports.php — DTR Reports with export
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

requireLogin();

$studentId = currentStudentId();
$student   = getStudent($pdo, $studentId);
$schedule  = getSchedule($pdo, $studentId);
$holidays  = getHolidaysMap($pdo, $studentId);

$reportType  = $_GET['type']  ?? 'monthly';   // daily | weekly | monthly
$reportMonth = $_GET['month'] ?? date('Y-m');
$reportDate  = $_GET['date']  ?? date('Y-m-d');
$reportWeek  = $_GET['week']  ?? date('Y-m-d', strtotime('monday this week'));

$allowedTypes = ['daily', 'weekly', 'monthly'];
if (!in_array($reportType, $allowedTypes, true)) {
  $reportType = 'monthly';
}

$reportMonth = normalizeMonthInput($reportMonth);
if ($reportMonth === '') {
  $reportMonth = date('Y-m');
}

if (!isValidDateYmd($reportDate)) {
  $reportDate = date('Y-m-d');
}

if (!isValidDateYmd($reportWeek)) {
  $reportWeek = date('Y-m-d', strtotime('monday this week'));
}

// ── Build date range ──────────────────────────────
switch ($reportType) {
    case 'daily':
        $startDate = $reportDate;
        $endDate   = $reportDate;
        $rangeLabel = fmtDate($reportDate);
        break;
    case 'weekly':
        $monday    = new DateTime($reportWeek);
        $monday->modify('monday this week');
        $sunday    = clone $monday;
        $sunday->modify('+6 days');
        $startDate = $monday->format('Y-m-d');
        $endDate   = $sunday->format('Y-m-d');
        $rangeLabel = $monday->format('M d') . ' – ' . $sunday->format('M d, Y');
        break;
    default: // monthly
      [$y, $m] = explode('-', $reportMonth);
        $startDate = "$y-$m-01";
        $endDate   = date("Y-m-t", strtotime($startDate));
        $rangeLabel = date('F Y', strtotime($startDate));
}

// ── Fetch records ─────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT * FROM dtr_records
     WHERE student_id=? AND record_date BETWEEN ? AND ?
     ORDER BY record_date ASC"
);
$stmt->execute([$studentId, $startDate, $endDate]);
$records = $stmt->fetchAll();

// ── Compute summary ───────────────────────────────
$totalRegular  = 0;
$totalOvertime = 0;
$totalHours    = 0;
$validDays     = 0;

foreach ($records as $r) {
    if ($r['status'] === 'valid') {
        $totalRegular  += $r['regular_hours'];
        $totalOvertime += $r['overtime_hours'];
        $totalHours    += $r['total_hours'];
        $validDays++;
    }
}

// Compute absences in range
$restDays = array_map('intval', explode(',', $schedule['rest_days'] ?? '0,6'));
$absences = 0;
$workingDaysInRange = 0;
$logDates = array_column(array_filter($records, fn($r) => $r['status']==='valid'), 'record_date');

$cursor = new DateTime($startDate);
$endDt  = new DateTime($endDate);
$today  = new DateTime('today');

while ($cursor <= $endDt && $cursor <= $today) {
    $ds = $cursor->format('Y-m-d');
    $dt = getDayType($ds, $holidays, $restDays);
    if ($dt === 'work' || $dt === 'holiday_special_working') {
        $workingDaysInRange++;
        if (!in_array($ds, $logDates)) {
            $absences++;
        }
    }
    $cursor->modify('+1 day');
}

// Totals
$totalRendered = getTotalRendered($pdo, $studentId);
$required = (float)$student['required_hours'];
$remaining = max(0, $required - $totalRendered);
$pct = $required > 0 ? min(100, round(($totalRendered / $required) * 100, 1)) : 0;

$pageTitle  = 'Reports';
$activePage = 'reports';
$bodyClass  = 'reports-page';
include 'includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div>
    <h1>Reports</h1>
    <p>View and export your time records by period</p>
  </div>
  <div class="btn-group">
    <a href="export.php?type=<?= urlencode($reportType) ?>&start=<?= urlencode($startDate) ?>&end=<?= urlencode($endDate) ?>&format=pdf"
       class="btn btn-primary">
      <i class="ti ti-file-type-pdf" aria-hidden="true"></i>
      Export PDF
    </a>
    <a href="export.php?type=<?= urlencode($reportType) ?>&start=<?= urlencode($startDate) ?>&end=<?= urlencode($endDate) ?>&format=csv"
       class="btn btn-success">
      <i class="ti ti-file-spreadsheet" aria-hidden="true"></i>
      Export Excel/CSV
    </a>
  </div>
</div>

<!-- Report Filter Bar -->
<div class="report-filters row g-3">
  <div class="form-group col-12 col-md-6 col-lg-3">
    <label class="form-label">Report Type</label>
    <select class="form-control" id="reportType" onchange="updateReportUrl()">
      <option value="daily"   <?= $reportType==='daily'  ?'selected':'' ?>>Daily</option>
      <option value="weekly"  <?= $reportType==='weekly' ?'selected':'' ?>>Weekly</option>
      <option value="monthly" <?= $reportType==='monthly'?'selected':'' ?>>Monthly</option>
    </select>
  </div>

  <div class="form-group col-12 col-md-6 col-lg-3" id="grp-daily"   style="display:<?= $reportType==='daily'?'block':'none' ?>">
    <label class="form-label">Date</label>
    <input type="date" class="form-control" id="filterDate" value="<?= htmlspecialchars($reportDate) ?>" max="<?= date('Y-m-d') ?>">
  </div>

  <div class="form-group col-12 col-md-6 col-lg-3" id="grp-weekly"  style="display:<?= $reportType==='weekly'?'block':'none' ?>">
    <label class="form-label">Week of</label>
    <input type="date" class="form-control" id="filterWeek" value="<?= htmlspecialchars($reportWeek) ?>" max="<?= date('Y-m-d') ?>">
  </div>

  <div class="form-group col-12 col-md-6 col-lg-3" id="grp-monthly" style="display:<?= $reportType==='monthly'?'block':'none' ?>">
    <label class="form-label">Month</label>
    <input type="month" class="form-control" id="filterMonth" value="<?= htmlspecialchars($reportMonth) ?>">
  </div>

  <div class="form-group col-12 col-md-6 col-lg-3 d-flex align-items-end report-apply-wrap">
    <button onclick="applyReport()" class="btn btn-primary">Apply</button>
  </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid" style="margin-bottom:1.5rem;">
  <div class="stat-card success">
    <div class="stat-label">Total Hours Rendered</div>
    <div class="stat-value"><?= hoursToHM($totalHours) ?></div>
    <div class="stat-sub"><?= $validDays ?> valid day(s)</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Regular Hours</div>
    <div class="stat-value"><?= hoursToHM($totalRegular) ?></div>
    <div class="stat-sub">Standard working hours</div>
  </div>
  <div class="stat-card accent">
    <div class="stat-label">Overtime Hours</div>
    <div class="stat-value"><?= hoursToHM($totalOvertime) ?></div>
    <div class="stat-sub">Extra hours logged</div>
  </div>
  <div class="stat-card warning">
    <div class="stat-label">Absences</div>
    <div class="stat-value"><?= $absences ?></div>
    <div class="stat-sub">of <?= $workingDaysInRange ?> working day(s)</div>
  </div>
</div>

<!-- Overall progress summary -->
<div class="card mb-3">
  <div class="card-body d-flex align-items-center gap-3 flex-wrap">
    <div>
      <div style="font-size:.75rem;color:var(--slate-400);font-weight:600;text-transform:uppercase;letter-spacing:.07em;margin-bottom:.3rem;">
        Report Range
      </div>
      <div style="font-family:var(--font-heading);font-size:1.2rem;font-weight:700;color:var(--slate-900);">
        <?= htmlspecialchars($rangeLabel) ?>
      </div>
    </div>
    <div class="flex-grow-1" style="min-width:200px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:.4rem;font-size:.82rem;">
        <span style="color:var(--slate-500);">Overall OJT Progress</span>
        <strong style="color:var(--amber-500);"><?= $pct ?>%</strong>
      </div>
      <div class="progress-bar-track" style="height:10px;">
        <div class="progress-bar-fill" data-width="<?= $pct ?>%" style="width:<?= $pct ?>%"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:.78rem;color:var(--slate-400);margin-top:.35rem;">
        <span><?= hoursToHM($totalRendered) ?> rendered</span>
        <span><?= hoursToHM($remaining) ?> remaining</span>
      </div>
    </div>
  </div>
</div>

<!-- DTR Table -->
<div class="card">
  <div class="card-header">
    <span class="card-title">
      DTR Records — <?= htmlspecialchars($rangeLabel) ?>
    </span>
    <span class="badge badge-info"><?= count($records) ?> record(s)</span>
  </div>
  <div class="table-wrap report-table-wrap">
    <?php if (empty($records)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">📊</div>
      <h3>No records for this period</h3>
      <p>There are no DTR entries for the selected date range.</p>
    </div>
    <?php else: ?>
    <table class="report-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Day</th>
          <th>Time In</th>
          <th>Time Out</th>
          <th>Break</th>
          <th>Raw Hrs</th>
          <th>Regular</th>
          <th>Overtime</th>
          <th>Total</th>
          <th>Status</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $r): ?>
        <tr>
          <td><strong><?= fmtDate($r['record_date']) ?></strong></td>
          <td><?= date('D', strtotime($r['record_date'])) ?></td>
          <td class="td-mono"><?= fmtTime($r['time_in']) ?></td>
          <td class="td-mono"><?= fmtTime($r['time_out']) ?></td>
          <td><?= number_format($r['break_duration'], 1) ?>h</td>
          <td class="td-mono text-muted"><?= hoursToHM($r['raw_hours']) ?></td>
          <td class="td-mono text-navy fw-600"><?= hoursToHM($r['regular_hours']) ?></td>
          <td class="td-mono <?= $r['overtime_hours']>0?'text-amber fw-600':'text-muted' ?>">
            <?= $r['overtime_hours']>0 ? hoursToHM($r['overtime_hours']) : '—' ?>
          </td>
          <td class="td-mono fw-bold"><?= hoursToHM($r['total_hours']) ?></td>
          <td><?= statusBadge($r['status']) ?></td>
          <td style="font-size:.82rem;color:var(--slate-500);">
            <?= $r['notes'] ? htmlspecialchars($r['notes']) : '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:var(--slate-50);color:var(--slate-700);font-weight:700;">
          <td colspan="6" style="padding:.8rem 1rem;">TOTAL</td>
          <td class="td-mono text-navy"><?= hoursToHM($totalRegular) ?></td>
          <td class="td-mono text-amber"><?= $totalOvertime>0 ? hoursToHM($totalOvertime) : '—' ?></td>
          <td class="td-mono" style="color:var(--slate-900);"><?= hoursToHM($totalHours) ?></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
    <?php endif; ?>
  </div>
</div>

<script>
function updateReportUrl() {
  const type = document.getElementById('reportType').value;
  document.getElementById('grp-daily').style.display   = type==='daily'   ? 'block':'none';
  document.getElementById('grp-weekly').style.display  = type==='weekly'  ? 'block':'none';
  document.getElementById('grp-monthly').style.display = type==='monthly' ? 'block':'none';
}

function applyReport() {
  const type = document.getElementById('reportType').value;
  let url = 'reports.php?type=' + type;
  if (type === 'daily')   url += '&date='  + (document.getElementById('filterDate').value || '');
  if (type === 'weekly')  url += '&week='  + (document.getElementById('filterWeek').value || '');
  if (type === 'monthly') url += '&month=' + (document.getElementById('filterMonth').value || '');
  window.location = url;
}
</script>

<?php include 'includes/footer.php'; ?>
