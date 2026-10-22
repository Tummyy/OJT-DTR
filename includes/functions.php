<?php
// includes/functions.php — Core DTR computation & helpers

/**
 * Convert H:i:s or H:i to decimal hours
 */
function timeToHours(string $time): float {
    if (empty($time)) return 0.0;
    $parts = explode(':', $time);
    $h = (int)($parts[0] ?? 0);
    $m = (int)($parts[1] ?? 0);
    $s = (int)($parts[2] ?? 0);
    return $h + ($m / 60) + ($s / 3600);
}

/**
 * Format decimal hours to H:MM
 */
function hoursToHM(float $hours): string {
    if ($hours <= 0) return '0:00';
    $h = floor($hours);
    $m = round(($hours - $h) * 60);
    if ($m === 60) { $h++; $m = 0; }
    return $h . ':' . str_pad($m, 2, '0', STR_PAD_LEFT);
}

/**
 * Compute DTR hours from raw inputs
 * Returns array: raw_hours, regular_hours, overtime_hours, total_hours, status
 */
function computeDTR(string $timeIn, string $timeOut, float $breakDuration,
                    float $maxHoursPerDay, bool $allowOvertime, float $maxOT): array
{
    if (empty($timeIn) || empty($timeOut)) {
        return [
            'raw_hours'      => 0,
            'regular_hours'  => 0,
            'overtime_hours' => 0,
            'total_hours'    => 0,
            'status'         => 'incomplete',
        ];
    }

    $inH  = timeToHours($timeIn);
    $outH = timeToHours($timeOut);

    // Handle overnight shift
    if ($outH <= $inH) $outH += 24;

    $raw     = $outH - $inH;
    $worked  = max(0, $raw - $breakDuration);

    if ($worked <= 0) {
        return [
            'raw_hours'      => round($raw, 2),
            'regular_hours'  => 0,
            'overtime_hours' => 0,
            'total_hours'    => 0,
            'status'         => 'invalid',
        ];
    }

    $effectiveMax = $maxHoursPerDay > 0 ? $maxHoursPerDay : $worked;
    $regular  = min($worked, $effectiveMax);
    $overtime = max(0, $worked - $regular);

    // Count all actual worked time (including minutes) toward rendered hours.
    $total = $worked;

    return [
        'raw_hours'      => round($raw, 2),
        'regular_hours'  => round($regular, 2),
        'overtime_hours' => round($overtime, 2),
        'total_hours'    => round($total, 2),
        'status'         => 'valid',
    ];
}

/**
 * Get student's total rendered hours
 */
function getTotalRendered(PDO $pdo, int $studentId): float {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_hours),0) FROM dtr_records WHERE student_id=? AND status='valid'");
    $stmt->execute([$studentId]);
    return (float)$stmt->fetchColumn();
}

/**
 * Get student's schedule (returns defaults if not set)
 */
function getSchedule(PDO $pdo, int $studentId): array {
    $stmt = $pdo->prepare("SELECT * FROM schedules WHERE student_id=?");
    $stmt->execute([$studentId]);
    $s = $stmt->fetch();
    if (!$s) {
        return [
            'student_id'         => $studentId,
            'start_date'         => date('Y-m-d'),
            'default_time_in'    => '08:00:00',
            'default_time_out'   => '17:00:00',
            'break_duration'     => 1.00,
            'max_hours_per_day'  => 8.00,
            'allow_overtime'     => 0,
            'max_overtime_per_day'=> 2.00,
            'rest_days'          => '0,6',
            '_is_default'        => 1,
        ];
    }
    $s['_is_default'] = 0;
    return $s;
}

/**
 * Get holidays for a student indexed by date string
 */
function getHolidaysMap(PDO $pdo, int $studentId): array {
    $stmt = $pdo->prepare("SELECT holiday_date, name, type FROM holidays WHERE student_id=?");
    $stmt->execute([$studentId]);
    $map = [];
    foreach ($stmt->fetchAll() as $h) {
        $map[$h['holiday_date']] = $h;
    }
    return $map;
}

/**
 * Check if a date is a working day
 * Returns: 'work', 'holiday_regular', 'holiday_special_non_working', 'holiday_special_working', 'rest_day'
 */
