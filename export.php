<?php
// export.php — Export DTR records as PDF (printable HTML) or CSV
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

requireLogin();

$studentId = currentStudentId();
$student   = getStudent($pdo, $studentId);
$schedule  = getSchedule($pdo, $studentId);
$holidays  = getHolidaysMap($pdo, $studentId);
$format    = $_GET['format'] ?? 'pdf';  // pdf | csv
$type      = $_GET['type']   ?? 'monthly';
$startDate = $_GET['start']  ?? date('Y-m-01');
$endDate   = $_GET['end']    ?? date('Y-m-t');

// Validate dates
if (!isValidDateYmd($startDate)) $startDate = date('Y-m-01');
if (!isValidDateYmd($endDate))   $endDate   = date('Y-m-t');

// Fetch records
$stmt = $pdo->prepare(
    "SELECT * FROM dtr_records
     WHERE student_id=? AND record_date BETWEEN ? AND ?
     ORDER BY record_date ASC"
);
$stmt->execute([$studentId, $startDate, $endDate]);
$records = $stmt->fetchAll();

// Compute totals
$totalRegular  = 0;
$totalOvertime = 0;
$totalHours    = 0;
$lateCount     = 0;
$totalRendered = getTotalRendered($pdo, $studentId);
$required      = (float)$student['required_hours'];
$remaining     = max(0, $required - $totalRendered);

$restDays = array_map('intval', explode(',', (string)($schedule['rest_days'] ?? '0,6')));
$expectedTimeIn = substr((string)($schedule['default_time_in'] ?? '08:00:00'), 0, 5);

foreach ($records as $r) {
    if ($r['status'] === 'valid') {
        $totalRegular  += $r['regular_hours'];
        $totalOvertime += $r['overtime_hours'];
        $totalHours    += $r['total_hours'];
    }

  if (in_array($r['status'], ['valid', 'incomplete'], true) && !empty($r['time_in'])) {
    $dayType = getDayType((string)$r['record_date'], $holidays, $restDays);
    if ($dayType === 'work' || $dayType === 'holiday_special_working') {
      $lateMinutes = computeLateMinutes(substr((string)$r['time_in'], 0, 5), $expectedTimeIn, 0);
      if ($lateMinutes > 0) {
        $lateCount++;
      }
    }
  }
}

$rangeLabel = fmtDate($startDate) . ' to ' . fmtDate($endDate);
$generatedAt = date('F j, Y h:i A');
$exportProfile = getExportProfile($pdo, $studentId);
$companyName = ($exportProfile['company_name'] ?? '') !== '' ? $exportProfile['company_name'] : (defined('APP_NAME') ? (string)constant('APP_NAME') : 'OJTSync');
$companyAddress = ($exportProfile['company_address'] ?? '') !== '' ? $exportProfile['company_address'] : '';
$companyLogo = ($exportProfile['company_logo'] ?? '') !== '' ? $exportProfile['company_logo'] : 'logo/Logo.png';
$preparedByName = (string)$student['name'];
$preparedByTitle = 'OJT Student';
$supervisorName = ($exportProfile['supervisor_name'] ?? '') !== '' ? $exportProfile['supervisor_name'] : 'Authorized Signatory';
$supervisorTitle = ($exportProfile['supervisor_title'] ?? '') !== '' ? $exportProfile['supervisor_title'] : 'Supervisor';
$reportRef = 'DTR-' . strtoupper(substr($type, 0, 1)) . '-' . date('Ymd') . '-' . str_pad((string)$studentId, 4, '0', STR_PAD_LEFT);

logActivity($pdo, $studentId, 'report.export_view', 'format=' . $format . '; range=' . $startDate . ' to ' . $endDate);

// Resolve a safe fallback page for the Close button.
$fallbackBackUrl = 'reports.php';
if (!empty($_SERVER['HTTP_REFERER'])) {
  $refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?? '';
  if ($refPath !== '') {
    $base = basename($refPath);
    if ($base !== 'export.php') {
      $fallbackBackUrl = $base;
    }
  }
}

