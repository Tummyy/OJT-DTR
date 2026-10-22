<?php
// profile.php — Account Profile & Security
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

requireLogin();

$studentId = currentStudentId();
$errors = [];
$openDeleteModal = false;
$deleteFlow = $_SESSION['delete_account_confirm'] ?? null;
$deleteReady = is_array($deleteFlow)
  && (int)($deleteFlow['student_id'] ?? 0) === $studentId
  && (int)($deleteFlow['expires_at'] ?? 0) > time()
  && !empty($deleteFlow['token']);

if (!$deleteReady && isset($_SESSION['delete_account_confirm'])) {
  unset($_SESSION['delete_account_confirm']);
}

$openDeleteModal = $deleteReady || (($_GET['delete'] ?? '') === 'confirm');

$stmt = $pdo->prepare("SELECT id, name, email, required_hours, created_at, password FROM students WHERE id=? LIMIT 1");
$stmt->execute([$studentId]);
$student = $stmt->fetch();
$exportProfile = getExportProfile($pdo, $studentId);

if (!$student) {
    logoutStudent();
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }

    $action = empty($errors) ? (string)($_POST['form_action'] ?? '') : '';

    if ($action === 'save_profile') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));

        if ($name === '') {
            $errors[] = 'Full name is required.';
        }
        if ($email === '') {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }

        if (empty($errors)) {
            $dup = $pdo->prepare("SELECT id FROM students WHERE email=? AND id<>? LIMIT 1");
            $dup->execute([$email, $studentId]);
            if ($dup->fetch()) {
                $errors[] = 'That email is already used by another account.';
            }
        }

        if (empty($errors)) {
            $upd = $pdo->prepare("UPDATE students SET name=?, email=? WHERE id=?");
            $upd->execute([$name, $email, $studentId]);

            $_SESSION['student_name'] = $name;
            $_SESSION['student_email'] = $email;

            logActivity($pdo, $studentId, 'profile.update', 'name_email_updated=1');
            flash('success', 'Profile updated successfully.');
            header('Location: profile.php');
            exit;
        }

        $student['name'] = $name;
        $student['email'] = $email;
    } elseif ($action === 'save_export_profile') {
      $companyName = trim((string)($_POST['company_name'] ?? ''));
      $companyAddress = trim((string)($_POST['company_address'] ?? ''));
      $supervisorName = trim((string)($_POST['supervisor_name'] ?? ''));
      $supervisorTitle = trim((string)($_POST['supervisor_title'] ?? ''));
      $companyLogo = (string)($exportProfile['company_logo'] ?? 'logo/Logo.png');

      if ($companyName === '') {
        $errors[] = 'Organization name is required for export settings.';
      }
      if ($supervisorName === '') {
        $errors[] = 'Authorized signatory name is required for export settings.';
      }
      if ($supervisorTitle === '') {
        $errors[] = 'Authorized signatory title is required for export settings.';
      }

      $processImageUpload = function (string $fieldName, int $maxSize, string $label, string $previousPath) use ($studentId, &$errors): string {
        if (!isset($_FILES[$fieldName]) || (int)($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
          return $previousPath;
        }

        $uploadErr = (int)($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_OK);
        if ($uploadErr !== UPLOAD_ERR_OK) {
          $errors[] = $label . ' upload failed. Please try again.';
          return $previousPath;
        }

        $tmpPath = (string)($_FILES[$fieldName]['tmp_name'] ?? '');
        $fileSize = (int)($_FILES[$fieldName]['size'] ?? 0);
        $originalName = (string)($_FILES[$fieldName]['name'] ?? 'file');

        if (!is_uploaded_file($tmpPath)) {
          $errors[] = 'Invalid upload request for ' . strtolower($label) . '.';
          return $previousPath;
        }

        if ($fileSize <= 0 || $fileSize > $maxSize) {
          $errors[] = $label . ' must be a valid image up to ' . (int)round($maxSize / 1048576) . 'MB.';
          return $previousPath;
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExt = ['png', 'jpg', 'jpeg', 'webp'];
        if (!in_array($ext, $allowedExt, true)) {
          $errors[] = $label . ' must be PNG, JPG, JPEG, or WEBP.';
          return $previousPath;
        }

        $mime = '';
        if (function_exists('finfo_open')) {
          $finfo = finfo_open(FILEINFO_MIME_TYPE);
          if ($finfo) {
            $mime = (string)finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
          }
        }
        $allowedMime = ['image/png', 'image/jpeg', 'image/webp'];
        if ($mime !== '' && !in_array($mime, $allowedMime, true)) {
          $errors[] = $label . ' is not a valid image type.';
          return $previousPath;
        }

        try {
          $folder = defined('CLOUDINARY_UPLOAD_FOLDER')
            ? (string)constant('CLOUDINARY_UPLOAD_FOLDER')
            : 'ojtsync/export-logos';
          $publicId = 'student_' . $studentId . '_' . bin2hex(random_bytes(8));
          return uploadImageToCloudinary($tmpPath, $publicId, $folder);
        } catch (Throwable $e) {
          error_log('Cloudinary image upload failed: ' . $e->getMessage());
          $errors[] = 'Could not save ' . strtolower($label) . '. Please try again.';
          return $previousPath;
        }
      };

      if (empty($errors)) {
        $companyLogo = $processImageUpload('company_logo_file', 2 * 1024 * 1024, 'Company logo', $companyLogo);
      }

      if (empty($errors)) {
        saveExportProfile($pdo, $studentId, [
          'company_name' => $companyName,
          'company_address' => $companyAddress,
          'company_logo' => $companyLogo,
          'supervisor_name' => $supervisorName,
          'supervisor_title' => $supervisorTitle,
        ]);
        logActivity($pdo, $studentId, 'profile.export_profile_update', 'updated=1');
        flash('success', 'Export settings updated successfully. Your next PDF/CSV export will use these details.');
        header('Location: profile.php');
        exit;
      }

      $exportProfile = [
        'company_name' => $companyName,
        'company_address' => $companyAddress,
        'company_logo' => $companyLogo,
        'supervisor_name' => $supervisorName,
        'supervisor_title' => $supervisorTitle,
      ];
    } elseif ($action === 'change_password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $errors[] = 'Please complete all password fields.';
        } elseif (!password_verify($currentPassword, (string)$student['password'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New password and confirmation do not match.';
        } elseif ($newPassword === $currentPassword) {
            $errors[] = 'New password must be different from your current password.';
        }

        if (empty($errors)) {
            $emailLocal = explode('@', (string)$student['email'])[0] ?? '';
            $policy = validatePasswordPolicy($newPassword, [(string)$student['name'], (string)$student['email'], $emailLocal]);
            if (!$policy['ok']) {
                $errors = array_merge($errors, $policy['errors']);
            }
        }

        if (empty($errors)) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE students SET password=? WHERE id=?")->execute([$hash, $studentId]);
            logActivity($pdo, $studentId, 'profile.password_change', 'success=1');
            flash('success', 'Password updated successfully.');
            header('Location: profile.php');
            exit;
        }
      } elseif ($action === 'verify_delete_password') {
        $openDeleteModal = true;
        $deletePassword = (string)($_POST['delete_password'] ?? '');

        if ($deletePassword === '') {
          $errors[] = 'Enter your password to continue account deletion.';
        } elseif (!password_verify($deletePassword, (string)$student['password'])) {
          $errors[] = 'Password is incorrect. Account deletion was cancelled.';
          logActivity($pdo, $studentId, 'profile.delete_verify_failed', 'reason=bad_password');
        }

        if (empty($errors)) {
          $deleteToken = bin2hex(random_bytes(24));
          $_SESSION['delete_account_confirm'] = [
            'student_id' => $studentId,
            'token' => $deleteToken,
            'expires_at' => time() + 600,
          ];
          logActivity($pdo, $studentId, 'profile.delete_verify_passed', 'step=1');
          header('Location: profile.php?delete=confirm#delete-account');
          exit;
        }
      } elseif ($action === 'cancel_delete_flow') {
        unset($_SESSION['delete_account_confirm']);
        header('Location: profile.php');
        exit;
      } elseif ($action === 'delete_account_permanent') {
        $openDeleteModal = true;
        $deleteToken = (string)($_POST['delete_token'] ?? '');
        $ackBackup = (string)($_POST['ack_backup'] ?? '') === '1';
        $confirmPhrase = strtoupper(trim((string)($_POST['confirm_phrase'] ?? '')));
        $flow = $_SESSION['delete_account_confirm'] ?? null;

        $validFlow = is_array($flow)
          && (int)($flow['student_id'] ?? 0) === $studentId
          && (int)($flow['expires_at'] ?? 0) > time()
          && hash_equals((string)($flow['token'] ?? ''), $deleteToken);

        if (!$validFlow) {
          $errors[] = 'Delete confirmation expired. Verify your password again to continue.';
          unset($_SESSION['delete_account_confirm']);
        }
        if (!$ackBackup) {
          $errors[] = 'Please confirm that you downloaded or saved your records before deleting.';
        }
        if ($confirmPhrase !== 'DELETE') {
          $errors[] = 'Type DELETE to confirm permanent account deletion.';
        }

        if (empty($errors)) {
          try {
            $pdo->beginTransaction();
            logActivity($pdo, $studentId, 'profile.delete_confirmed', 'backup_ack=1');

            $del = $pdo->prepare("DELETE FROM students WHERE id=? LIMIT 1");
            $del->execute([$studentId]);

            if ($del->rowCount() !== 1) {
              throw new RuntimeException('Account not found for deletion.');
            }

            $pdo->commit();
            unset($_SESSION['delete_account_confirm']);
            logoutStudent();
            header('Location: login.php?account_deleted=1');
            exit;
          } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
              $pdo->rollBack();
            }
            $errors[] = 'Unable to delete your account right now. Please try again.';
          }
        }
    }
}

    $deleteFlow = $_SESSION['delete_account_confirm'] ?? null;
    $deleteReady = is_array($deleteFlow)
      && (int)($deleteFlow['student_id'] ?? 0) === $studentId
      && (int)($deleteFlow['expires_at'] ?? 0) > time()
      && !empty($deleteFlow['token']);
    $openDeleteModal = $openDeleteModal || $deleteReady;

