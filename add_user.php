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

// Функция для добавления пользователя в базу данных
function addUser($conn, $telegram_id, $username, $profile_img_url, $full_name, $invited_by_code, $reward) {

    $reward = "25000|0,002";

    $reward = str_replace(',', '.', $reward);

    $values = explode('|', $reward);
    $value1 = intval($values[0]); // Значение для balance
    $value2 = floatval($values[1]);
    error_log("Extracted values from reward: Value1 = $value1, Value2 = $value2, Reward string = $reward");

    // Вставка пользователя в таблицу users
    $stmt = $conn->prepare("INSERT INTO users (telegram_id, username, profile_img_url, full_name) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $telegram_id, $username, $profile_img_url, $full_name);
    $stmt->execute();
    $stmt->close();

    // Добавляем запись в таблицу referrals с добавлением reward
    $referral_code = generateReferralCode();
    $stmt = $conn->prepare("INSERT INTO referrals (telegram_id, referral_code, invited_by_code, reward) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $telegram_id, $referral_code, $invited_by_code, $reward);
    $stmt->execute();
    $stmt->close();

    // Добавляем запись в таблицу boosts
    $stmt = $conn->prepare("INSERT INTO boosts (telegram_id) VALUES (?)");
    $stmt->bind_param("i", $telegram_id);
    $stmt->execute();
    $stmt->close();
    
    // Добавляем запись в таблицу bot
    $stmt = $conn->prepare("INSERT INTO bot (telegram_id) VALUES (?)");
    $stmt->bind_param("i", $telegram_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO daily_rewards (telegram_id) VALUES (?)");
    $stmt->bind_param("i", $telegram_id);
    $stmt->execute();
    $stmt->close();

    // Update referral count for the inviter and add bonus
    if ($invited_by_code) {
        // Найдем telegram_id пользователя, который пригласил
        $stmt = $conn->prepare("SELECT telegram_id FROM referrals WHERE referral_code = ?");
        $stmt->bind_param("s", $invited_by_code);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $inviter_telegram_id = $row['telegram_id'];

            $stmt = $conn->prepare("UPDATE referrals SET referral_count = referral_count + 1 WHERE telegram_id = ?");
            $stmt->bind_param("i", $inviter_telegram_id);
            $stmt->execute();

            // Обновим untaken_reward и ton_balance пригласившего
            $stmt = $conn->prepare("UPDATE users SET untaken_reward = untaken_reward + ?, ton_balance = ton_balance + ? WHERE telegram_id = ?");
            $stmt->bind_param("idi", $value1, $value2, $inviter_telegram_id);
            $stmt->execute();
            
            // Обновим untaken_reward и ton_balance приглашенного
            $stmt = $conn->prepare("UPDATE users SET untaken_reward = untaken_reward + ?, ton_balance = ton_balance + ? WHERE telegram_id = ?");
            $stmt->bind_param("idi", $value1, $value2, $telegram_id);
            $stmt->execute();
        }
        $stmt->close();
    }
}

// Генерация реферального кода
function generateReferralCode($length = 8) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

// Получение профиля пользователя из базы данных
function getUserProfile($conn, $telegram_id) {
    $stmt = $conn->prepare("SELECT username, profile_img_url, full_name FROM users WHERE telegram_id = ?");
    $stmt->bind_param("i", $telegram_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
}

// Проверка и добавление пользователя
if (isset($_GET['telegram_id'])) {
    $telegram_id = intval($_GET['telegram_id']);
    $username = $_GET['username'] ?? '';
    $full_name = $_GET['full_name'] ?? '';
    $invited_by_code = $_GET['invited_by_code'] ?? null;
    $profile_img_url = $_GET['profile_img_url'] ?? 'default_profile_img.png';
    $reward = $_GET['reward'] ?? '10000|0,001';

    // Проверка существования пользователя
    $stmt = $conn->prepare("SELECT id FROM users WHERE telegram_id = ?");
    $stmt->bind_param("i", $telegram_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
        addUser($conn, $telegram_id, $username, $profile_img_url, $full_name, $invited_by_code, $reward);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'User already exists']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

// Закрытие соединения с базой данных
$conn->close();
?>