<?php
// index.php — Landing Page
require_once 'includes/auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$stylePath = __DIR__ . '/assets/css/style.css';
$scriptPath = __DIR__ . '/assets/js/script.js';
$styleVer = is_file($stylePath) ? (string)filemtime($stylePath) : (string)time();
$scriptVer = is_file($scriptPath) ? (string)filemtime($scriptPath) : (string)time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="OJTSync helps OJT students log daily time records, monitor progress, and export reports.">
<meta property="og:title" content="Track Your Hours - OJTSync">
<meta property="og:description" content="Log your OJT records, monitor progress, and export reports in one place.">
<meta property="og:type" content="website">
<meta property="og:image" content="logo/Logo.png">
<meta name="twitter:card" content="summary_large_image">
<title>Track Your Hours — OJTSync</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
<link rel="stylesheet" href="assets/css/style.css?v=<?= htmlspecialchars($styleVer) ?>">
</head>
<body class="landing-page">
<a class="skip-link" href="#mainContent">Skip to main content</a>

<header class="landing-topbar">
  <div class="landing-topbar-inner">
    <a href="index.php" class="landing-topbar-brand" aria-label="OJTSync home">
      <img src="logo/Logo.png" alt="OJTSync" class="landing-topbar-logo">
    </a>
    <nav class="landing-topbar-actions" aria-label="Primary">
      <a href="login.php" class="landing-nav-link">Sign In</a>
      <a href="register.php" class="landing-nav-link landing-nav-link-accent">Create Account</a>
    </nav>
  </div>
</header>

<main id="mainContent">

<!-- ── HERO ─────────────────────────────────────── -->
<section class="hero">
  <div class="hero-grid"></div>
  <div class="hero-content">
    <div class="hero-badge">
      <img src="logo/Logo.png" alt="OJTSync" class="hero-badge-logo">
    </div>
    <p class="hero-proof">Built for OJT students and coordinators</p>
    <h1>Track Your Hours.<br><span>Stay On Schedule.</span></h1>
    <p class="hero-subtitle">
      Complete your OJT with confidence. Log your daily time records, monitor your progress, and never lose track of your hours again.
    </p>
    <div class="hero-cta-group">
      <a href="login.php" class="hero-cta">
        Get Started
        <i class="ti ti-arrow-right" aria-hidden="true"></i>
      </a>
      <a href="#how-it-works" class="hero-cta hero-cta-secondary">
        Learn How It Works
      </a>
    </div>
    <div class="hero-stats">
      <div>
        <div class="hero-stat-num">Smart</div>
        <div class="hero-stat-lbl">Holiday-aware Computation</div>
      </div>
      <div>
        <div class="hero-stat-num">PDF/CSV</div>
        <div class="hero-stat-lbl">Export-ready Reports</div>
      </div>
      <div>
        <div class="hero-stat-num">Live</div>
        <div class="hero-stat-lbl">Progress Tracking</div>
      </div>
    </div>
  </div>
</section>

