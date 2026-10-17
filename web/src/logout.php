<?php
require_once __DIR__ . '/lib.php';
start_sess();
$_SESSION = [];
session_destroy();
setcookie('token', '', ['path' => '/', 'expires' => time() - 3600]);
header('Location: login.php');
