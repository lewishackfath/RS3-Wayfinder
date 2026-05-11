<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
logout_user();
redirect('/index.php');
