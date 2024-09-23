<?php
require_once 'config.php';

// Подключение к базе данных
$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['telegram_id']) && isset($data['wallet_address'])) {
    $telegram_id = intval($data['telegram_id']);
    $wallet_address = $conn->real_escape_string($data['wallet_address']);

    $stmt = $conn->prepare("UPDATE users SET wallet = ? WHERE telegram_id = ?");
    $stmt->bind_param("si", $wallet_address, $telegram_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
?>
