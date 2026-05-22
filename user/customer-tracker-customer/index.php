<?php
/**
 * Legacy route — redirects to customer-manager.
 */
$script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$base = preg_replace('#/user(/.*)?$#', '', $script_dir);
if ($base === '/') {
    $base = '';
}
header('Location: ' . $base . '/user/customer-manager/', true, 301);
exit;
