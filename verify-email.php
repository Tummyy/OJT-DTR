<?php
// verify-email.php — Verify newly registered email via OTP
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

requireGuest();

$error = '';
$notice = '';
$pending = getPendingVerification();
$student = null;

$stylePath = __DIR__ . '/assets/css/style.css';
$scriptPath = __DIR__ . '/assets/js/script.js';
$styleVer = is_file($stylePath) ? (string)filemtime($stylePath) : (string)time();
$scriptVer = is_file($scriptPath) ? (string)filemtime($scriptPath) : (string)time();

if ($pending) {
    $stmt = $pdo->prepare('SELECT id, name, email, email_verified_at FROM students WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$pending['student_id']]);
    $student = $stmt->fetch();

    if (!$student || strtolower((string)$student['email']) !== strtolower((string)$pending['email'])) {
        clearPendingVerification();
        $pending = null;
        $student = null;
    } elseif (isStudentEmailVerified($student)) {
      loginStudent($student);
        clearPendingVerification();
      flash('success', 'Your email is already verified. Welcome back.');
      header('Location: dashboard.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } elseif ($action === 'verify_code') {
        if (!$pending || !$student) {
            $error = 'Verification session not found. Request a new code below.';
        } else {
            $otp = trim((string)($_POST['otp'] ?? ''));
            $result = verifyStudentEmailOtp($pdo, (int)$student['id'], $otp);
            if (!empty($result['ok'])) {
              loginStudent($student);
                clearPendingVerification();
              flash('success', 'Email verified successfully. Welcome to OJTSync.');
                logActivity($pdo, (int)$student['id'], 'auth.email_verified', 'email=' . (string)$student['email']);
              header('Location: dashboard.php');
                exit;
            }

            $error = (string)($result['error'] ?? 'Invalid verification code.');
            logActivity($pdo, (int)$student['id'], 'auth.email_verify_failed', 'email=' . (string)$student['email']);
        }
    } elseif ($action === 'resend_code') {
        if (!$pending || !$student) {
            $error = 'Verification session not found. Request a new code below.';
        } else {
            $wait = secondsUntilVerificationResend($pdo, (int)$student['id'], 30);
            if ($wait > 0) {
                $error = 'Please wait ' . $wait . ' second(s) before requesting another code.';
            } else {
                $active = getActiveEmailVerification($pdo, (int)$student['id']);
                $resendCount = $active ? ((int)$active['resend_count'] + 1) : 1;
                if ($resendCount > 8) {
                    $error = 'Too many resend requests. Please try again later.';
                } else {
                    $otp = createEmailVerificationOtp($pdo, (int)$student['id'], $resendCount);
                    $sent = sendEmailVerificationOtp((string)$student['email'], (string)$student['name'], $otp);
                    if ($sent) {
                        $notice = 'A new verification code was sent to your email.';
                    } else {
                        $error = 'Unable to send email right now. Please try again later.';
                    }

                    logActivity($pdo, (int)$student['id'], 'auth.email_verify_resend', $sent ? 'sent=1' : 'sent=0');
                }
            }
        }
    } elseif ($action === 'request_by_email') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $stmt = $pdo->prepare('SELECT id, name, email, email_verified_at FROM students WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $row = $stmt->fetch();

            if ($row && !isStudentEmailVerified($row)) {
                setPendingVerification((int)$row['id'], (string)$row['email']);
                $wait = secondsUntilVerificationResend($pdo, (int)$row['id'], 30);
                if ($wait <= 0) {
                    $active = getActiveEmailVerification($pdo, (int)$row['id']);
                    $resendCount = $active ? ((int)$active['resend_count'] + 1) : 1;
                    if ($resendCount <= 8) {
                        $otp = createEmailVerificationOtp($pdo, (int)$row['id'], $resendCount);
                        sendEmailVerificationOtp((string)$row['email'], (string)$row['name'], $otp);
                    }
                }

                logActivity($pdo, (int)$row['id'], 'auth.email_verify_requested', 'email=' . (string)$row['email']);
                header('Location: verify-email.php');
                exit;
            }

            $notice = 'If your account exists and is pending verification, a code has been sent.';
            logActivity($pdo, null, 'auth.email_verify_requested_unknown', 'email=' . $email);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Verify your OJTSync account email using the one-time code sent to your inbox.">
<title>Verify Email — OJTSync</title>
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
      <h2>Verify Email</h2>
      <p>Confirm your account with the 6-digit code sent to your inbox</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($notice): ?>
    <div class="alert alert-success">✓ <?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>

    <?php $flashOk = flash('success'); if ($flashOk): ?>
    <div class="alert alert-success">✓ <?= htmlspecialchars($flashOk) ?></div>
    <?php endif; ?>

    <?php if ($pending && $student): ?>
    <div class="alert alert-info">
      <p class="alert-copy">We sent a code to <strong><?= htmlspecialchars((string)$student['email']) ?></strong>. Enter it below.</p>
    </div>

    <form method="POST" action="verify-email.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="verify_code">
      <div class="form-group">
        <label class="form-label" for="otp">Verification Code <span>*</span></label>
        <input type="text" id="otp" name="otp" class="form-control"
               placeholder="123456" inputmode="numeric" pattern="\d{6}" maxlength="6" required autofocus>
        <p class="form-hint">Code expires in 5 minutes.</p>
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:.5rem;">
        Verify Email
      </button>
    </form>

    <form method="POST" action="verify-email.php" style="margin-top:.6rem;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="resend_code">
      <button type="submit" class="btn btn-outline btn-full">Resend Code</button>
    </form>
    <?php else: ?>
    <form method="POST" action="verify-email.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="request_by_email">
      <div class="form-group">
        <label class="form-label" for="email">Email Address <span>*</span></label>
        <input type="email" id="email" name="email" class="form-control" placeholder="your@email.com" required autofocus>
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:.5rem;">
        Send Verification Code
      </button>
    </form>
    <?php endif; ?>

    <div class="auth-divider">
      Already verified?
      <a href="login.php" class="auth-link">Back to sign in</a>
    </div>
  </div>
</div>
<script src="assets/js/script.js?v=<?= htmlspecialchars($scriptVer) ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
