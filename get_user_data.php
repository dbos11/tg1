<?php
require_once 'config.php';

$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

if (isset($_GET['telegram_id'])) {
    $telegram_id = intval($_GET['telegram_id']);

    // Подготовка SQL запроса для получения данных пользователя из daily_rewards
    $stmt = $conn->prepare("SELECT last_login_date, login_streak FROM daily_rewards WHERE telegram_id = ?");
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
        echo json_encode([
            'success' => true,
            'daily_reward_day' => intval($user['login_streak']),
            'last_login_date' => $user['last_login_date']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
?>
