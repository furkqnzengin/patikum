<?php
require __DIR__ . '/../app/bootstrap.php';
unset($_SESSION['cid']);
session_regenerate_id(true);
redirect(url('panel/login.php'));
