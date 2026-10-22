<?php
// forgot-password.php — Request a password reset link
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

requireGuest();

$notice = '';
$error = '';
$stylePath = __DIR__ . '/assets/css/style.css';
$scriptPath = __DIR__ . '/assets/js/script.js';
$styleVer = is_file($stylePath) ? (string)filemtime($stylePath) : (string)time();
$scriptVer = is_file($scriptPath) ? (string)filemtime($scriptPath) : (string)time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $stmt = $pdo->prepare('SELECT id, name, email FROM students WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $student = $stmt->fetch();

            if ($student) {
                $token = createPasswordResetToken($pdo, (int)$student['id']);
                $resetUrl = rtrim(APP_URL, '/') . '/reset-password.php?token=' . urlencode($token);
                $sent = sendPasswordResetEmail((string)$student['email'], (string)$student['name'], $resetUrl);

                logActivity(
                    $pdo,
                    (int)$student['id'],
                    'auth.password_reset_requested',
                    $sent ? 'email_sent=1' : 'email_sent=0'
                );
            } else {
                logActivity($pdo, null, 'auth.password_reset_requested_unknown_email', 'email=' . $email);
            }

            // Keep response identical to prevent account enumeration.
            $notice = 'If your email is registered, a reset link has been sent. Please check your inbox.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Request a secure password reset link for your OJTSync account.">
<title>Forgot Password — OJTSync</title>
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
      <h2>Forgot Password</h2>
      <p>Enter your account email to request a reset link</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($notice): ?>
    <div class="alert alert-success">✓ <?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>

    <form method="POST" action="forgot-password.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <div class="form-group">
        <label class="form-label" for="email">Email Address <span>*</span></label>
        <input type="email" id="email" name="email" class="form-control"
               placeholder="your@email.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               required autofocus>
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:.5rem;">
        Send Reset Link
      </button>
    </form>

    <div class="auth-divider">
      Remembered your password?
      <a href="login.php" class="auth-link">Back to sign in</a>
    </div>
  </div>
</div>
<script src="assets/js/script.js?v=<?= htmlspecialchars($scriptVer) ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
