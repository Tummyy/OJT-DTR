<?php
// login.php — Student Login
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

requireGuest();

$error = '';
$accountDeleted = isset($_GET['account_deleted']) && $_GET['account_deleted'] === '1';
$stylePath = __DIR__ . '/assets/css/style.css';
$scriptPath = __DIR__ . '/assets/js/script.js';
$styleVer = is_file($stylePath) ? (string)filemtime($stylePath) : (string)time();
$scriptVer = is_file($scriptPath) ? (string)filemtime($scriptPath) : (string)time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    $error = 'Your session expired. Please refresh the page and try again.';
  } else {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (isLoginBlocked($email)) {
      $wait = loginBlockRemainingSeconds($email);
      $error = 'Too many login attempts. Please try again in ' . ceil($wait / 60) . ' minute(s).';
      logActivity($pdo, null, 'auth.login_blocked', 'email=' . $email);
    } elseif (empty($email) || empty($password)) {
      $error = 'Please enter your email and password.';
    } else {
      $stmt = $pdo->prepare("SELECT * FROM students WHERE email = ? LIMIT 1");
      $stmt->execute([$email]);
      $student = $stmt->fetch();

      if ($student && password_verify($password, $student['password'])) {
        clearLoginFailures($email);
        if (isStudentEmailVerified($student)) {
          loginStudent($student);
          logActivity($pdo, (int)$student['id'], 'auth.login_success', 'email=' . $email);
          header('Location: dashboard.php');
          exit;
        }

        setPendingVerification((int)$student['id'], (string)$student['email']);

        $sent = false;
        $wait = secondsUntilVerificationResend($pdo, (int)$student['id'], 30);
        if ($wait <= 0) {
          $active = getActiveEmailVerification($pdo, (int)$student['id']);
          $resendCount = $active ? ((int)$active['resend_count'] + 1) : 1;
          if ($resendCount <= 8) {
            $otp = createEmailVerificationOtp($pdo, (int)$student['id'], $resendCount);
            $sent = sendEmailVerificationOtp((string)$student['email'], (string)$student['name'], $otp);
          }
        }

        logActivity($pdo, (int)$student['id'], 'auth.login_pending_verification', 'email=' . $email);
        flash('success', $sent
          ? 'Please verify your email to continue. We sent a new code to your inbox.'
          : 'Please verify your email to continue. Open the verification page and request a new code if needed.');
        header('Location: verify-email.php');
        exit;
      } else {
        registerLoginFailure($email);
        logActivity($pdo, null, 'auth.login_failed', 'email=' . $email);
        $error = 'Invalid email or password. Please try again.';
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Sign in to OJTSync to manage your OJT time records and progress.">
<title>Sign In — OJTSync</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=<?= htmlspecialchars($styleVer) ?>">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <img src="logo/Logo.png" alt="OJTSync" class="auth-logo-img">
      <h2>Sign In</h2>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php $msg = flash('success'); if ($msg): ?>
    <div class="alert alert-success">✓ <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if ($accountDeleted): ?>
    <div class="alert alert-success">✓ Your account has been deleted permanently.</div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <div class="form-group">
        <label class="form-label" for="email">Email Address <span>*</span></label>
        <input type="email" id="email" name="email" class="form-control"
               placeholder="your@email.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               required autofocus>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password <span>*</span></label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="••••••••" required>
      </div>

      <div style="text-align:right;margin-top:-.15rem;margin-bottom:.75rem;">
        <a href="forgot-password.php" style="font-size:.82rem;color:var(--slate-400);">Forgot password?</a>
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:.5rem;">
        Sign In
      </button>
    </form>

    <div class="auth-divider">
      Don't have an account?
      <a href="register.php" class="auth-link">Create one here</a>
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
