<?php
// reset-password.php — Complete password reset using secure token
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

requireGuest();

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = '';
$stylePath = __DIR__ . '/assets/css/style.css';
$scriptPath = __DIR__ . '/assets/js/script.js';
$styleVer = is_file($stylePath) ? (string)filemtime($stylePath) : (string)time();
$scriptVer = is_file($scriptPath) ? (string)filemtime($scriptPath) : (string)time();

$resetContext = findValidPasswordReset($pdo, $token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } elseif (!$resetContext) {
        $error = 'This password reset link is invalid or has expired.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';

      if ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
        $emailLocal = explode('@', (string)$resetContext['email'])[0] ?? '';
        $policy = validatePasswordPolicy($password, [
          (string)$resetContext['name'],
          (string)$resetContext['email'],
          $emailLocal,
        ]);

        if (!$policy['ok']) {
          $error = implode(' ', $policy['errors']);
        }
      }

      if ($error === '') {
            $ok = resetPasswordWithToken($pdo, $token, $password);
            if ($ok) {
                logActivity($pdo, (int)$resetContext['student_id'], 'auth.password_reset_completed', 'success=1');
                flash('success', 'Password updated successfully. You can now sign in.');
                header('Location: login.php');
                exit;
            }

            logActivity($pdo, (int)$resetContext['student_id'], 'auth.password_reset_completed', 'success=0');
            $error = 'Unable to reset password. Please request a new reset link.';
            $resetContext = findValidPasswordReset($pdo, $token);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Set a new password for your OJTSync account.">
<title>Reset Password — OJTSync</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=<?= htmlspecialchars($styleVer) ?>">
</head>
<body>
<div class="auth-page">
  <div class="auth-card" style="max-width:460px;">
    <div class="auth-logo">
      <img src="logo/Logo.png" alt="OJTSync" class="auth-logo-img">
      <h2>Reset Password</h2>
      <p>Create a new password for your account</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$resetContext): ?>
    <div class="alert alert-warning">
      ⚠ This reset link is invalid or expired.
      <a href="forgot-password.php" class="auth-link">Request a new link</a>
    </div>
    <?php else: ?>
    <form method="POST" action="reset-password.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

      <div class="form-group">
        <label class="form-label" for="password">New Password <span>*</span></label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="Min. 12 chars, upper/lower/number/symbol" required autofocus>
      </div>

      <div class="form-group">
        <label class="form-label" for="confirm">Confirm New Password <span>*</span></label>
        <input type="password" id="confirm" name="confirm" class="form-control"
               placeholder="Repeat your new password" required>
      </div>

      <button type="submit" class="btn btn-accent btn-full btn-lg" style="margin-top:.5rem;">
        Update Password
      </button>
    </form>
    <?php endif; ?>

    <div class="auth-divider">
      <a href="login.php" class="auth-link">Back to sign in</a>
    </div>
  </div>
</div>
<script src="assets/js/script.js?v=<?= htmlspecialchars($scriptVer) ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