function getDayType(string $dateStr, array $holidaysMap, array $restDays): string {
    if (isset($holidaysMap[$dateStr])) {
        $h = $holidaysMap[$dateStr];
        if ($h['type'] === 'regular')              return 'holiday_regular';
        if ($h['type'] === 'special_non_working')  return 'holiday_special_non_working';
        if ($h['type'] === 'special_working')      return 'holiday_special_working';
    }
    $dow = (int)date('w', strtotime($dateStr)); // 0=Sun, 6=Sat
    if (in_array($dow, $restDays)) {
        return 'rest_day';
    }
    return 'work';
}

/**
 * Estimate effective rendered hours per workday from actual valid logs.
 */
function getProjectedDailyHours(PDO $pdo, int $studentId, array $schedule, array $holidays): float {
    $restDays = array_map('intval', explode(',', $schedule['rest_days'] ?? '0,6'));
    $stmt = $pdo->prepare(
        "SELECT record_date, total_hours
         FROM dtr_records
         WHERE student_id=? AND status='valid' AND total_hours > 0
         ORDER BY record_date DESC
         LIMIT 120"
    );
    $stmt->execute([$studentId]);

    $sum = 0.0;
    $count = 0;
    foreach ($stmt->fetchAll() as $r) {
        $dayType = getDayType((string)$r['record_date'], $holidays, $restDays);
        if ($dayType === 'work' || $dayType === 'holiday_special_working') {
            $sum += (float)$r['total_hours'];
            $count++;
        }
    }

    if ($count > 0) {
        return max(0.25, min(16.0, $sum / $count));
    }

    $defaultIn = (string)($schedule['default_time_in'] ?? '08:00:00');
    $defaultOut = (string)($schedule['default_time_out'] ?? '17:00:00');
    $defaultBreak = (float)($schedule['break_duration'] ?? 1.0);
    $fallback = max(0, timeToHours($defaultOut) - timeToHours($defaultIn) - $defaultBreak);
    if ($fallback <= 0) {
        $fallback = (float)($schedule['max_hours_per_day'] ?? 8.0);
    }
    if ($fallback <= 0) {
        $fallback = 8.0;
    }
    return $fallback;
}

/**
 * Calculate expected OJT end date
 */
function calculateEndDate(PDO $pdo, int $studentId, array $schedule, array $holidays): string {
    $student = getStudent($pdo, $studentId);
    $required = (float)$student['required_hours'];
    $rendered = getTotalRendered($pdo, $studentId);
    $remaining = $required - $rendered;

    if ($remaining <= 0) return 'Completed!';

    $restDays = array_map('intval', explode(',', $schedule['rest_days'] ?? '0,6'));
    // Official policy: project using fixed regular hours per working day.
    // This keeps expected completion deterministic across environments.
    $dailyEff = (float)($schedule['max_hours_per_day'] ?? 8.0);
    if ($dailyEff <= 0) {
        $dailyEff = 8.0;
    }

    $cursor = new DateTime('today');
    $limit  = 730; // safety: max 2 years
    $i = 0;

    while ($remaining > 0 && $i < $limit) {
        $dateStr = $cursor->format('Y-m-d');
        $dayType = getDayType($dateStr, $holidays, $restDays);

        if ($dayType === 'work' || $dayType === 'holiday_special_working') {
            $remaining -= $dailyEff;
        }
        $cursor->modify('+1 day');
        $i++;
    }

    // Go back one day (the loop overshoots by one)
    $cursor->modify('-1 day');
    return $cursor->format('F j, Y');
}

/**
 * Fetch a single student record
 */
function getStudent(PDO $pdo, int $id): array {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id=?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: [];
}

/**
 * Ensure per-student export profile table exists.
 */
function ensureExportProfileTable(PDO $pdo): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS export_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            company_name VARCHAR(150) NOT NULL DEFAULT '',
            company_address VARCHAR(255) NOT NULL DEFAULT '',
            company_logo VARCHAR(255) NOT NULL DEFAULT 'logo/Logo.png',
            supervisor_name VARCHAR(120) NOT NULL DEFAULT '',
            supervisor_title VARCHAR(120) NOT NULL DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_export_profile_student (student_id),
            CONSTRAINT fk_export_profile_student FOREIGN KEY (student_id)
                REFERENCES students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Backward compatibility for existing databases created before company_address existed.
    try {
        $col = $pdo->query("SHOW COLUMNS FROM export_profiles LIKE 'company_address'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE export_profiles ADD COLUMN company_address VARCHAR(255) NOT NULL DEFAULT '' AFTER company_name");
        }
    } catch (Throwable $e) {
        // Never block main app flows due to migration checks.
    }

    $initialized = true;
}

