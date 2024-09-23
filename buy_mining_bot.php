<?php
require_once 'config.php';

// Подключение к базе данных
$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

if (isset($_GET['telegram_id']) && isset($_GET['cost'])) {
    $telegram_id = intval($_GET['telegram_id']);
    $cost = intval($_GET['cost']);

    // Update the balance in users table
    $stmt_balance = $conn->prepare("UPDATE users SET balance = balance - ? WHERE telegram_id = ?");
    $stmt_balance->bind_param("ii", $cost, $telegram_id);
    $stmt_balance->execute();

    if ($stmt_balance->affected_rows > 0) {
        // Update the mining bot status in boosts table
        $stmt_bot = $conn->prepare("UPDATE boosts SET mining_bot_status = 1 WHERE telegram_id = ?");
        $stmt_bot->bind_param("i", $telegram_id);
        $stmt_bot->execute();

        if ($stmt_bot->affected_rows > 0) {
            // Add user to bot table
            $stmt_insert_bot = $conn->prepare("INSERT INTO bot (telegram_id, last_activity, auto_collect_start) VALUES (?, NOW(), NOW() + INTERVAL 1 MINUTE)");
            $stmt_insert_bot->bind_param("i", $telegram_id);
            $stmt_insert_bot->execute();

            if ($stmt_insert_bot->affected_rows > 0) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to add user to bot table']);
            }

            $stmt_insert_bot->close();
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update mining bot status']);
        }

        $stmt_bot->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Insufficient balance or failed to update balance']);
    }

    $stmt_balance->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
?>
