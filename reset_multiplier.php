<?php
require_once 'config.php';

// Подключение к базе данных
$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

// Получение сырых данных POST-запроса
$post_data = file_get_contents("php://input");
$request = json_decode($post_data, true);

if (isset($request['telegram_id']) && isset($request['multiplier'])) {
    $telegram_id = intval($request['telegram_id']);
    $multiplier = intval($request['multiplier']);
    
    $stmt = $conn->prepare("UPDATE boosts SET turbo_multiplier = ? WHERE telegram_id = ?");
    $stmt->bind_param("ii", $multiplier, $telegram_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update turbo multiplier']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
?>
