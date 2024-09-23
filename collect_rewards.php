<?php
require_once 'config.php';

// Подключение к базе данных
$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

if (isset($_GET['telegram_id'])) {
    $telegram_id = intval($_GET['telegram_id']);

    $stmt = $conn->prepare("SELECT last_activity, auto_collect_start FROM bot WHERE telegram_id = ?");
    $stmt->bind_param("i", $telegram_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $bot_data = $result->fetch_assoc();
        $last_activity = new DateTime($bot_data['last_activity']);
        $auto_collect_start = new DateTime($bot_data['auto_collect_start']);
        $current_time = new DateTime();

        // Check if the user has been inactive for more than a minute
        if ($last_activity < $current_time) {
            $seconds_inactive = min($current_time->getTimestamp() - $auto_collect_start->getTimestamp(), 60);
            $coins_earned = max(0, $seconds_inactive);

            // Update the last activity and auto_collect_start times
            $stmt_update = $conn->prepare("UPDATE bot SET last_activity = ?, auto_collect_start = ? WHERE telegram_id = ?");
            $new_auto_collect_start = $current_time->modify('+1 minute')->format('Y-m-d H:i:s');
            $current_time_formatted = (new DateTime())->format('Y-m-d H:i:s');
            $stmt_update->bind_param("ssi", $current_time_formatted, $new_auto_collect_start, $telegram_id);
            $stmt_update->execute();

            // Add the earned coins to the user's balance
            $stmt_update_balance = $conn->prepare("UPDATE users SET balance = balance + ? WHERE telegram_id = ?");
            $stmt_update_balance->bind_param("ii", $coins_earned, $telegram_id);
            $stmt_update_balance->execute();

            echo json_encode(['success' => true, 'coins_earned' => $coins_earned]);
        } else {
            echo json_encode(['success' => false, 'coins_earned' => 0, 'error' => 'No inactivity detected']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Bot data not found']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
?>
