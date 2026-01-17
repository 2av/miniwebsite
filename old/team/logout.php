<?php
require_once(__DIR__ . '/../common/config.php');

// Clear team member session variables (using old session names)
unset($_SESSION['user_email']);
unset($_SESSION['user_name']);
unset($_SESSION['is_logged_in']);
unset($_SESSION['login_time']);
unset($_SESSION['team_member_id']);

// Destroy the session
session_destroy();

// Start a new session for the login page
session_start();

// Redirect to team login page
header('Location: login.php');
exit;