$pageTitle = 'Profile';
$activePage = 'profile';
$bodyClass = 'profile-page';
include 'includes/header.php';
?>

<?php $msg = flash('success'); if ($msg): ?>
<div class="alert alert-success">✓ <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1>Profile & Security</h1>
  <p>Manage your account details, credentials, and account lifecycle.</p>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e): ?><div>⚠ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<section class="profile-hero card">
  <div class="card-body profile-hero-body">
    <div class="profile-identity">
      <div class="profile-avatar" aria-hidden="true">
        <?= htmlspecialchars(strtoupper(substr((string)$student['name'], 0, 1))) ?>
      </div>
      <div>
        <h2><?= htmlspecialchars((string)$student['name']) ?></h2>
        <p><?= htmlspecialchars((string)$student['email']) ?></p>
      </div>
    </div>
    <div class="profile-meta-grid">
      <div class="profile-meta-item">
        <span class="meta-label">Required Hours</span>
        <span class="meta-value"><?= number_format((float)$student['required_hours']) ?> hrs</span>
      </div>
      <div class="profile-meta-item">
        <span class="meta-label">Member Since</span>
        <span class="meta-value"><?= fmtDate((string)$student['created_at']) ?></span>
      </div>
    </div>
  </div>
</section>

<div class="row g-4 align-items-start profile-grid">
  <div class="col-12 col-xl-7">
    <div class="card">
      <div class="card-header">
        <span class="card-title">Account Information</span>
      </div>
      <div class="card-body">
        <form method="POST" action="profile.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="form_action" value="save_profile">

          <div class="form-group">
            <label class="form-label" for="name">Full Name <span>*</span></label>
            <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars((string)$student['name']) ?>" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="email">Email Address <span>*</span></label>
            <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars((string)$student['email']) ?>" required>
          </div>

          <div class="form-group">
            <label class="form-label">Required OJT Hours</label>
            <input type="text" class="form-control" value="<?= number_format((float)$student['required_hours']) ?>" disabled>
            <p class="form-hint">To change required hours, update your setup in Schedule.</p>
          </div>

          <div class="profile-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header">
        <span class="card-title">Export Document Settings</span>
      </div>
      <div class="card-body">
        <form method="POST" action="profile.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="form_action" value="save_export_profile">

          <div class="form-group">
               <label class="form-label" for="company_name">Organization Name <span>*</span></label>
            <input type="text" id="company_name" name="company_name" class="form-control"
                   value="<?= htmlspecialchars((string)($exportProfile['company_name'] ?? '')) ?>"
                 placeholder="Company, institution, or partner organization" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="company_address">Company Address</label>
            <input type="text" id="company_address" name="company_address" class="form-control"
                   value="<?= htmlspecialchars((string)($exportProfile['company_address'] ?? '')) ?>"
                   placeholder="Street, city, province, country">
            <p class="form-hint">This appears below the company name in the PDF header.</p>
          </div>

          <div class="form-group">
            <label class="form-label" for="company_logo_file">Company Logo</label>
            <input type="file" id="company_logo_file" name="company_logo_file" class="form-control"
                   accept="image/png,image/jpeg,image/webp">
            <p class="form-hint">Upload PNG, JPG, or WEBP (max 2MB). Leave blank to keep the current logo.</p>
            <div style="margin-top:.5rem;font-size:.82rem;color:var(--slate-500);">
              Current file: <?= htmlspecialchars((string)($exportProfile['company_logo'] ?? 'logo/Logo.png')) ?>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="supervisor_name">Authorized Signatory Name <span>*</span></label>
            <input type="text" id="supervisor_name" name="supervisor_name" class="form-control"
                   value="<?= htmlspecialchars((string)($exportProfile['supervisor_name'] ?? '')) ?>"
                   placeholder="Full name of immediate supervisor/coordinator" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="supervisor_title">Authorized Signatory Title <span>*</span></label>
            <input type="text" id="supervisor_title" name="supervisor_title" class="form-control"
                   value="<?= htmlspecialchars((string)($exportProfile['supervisor_title'] ?? '')) ?>"
                   placeholder="Position / title (e.g., IT Supervisor)" required>
          </div>

          <div class="profile-actions">
            <button type="submit" class="btn btn-primary">Save Export Settings</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-5">
    <div class="card">
      <div class="card-header">
        <span class="card-title">Change Password</span>
      </div>
      <div class="card-body">
        <form method="POST" action="profile.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="form_action" value="change_password">

          <div class="form-group">
            <label class="form-label" for="current_password">Current Password <span>*</span></label>
            <input type="password" id="current_password" name="current_password" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="new_password">New Password <span>*</span></label>
            <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Min. 12 chars, upper/lower/number/symbol" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="confirm_password">Confirm New Password <span>*</span></label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
          </div>

          <div class="profile-actions">
            <button type="submit" class="btn btn-accent">Update Password</button>
          </div>
        </form>
      </div>
    </div>

    <div id="delete-account" class="card mt-3 danger-zone-card">
      <div class="card-header danger-zone-head">
        <span class="card-title">Delete Account</span>
      </div>
      <div class="card-body">
        <p class="danger-zone-text">
          Deleting your account permanently removes your profile and all records. This cannot be undone.
        </p>
        <button type="button" class="btn btn-danger" data-delete-modal-open="true">
          Delete Account
        </button>
      </div>
    </div>
  </div>
