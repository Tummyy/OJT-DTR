<?php
// holidays.php — Holiday Management
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

requireLogin();

$studentId = currentStudentId();
$errors    = [];

// ── Handle POST ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Your session expired. Please refresh the page and try again.';
  }

  $action = empty($errors) ? ($_POST['form_action'] ?? '') : '';

    if ($action === 'add') {
        $date = $_POST['holiday_date'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? '';

        $validTypes = ['regular', 'special_non_working', 'special_working'];

        if (empty($date)) $errors[] = 'Holiday date is required.';
        if (empty($name)) $errors[] = 'Holiday name is required.';
        if (!in_array($type, $validTypes)) $errors[] = 'Invalid holiday type.';
        if (!empty($date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errors[] = 'Invalid date format.';

        if (empty($errors)) {
            try {
                $pdo->prepare(
                    "INSERT INTO holidays (student_id, holiday_date, name, type) VALUES (?,?,?,?)
                     ON DUPLICATE KEY UPDATE name=VALUES(name), type=VALUES(type)"
                )->execute([$studentId, $date, $name, $type]);
                logActivity($pdo, $studentId, 'holiday.add_or_update', $date . ' ' . $name);
                flash('success', "Holiday \"{$name}\" added successfully.");
                header('Location: holidays.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Failed to add holiday: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['holiday_id'] ?? 0);
        $pdo->prepare("DELETE FROM holidays WHERE id=? AND student_id=?")->execute([$id, $studentId]);
      logActivity($pdo, $studentId, 'holiday.delete', 'holiday_id=' . $id);
        flash('success', 'Holiday removed.');
        header('Location: holidays.php');
        exit;
    } elseif ($action === 'seed') {
      // Seed Philippine holidays for a specific year (defaults to current year)
      $year = (int)($_POST['seed_year'] ?? date('Y'));
      if ($year < 2000 || $year > 2100) {
        $year = (int)date('Y');
      }

        $phHolidays = [
        // First Quarter (January - March)
        ["$year-01-01", "New Year's Day",                                "regular"],
        ["$year-01-23", "First Philippine Republic Day",                 "special_working"],
        ["$year-02-17", "Chinese New Year",                              "special_non_working"],
        ["$year-02-25", "EDSA People Power Anniversary",                 "special_working"],
        ["$year-03-20", "Eid'l Fitr (Feast of Ramadhan)",                "regular"],

        // Second Quarter (April - June)
        ["$year-04-02", "Maundy Thursday (Huwebes Santo)",               "regular"],
        ["$year-04-03", "Good Friday (Biyernes Santo)",                  "regular"],
        ["$year-04-04", "Black Saturday (Sabado de Gloria)",             "special_non_working"],
        ["$year-04-09", "Araw ng Kagitingan (Day of Valor)",             "regular"],
        ["$year-05-01", "Labor Day",                                     "regular"],
        ["$year-05-27", "Eid'l Adha (Feast of Sacrifice)",               "regular"],
        ["$year-06-12", "Independence Day",                              "regular"],

        // Third Quarter (July - September)
        ["$year-07-27", "Iglesia ni Cristo Founding Anniversary",        "special_working"],
        ["$year-08-21", "Ninoy Aquino Day",                              "special_non_working"],
        ["$year-08-31", "National Heroes Day",                           "regular"],
        ["$year-09-03", "Yamashita Surrender Day",                        "special_working"],
        ["$year-09-08", "Feast of the Nativity of Mary",                 "special_working"],

        // Fourth Quarter (October - December)
        ["$year-11-01", "All Saints' Day (Undas)",                       "special_non_working"],
        ["$year-11-02", "All Souls' Day",                                "special_non_working"],
        ["$year-11-30", "Bonifacio Day",                                 "regular"],
        ["$year-12-08", "Feast of the Immaculate Conception",            "special_non_working"],
        ["$year-12-24", "Christmas Eve",                                 "special_non_working"],
        ["$year-12-25", "Christmas Day",                                 "regular"],
        ["$year-12-30", "Rizal Day",                                     "regular"],
        ["$year-12-31", "Last Day of the Year",                          "special_non_working"],
        ];

        $seeded = 0;
        foreach ($phHolidays as [$d, $n, $t]) {
            try {
          $stmt = $pdo->prepare(
                    "INSERT IGNORE INTO holidays (student_id, holiday_date, name, type) VALUES (?,?,?,?)"
          );
          $stmt->execute([$studentId, $d, $n, $t]);
          if ($stmt->rowCount() > 0) {
            $seeded++;
          }
            } catch (PDOException $e) { /* skip duplicates */ }
        }
        logActivity($pdo, $studentId, 'holiday.seed', 'year=' . $year . '; inserted=' . $seeded);
        flash('success', "Seeded {$seeded} Philippine {$year} holidays.");
        header('Location: holidays.php');
        exit;
    }
}

// ── Fetch holidays ────────────────────────────────
$filterYear = (int)($_GET['year'] ?? date('Y'));
$holidays = $pdo->prepare(
    "SELECT * FROM holidays WHERE student_id=? AND YEAR(holiday_date)=? ORDER BY holiday_date ASC"
);
$holidays->execute([$studentId, $filterYear]);
$holidayList = $holidays->fetchAll();

$typeLabels = [
    'regular'               => 'Regular Holiday',
    'special_non_working'   => 'Special Non-Working',
    'special_working'       => 'Special Working',
];

$pageTitle  = 'Holidays';
$activePage = 'holidays';
include 'includes/header.php';
?>

<?php $msg = flash('success'); if ($msg): ?>
<div class="alert alert-success">✓ <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1>Holiday Management</h1>
  <p>Holidays affect your DTR computation and expected completion date.</p>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e): ?><div>⚠ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Info box -->
<div class="alert alert-info" style="margin-bottom:1.5rem;">
  <div>
    <strong>Holiday Rules:</strong><br>
    • <strong>Regular Holiday</strong> — Skipped in computation (not a working day)<br>
    • <strong>Special Non-Working</strong> — Skipped in computation (not a working day)<br>
    • <strong>Special Working</strong> — Counted as a normal work day
  </div>
</div>

<div class="row g-4 align-items-start">

  <!-- Holiday List -->
  <div class="col-12 col-xl-8">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div class="card-title" style="font-size:1.1rem;">
        Holidays for
        <select onchange="window.location='holidays.php?year='+this.value"
                class="form-control d-inline-block ms-2" style="width:auto;padding:.3rem .6rem;">
          <?php for ($y = date('Y')-1; $y <= date('Y')+2; $y++): ?>
          <option value="<?= $y ?>" <?= $y===$filterYear?'selected':'' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <form method="POST" class="d-inline">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="form_action" value="seed">
        <input type="hidden" name="seed_year" value="<?= (int)$filterYear ?>">
        <button type="submit" class="btn btn-outline btn-sm"
                data-confirm="This will add Philippine <?= $filterYear ?> holidays to your list. Continue?">
          🇵🇭 Seed PH Holidays
        </button>
      </form>
    </div>

    <div class="card">
      <div class="table-wrap">
        <?php if (empty($holidayList)): ?>
        <div class="empty-state">
          <div class="empty-state-icon">📅</div>
          <h3>No holidays added for <?= $filterYear ?></h3>
          <p>Add holidays manually or use the "Seed PH Holidays" button for Philippine holidays.</p>
        </div>
        <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Day</th>
              <th>Holiday Name</th>
              <th>Type</th>
              <th>Counted?</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($holidayList as $h): ?>
            <tr>
              <td><strong><?= fmtDate($h['holiday_date']) ?></strong></td>
              <td><?= date('D', strtotime($h['holiday_date'])) ?></td>
              <td><?= htmlspecialchars($h['name']) ?></td>
              <td>
                <span class="htype-<?= $h['type'] ?>">
                  <?= htmlspecialchars($typeLabels[$h['type']] ?? $h['type']) ?>
                </span>
              </td>
              <td>
                <?php if ($h['type'] === 'special_working'): ?>
                  <span class="badge badge-success">✓ Yes</span>
                <?php else: ?>
                  <span class="badge badge-danger">✗ No</span>
                <?php endif; ?>
              </td>
              <td>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="form_action" value="delete">
                  <input type="hidden" name="holiday_id" value="<?= $h['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm"
                          data-confirm="Remove &quot;<?= htmlspecialchars($h['name']) ?>&quot;?">
                    Remove
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="6" style="padding:.75rem 1rem;font-size:.82rem;color:var(--slate-400);">
                <?= count($holidayList) ?> holiday(s) listed for <?= $filterYear ?>
              </td>
            </tr>
          </tfoot>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Add Holiday Form -->
  <div class="col-12 col-xl-4">
    <div class="card">
      <div class="card-header">
        <span class="card-title">Add Holiday</span>
      </div>
      <div class="card-body">
        <form method="POST" action="holidays.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="form_action" value="add">

          <div class="form-group">
            <label class="form-label" for="holiday_date">Date <span>*</span></label>
            <input type="date" id="holiday_date" name="holiday_date" class="form-control"
                   value="<?= htmlspecialchars($_POST['holiday_date'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="name">Holiday Name <span>*</span></label>
            <input type="text" id="name" name="name" class="form-control"
                   placeholder="e.g., Christmas Day"
                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="type">Holiday Type <span>*</span></label>
            <select id="type" name="type" class="form-control" required>
              <option value="">— Select type —</option>
              <?php foreach ($typeLabels as $k => $v): ?>
              <option value="<?= $k ?>" <?= ($_POST['type']??'')===$k?'selected':'' ?>>
                <?= htmlspecialchars($v) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <button type="submit" class="btn btn-primary btn-full">Add Holiday</button>
        </form>
      </div>
    </div>
  </div>

</div>

<?php include 'includes/footer.php'; ?>
