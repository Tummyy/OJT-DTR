<?php
// includes/header.php — Shared page header
// Usage: include with $pageTitle and $activePage defined before

$_pageName  = $pageTitle  ?? 'OJTSync';
$_active    = $activePage ?? '';
$_bodyClass = $bodyClass  ?? '';
$_theme     = currentTheme();
$_stylePath = __DIR__ . '/../assets/css/style.css';
$_styleVer  = is_file($_stylePath) ? (string)filemtime($_stylePath) : (string)time();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($_pageName) ?> — OJTSync</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
<link rel="stylesheet" href="assets/css/style.css?v=<?= htmlspecialchars($_styleVer) ?>">
</head>
<body class="<?= htmlspecialchars($_bodyClass) ?>">
<a class="skip-link" href="#mainContent">Skip to main content</a>

<?php if (isLoggedIn()): ?>
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid nav-inner">
    <button id="navToggle" class="navbar-toggler nav-toggle" type="button" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle menu">
      <span class="nav-toggle-bar"></span>
      <span class="nav-toggle-bar"></span>
      <span class="nav-toggle-bar"></span>
    </button>
    <a href="dashboard.php" class="nav-brand">
      <img src="logo/Logo.png" alt="OJTSync logo" class="brand-logo">
      
    </a>
    <div class="theme-control theme-control-mobile">
      <label class="switch" aria-label="Toggle dark mode">
        <span class="sun" aria-hidden="true">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <g fill="#ffd43b">
              <circle r="5" cy="12" cx="12"></circle>
              <path d="m21 13h-1a1 1 0 0 1 0-2h1a1 1 0 0 1 0 2zm-17 0h-1a1 1 0 0 1 0-2h1a1 1 0 0 1 0 2zm13.66-5.66a1 1 0 0 1 -.66-.29 1 1 0 0 1 0-1.41l.71-.71a1 1 0 1 1 1.41 1.41l-.71.71a1 1 0 0 1 -.75.29zm-12.02 12.02a1 1 0 0 1 -.71-.29 1 1 0 0 1 0-1.41l.71-.66a1 1 0 0 1 1.41 1.41l-.71.71a1 1 0 0 1 -.7.24zm6.36-14.36a1 1 0 0 1 -1-1v-1a1 1 0 0 1 2 0v1a1 1 0 0 1 -1 1zm0 17a1 1 0 0 1 -1-1v-1a1 1 0 0 1 2 0v1a1 1 0 0 1 -1 1zm-5.66-14.66a1 1 0 0 1 -.7-.29l-.71-.71a1 1 0 0 1 1.41-1.41l.71.71a1 1 0 0 1 0 1.41 1 1 0 0 1 -.71.29zm12.02 12.02a1 1 0 0 1 -.7-.29l-.66-.71a1 1 0 0 1 1.36-1.36l.71.71a1 1 0 0 1 0 1.41 1 1 0 0 1 -.71.24z"></path>
            </g>
          </svg>
        </span>
        <span class="moon" aria-hidden="true">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
            <path d="m223.5 32c-123.5 0-223.5 100.3-223.5 224s100 224 223.5 224c60.6 0 115.5-24.2 155.8-63.4 5-4.9 6.3-12.5 3.1-18.7s-10.1-9.7-17-8.5c-9.8 1.7-19.8 2.6-30.1 2.6-96.9 0-175.5-78.8-175.5-176 0-65.8 36-123.1 89.3-153.3 6.1-3.5 9.2-10.5 7.7-17.3s-7.3-11.9-14.3-12.5c-6.3-.5-12.6-.8-19-.8z"></path>
          </svg>
        </span>
        <input
          type="checkbox"
          id="themeToggleMobile"
          class="input theme-toggle-input-js"
          data-csrf="<?= htmlspecialchars(csrf_token()) ?>"
          aria-label="Toggle dark mode"
          <?= $_theme === 'dark' ? 'checked' : '' ?>
        >
        <span class="slider"></span>
      </label>
    </div>
    <div class="collapse navbar-collapse" id="mainNavbar">
      <a href="dashboard.php" class="sidebar-brand" aria-label="OJTSync Home">
        <img src="logo/Logo.png" alt="OJTSync logo" class="brand-logo">
      </a>
      <div class="nav-links">
      <a href="dashboard.php" class="nav-link <?= $_active==='dashboard' ? 'active':'' ?>" <?= $_active==='dashboard' ? 'aria-current="page"' : '' ?>>
        <i class="ti ti-home-2" aria-hidden="true"></i>
        Dashboard
      </a>
      <a href="dtr-log.php" class="nav-link <?= $_active==='dtr' ? 'active':'' ?>" <?= $_active==='dtr' ? 'aria-current="page"' : '' ?>>
        <i class="ti ti-clock-hour-4" aria-hidden="true"></i>
        Time Log
      </a>
      <a href="schedule.php" class="nav-link <?= $_active==='schedule' ? 'active':'' ?>" <?= $_active==='schedule' ? 'aria-current="page"' : '' ?>>
        <i class="ti ti-calendar" aria-hidden="true"></i>
        Schedule
      </a>
      <a href="holidays.php" class="nav-link <?= $_active==='holidays' ? 'active':'' ?>" <?= $_active==='holidays' ? 'aria-current="page"' : '' ?>>
        <i class="ti ti-heart" aria-hidden="true"></i>
        Holidays
      </a>
      <a href="reports.php" class="nav-link <?= $_active==='reports' ? 'active':'' ?>" <?= $_active==='reports' ? 'aria-current="page"' : '' ?>>
        <i class="ti ti-chart-bar" aria-hidden="true"></i>
        Reports
      </a>
      <a href="profile.php" class="nav-link <?= $_active==='profile' ? 'active':'' ?>" <?= $_active==='profile' ? 'aria-current="page"' : '' ?>>
        <i class="ti ti-user-circle" aria-hidden="true"></i>
        Profile
      </a>
      </div>
      <div class="nav-user ms-lg-auto">
        <div class="theme-control">
          <label class="switch" aria-label="Toggle dark mode">
            <span class="sun" aria-hidden="true">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <g fill="#ffd43b">
                  <circle r="5" cy="12" cx="12"></circle>
                  <path d="m21 13h-1a1 1 0 0 1 0-2h1a1 1 0 0 1 0 2zm-17 0h-1a1 1 0 0 1 0-2h1a1 1 0 0 1 0 2zm13.66-5.66a1 1 0 0 1 -.66-.29 1 1 0 0 1 0-1.41l.71-.71a1 1 0 1 1 1.41 1.41l-.71.71a1 1 0 0 1 -.75.29zm-12.02 12.02a1 1 0 0 1 -.71-.29 1 1 0 0 1 0-1.41l.71-.66a1 1 0 0 1 1.41 1.41l-.71.71a1 1 0 0 1 -.7.24zm6.36-14.36a1 1 0 0 1 -1-1v-1a1 1 0 0 1 2 0v1a1 1 0 0 1 -1 1zm0 17a1 1 0 0 1 -1-1v-1a1 1 0 0 1 2 0v1a1 1 0 0 1 -1 1zm-5.66-14.66a1 1 0 0 1 -.7-.29l-.71-.71a1 1 0 0 1 1.41-1.41l.71.71a1 1 0 0 1 0 1.41 1 1 0 0 1 -.71.29zm12.02 12.02a1 1 0 0 1 -.7-.29l-.66-.71a1 1 0 0 1 1.36-1.36l.71.71a1 1 0 0 1 0 1.41 1 1 0 0 1 -.71.24z"></path>
                </g>
              </svg>
            </span>
            <span class="moon" aria-hidden="true">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                <path d="m223.5 32c-123.5 0-223.5 100.3-223.5 224s100 224 223.5 224c60.6 0 115.5-24.2 155.8-63.4 5-4.9 6.3-12.5 3.1-18.7s-10.1-9.7-17-8.5c-9.8 1.7-19.8 2.6-30.1 2.6-96.9 0-175.5-78.8-175.5-176 0-65.8 36-123.1 89.3-153.3 6.1-3.5 9.2-10.5 7.7-17.3s-7.3-11.9-14.3-12.5c-6.3-.5-12.6-.8-19-.8z"></path>
              </svg>
            </span>
            <input
              type="checkbox"
              id="themeToggleDesktop"
              class="input theme-toggle-input-js"
              data-csrf="<?= htmlspecialchars(csrf_token()) ?>"
              aria-label="Toggle dark mode"
              <?= $_theme === 'dark' ? 'checked' : '' ?>
            >
            <span class="slider"></span>
          </label>
        </div>
        <span class="user-greeting">Hi, <?= htmlspecialchars(currentStudentName()) ?></span>
        <a href="logout.php" class="btn-logout"
           data-confirm="Are you sure you want to sign out?"
           data-confirm-ok="Sign Out"
           data-confirm-title="Sign Out"
           data-confirm-variant="danger"
            data-confirm-icon="!"
            data-confirm-note="Your current session will end. You can sign in again anytime.">Sign Out</a>
      </div>
    </div>
  </div>
</nav>
<div id="navBackdrop" class="nav-backdrop" aria-hidden="true"></div>
<?php endif; ?>

<main id="mainContent" class="<?= isLoggedIn() ? 'app-main' : 'public-main' ?>">