/**
 * Load per-student export profile (phase 1).
 */
function getExportProfile(PDO $pdo, int $studentId): array {
    ensureExportProfileTable($pdo);

    try {
        $stmt = $pdo->prepare('SELECT company_name, company_address, company_logo, supervisor_name, supervisor_title FROM export_profiles WHERE student_id = ? LIMIT 1');
        $stmt->execute([$studentId]);
        $row = $stmt->fetch() ?: [];
    } catch (Throwable $e) {
        // Fallback for legacy schema while migration catches up.
        $stmt = $pdo->prepare('SELECT company_name, company_logo, supervisor_name, supervisor_title FROM export_profiles WHERE student_id = ? LIMIT 1');
        $stmt->execute([$studentId]);
        $row = $stmt->fetch() ?: [];
    }

    return [
        'company_name' => trim((string)($row['company_name'] ?? '')),
        'company_address' => trim((string)($row['company_address'] ?? '')),
        'company_logo' => trim((string)($row['company_logo'] ?? 'logo/Logo.png')),
        'supervisor_name' => trim((string)($row['supervisor_name'] ?? '')),
        'supervisor_title' => trim((string)($row['supervisor_title'] ?? '')),
    ];
}

/**
 * Save per-student export profile (phase 1).
 */
function saveExportProfile(PDO $pdo, int $studentId, array $profile): void {
    ensureExportProfileTable($pdo);

    $companyName = trim((string)($profile['company_name'] ?? ''));
    $companyAddress = trim((string)($profile['company_address'] ?? ''));
    $companyLogo = trim((string)($profile['company_logo'] ?? 'logo/Logo.png'));
    $supervisorName = trim((string)($profile['supervisor_name'] ?? ''));
    $supervisorTitle = trim((string)($profile['supervisor_title'] ?? ''));

    $stmt = $pdo->prepare(
        "INSERT INTO export_profiles (student_id, company_name, company_address, company_logo, supervisor_name, supervisor_title)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            company_name = VALUES(company_name),
            company_address = VALUES(company_address),
            company_logo = VALUES(company_logo),
            supervisor_name = VALUES(supervisor_name),
            supervisor_title = VALUES(supervisor_title)"
    );
    $stmt->execute([$studentId, $companyName, $companyAddress, $companyLogo, $supervisorName, $supervisorTitle]);
}

/**
 * Upload an image to Cloudinary and return its delivery URL.
 */
function uploadImageToCloudinary(string $filePath, string $publicId, string $folder): ?string {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required for image uploads.');
    }

    $cloudName = defined('CLOUDINARY_CLOUD_NAME') ? (string)constant('CLOUDINARY_CLOUD_NAME') : '';
    $apiKey = defined('CLOUDINARY_API_KEY') ? (string)constant('CLOUDINARY_API_KEY') : '';
    $apiSecret = defined('CLOUDINARY_API_SECRET') ? (string)constant('CLOUDINARY_API_SECRET') : '';
    if ($cloudName === '' || $apiKey === '' || $apiSecret === '') {
        throw new RuntimeException('Cloudinary image storage is not configured.');
    }
    if (!is_file($filePath) || !is_readable($filePath)) {
        throw new RuntimeException('The uploaded image could not be read.');
    }

    $timestamp = time();
    $signatureParams = [
        'folder' => $folder,
        'public_id' => $publicId,
        'timestamp' => $timestamp,
    ];
    ksort($signatureParams);
    $signatureParts = [];
    foreach ($signatureParams as $key => $value) {
        $signatureParts[] = $key . '=' . $value;
    }
    $signature = sha1(implode('&', $signatureParts) . $apiSecret);

    $ch = curl_init('https://api.cloudinary.com/v1_1/' . rawurlencode($cloudName) . '/image/upload');
    if ($ch === false) {
        throw new RuntimeException('Unable to initialize the image upload request.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POSTFIELDS => [
            'file' => new CURLFile($filePath),
            'api_key' => $apiKey,
            'timestamp' => (string)$timestamp,
            'folder' => $folder,
            'public_id' => $publicId,
            'signature' => $signature,
        ],
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        throw new RuntimeException('Image upload request failed: ' . $curlError);
    }

    $result = json_decode($response, true);
    if ($statusCode < 200 || $statusCode >= 300 || !is_array($result) || empty($result['secure_url'])) {
        $message = is_array($result) ? (string)($result['error']['message'] ?? '') : '';
        throw new RuntimeException('Image storage rejected the upload.' . ($message !== '' ? ' ' . $message : ''));
    }

    return (string)$result['secure_url'];
}

