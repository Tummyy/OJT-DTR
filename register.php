<?php
// register.php — Student Registration
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

requireGuest();

$errors = [];
$vals   = [];
$stylePath = __DIR__ . '/assets/css/style.css';
$scriptPath = __DIR__ . '/assets/js/script.js';
$styleVer = is_file($stylePath) ? (string)filemtime($stylePath) : (string)time();
$scriptVer = is_file($scriptPath) ? (string)filemtime($scriptPath) : (string)time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Your session expired. Please refresh the page and try again.';
  }

  $vals = [
    'name'           => trim($_POST['name'] ?? ''),
    'email'          => trim($_POST['email'] ?? ''),
    'password'       => $_POST['password'] ?? '',
    'confirm'        => $_POST['confirm'] ?? '',
    'required_hours' => (int)($_POST['required_hours'] ?? 600),
  ];

  if (empty($vals['name']))             $errors[] = 'Full name is required.';
  if (empty($vals['email']))            $errors[] = 'Email address is required.';
  if (!filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
  if ($vals['password'] !== $vals['confirm']) $errors[] = 'Passwords do not match.';
  if ($vals['required_hours'] < 1 || $vals['required_hours'] > 9999)
                       $errors[] = 'Required hours must be between 1 and 9999.';

  if (!empty($vals['password'])) {
    $emailLocal = explode('@', $vals['email'])[0] ?? '';
    $policy = validatePasswordPolicy($vals['password'], [$vals['name'], $vals['email'], $emailLocal]);
    if (!$policy['ok']) {
      $errors = array_merge($errors, $policy['errors']);
    }
  }

  if (empty($errors)) {
    // Check duplicate email
    $chk = $pdo->prepare("SELECT id FROM students WHERE email=? LIMIT 1");
    $chk->execute([$vals['email']]);
    if ($chk->fetch()) {
      $errors[] = 'This email is already registered. Please sign in instead.';
    } else {
      $hash = password_hash($vals['password'], PASSWORD_DEFAULT);
      $ins  = $pdo->prepare(
        "INSERT INTO students (name, email, password, required_hours) VALUES (?,?,?,?)"
      );
      $ins->execute([$vals['name'], $vals['email'], $hash, $vals['required_hours']]);

      $studentId = (int)$pdo->lastInsertId();

      $stmt = $pdo->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
      $stmt->execute([$studentId]);
      $student = $stmt->fetch();

      setPendingVerification($studentId, $vals['email']);

      $sent = false;
      $wait = secondsUntilVerificationResend($pdo, $studentId, 30);
      if ($wait <= 0) {
        $active = getActiveEmailVerification($pdo, $studentId);
        $resendCount = $active ? ((int)$active['resend_count'] + 1) : 1;
        if ($resendCount <= 8) {
          $otp = createEmailVerificationOtp($pdo, $studentId, $resendCount);
          $sent = sendEmailVerificationOtp((string)$vals['email'], (string)$vals['name'], $otp);
        }
      }

      logActivity($pdo, $studentId, 'auth.registered', 'email=' . $vals['email']);
      logActivity($pdo, $studentId, 'auth.email_verify_requested', $sent ? 'sent=1' : 'sent=0');

      flash('success', $sent
        ? 'Account created successfully. Check your email for the verification code.'
        : 'Account created successfully. Please request a verification code from the next page.');
      header('Location: verify-email.php');
      exit;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Create your OJTSync account to start tracking OJT hours and generate reports.">
<title>Create Account — OJTSync</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=<?= htmlspecialchars($styleVer) ?>">
</head>
<body>
<div class="auth-page">
  <div class="auth-card auth-card-register">
    <div class="auth-logo">
      <img src="logo/Logo.png" alt="OJTSync" class="auth-logo-img">
      <h2>Create Account</h2>
      <p>Register your OJT student profile</p>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $e): ?>
        <div>⚠ <?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="register.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <div class="form-group">
        <label class="form-label" for="name">Full Name <span>*</span></label>
        <input type="text" id="name" name="name" class="form-control"
               placeholder="Juan Dela Cruz"
               value="<?= htmlspecialchars($vals['name'] ?? '') ?>"
               required autofocus>
      </div>

      <div class="form-group">
        <label class="form-label" for="email">Email Address <span>*</span></label>
        <input type="email" id="email" name="email" class="form-control"
               placeholder="your@email.com"
               value="<?= htmlspecialchars($vals['email'] ?? '') ?>"
               required>
      </div>

      <div class="form-group">
        <label class="form-label" for="required_hours">Required OJT Hours <span>*</span></label>
        <input type="number" id="required_hours" name="required_hours" class="form-control"
               min="1" max="9999" placeholder="600"
               value="<?= htmlspecialchars($vals['required_hours'] ?? '600') ?>"
               required>
        <p class="form-hint">Total hours you need to complete for OJT (e.g., 486, 600, 720)</p>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="password">Password <span>*</span></label>
          <input type="password" id="password" name="password" class="form-control"
                 placeholder="Enter a strong password" required>
          <p class="form-hint">Use 12+ characters with upper, lower, number, and symbol.</p>
        </div>
        <div class="form-group">
          <label class="form-label" for="confirm">Confirm Password <span>*</span></label>
          <input type="password" id="confirm" name="confirm" class="form-control"
                 placeholder="Repeat password" required>
          <p class="form-hint">Type the same password again.</p>
        </div>
      </div>

      <button type="submit" class="btn btn-accent btn-full btn-lg" style="margin-top:.5rem;">
        Create My Account
      </button>
    </form>

    <div class="auth-divider">
      Already have an account?
      <a href="login.php" class="auth-link">Sign in here</a>
    </div>

    <div style="text-align:center;margin-top:.75rem;">
      <a href="index.php" style="font-size:.82rem;color:var(--slate-400);">← Back to home</a>
    </div>
  </div>
</div>
<script src="assets/js/script.js?v=<?= htmlspecialchars($scriptVer) ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
