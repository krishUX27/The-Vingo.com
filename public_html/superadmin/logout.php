<?php
// superadmin/logout.php
session_start();
unset($_SESSION['super_logged_in']);
header('Location: login.php');
exit;
?>