/**
 * Format a date nicely
 */
function fmtDate(string $date): string {
    if (!$date) return '—';
    return date('M d, Y', strtotime($date));
}

/**
 * Format time to 12-hour
 */
function fmtTime(string $time): string {
    if (!$time || $time === '00:00:00') return '—';
    return date('h:i A', strtotime('1970-01-01 ' . $time));
}

/**
 * Convert HH:MM or HH:MM:SS to minutes from midnight.
 */
function timeToMinutes(string $time): int {
    if ($time === '') return 0;
    $parts = explode(':', $time);
    $h = (int)($parts[0] ?? 0);
    $m = (int)($parts[1] ?? 0);
    return ($h * 60) + $m;
}

/**
 * Compute late minutes for a given time-in and expected schedule time-in.
 */
function computeLateMinutes(string $timeIn, string $expectedTimeIn, int $graceMinutes = 0): int {
    if ($timeIn === '' || $expectedTimeIn === '') {
        return 0;
    }

    $late = timeToMinutes($timeIn) - timeToMinutes($expectedTimeIn) - max(0, $graceMinutes);
    return max(0, $late);
}

/**
 * Summarize late statistics from DTR records using record date rules.
 */
function getLateStats(PDO $pdo, int $studentId, array $schedule, array $holidays, int $graceMinutes = 0): array {
    $restDays = array_map('intval', explode(',', (string)($schedule['rest_days'] ?? '0,6')));
    $expectedTimeIn = substr((string)($schedule['default_time_in'] ?? '08:00:00'), 0, 5);
    $today = date('Y-m-d');

    $stmt = $pdo->prepare(
        "SELECT record_date, time_in, status
         FROM dtr_records
         WHERE student_id = ?
           AND time_in IS NOT NULL
           AND time_in <> '00:00:00'
           AND status IN ('valid', 'incomplete')
         ORDER BY record_date DESC"
    );
    $stmt->execute([$studentId]);

    $lateDays = 0;
    $totalLateMinutes = 0;
    $todayLateMinutes = 0;
    $todayHasRecord = false;
    $recentLateDates = [];

    foreach ($stmt->fetchAll() as $r) {
        $recordDate = (string)$r['record_date'];
        $timeIn = substr((string)$r['time_in'], 0, 5);

        $dayType = getDayType($recordDate, $holidays, $restDays);
        if ($dayType !== 'work' && $dayType !== 'holiday_special_working') {
            continue;
        }

        $lateMinutes = computeLateMinutes($timeIn, $expectedTimeIn, $graceMinutes);
        if ($recordDate === $today) {
            $todayHasRecord = true;
            $todayLateMinutes = $lateMinutes;
        }

        if ($lateMinutes > 0) {
            $lateDays++;
            $totalLateMinutes += $lateMinutes;
            if (count($recentLateDates) < 5) {
                $recentLateDates[] = [
                    'record_date' => $recordDate,
                    'late_minutes' => $lateMinutes,
                ];
            }
        }
    }

    return [
        'late_days' => $lateDays,
        'total_late_minutes' => $totalLateMinutes,
        'today_has_record' => $todayHasRecord,
        'today_late_minutes' => $todayLateMinutes,
        'today_is_late' => $todayHasRecord && $todayLateMinutes > 0,
        'grace_minutes' => max(0, $graceMinutes),
        'recent_late_dates' => $recentLateDates,
    ];
}

/**
 * Format minutes into H:MM (e.g., 125 -> 2:05).
 */
function minutesToHM(int $minutes): string {
    if ($minutes <= 0) return '0:00';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
}

/**
 * Get status badge HTML
 */
function statusBadge(string $status): string {
    $map = [
        'valid'      => ['✓ Valid',      'badge-success'],
        'incomplete' => ['⏳ Incomplete', 'badge-warning'],
        'invalid'    => ['✗ Invalid',    'badge-danger'],
        'absent'     => ['✗ Absent',     'badge-danger'],
    ];
    [$label, $cls] = $map[$status] ?? [$status, 'badge-secondary'];
    return "<span class=\"badge $cls\">$label</span>";
}

/**
 * Get weekly range label
 */
