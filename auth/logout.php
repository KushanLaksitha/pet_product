<?php
// Include database connection
require_once '../includes/db_connect.php';

// Unset all of the session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to the home page
header("Location: ../index.php");
exit();
?>