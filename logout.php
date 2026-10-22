<?php
// logout.php — Logout handler
require_once 'includes/auth.php';
logoutStudent();
header('Location: login.php');
exit;