function getWeekLabel(string $date): string {
    $ts  = strtotime($date);
    $mon = strtotime('monday this week', $ts);
    $sun = strtotime('sunday this week', $ts);
    return date('M d', $mon) . ' – ' . date('M d, Y', $sun);
}

/**
 * Validate YYYY-MM-DD date string.
 */
function isValidDateYmd(string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $date));
    return checkdate($m, $d, $y);
}

/**
 * Validate YYYY-MM month string.
 */
function isValidMonthYm(string $month): bool {
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        return false;
    }
    [$y, $m] = array_map('intval', explode('-', $month));
    return $y >= 1970 && $y <= 2100 && $m >= 1 && $m <= 12;
}

/**
 * Normalize month input. Accepts YYYY-MM, "March", or "March 2026".
 */
function normalizeMonthInput(string $input): string {
    $input = trim($input);
    if ($input === '') {
        return '';
    }

    if (isValidMonthYm($input)) {
        return $input;
    }

    $candidate = $input;
    if (!preg_match('/\b\d{4}\b/', $candidate)) {
        $candidate .= ' ' . date('Y');
    }

    $ts = strtotime('1 ' . $candidate);
    if ($ts === false) {
        return '';
    }

    return date('Y-m', $ts);
}

/**
 * Ensure the activity log table exists.
 */
function ensureActivityLogTable(PDO $pdo): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS activity_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NULL,
            action VARCHAR(60) NOT NULL,
            details VARCHAR(255) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_activity_student_time (student_id, created_at),
            INDEX idx_activity_action_time (action, created_at),
            CONSTRAINT fk_activity_student FOREIGN KEY (student_id)
                REFERENCES students(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $initialized = true;
}

/**
 * Write an audit log entry for sensitive actions.
 */
function logActivity(PDO $pdo, ?int $studentId, string $action, string $details = ''): void {
    try {
        ensureActivityLogTable($pdo);
        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs (student_id, action, details, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $studentId,
            substr($action, 0, 60),
            $details !== '' ? substr($details, 0, 255) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
        ]);
    } catch (Throwable $e) {
        // Logging should never break user flows.
    }
}

/**
 * Ensure password reset table exists.
 */
function ensurePasswordResetTable(PDO $pdo): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_resets (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            request_ip VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_reset_token_hash (token_hash),
            INDEX idx_reset_student_time (student_id, created_at),
            INDEX idx_reset_expiry (expires_at),
            CONSTRAINT fk_password_reset_student FOREIGN KEY (student_id)
                REFERENCES students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $initialized = true;
}

/**
 * Create a single-use, expiring password reset token.
 */
function createPasswordResetToken(PDO $pdo, int $studentId): string {
    ensurePasswordResetTable($pdo);

    // Invalidate any still-active tokens to reduce token surface.
    $pdo->prepare("UPDATE password_resets SET used_at=NOW() WHERE student_id=? AND used_at IS NULL")
        ->execute([$studentId]);

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        "INSERT INTO password_resets (student_id, token_hash, expires_at, request_ip, user_agent)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $studentId,
        $tokenHash,
        $expiresAt,
        $_SERVER['REMOTE_ADDR'] ?? null,
        isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
    ]);

    return $token;
}

/**
 * Resolve a reset token to a student if still valid.
 */