// ── CSV EXPORT ────────────────────────────────────
if ($format === 'csv') {
    $filename = 'DTR_' . $student['name'] . '_' . $startDate . '_' . $endDate . '.csv';
    $filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $filename);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');

    // BOM for Excel UTF-8
    fputs($out, "\xEF\xBB\xBF");

    // Header info
    fputcsv($out, ['Internship Daily Time Record Report']);
    fputcsv($out, ['Organization:', $companyName]);
    fputcsv($out, ['Authorized Signatory:', $supervisorName]);
    fputcsv($out, ['Signatory Title:', $supervisorTitle]);
    fputcsv($out, ['Report Reference:', $reportRef]);
    fputcsv($out, ['Student:', $student['name']]);
    fputcsv($out, ['Report Type:', ucfirst($type)]);
    fputcsv($out, ['Period:', $rangeLabel]);
    fputcsv($out, ['Required Hours:', $student['required_hours']]);
    fputcsv($out, ['Total Rendered:', hoursToHM($totalRendered)]);
    fputcsv($out, ['Remaining Hours:', hoursToHM($remaining)]);
    fputcsv($out, ['Prepared By:', $preparedByName]);
    fputcsv($out, ['Validated By:', $supervisorName]);
    fputcsv($out, ['Generated:', $generatedAt]);
    fputcsv($out, []);

    // Table header
    fputcsv($out, [
        'Date', 'Day', 'Time In', 'Time Out', 'Break (hrs)',
        'Raw Hours', 'Regular Hours', 'Overtime Hours', 'Total Hours', 'Status', 'Notes'
    ]);

    foreach ($records as $r) {
        fputcsv($out, [
            $r['record_date'],
            date('l', strtotime($r['record_date'])),
            $r['time_in'] ? date('h:i A', strtotime('1970-01-01 '.$r['time_in'])) : '',
            $r['time_out'] ? date('h:i A', strtotime('1970-01-01 '.$r['time_out'])) : '',
            number_format($r['break_duration'], 2),
            number_format($r['raw_hours'], 2),
            number_format($r['regular_hours'], 2),
            number_format($r['overtime_hours'], 2),
            number_format($r['total_hours'], 2),
            ucfirst($r['status']),
            $r['notes'] ?? '',
        ]);
    }

    fputcsv($out, []);
    fputcsv($out, ['', '', '', '', 'TOTALS:',
        '',
        number_format($totalRegular, 2),
        number_format($totalOvertime, 2),
        number_format($totalHours, 2),
    ]);

    fclose($out);
    exit;
}

