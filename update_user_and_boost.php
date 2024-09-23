<?php
require_once 'config.php';
require_once 'telegram_validation.php';

error_log("Script started.");

$data = json_decode(file_get_contents('php://input'), true);

$validation_result = validate_telegram_data($data);

error_log("Validation result: " . $validation_result);

if ($validation_result !== 'Data is up-to-date.') {
    echo json_encode(['success' => false, 'error' => $validation_result]);
    error_log("Validation failed: " . $validation_result);
    exit;
}

$mysqli = db_connect();

if (isset($data['telegram_id']) && isset($data['balance']) && isset($data['league']) && isset($data['current_energy']) && isset($data['last_energy_update']) && isset($data['level']) && isset($data['tappedCoins'])) {
    $telegram_id = $data['telegram_id'];
    $balance = $data['balance'];
    $league = $data['league'];
    $current_energy = $data['current_energy'];
    $last_energy_update = $data['last_energy_update'];
    $level = $data['level'];
    $tapped_coins = $data['tappedCoins'];

    $stmt = $mysqli->prepare("UPDATE users SET balance = ?, league = ?, level = ?, tapped_coins = ? WHERE telegram_id = ?");
    $stmt->bind_param("isiii", $balance, $league, $level, $tapped_coins, $telegram_id);
    if (!$stmt->execute()) {
        error_log("Error updating users table: " . $stmt->error);
    }

    $stmt = $mysqli->prepare("UPDATE boosts SET current_energy = ?, last_energy_update = ? WHERE telegram_id = ?");
    $stmt->bind_param("isi", $current_energy, $last_energy_update, $telegram_id);
    if (!$stmt->execute()) {
        error_log("Error updating boosts table: " . $stmt->error);
    }

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Missing required data']);
}
?>