function findValidPasswordReset(PDO $pdo, string $token): ?array {
    ensurePasswordResetTable($pdo);
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        "SELECT pr.id AS reset_id, pr.student_id, s.email, s.name
         FROM password_resets pr
         INNER JOIN students s ON s.id = pr.student_id
         WHERE pr.token_hash = ?
           AND pr.used_at IS NULL
           AND pr.expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * Consume a token and update student password atomically.
 */
function resetPasswordWithToken(PDO $pdo, string $token, string $newPassword): bool {
    $valid = findValidPasswordReset($pdo, $token);
    if (!$valid) {
        return false;
    }

    $pdo->beginTransaction();
    try {
        $resetId = (int)$valid['reset_id'];
        $studentId = (int)$valid['student_id'];

        $mark = $pdo->prepare(
            "UPDATE password_resets
             SET used_at = NOW()
             WHERE id = ? AND used_at IS NULL AND expires_at > NOW()"
        );
        $mark->execute([$resetId]);

        if ($mark->rowCount() !== 1) {
            $pdo->rollBack();
            return false;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE students SET password = ? WHERE id = ?")
            ->execute([$hash, $studentId]);

        // Invalidate all other outstanding tokens for this user.
        $pdo->prepare("UPDATE password_resets SET used_at=NOW() WHERE student_id=? AND used_at IS NULL")
            ->execute([$studentId]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

/**
 * Write mail delivery diagnostics to logs/mail.log.
 */
function logMailEvent(string $message): void {
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($logDir . '/mail.log', $line, FILE_APPEND);
}

/**
 * Load PHPMailer classes from Composer if available.
 */
function loadPhpMailerClasses(): bool {
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
        return true;
    }

    $baseDir = dirname(__DIR__);
    $autoload = $baseDir . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }

    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return true;
    }

    $mailerDir = $baseDir . '/vendor/phpmailer/phpmailer/src/';
    $exceptionFile = $mailerDir . 'Exception.php';
    $phpMailerFile = $mailerDir . 'PHPMailer.php';
    $smtpFile = $mailerDir . 'SMTP.php';

    if (is_file($exceptionFile) && is_file($phpMailerFile) && is_file($smtpFile)) {
        require_once $exceptionFile;
        require_once $phpMailerFile;
        require_once $smtpFile;
    }

    return class_exists('PHPMailer\\PHPMailer\\PHPMailer');
}

/**
 * Send a plain-text email through PHPMailer SMTP when configured, else fallback to mail().
 */
function sendAppEmail(string $to, string $subject, string $plainBody): bool {
    $fromEmail = defined('MAIL_FROM') ? (string)constant('MAIL_FROM') : 'noreply@localhost';
    $fromName = defined('MAIL_FROM_NAME') ? (string)constant('MAIL_FROM_NAME') : (defined('APP_NAME') ? (string)constant('APP_NAME') : 'OJTSync');

    $smtpHost = defined('SMTP_HOST') ? trim((string)constant('SMTP_HOST')) : '';
    $smtpPort = defined('SMTP_PORT') ? (int)constant('SMTP_PORT') : 0;
    $smtpSecure = defined('SMTP_SECURE') ? strtolower(trim((string)constant('SMTP_SECURE'))) : 'tls';
    $smtpUser = defined('SMTP_USER') ? (string)constant('SMTP_USER') : '';
    $smtpPass = defined('SMTP_PASS') ? (string)constant('SMTP_PASS') : '';

    $looksPlaceholder = (
        stripos($fromEmail, 'yourgmail@gmail.com') !== false ||
        stripos($smtpUser, 'yourgmail@gmail.com') !== false ||
        stripos($smtpPass, 'replace-with-gmail-app-password') !== false
    );
    if ($looksPlaceholder) {
        logMailEvent('SMTP not configured: placeholder values detected in MAIL_FROM/SMTP_USER/SMTP_PASS');
    }

    if ($smtpHost === '' || $smtpPort <= 0 || $smtpUser === '' || $smtpPass === '') {
        logMailEvent('SMTP not configured: missing SMTP_HOST/SMTP_PORT/SMTP_USER/SMTP_PASS values');
    }

    if (!$looksPlaceholder && $smtpHost !== '' && $smtpPort > 0 && $smtpUser !== '' && $smtpPass !== '' && loadPhpMailerClasses()) {
        try {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->CharSet = 'UTF-8';
            $mailer->isSMTP();
            $mailer->Host = $smtpHost;
            $mailer->Port = $smtpPort;
            $mailer->SMTPAuth = true;
            $mailer->Username = $smtpUser;
            $mailer->Password = $smtpPass;
            $mailer->SMTPSecure = $smtpSecure === 'ssl'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : ($smtpSecure === 'tls' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : '');
            $mailer->SMTPAutoTLS = $smtpSecure === 'tls';
            $mailer->setFrom($fromEmail, $fromName, false);
            $mailer->addAddress($to);
            $mailer->Subject = $subject;
            $mailer->Body = $plainBody;
            $mailer->AltBody = $plainBody;
            $mailer->isHTML(false);
            $mailer->send();
            return true;
        } catch (\Throwable $throwable) {
            logMailEvent('PHPMailer send failed for ' . $to . ' subject="' . $subject . '" error="' . str_replace('"', '\\"', $throwable->getMessage()) . '"');
        }
    } elseif (!$looksPlaceholder && $smtpHost !== '' && $smtpPort > 0 && $smtpUser !== '' && $smtpPass !== '') {
        logMailEvent('PHPMailer unavailable: install phpmailer/phpmailer and refresh Composer autoload files');
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/plain; charset=UTF-8',
        'From: ' . $fromEmail,
    ];

    $sent = @mail($to, $subject, $plainBody, implode("\r\n", $headers));
    if (!$sent) {
        logMailEvent('mail() send failed for ' . $to . ' subject="' . $subject . '"');
    }
    return $sent;
}

/**
 * Send password reset email link.
 */
function sendPasswordResetEmail(string $email, string $name, string $resetUrl): bool {
    $appName = defined('APP_NAME') ? (string)constant('APP_NAME') : 'OJTSync';
    $subject = $appName . ' Password Reset';
    $safeName = $name !== '' ? $name : 'Student';
    $message = "Hello {$safeName},\n\n"
        . "We received a request to reset your password for " . $appName . ".\n"
        . "Use the link below within 1 hour:\n\n"
        . $resetUrl . "\n\n"
        . "If you did not request this, you can safely ignore this message.\n";

    return sendAppEmail($email, $subject, $message);
}

/**
 * Ensure email verification schema exists.
 */
function ensureEmailVerificationSchema(PDO $pdo): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $col = $pdo->query("SHOW COLUMNS FROM students LIKE 'email_verified_at'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE students ADD COLUMN email_verified_at DATETIME NULL AFTER password");
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS email_verifications (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            code_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            resend_count INT NOT NULL DEFAULT 0,
            attempt_count INT NOT NULL DEFAULT 0,
            request_ip VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email_verify_student_time (student_id, created_at),
            INDEX idx_email_verify_expiry (expires_at),
            CONSTRAINT fk_email_verify_student FOREIGN KEY (student_id)
                REFERENCES students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $initialized = true;
}

/**
 * Determine if the student account has a verified email.
 */
function isStudentEmailVerified(array $student): bool {
    return !empty($student['email_verified_at']);
}

/**
 * Generate and persist a 6-digit email verification OTP.
 */
function createEmailVerificationOtp(PDO $pdo, int $studentId, int $resendCount = 0): string {
    ensureEmailVerificationSchema($pdo);

    // Keep only one active OTP at a time.
    $pdo->prepare("UPDATE email_verifications SET used_at=NOW() WHERE student_id=? AND used_at IS NULL")
        ->execute([$studentId]);

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $codeHash = hash('sha256', $code);
    $expiresAt = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        "INSERT INTO email_verifications
            (student_id, code_hash, expires_at, resend_count, request_ip, user_agent)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $studentId,
        $codeHash,
        $expiresAt,
        max(0, $resendCount),
        $_SERVER['REMOTE_ADDR'] ?? null,
        isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
    ]);

    return $code;
}