</div>

<div id="deleteAccountModal" class="modal-overlay profile-delete-modal <?= $openDeleteModal ? 'active' : '' ?>" aria-hidden="<?= $openDeleteModal ? 'false' : 'true' ?>">
  <div class="app-modal delete-account-modal" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
    <div class="modal-header delete-modal-header">
      <div class="delete-modal-headline">
        <span class="delete-modal-icon" aria-hidden="true"><i class="ti ti-trash"></i></span>
        <div>
          <h3 class="modal-title" id="deleteModalTitle">Delete Account</h3>
          <p class="delete-modal-subtitle">This action is permanent.</p>
        </div>
      </div>
    </div>

    <div class="modal-body delete-modal-body">
      <?php if (!$deleteReady): ?>
      <p class="delete-modal-copy">
        Enter your password to verify this is your account.
      </p>

      <form method="POST" action="profile.php#delete-account">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="form_action" value="verify_delete_password">

        <div class="form-group">
          <label class="form-label" for="delete_password">Password <span>*</span></label>
          <input type="password" id="delete_password" name="delete_password" class="form-control" required>
        </div>

        <div class="modal-footer delete-modal-footer">
          <button type="button" class="btn btn-outline" data-delete-modal-close="true">Cancel</button>
          <button type="submit" class="btn btn-danger">Verify</button>
        </div>
      </form>
      <?php else: ?>
      <div class="alert alert-warning delete-reminder">
        <p><strong>Before deleting:</strong> Save or download your records first. Use <a href="reports.php">Reports</a> and <a href="export.php">Export</a>.</p>
      </div>

      <form method="POST" action="profile.php#delete-account">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="delete_token" value="<?= htmlspecialchars((string)$deleteFlow['token']) ?>">

        <div class="form-check delete-check-wrap">
          <input class="form-check-input" type="checkbox" id="ack_backup" name="ack_backup" value="1" required>
          <label class="form-check-label" for="ack_backup">I already saved or downloaded my records.</label>
        </div>

        <div class="form-group">
          <label class="form-label" for="confirm_phrase">Type <strong>DELETE</strong> to confirm <span>*</span></label>
          <input type="text" id="confirm_phrase" name="confirm_phrase" class="form-control" placeholder="DELETE" required>
        </div>

        <div class="modal-footer delete-modal-footer delete-modal-footer-split">
          <button type="submit" name="form_action" value="cancel_delete_flow" formnovalidate class="btn btn-outline">Cancel</button>
          <button
            type="submit"
            name="form_action"
            value="delete_account_permanent"
            class="btn btn-danger"
            data-confirm="This will permanently delete your account and all records. Continue?"
            data-confirm-ok="Delete Permanently"
            data-confirm-title="Final Delete Confirmation"
            data-confirm-variant="danger"
            data-confirm-icon="!"
            data-confirm-note="This action cannot be undone."
          >Delete Permanently</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
