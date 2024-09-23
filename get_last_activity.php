<?php
require_once 'config.php';

// Подключение к базе данных
$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

if (isset($_GET['telegram_id'])) {
    $telegram_id = intval($_GET['telegram_id']);
    
    // Получаем информацию о последней активности
    $stmt = $conn->prepare("SELECT DATEDIFF(NOW(), last_activity) AS days_since_last_activity FROM bot WHERE telegram_id = ?");
    $stmt->bind_param("i", $telegram_id);
    $stmt->execute();
    $stmt->bind_result($days_since_last_activity);
    $stmt->fetch();
    
    $stmt->close();
    
    // Определяем текущий день и награды
    $rewards = [500, 1000, 5000, 10000, 50000, 100000, 500000, 1000000, 2000000, 5000000];
    $current_day = $days_since_last_activity % 10 + 1;
    
    echo json_encode(['success' => true, 'rewards' => $rewards, 'current_day' => $current_day]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
?>
