<?php
require_once 'config.php';

// Подключение к базе данных
$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

if (isset($_GET['telegram_id'])) {
    $telegram_id = intval($_GET['telegram_id']);
    
    $stmt = $conn->prepare("SELECT turbo_multiplier FROM boosts WHERE telegram_id = ?");
    $stmt->bind_param("i", $telegram_id);
    $stmt->execute();
    $stmt->bind_result($turbo_multiplier);
    $stmt->fetch();

    if ($turbo_multiplier !== null) {
        echo json_encode(['success' => true, 'multiplier' => $turbo_multiplier]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No multiplier found']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
?>
