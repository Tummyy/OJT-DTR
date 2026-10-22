<?php
// includes/footer.php — Shared page footer
$_scriptPath = __DIR__ . '/../assets/js/script.js';
$_scriptVer  = is_file($_scriptPath) ? (string)filemtime($_scriptPath) : (string)time();
?>
</main>

<?php if (isLoggedIn()): ?>
<footer class="app-footer">
  <p>&copy; <?= date('Y') ?> OJTSync &mdash; Built for OJT Students</p>
</footer>

<div id="appConfirmModal" class="modal-overlay" aria-hidden="true">
  <div class="app-modal confirm-modal" role="dialog" aria-modal="true" aria-labelledby="appConfirmTitle">
    <div class="modal-header">
      <div class="confirm-head">
        <span class="confirm-icon" id="appConfirmIcon" aria-hidden="true">!</span>
        <h3 class="modal-title" id="appConfirmTitle">Confirm Action</h3>
      </div>
      <button type="button" class="modal-close" id="appConfirmClose" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">
      <p id="appConfirmMessage" class="confirm-message">Are you sure you want to continue?</p>
      <p id="appConfirmNote" class="confirm-note" hidden></p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline btn-sm" id="appConfirmCancel">Cancel</button>
      <button type="button" class="btn btn-danger btn-sm" id="appConfirmOk">Continue</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="assets/js/script.js?v=<?= htmlspecialchars($_scriptVer) ?>"></script>
</body>
</html>
