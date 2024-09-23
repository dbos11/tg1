<?php
require_once 'config.php';
require_once 'telegram_validation.php';

// Логирование начала работы скрипта
error_log("Script started.");

// Получаем данные из запроса
$data = $_GET;

// Выполняем валидацию данных
$validation_result = validate_telegram_data($data);

error_log("Validation result: " . $validation_result);

if ($validation_result !== 'Data is up-to-date.') {
    // Если валидация не прошла, возвращаем ошибку и завершаем скрипт
    echo json_encode(['success' => false, 'error' => $validation_result]);
    error_log("Validation failed: " . $validation_result);
    exit;
}

// Подключение к базе данных
$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

function updateWallet($conn, $telegram_id, $wallet_address, $wallet_hash) {
    $stmt = $conn->prepare("UPDATE users SET wallet_address = ?, wallet_hash = ? WHERE telegram_id = ?");
    $stmt->bind_param("ssi", $wallet_address, $wallet_hash, $telegram_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
    $stmt->close();
}

if (isset($data['telegram_id']) && isset($data['wallet_address']) && isset($data['wallet_hash'])) {
    $telegram_id = intval($data['telegram_id']);
    $wallet_address = $data['wallet_address'];
    $wallet_hash = $data['wallet_hash'];
    updateWallet($conn, $telegram_id, $wallet_address, $wallet_hash);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
?>
