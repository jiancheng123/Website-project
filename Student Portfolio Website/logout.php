<?php
session_start();
session_unset();
session_destroy();
$_SESSION['login_error'] = "You have been logged out.";
$_SESSION['active_form'] = 'login';
header("Location: login.php");
exit;
?>

