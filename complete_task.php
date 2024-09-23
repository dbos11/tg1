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

// Подключение к базе данных
$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

function checkSubscription($telegram_id, $channel_username) {
    $bot_token = BOT_CHECKER_TOKEN;
    $url = "https://api.telegram.org/bot$bot_token/getChatMember?chat_id=@$channel_username&user_id=$telegram_id";
    
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    if (isset($data['result']['status'])) {
        $status = $data['result']['status'];
        return in_array($status, ['member', 'administrator', 'creator']);
    }
    return false;
}

// Получаем параметры запроса
$telegram_id = isset($_GET['telegram_id']) ? intval($_GET['telegram_id']) : null;
$task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : null;
$input_code = isset($_GET['input_code']) ? $_GET['input_code'] : null;

if ($telegram_id && $task_id) {

    // Проверка, выполнялось ли задание ранее
    $stmt = $conn->prepare("SELECT id FROM user_tasks WHERE user_id = ? AND task_id = ?");
    $stmt->bind_param("ii", $telegram_id, $task_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Если задание уже выполнено
        echo json_encode(['success' => false, 'error' => 'Task already completed']);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();

    // Получение информации о задании
    $stmt = $conn->prepare("SELECT reward, ton_reward, link, task_type, validation_code FROM tasks WHERE id = ?");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();
    $reward = $task['reward'];
    $ton_reward = $task['ton_reward'];
    $link = $task['link'];
    $task_type = $task['task_type'];
    $validation_code = $task['validation_code'];
    $channel_username = trim(parse_url($link, PHP_URL_PATH), '/');
    $stmt->close();

    // Обработка заданий в зависимости от типа
    if ($task_type === 'code_manual') {
        // Вставка задания с ручной проверкой кода
        $stmt = $conn->prepare("INSERT INTO user_tasks (user_id, task_id, input_code, status) VALUES (?, ?, ?, 'pending')");
        $stmt->bind_param("iis", $telegram_id, $task_id, $input_code);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Task is pending approval']);
        $conn->close();
        exit;

    } elseif ($task_type === 'code_auto') {
        // Проверка введенного кода с validation_code
        if (strcasecmp($input_code, $validation_code) === 0) {
            // Код совпадает, продолжаем начисление награды
            $stmt = $conn->prepare("INSERT INTO user_tasks (user_id, task_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $telegram_id, $task_id);
            $stmt->execute();
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid code']);
            $conn->close();
            exit;
        }
    } elseif ($task_type === 'subscribe') {
        // Логика проверки подписки
        if (!checkSubscription($telegram_id, $channel_username)) {
            echo json_encode(['success' => false, 'error' => 'User is not subscribed to the channel']);
            $conn->close();
            exit;
        }

        // Подписка подтверждена, добавляем задание в user_tasks
        $stmt = $conn->prepare("INSERT INTO user_tasks (user_id, task_id, status) VALUES (?, ?, 'completed')");
        $stmt->bind_param("ii", $telegram_id, $task_id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Subscription confirmed and task completed']);
        $conn->close();
        exit;
    } elseif ($task_type === 'cooldown') {
        // Добавляем задание в user_tasks со статусом cooldown
        $stmt = $conn->prepare("INSERT INTO user_tasks (user_id, task_id, status) VALUES (?, ?, 'cooldown')");
        $stmt->bind_param("ii", $telegram_id, $task_id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Task is on cooldown']);
        $conn->close();
        exit;
    }

    // Начисление награды пользователю
    if ($task_type !== 'code_manual' && $task_type !== 'cooldown') {
        $stmt = $conn->prepare("UPDATE users SET balance = balance + ?, ton_balance = ton_balance + ? WHERE telegram_id = ?");
        $stmt->bind_param("ddi", $reward, $ton_reward, $telegram_id);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
?>
