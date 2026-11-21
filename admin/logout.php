<?php
require __DIR__ . '/../app/bootstrap.php';
unset($_SESSION['uid']);
session_regenerate_id(true);
redirect(url('admin/login.php'));
