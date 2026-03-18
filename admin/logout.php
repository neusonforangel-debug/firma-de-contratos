<?php
require_once __DIR__ . '/../app/helpers.php';
start_session();
session_destroy();
header('Location: ' . base_url('admin/login.php'));
exit;
?>