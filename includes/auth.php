<?php
// includes/auth.php — Session & Auth helpers

function isHttpsRequest(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    // Render terminates TLS at its proxy and forwards the original scheme.
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return false;
}

if (session_status() === PHP_SESSION_NONE) {
    $sessionLifetime = 60 * 60 * 24 * 30; // 30 days
    ini_set('session.gc_maxlifetime', (string)$sessionLifetime);
    ini_set('session.cookie_lifetime', (string)$sessionLifetime);
    $sessionSavePath = trim((string)(getenv('SESSION_SAVE_PATH') ?: ''));
    if ($sessionSavePath !== '') {
        if (!is_dir($sessionSavePath) || !is_writable($sessionSavePath)) {
            throw new RuntimeException('Configured SESSION_SAVE_PATH is not writable.');
        }
        ini_set('session.save_path', $sessionSavePath);
    }

    // Set secure defaults before starting session.
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'domain' => '',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

const SESSION_IDLE_TIMEOUT = 2592000;   // 30 days
const SESSION_ABSOLUTE_TTL = 2592000;    // 30 days
const ALLOWED_THEMES = ['light', 'dark']; 

function normalizeTheme(string $theme): string {
    $theme = trim(strtolower($theme));
    return in_array($theme, ALLOWED_THEMES, true) ? $theme : 'light';
}

function isAllowedTheme(string $theme): bool {
    return in_array(trim(strtolower($theme)), ALLOWED_THEMES, true);
}

function currentTheme(): string {
    return normalizeTheme((string)($_SESSION['theme_preference'] ?? 'light'));
}

function setSessionTheme(string $theme): void {
    $_SESSION['theme_preference'] = normalizeTheme($theme);
}

function clearAuthSession(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
    }
    session_destroy();
}

function sessionFingerprint(): string {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $ip = getClientIp();
    return hash('sha256', $ua . '|' . $ip);
}

function isSessionValid(): bool {
    if (empty($_SESSION['student_id'])) {
        return false;
    }

    $now = time();
    $createdAt = (int)($_SESSION['session_created_at'] ?? 0);
    $lastActivity = (int)($_SESSION['last_activity'] ?? 0);
    $storedFingerprint = (string)($_SESSION['session_fingerprint'] ?? '');

    if ($createdAt <= 0 || $lastActivity <= 0 || $storedFingerprint === '') {
        return false;
    }

    if (($now - $lastActivity) > SESSION_IDLE_TIMEOUT) {
        return false;
    }

    if (($now - $createdAt) > SESSION_ABSOLUTE_TTL) {
        return false;
    }

    if (!hash_equals($storedFingerprint, sessionFingerprint())) {
        return false;
    }

    $_SESSION['last_activity'] = $now;
    return true;
}

function isLoggedIn(): bool {
    return isset($_SESSION['student_id']) && !empty($_SESSION['student_id']);
}

function requireLogin(): void {
    if (!isLoggedIn() || !isSessionValid()) {
        clearAuthSession();
        header('Location: login.php');
        exit;
    }
}

function requireGuest(): void {
    if (isLoggedIn() && isSessionValid()) {
        header('Location: dashboard.php');
        exit;
    }
    if (isLoggedIn()) {
        clearAuthSession();
    }
}

function currentStudentId(): int {
    return (int)($_SESSION['student_id'] ?? 0);
}

function currentStudentName(): string {
    return $_SESSION['student_name'] ?? 'Student';
}

function loginStudent(array $student): void {
    session_regenerate_id(true);
    $now = time();
    $_SESSION['student_id']    = $student['id'];
    $_SESSION['student_name']  = $student['name'];
    $_SESSION['student_email'] = $student['email'];
    $_SESSION['session_created_at'] = $now;
    $_SESSION['last_activity'] = $now;
    $_SESSION['session_fingerprint'] = sessionFingerprint();
    setSessionTheme((string)($student['theme_preference'] ?? 'light'));
}

function logoutStudent(): void {
    clearAuthSession();
}

function setPendingVerification(int $studentId, string $email): void {
    $_SESSION['pending_verification'] = [
        'student_id' => $studentId,
        'email' => strtolower(trim($email)),
        'created_at' => time(),
    ];
}

function getPendingVerification(): ?array {
    $pending = $_SESSION['pending_verification'] ?? null;
    if (!is_array($pending)) {
        return null;
    }

    $studentId = (int)($pending['student_id'] ?? 0);
    $email = (string)($pending['email'] ?? '');
    if ($studentId <= 0 || $email === '') {
        return null;
    }

    return [
        'student_id' => $studentId,
        'email' => $email,
        'created_at' => (int)($pending['created_at'] ?? 0),
    ];
}

function clearPendingVerification(): void {
    unset($_SESSION['pending_verification']);
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getClientIp(): string {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function loginThrottleKey(string $email = ''): string {
    $ip = getClientIp();
    $normalizedEmail = strtolower(trim($email));
    return hash('sha256', $ip . '|' . $normalizedEmail);
}

function getLoginThrottleState(string $email = ''): array {
    $key = loginThrottleKey($email);
    $state = $_SESSION['login_throttle'][$key] ?? ['count' => 0, 'first' => time(), 'until' => 0];

    // Reset rolling window after 15 minutes.
    if (time() - (int)$state['first'] > 900) {
        $state = ['count' => 0, 'first' => time(), 'until' => 0];
        $_SESSION['login_throttle'][$key] = $state;
    }

    return $state;
}

function isLoginBlocked(string $email = ''): bool {
    $state = getLoginThrottleState($email);
    return time() < (int)$state['until'];
}

function loginBlockRemainingSeconds(string $email = ''): int {
    $state = getLoginThrottleState($email);
    return max(0, (int)$state['until'] - time());
}

function registerLoginFailure(string $email = ''): void {
    $key = loginThrottleKey($email);
    $state = getLoginThrottleState($email);
    $state['count'] = (int)$state['count'] + 1;

    // Escalating lock durations after repeated failures.
    if ($state['count'] >= 10) {
        $state['until'] = time() + 900; // 15 minutes
    } elseif ($state['count'] >= 7) {
        $state['until'] = time() + 300; // 5 minutes
    } elseif ($state['count'] >= 5) {
        $state['until'] = time() + 120; // 2 minutes
    }

    $_SESSION['login_throttle'][$key] = $state;
}

function clearLoginFailures(string $email = ''): void {
    $key = loginThrottleKey($email);
    if (isset($_SESSION['login_throttle'][$key])) {
        unset($_SESSION['login_throttle'][$key]);
    }
}

function flash(string $key, string $message = ''): string {
    if ($message !== '') {
        $_SESSION['flash'][$key] = $message;
        return '';
    }
    $msg = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $msg;
}
