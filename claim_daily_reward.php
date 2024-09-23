<?php
require_once 'config.php';
require_once 'telegram_validation.php';

// Логирование начала работы скрипта
error_log("Script started.");

// Получаем данные из запроса
$data = $_GET; // или $_POST в зависимости от типа запроса

// Выполняем валидацию данных
$validation_result = validate_telegram_data($data);

error_log("Validation result: " . $validation_result);

if ($validation_result !== 'Data is up-to-date.') {
    // Если валидация не прошла, возвращаем ошибку и завершаем скрипт
    echo json_encode(['success' => false, 'error' => $validation_result]);
    error_log("Validation failed: " . $validation_result);
    exit;
}

$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

if (isset($_GET['telegram_id']) && isset($_GET['reward']) && isset($_GET['newDailyRewardDay']) && isset($_GET['current_date'])) {
    $telegram_id = intval($_GET['telegram_id']);
    $reward = intval($_GET['reward']);
    $newDailyRewardDay = intval($_GET['newDailyRewardDay']);
    $current_date = $_GET['current_date'];

    $stmt = $conn->prepare("SELECT last_login_date FROM daily_rewards WHERE telegram_id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Prepare statement failed: ' . $conn->error]);
        $conn->close();
        exit;
    }
    $stmt->bind_param("i", $telegram_id);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'Execute statement failed: ' . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit;
    }
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        $last_claim_date = $user['last_login_date'];
        $days_since_last_claim = (strtotime($current_date) - strtotime($last_claim_date)) / 86400;

        if ($days_since_last_claim < 1) {
            echo json_encode(['success' => false, 'error' => 'Daily reward already claimed']);
            $conn->close();
            exit;
        }

        $stmt = $conn->prepare("UPDATE daily_rewards SET last_login_date = ?, login_streak = ? WHERE telegram_id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Prepare statement failed: ' . $conn->error]);
            $conn->close();
            exit;
        }
        $stmt->bind_param("sii", $current_date, $newDailyRewardDay, $telegram_id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Execute statement failed: ' . $stmt->error]);
            $stmt->close();
            $conn->close();
            exit;
        }
        $stmt->close();

        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE telegram_id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Prepare statement failed: ' . $conn->error]);
            $conn->close();
            exit;
        }
        $stmt->bind_param("ii", $reward, $telegram_id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'error' => 'Execute statement failed: ' . $stmt->error]);
            $stmt->close();
            $conn->close();
            exit;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'reward' => $reward]);
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
?>