// ── PDF / PRINTABLE HTML ──────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DTR Report — OJTSync</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --font-body: 'DM Sans', sans-serif;
    --font-heading: 'Sora', sans-serif;
  }
  body {
    font-family: var(--font-body);
    font-size: 12.5px;
    line-height: 1.45;
    color: #1e293b;
    background: #fff;
    padding: 2cm 1.5cm;
    max-width: 900px;
    margin: 0 auto;
  }

  @page {
    size: auto;
    margin: 1.5cm 1.4cm 2.8cm;
  }

  .no-print { margin-bottom: 1.5rem; }
  @media print { .no-print { display: none !important; } }

  .report-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 3px solid #0f2147;
    padding-bottom: 1rem;
    margin-bottom: 1.5rem;
  }

  .report-brand {
    display: flex;
    align-items: flex-start;
    gap: .55rem;
  }

  .report-company {
    font-family: var(--font-heading);
    font-size: 1.4rem;
    font-weight: 700;
    color: #0f2147;
    letter-spacing: -0.01em;
    line-height: 1.2;
  }

  .report-logo {
    width: auto;
    height: 44px;
    object-fit: contain;
  }

  .report-brand small {
    display: block;
    font-size: .8rem;
    font-weight: 500;
    color: #64748b;
    font-family: var(--font-body);
  }

  .report-company-address {
    margin-top: .15rem;
    font-size: .76rem;
    color: #64748b;
    line-height: 1.25;
  }

  .report-info { text-align: right; font-size: .84rem; color: #64748b; line-height: 1.3; }
  .report-info strong {
    display: block;
    color: #1e293b;
    font-size: .82rem;
    margin-bottom: .1rem;
  }

  .report-title {
    font-family: var(--font-heading);
    font-size: 1.08rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: #0f2147;
    margin-bottom: 1rem;
    padding: .6rem .9rem;
    background: #f0f5fc;
    border-left: 3px solid #f59e0b;
    border-radius: 4px;
  }

  .meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: .75rem;
    margin-bottom: 1.5rem;
  }

  .meta-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: .6rem .9rem;
  }

  .meta-box label {
    display: block;
    font-size: .69rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #94a3b8;
    margin-bottom: .25rem;
  }

  .meta-box strong {
    font-family: var(--font-heading);
    font-size: 1.1rem;
    font-weight: 600;
    letter-spacing: -0.01em;
    font-variant-numeric: tabular-nums;
    color: #0f2147;
  }

  .summary-strip .meta-box {
    min-height: 86px;
  }

  .summary-strip {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .summary-strip .meta-box label {
    margin-bottom: .35rem;
  }

  .summary-strip .meta-box strong {
    font-size: 1.45rem;
    line-height: 1;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: .84rem;
    margin-bottom: 1.5rem;
  }

  thead th {
    background: #132a56;
    color: #fff;
    padding: .58rem .75rem;
    text-align: left;
    font-weight: 600;
    font-size: .71rem;
    text-transform: uppercase;
    letter-spacing: .045em;
    white-space: nowrap;
  }

  tbody td {
    padding: .55rem .75rem;
    border-bottom: 1px solid #e2e8f0;
    color: #334155;
    vertical-align: middle;
  }

  tbody tr:nth-child(even) td { background: #f8fafc; }

  tfoot td {
    padding: .6rem .75rem;
    background: #f0f5fc;
    font-weight: 700;
    border-top: 2px solid #0f2147;
    color: #0f2147;
  }

  .status-valid      { color: #065f46; font-weight: 600; }
  .status-incomplete { color: #92400e; }
  .status-invalid    { color: #991b1b; }

  .num,
  .time {
    font-variant-numeric: tabular-nums;
    letter-spacing: 0;
  }

  .sig-line {
    margin-top: 3rem;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    break-inside: avoid;
    page-break-inside: avoid;
  }

  .sig-box {
    border-top: 2px solid #334155;
    min-height: 48px;
    padding-top: .5rem;
    font-size: .78rem;
    color: #64748b;
    break-inside: avoid;
    page-break-inside: avoid;
    background-image: linear-gradient(to right, #334155 100%, transparent 0%);
    background-repeat: no-repeat;
    background-position: top left;
    background-size: 100% 2px;
  }
  .sig-box strong { display: block; font-size: .83rem; color: #1e293b; }

  .sig-box em {
    display: block;
    font-style: normal;
    color: #94a3b8;
    margin-top: .25rem;
    font-size: .72rem;
  }

  .report-note {
    margin-top: .65rem;
    color: #94a3b8;
    font-size: .72rem;
    text-align: right;
  }

  @media (max-width: 768px) {
    body {
      padding: 1rem .75rem;
      font-size: 12px;
    }

    .report-header {
      flex-direction: column;
      gap: .8rem;
      align-items: flex-start;
    }

    .report-info {
      text-align: left;
      font-size: .8rem;
    }

    .meta-grid:not(.summary-strip) {
      grid-template-columns: 1fr;
    }

    .sig-line {
      grid-template-columns: 1fr;
      gap: 2rem;
    }

    table {
      font-size: .78rem;
    }

    thead th,
    tbody td,
    tfoot td {
      padding: .48rem .52rem;
    }

  }

  @media print {
    body {
      padding: 0;
      max-width: none;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    .report-header {
      display: flex !important;
      flex-direction: row !important;
      justify-content: space-between;
      align-items: flex-start;
      gap: .8rem;
    }

    .report-info {
      text-align: right !important;
      margin-left: auto;
      white-space: nowrap;
    }

    table {
      margin-bottom: 1rem;
    }

    thead {
      display: table-header-group;
    }

    tfoot {
      display: table-row-group;
    }

    tr,
    td,
    th {
      page-break-inside: avoid !important;
      break-inside: avoid;
    }

    .sig-line {
      grid-template-columns: 1fr 1fr !important;
      gap: 2rem;
      margin-bottom: .75rem;
      page-break-inside: avoid;
      break-inside: avoid;
    }

  }
</style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()" style="
    font-family:var(--font-body);
    background:#0f2147;color:#fff;border:none;padding:.6rem 1.5rem;
    border-radius:9px;cursor:pointer;font-size:.88rem;font-weight:600;margin-right:.5rem;">
    🖨 Print / Save as PDF
  </button>
  <button type="button" onclick="goBackFromExport()" style="
    font-family:var(--font-body);
    background:#e2e8f0;color:#334155;border:none;padding:.6rem 1.5rem;
    border-radius:9px;cursor:pointer;font-size:.88rem;font-weight:600;">
    ← Close
  </button>
  <p style="margin-top:.75rem;font-size:.82rem;color:#64748b;">
    To save as PDF: Click "Print / Save as PDF" → Choose "Save as PDF" in the destination dropdown.
  </p>
</div>

<script>
  function goBackFromExport() {
    if (window.history.length > 1) {
      window.history.back();
      return;
    }
    window.location.href = <?= json_encode($fallbackBackUrl) ?>;
  }
</script>

<div class="report-header">
  <div>
    <div class="report-brand">
      <img src="<?= htmlspecialchars($companyLogo) ?>" alt="Company logo" class="report-logo">
      <div>
        <div class="report-company"><?= htmlspecialchars($companyName) ?></div>
        <?php if ($companyAddress !== ''): ?>
        <div class="report-company-address"><?= htmlspecialchars($companyAddress) ?></div>
        <?php else: ?>
        <small>Internship Daily Time Record Report</small>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="report-info">
    <strong><?= htmlspecialchars($student['name']) ?></strong>
    Generated: <?= htmlspecialchars($generatedAt) ?><br>
    Period: <?= htmlspecialchars($rangeLabel) ?>
  </div>
</div>

<div class="report-title">
  <?= ucfirst($type) ?> Report — <?= htmlspecialchars($rangeLabel) ?>
</div>

<div class="meta-grid summary-strip">
  <div class="meta-box">
    <label>Total Hours Rendered</label>
    <strong><?= hoursToHM($totalHours) ?></strong>
    <span style="font-size:.72rem;color:#64748b;"> in this period</span>
  </div>
  <div class="meta-box">
    <label>Overtime Hours</label>
    <strong><?= hoursToHM($totalOvertime) ?></strong>
    <span style="font-size:.72rem;color:#64748b;"> extra hours logged</span>
  </div>
  <div class="meta-box">
    <label>Lates</label>
    <strong><?= $lateCount ?></strong>
    <span style="font-size:.72rem;color:#64748b;"> late day(s)</span>
  </div>
</div>

<table>
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
    <?php if (empty($records)): ?>
    <tr><td colspan="11" style="text-align:center;padding:1.5rem;color:#94a3b8;">
      No records for this period.
    </td></tr>
    <?php else: ?>
    <?php foreach ($records as $r): ?>
    <tr>
      <td><?= fmtDate($r['record_date']) ?></td>
      <td><?= date('D', strtotime($r['record_date'])) ?></td>
      <td class="time"><?= fmtTime($r['time_in']) ?></td>
      <td class="time"><?= fmtTime($r['time_out']) ?></td>
      <td class="num"><?= number_format($r['break_duration'], 1) ?>h</td>
      <td class="num"><?= hoursToHM($r['raw_hours']) ?></td>
      <td class="num"><strong><?= hoursToHM($r['regular_hours']) ?></strong></td>
      <td class="num"><?= $r['overtime_hours']>0 ? hoursToHM($r['overtime_hours']) : '—' ?></td>
      <td class="num"><strong><?= hoursToHM($r['total_hours']) ?></strong></td>
      <td class="status-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></td>
      <td><?= $r['notes'] ? htmlspecialchars($r['notes']) : '—' ?></td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="6">TOTALS FOR PERIOD</td>
      <td><?= hoursToHM($totalRegular) ?></td>
      <td><?= $totalOvertime>0 ? hoursToHM($totalOvertime) : '—' ?></td>
      <td><?= hoursToHM($totalHours) ?></td>
      <td colspan="2"></td>
    </tr>
  </tfoot>
</table>

<div class="sig-line">
  <div class="sig-box">
    <strong><?= htmlspecialchars($preparedByName) ?></strong>
    <?= htmlspecialchars($preparedByTitle) ?>
    <em>Prepared by</em>
  </div>
  <div class="sig-box">
    <strong><?= htmlspecialchars($supervisorName) ?></strong>
    <?= htmlspecialchars($supervisorTitle) ?>
    <em>Validated by</em>
  </div>
</div>

</body>
</html>
