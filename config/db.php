<?php
// Set default timezone for the entire app (Asia/Manila = UTC+8)
// Change this to your timezone if different: https://www.php.net/manual/en/timezones.php
date_default_timezone_set('Asia/Manila');

// ============================================================
// Database Configuration — use environment variables in production
// ============================================================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'ojt_dtr');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
define('DB_SSL_CA', trim((string)(getenv('DB_SSL_CA') ?: '')));

define('APP_NAME', getenv('APP_NAME') ?: 'OJTSync');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/ojt-dtr');
define('APP_ENV', strtolower(getenv('APP_ENV') ?: 'development'));

// ============================================================
// Mail Configuration (recommended for OTP/reset delivery)
// Use a Gmail App Password (not your regular Gmail password).
// ============================================================
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'ojtsync@gmail.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: APP_NAME);
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 465));
define('SMTP_SECURE', strtolower((string)(getenv('SMTP_SECURE') ?: 'ssl'))); // tls, ssl, or none
define('SMTP_USER', getenv('SMTP_USER') ?: 'ojtsync@gmail.com');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');

define('CLOUDINARY_CLOUD_NAME', trim((string)(getenv('CLOUDINARY_CLOUD_NAME') ?: '')));
define('CLOUDINARY_API_KEY', trim((string)(getenv('CLOUDINARY_API_KEY') ?: '')));
define('CLOUDINARY_API_SECRET', trim((string)(getenv('CLOUDINARY_API_SECRET') ?: '')));
define('CLOUDINARY_UPLOAD_FOLDER', trim((string)(getenv('CLOUDINARY_UPLOAD_FOLDER') ?: 'ojtsync/export-logos')));

// ============================================================
// PDO Connection — do not edit below unless necessary
// ============================================================
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (DB_SSL_CA !== '') {
        $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
    } else {
        // Aiven requires TLS; certificate verification can be enabled by setting DB_SSL_CA.
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Show a user-friendly error without exposing connection details in production.
    http_response_code(500);
    $errorDetails = APP_ENV === 'development'
        ? '<p><small>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</small></p>'
        : '';
    die('
    <!DOCTYPE html><html><head>
    <title>Database Error</title>
    <style>
        body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f8fafc;}
        .box{background:#fff;padding:2rem;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.1);max-width:500px;text-align:center;}
        h2{color:#dc2626;} p{color:#64748b;}
    </style>
    </head><body>
    <div class="box">
        <h2>⚠ Database Connection Failed</h2>
        <p>Could not connect to the database. Please check your <code>config/db.php</code> settings and ensure MySQL is running.</p>
        ' . $errorDetails . '
    </div>
    </body></html>
    ');
}
