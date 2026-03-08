<?php
$host = 'localhost'; 
$user = 'root'; 
$password = 'Ansari_221'; 
$dbname = 'crypto_transaction';

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
