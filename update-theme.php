<?php
// update-theme.php - Save theme preference for the logged-in user
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

requireLogin();
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$rawTheme = (string)($_POST['theme'] ?? '');
if (!isAllowedTheme($rawTheme)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid theme']);
    exit;
}

$theme = normalizeTheme($rawTheme);

// Keeps existing installations working even before they run schema updates.
function ensureThemePreferenceColumn(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM students LIKE 'theme_preference'");
    $exists = (bool)$stmt->fetch();

    if (!$exists) {
        $pdo->exec("ALTER TABLE students ADD COLUMN theme_preference VARCHAR(20) NOT NULL DEFAULT 'light' AFTER password");
    }

    $checked = true;
}

try {
    ensureThemePreferenceColumn($pdo);

    $studentId = currentStudentId();
    $stmt = $pdo->prepare("UPDATE students SET theme_preference = ? WHERE id = ? LIMIT 1");
    $stmt->execute([$theme, $studentId]);

    setSessionTheme($theme);
    logActivity($pdo, $studentId, 'ui.theme_changed', 'theme=' . $theme);

    echo json_encode(['ok' => true, 'theme' => $theme]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Unable to save theme']);
}
