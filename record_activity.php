<?php
require_once 'config.php';

// Подключение к базе данных
$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

if (isset($_GET['telegram_id'])) {
    $telegram_id = intval($_GET['telegram_id']);
    
    $stmt = $conn->prepare("UPDATE bot SET last_activity = NOW() WHERE telegram_id = ?");
    $stmt->bind_param("i", $telegram_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to record activity']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
?>
