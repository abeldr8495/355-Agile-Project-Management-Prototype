<?php
// Logout controller: destroys session and redirects to login page
require_once '../src/auth.php';
logout();
header('Location: ' . appPath('login.php'));
exit;