<!-- ── HOW IT WORKS ───────────────────────────── -->
<section class="landing-section how-section" id="how-it-works">
  <div class="container">
    <div class="landing-section-head reveal-on-scroll">
      <div class="section-tag">How It Works</div>
      <h2 class="section-title">Simple in 3 Steps</h2>
      <p class="section-body section-body-centered">Set up once, log consistently, and export anytime your coordinator needs proof of progress.</p>
    </div>
    <div class="how-grid">
      <article class="how-card reveal-on-scroll" style="--reveal-delay: .02s;">
        <span class="how-step">01</span>
        <h3>Set Up Your Schedule</h3>
        <p>Configure your OJT start date, daily shift, break duration, and rest days in minutes.</p>
      </article>
      <article class="how-card reveal-on-scroll" style="--reveal-delay: .12s;">
        <span class="how-step">02</span>
        <h3>Log Daily Time Records</h3>
        <p>Track time in and out per day while OJTSync computes valid hours and overtime automatically.</p>
      </article>
      <article class="how-card reveal-on-scroll" style="--reveal-delay: .22s;">
        <span class="how-step">03</span>
        <h3>Review and Export Reports</h3>
        <p>Monitor completion pace in your dashboard and export PDF or CSV reports whenever needed.</p>
      </article>
    </div>

    <div class="demo-lab reveal-on-scroll" style="--reveal-delay: .28s;">
      <div class="demo-lab-head">
        <h3>Try a Live Computation</h3>
        <p>Preview how OJTSync computes daily totals before you even sign in.</p>
      </div>
      <div class="demo-lab-grid">
        <div class="demo-lab-inputs">
          <div class="demo-field">
            <label for="demoTimeIn">Time In</label>
            <input type="time" id="demoTimeIn" value="08:30">
          </div>
          <div class="demo-field">
            <label for="demoTimeOut">Time Out</label>
            <input type="time" id="demoTimeOut" value="17:30">
          </div>
          <div class="demo-field">
            <label for="demoBreak">Break (hours)</label>
            <input type="number" id="demoBreak" min="0" max="4" step="0.25" value="1">
          </div>
          <div class="demo-field">
            <label for="demoRegularCap">Regular Cap (hours)</label>
            <input type="number" id="demoRegularCap" min="1" max="12" step="0.5" value="8">
          </div>
          <div class="demo-field">
            <label for="demoRequiredHours">Required Hours</label>
            <input type="number" id="demoRequiredHours" min="1" max="1200" step="1" value="600">
          </div>
          <div class="demo-field">
            <label for="demoRenderedHours">Rendered So Far</label>
            <input type="number" id="demoRenderedHours" min="0" max="1200" step="1" value="88">
          </div>
          <div class="demo-field">
            <label for="demoWorkdays">Workdays / Week</label>
            <input type="number" id="demoWorkdays" min="1" max="7" step="1" value="5">
          </div>
          <div class="demo-field">
            <label for="demoTargetWeeks">Target Weeks</label>
            <input type="number" id="demoTargetWeeks" min="1" max="52" step="1" value="16">
          </div>
        </div>
        <div class="demo-lab-output" aria-live="polite">
          <div class="demo-metric">
            <span>Worked Hours</span>
            <strong id="demoWorked">0:00</strong>
          </div>
          <div class="demo-metric">
            <span>Regular Hours</span>
            <strong id="demoRegular">0:00</strong>
          </div>
          <div class="demo-metric">
            <span>Overtime</span>
            <strong id="demoOvertime">0:00</strong>
          </div>
          <div class="demo-metric">
            <span>Remaining Hours</span>
            <strong id="demoRemaining">0:00</strong>
          </div>
          <div class="demo-metric">
            <span>Estimated Completion</span>
            <strong id="demoFinishDate">--</strong>
            <p id="demoWeeksLeft" class="demo-metric-note">Adjust values to preview timeline.</p>
            <span id="demoPaceBadge" class="demo-pace-badge neutral">Set your target to evaluate pace</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── MISSION & VISION ──────────────────────────── -->
<section class="landing-section alt py-5">
  <div class="container">
    <div class="landing-section-head landing-section-head-wide reveal-on-scroll">
      <div class="section-tag">Who We Are</div>
      <h2 class="section-title">Built for OJT Students,<br>By Design</h2>
    </div>
    <div class="row g-4 justify-content-center">
      <div class="col-12 col-lg-6">
      <div class="mv-card h-100 reveal-on-scroll" style="--reveal-delay: .04s;">
        <div class="mv-icon">🎯</div>
        <h3>Our Mission</h3>
        <p>Provide a clear and reliable system for OJT students to manage their daily time records with accurate tracking and simple access to reports — reducing manual effort and ensuring every hour is properly counted.</p>
      </div>
      </div>
      <div class="col-12 col-lg-6">
      <div class="mv-card h-100 reveal-on-scroll" style="--reveal-delay: .12s;">
        <div class="mv-icon">🌟</div>
        <h3>Our Vision</h3>
        <p>Create a system students trust during their OJT journey — one that improves time tracking discipline, reduces manual errors, and gives a clear picture of progress toward completion at any point in time.</p>
      </div>
      </div>
    </div>
  </div>
