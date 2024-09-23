<?php
require_once 'config.php';

// Подключение к базе данных
$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

if (isset($_GET['telegram_id'])) {
    $telegram_id = intval($_GET['telegram_id']);

    $stmt = $conn->prepare("SELECT current_energy, energy_level, energy_recovery_level, last_energy_update FROM boosts WHERE telegram_id = ?");
    $stmt->bind_param("i", $telegram_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $boosts = $result->fetch_assoc();
    $stmt->close();

    if ($boosts) {
        $current_energy = $boosts['current_energy'];
        $energy_level = $boosts['energy_level'];
        $energy_recovery_level = $boosts['energy_recovery_level'];
        $last_energy_update = strtotime($boosts['last_energy_update']);
        $now = time();

        $time_elapsed = $now - $last_energy_update;

        $recovered_energy = intval($time_elapsed * $energy_recovery_level);

        $max_energy = 100 + 10 * ($energy_level - 1);
        $new_energy = min($current_energy + $recovered_energy, $max_energy);

        $stmt = $conn->prepare("UPDATE boosts SET current_energy = ?, last_energy_update = NOW() WHERE telegram_id = ?");
        $stmt->bind_param("ii", $new_energy, $telegram_id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'current_energy' => $new_energy, 'max_energy' => $max_energy]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Boost data not found']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
?>
