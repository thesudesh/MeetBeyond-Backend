<?php
$servername = "localhost";
$username = "i9808830_dlk11"; 
$password = "P.ovRJ03xjX1GN6MrHh51"; 
$dbname = "i9808830_dlk11";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>