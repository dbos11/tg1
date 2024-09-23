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

if (isset($_GET['telegram_id']) && isset($_GET['boost_type']) && isset($_GET['cost']) && isset($_GET['spent_coin'])) {
    $telegram_id = intval($_GET['telegram_id']);
    $boost_type = $_GET['boost_type'];
    $cost = intval($_GET['cost']);
    $spent_coin = intval($_GET['spent_coin']);

    // Update the balance and spent_coin in users table
    $stmt_balance = $conn->prepare("UPDATE users SET balance = balance - ?, spent_coin = ? WHERE telegram_id = ?");
    $stmt_balance->bind_param("iii", $cost, $spent_coin, $telegram_id);
    $stmt_balance->execute();

    if ($stmt_balance->affected_rows > 0) {
        // Update the boost level in boosts table
        $column_name = $boost_type . "_level";
        $stmt_boost = $conn->prepare("UPDATE boosts SET $column_name = $column_name + 1 WHERE telegram_id = ?");
        $stmt_boost->bind_param("i", $telegram_id);
        $stmt_boost->execute();

        if ($stmt_boost->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to upgrade boost']);
        }

        $stmt_boost->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Insufficient balance or failed to update balance']);
    }

    $stmt_balance->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
?>