</section>

<!-- ── FEATURES ──────────────────────────────────── -->
<section class="landing-section" id="features">
  <div class="container">
    <div class="landing-section-head reveal-on-scroll">
      <div class="section-tag">Features</div>
      <h2 class="section-title">Everything You Need</h2>
      <p class="section-body section-body-centered">From daily time logging to holiday-aware end date computation and exportable reports — all in one place.</p>
    </div>
    <div class="row g-3 mt-1">
      <div class="col-12 col-md-6 col-xl-4">
      <div class="feature-card h-100 reveal-on-scroll" style="--reveal-delay: .03s;">
        <div class="feature-icon">⏱</div>
        <h4>Accurate Time Tracking</h4>
        <p>Log your time in and time out each day. The system computes break deductions, regular hours, and overtime automatically.</p>
      </div>
      </div>
      <div class="col-12 col-md-6 col-xl-4">
      <div class="feature-card h-100 reveal-on-scroll" style="--reveal-delay: .09s;">
        <div class="feature-icon">📅</div>
        <h4>Schedule Management</h4>
        <p>Set your shift hours, break duration, max working hours, and rest days — tailored to your specific OJT arrangement.</p>
      </div>
      </div>
      <div class="col-12 col-md-6 col-xl-4">
      <div class="feature-card h-100 reveal-on-scroll" style="--reveal-delay: .15s;">
        <div class="feature-icon">🗓</div>
        <h4>Holiday-Aware End Date</h4>
        <p>Your expected completion date is automatically computed, skipping regular holidays and special non-working days accurately.</p>
      </div>
      </div>
      <div class="col-12 col-md-6 col-xl-4">
      <div class="feature-card h-100 reveal-on-scroll" style="--reveal-delay: .21s;">
        <div class="feature-icon">⭐</div>
        <h4>Overtime Support</h4>
        <p>Enable overtime tracking with a configurable daily limit. Overtime hours count toward your required total.</p>
      </div>
      </div>
      <div class="col-12 col-md-6 col-xl-4">
      <div class="feature-card h-100 reveal-on-scroll" style="--reveal-delay: .27s;">
        <div class="feature-icon">📊</div>
        <h4>Progress Dashboard</h4>
        <p>Visual progress bar, total rendered hours, remaining hours, and recent DTR records all at a glance.</p>
      </div>
      </div>
      <div class="col-12 col-md-6 col-xl-4">
      <div class="feature-card h-100 reveal-on-scroll" style="--reveal-delay: .33s;">
        <div class="feature-icon">📁</div>
        <h4>Reports & Export</h4>
        <p>Generate daily, weekly, or monthly reports. Export to PDF or Excel for submission to your school coordinator.</p>
      </div>
      </div>
    </div>
  </div>
</section>

<!-- ── GOOD LUCK ──────────────────────────────────── -->
<section class="goodluck-section">
  <div class="goodluck-box reveal-on-scroll">
    <div class="goodluck-emoji">🎓</div>
    <blockquote>
      Stay consistent. Log your time daily.<br>
      Every hour you record brings you closer to completion.<br><br>
      <strong>Good luck on your OJT journey.</strong>
    </blockquote>
    <div class="goodluck-cta-wrap">
      <a href="login.php" class="hero-cta">
        Start Tracking Now
        <i class="ti ti-arrow-right" aria-hidden="true"></i>
      </a>
    </div>
  </div>
</section>

<footer class="landing-footer">
  <p>&copy; <?= date('Y') ?> OJTSync &mdash; Built for OJT Students &mdash;
    <a href="login.php" class="landing-footer-link">Sign In</a> &bull;
    <a href="register.php" class="landing-footer-link">Create Account</a>
  </p>
</footer>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="assets/js/script.js?v=<?= htmlspecialchars($scriptVer) ?>"></script>
</body>
</html>