/**
 * Fetch latest active OTP context for a student.
 */
function getActiveEmailVerification(PDO $pdo, int $studentId): ?array {
    ensureEmailVerificationSchema($pdo);

    $stmt = $pdo->prepare(
        "SELECT *
         FROM email_verifications
         WHERE student_id = ?
           AND used_at IS NULL
           AND expires_at > NOW()
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([$studentId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * Cooldown checker for OTP resend actions.
 */
function secondsUntilVerificationResend(PDO $pdo, int $studentId, int $cooldownSeconds = 30): int {
    ensureEmailVerificationSchema($pdo);

    $stmt = $pdo->prepare(
        "SELECT created_at
         FROM email_verifications
         WHERE student_id = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([$studentId]);
    $createdAt = $stmt->fetchColumn();
    if (!$createdAt) {
        return 0;
    }

    $createdTs = strtotime((string)$createdAt);
    if ($createdTs <= 0) {
        return 0;
    }

    return max(0, ($createdTs + max(1, $cooldownSeconds)) - time());
}

/**
 * Verify OTP and mark student email as verified.
 */
function verifyStudentEmailOtp(PDO $pdo, int $studentId, string $otp): array {
    ensureEmailVerificationSchema($pdo);

    if (!preg_match('/^\d{6}$/', $otp)) {
        return ['ok' => false, 'error' => 'Enter a valid 6-digit code.'];
    }

    $active = getActiveEmailVerification($pdo, $studentId);
    if (!$active) {
        return ['ok' => false, 'error' => 'Verification code is missing or expired. Please resend a new code.'];
    }

    $otpHash = hash('sha256', $otp);
    if (!hash_equals((string)$active['code_hash'], $otpHash)) {
        $attempts = (int)$active['attempt_count'] + 1;
        $stmt = $pdo->prepare(
            "UPDATE email_verifications
             SET attempt_count = ?, used_at = CASE WHEN ? >= 8 THEN NOW() ELSE used_at END
             WHERE id = ?"
        );
        $stmt->execute([$attempts, $attempts, (int)$active['id']]);

        return ['ok' => false, 'error' => 'Incorrect verification code. Please try again.'];
    }

    $pdo->beginTransaction();
    try {
        $mark = $pdo->prepare(
            "UPDATE email_verifications
             SET used_at = NOW()
             WHERE id = ? AND used_at IS NULL AND expires_at > NOW()"
        );
        $mark->execute([(int)$active['id']]);

        if ($mark->rowCount() !== 1) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Verification code is no longer valid. Please resend a new code.'];
        }

        $pdo->prepare("UPDATE students SET email_verified_at = NOW() WHERE id = ? AND email_verified_at IS NULL")
            ->execute([$studentId]);

        $pdo->prepare("UPDATE email_verifications SET used_at = NOW() WHERE student_id = ? AND used_at IS NULL")
            ->execute([$studentId]);

        $pdo->commit();
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => 'Unable to verify email right now. Please try again.'];
    }
}

/**
 * Send a verification OTP to a newly registered user.
 */
function sendEmailVerificationOtp(string $email, string $name, string $otp): bool {
    $appName = defined('APP_NAME') ? (string)constant('APP_NAME') : 'OJTSync';
    $subject = $appName . ' Email Verification Code';
    $safeName = $name !== '' ? $name : 'Student';
    $message = "Hello {$safeName},\n\n"
        . "Welcome to {$appName}. Use the verification code below to confirm your email:\n\n"
        . "{$otp}\n\n"
        . "This code expires in 5 minutes and can be used once.\n"
        . "If you did not create this account, you can ignore this email.\n";

    return sendAppEmail($email, $subject, $message);
}

/**
 * Check if a password appears in public breach corpora via HIBP k-anonymity API.
 */
function breachedPasswordCount(string $password, ?string &$error = null): int {
    $error = null;
    if ($password === '') {
        return 0;
    }

    $sha1 = strtoupper(sha1($password));
    $prefix = substr($sha1, 0, 5);
    $suffix = substr($sha1, 5);
    $url = 'https://api.pwnedpasswords.com/range/' . $prefix;
    $response = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER => ['Add-Padding: true', 'User-Agent: OJTSync-PasswordPolicy/1.0'],
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $error = 'Unable to verify password breach status.';
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 6,
                'header' => "Add-Padding: true\r\nUser-Agent: OJTSync-PasswordPolicy/1.0\r\n",
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $error = 'Unable to verify password breach status.';
        }
    }

    if ($response === false) {
        return -1;
    }

    $lines = preg_split('/\r\n|\r|\n/', (string)$response);
    foreach ($lines as $line) {
        if (strpos($line, ':') === false) {
            continue;
        }
        [$hashSuffix, $count] = explode(':', $line, 2);
        if (strtoupper(trim($hashSuffix)) === $suffix) {
            return (int)trim($count);
        }
    }

    return 0;
}

/**
 * Enforce strong password policy with complexity and breach checks.
 */
function validatePasswordPolicy(string $password, array $forbiddenFragments = []): array {
    $errors = [];

    if (strlen($password) < 12) {
        $errors[] = 'Password must be at least 12 characters.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must include at least one lowercase letter.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must include at least one uppercase letter.';
    }
    if (!preg_match('/\d/', $password)) {
        $errors[] = 'Password must include at least one number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must include at least one special character.';
    }
    if (preg_match('/\s/', $password)) {
        $errors[] = 'Password cannot contain spaces.';
    }

    foreach ($forbiddenFragments as $fragment) {
        $fragment = trim((string)$fragment);
        if (strlen($fragment) < 3) {
            continue;
        }
        if (stripos($password, $fragment) !== false) {
            $errors[] = 'Password cannot contain personal account info.';
            break;
        }
    }

    $breachError = null;
    $breachCount = breachedPasswordCount($password, $breachError);
    if ($breachCount < 0) {
        $errors[] = 'Password safety check is unavailable right now. Please try again.';
    } elseif ($breachCount > 0) {
        $errors[] = 'This password has appeared in known data breaches. Choose a different password.';
    }

    return [
        'ok' => empty($errors),
        'errors' => $errors,
        'breach_count' => max(0, $breachCount),
    ];
}
