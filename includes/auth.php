<?php
if (session_status() === PHP_SESSION_NONE){ session_start(); }
$public_pages = ['login.php'];
$current = basename ($_SERVER ['SCRIPT_NAME']);
if (!isset($_SESSION['logged_in']) && !in_array($current, $public_pages)){
	header("location: login.php");
	exit;
}
?>