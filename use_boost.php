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

if (isset($_GET['telegram_id']) && isset($_GET['boost_type'])) {
    $telegram_id = intval($_GET['telegram_id']);
    $boost_type = $_GET['boost_type'];
    
    $stmt = $conn->prepare("SELECT current_energy, energy_level, daily_full_energy_count, daily_turbo_count FROM boosts WHERE telegram_id = ?");
    $stmt->bind_param("i", $telegram_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $boosts = $result->fetch_assoc();
        $current_energy = $boosts['current_energy'];
        $max_energy = 100 + 10 * ($boosts['energy_level'] - 1);

        if ($boost_type == 'fullEnergy') {
            if ($boosts['daily_full_energy_count'] > 0) {
                $current_energy = $max_energy;
                $stmt = $conn->prepare("UPDATE boosts SET current_energy = ?, daily_full_energy_count = daily_full_energy_count - 1 WHERE telegram_id = ?");
                $stmt->bind_param("ii", $current_energy, $telegram_id);
                $stmt->execute();
                echo json_encode(['success' => true, 'current_energy' => $current_energy]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No Full Energy boosts left']);
            }
        } elseif ($boost_type == 'turbo') {
            if ($boosts['daily_turbo_count'] > 0) {
                session_start();
                $turboEndTime = time() + 15;
                $_SESSION['turbo_end_time'] = $turboEndTime;
                $_SESSION['telegram_id'] = $telegram_id;
                $stmt = $conn->prepare("UPDATE boosts SET daily_turbo_count = daily_turbo_count - 1, turbo_multiplier = 10 WHERE telegram_id = ?");
                $stmt->bind_param("i", $telegram_id);
                $stmt->execute();
                echo json_encode(['success' => true, 'turbo_end_time' => $turboEndTime]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No Turbo boosts left']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid boost type']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No boost data found']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
?>
