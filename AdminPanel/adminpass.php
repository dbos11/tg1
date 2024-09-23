<?php
require_once 'config.php';

$username = 'admin';
$password = 'jHFcdHPXBc07X+ut'; // замените на ваш пароль

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$conn = db_connect();

$stmt = $conn->prepare('INSERT INTO admin_users (username, password) VALUES (?, ?)');
$stmt->bind_param('ss', $username, $hashed_password);

if ($stmt->execute()) {
    echo "New admin user created successfully";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
