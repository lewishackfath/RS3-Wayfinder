<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_login();
header('Location: /index.php');
exit;
