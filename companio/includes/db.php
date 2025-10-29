<?php

$DB_HOST = 'localhost';                 
$DB_USER = 'i9808830_pht01';           
$DB_PASS = 'Z.43O4ktUPuVCNA6Sm542';    
$DB_NAME = 'i9808830_pht01';           

$db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// Check connection
if ($db->connect_errno) {
    die("Database connection failed: " . $db->connect_error);
}

// Ensure UTF-8 compatibility
$db->set_charset("utf8mb4");
?>

