<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/database.php';

if (empty($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

require_once __DIR__ . '/../../app/helpers/documentation_helper.php';

global $connect;
doc_ensure_schema($connect);
