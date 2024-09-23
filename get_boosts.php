<?php
require_once 'config.php';

// Подключение к базе данных
$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

if (isset($_GET['telegram_id'])) {
    $telegram_id = intval($_GET['telegram_id']);

    // Get balance from users table
    $stmt_user = $conn->prepare("SELECT balance FROM users WHERE telegram_id = ?");
    $stmt_user->bind_param("i", $telegram_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    $user = $result_user->fetch_assoc();

    // Get boosts from boosts table
    $stmt_boost = $conn->prepare("SELECT damage_level, energy_level, energy_recovery_level, daily_full_energy_count, daily_turbo_count, daily_boosts_last_reset, mining_bot_status FROM boosts WHERE telegram_id = ?");
    $stmt_boost->bind_param("i", $telegram_id);
    $stmt_boost->execute();
    $result_boost = $stmt_boost->get_result();
    $boosts = $result_boost->fetch_assoc();

    // Reset daily boosts if last reset was not today
    $last_reset_date = new DateTime($boosts['daily_boosts_last_reset']);
    $current_date = new DateTime();
    if ($last_reset_date->format('Y-m-d') !== $current_date->format('Y-m-d')) {
        $boosts['daily_full_energy_count'] = 3;
        $boosts['daily_turbo_count'] = 3;
        $stmt_update = $conn->prepare("UPDATE boosts SET daily_full_energy_count = 3, daily_turbo_count = 3, daily_boosts_last_reset = NOW() WHERE telegram_id = ?");
        $stmt_update->bind_param("i", $telegram_id);
        $stmt_update->execute();
        $stmt_update->close();
    }

    echo json_encode(['success' => true, 'balance' => $user['balance']] + $boosts);

    $stmt_user->close();
    $stmt_boost->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
?>
