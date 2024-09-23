<?php
require_once 'config.php';

// Подключение к базе данных
$conn = db_connect();

// Получение входящих обновлений
$update = file_get_contents("php://input");
$updateArray = json_decode($update, TRUE);

// Функция для отправки сообщений
function sendMessage($chatId, $message, $keyboard = null) {
    $postData = [
        'chat_id' => $chatId,
        'text' => $message
    ];

    if ($keyboard) {
        $postData['reply_markup'] = json_encode($keyboard);
    }

    $url = WEBSITE . "/sendMessage";
    file_get_contents($url . "?" . http_build_query($postData));
}

// Функция для обновления баланса пользователя
function updateBalance($conn, $telegram_id, $balance) {
    $stmt = $conn->prepare("UPDATE users SET balance = ? WHERE telegram_id = ?");
    $stmt->bind_param("ii", $balance, $telegram_id);
    $stmt->execute();
    $stmt->close();
}

// Функция для добавления пользователя в базу данных
function addUser($conn, $telegram_id, $username, $profile_img_url, $full_name, $invited_by_code) {
    $stmt = $conn->prepare("INSERT INTO users (telegram_id, username, profile_img_url, full_name) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $telegram_id, $username, $profile_img_url, $full_name);
    $stmt->execute();
    $stmt->close();

    // Добавляем запись в таблицу referrals
    $referral_code = generateReferralCode();
    $stmt = $conn->prepare("INSERT INTO referrals (telegram_id, referral_code, invited_by_code) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $telegram_id, $referral_code, $invited_by_code);
    $stmt->execute();
    $stmt->close();

    // Добавляем запись в таблицу boosts
    $stmt = $conn->prepare("INSERT INTO boosts (telegram_id, current_energy) VALUES (?, 100)");
    $stmt->bind_param("i", $telegram_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO daily_rewards (telegram_id) VALUES (?)");
    $stmt->bind_param("i", $telegram_id);
    $stmt->execute();
    $stmt->close();

    // Update referral count for the inviter
    if ($invited_by_code) {
        $stmt = $conn->prepare("UPDATE referrals SET referral_count = referral_count + 1 WHERE referral_code = ?");
        $stmt->bind_param("s", $invited_by_code);
        $stmt->execute();
    }
}

// Генерация реферального кода
function generateReferralCode($length = 8) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

// Получение профиля пользователя из базы данных
function getUserProfile($conn, $telegram_id) {
    $stmt = $conn->prepare("SELECT username, profile_img_url, full_name, balance FROM users WHERE telegram_id = ?");
    $stmt->bind_param("i", $telegram_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
}

function getProfilePhotoUrl($userId) {
    $userProfilePhotosUrl = WEBSITE . "/getUserProfilePhotos?user_id=" . $userId . "&limit=1";
    $userProfilePhotos = json_decode(file_get_contents($userProfilePhotosUrl), TRUE);
    $profilePhotoFileId = $userProfilePhotos["result"]["photos"][0][0]["file_id"] ?? null;
    $profilePhotoUrl = 'default_profile_img.png'; // URL изображения по умолчанию

    if ($profilePhotoFileId) {
        $filePathUrl = WEBSITE . "/getFile?file_id=" . $profilePhotoFileId;
        $filePath = json_decode(file_get_contents($filePathUrl), TRUE)["result"]["file_path"];
        $profilePhotoUrl = "https://api.telegram.org/file/bot" . BOT_TOKEN . "/" . $filePath;
    }

    return $profilePhotoUrl;
}

if (isset($updateArray["message"])) {
    /*if (!isset($updateArray["message"]["text"])) {
        // Игнорируем сообщение
        error_log("Ignoring service message.");
        exit;
    }*/
    $chatId = $updateArray["message"]["chat"]["id"];
    $message = $updateArray["message"]["text"];
    $userId = $updateArray["message"]["from"]["id"];
    $username = $updateArray["message"]["from"]["username"] ?? '';
    $firstName = $updateArray["message"]["from"]["first_name"];
    $lastName = $updateArray["message"]["from"]["last_name"] ?? '';
    $fullName = trim($firstName . ' ' . $lastName);
    if (isset($updateArray["message"]["text"])) {
        // Получение данных от пользователя
        

        // Логирование для отладки
        error_log("User ID: $userId, Username: $username, Full Name: $fullName");

        // Проверка существования пользователя и добавление нового
        $stmt = $conn->prepare("SELECT id FROM users WHERE telegram_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->store_result();

        $invited_by_code = null;
        if (strpos($message, '/start') === 0) {
            $parts = explode(' ', $message);
            if (count($parts) > 1) {
                $invited_by_code = $parts[1];
            }
        }

        if ($stmt->num_rows == 0) {
            $profilePhotoUrl = getProfilePhotoUrl($userId);
            // Логирование для отладки
            error_log("Profile Photo URL: $profilePhotoUrl");

            addUser($conn, $userId, $username, $profilePhotoUrl, $fullName, $invited_by_code);
        } else {
            $stmt->close();
            // Обновление фото и полного имени, если пользователь уже существует
            $profilePhotoUrl = getProfilePhotoUrl($userId);

            $stmt = $conn->prepare("UPDATE users SET profile_img_url = ?, full_name = ? WHERE telegram_id = ?");
            $stmt->bind_param("ssi", $profilePhotoUrl, $fullName, $userId);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        // Логирование для отладки, если сообщение без текста
        error_log("Message without text received, ignoring.");
    }

    // Получение профиля пользователя из базы данных
    $userProfile = getUserProfile($conn, $userId);
    $profilePhotoUrl = $userProfile['profile_img_url'];
    $newBalance = $userProfile['balance']; // Получаем текущий баланс

    // Публичный URL вашего мини-приложения
    $miniAppUrl = MINI_APP_URL . "?username=" . urlencode($fullName) . "&profile_img=" . urlencode($profilePhotoUrl) . "&balance=" . $newBalance;

    // Простой ответ на сообщение
    switch ($message) {
        case "/start":
            $text = "Welcome, $fullName! Your current balance is $newBalance. Click the button below to open the Mini App.";
            $keyboard = [
                "inline_keyboard" => [
                    [
                        ["text" => "Open Web App", "web_app" => ["url" => $miniAppUrl]]
                    ]
                ]
            ];
            error_log("Sending /start message to $chatId: $text");
            sendMessage($chatId, $text, $keyboard);
            break;
        case "/help":
            $text = "Here is a list of commands you can use:\n/start - Start the bot\n/help - Show this help message";
            error_log("Sending /help message to $chatId: $text");
            sendMessage($chatId, $text);
            break;
        default:
            $text = "Welcome, $fullName! Click the button below to open the Mini App.";
            $keyboard = [
                "inline_keyboard" => [
                    [
                        ["text" => "Open Web App", "web_app" => ["url" => $miniAppUrl]]
                    ]
                ]
            ];
            error_log("Sending default message to $chatId: $text");
            sendMessage($chatId, $text, $keyboard);
            break;
    }
} elseif (isset($updateArray["callback_query"])) {
    $queryData = $updateArray["callback_query"]["data"];
    $chatId = $updateArray["callback_query"]["message"]["chat"]["id"];
    $userId = $updateArray["callback_query"]["from"]["id"];
    
    if (strpos($queryData, 'startapp=') === 0) {
        $referralCode = substr($queryData, strlen('startapp='));

        $stmt = $conn->prepare("UPDATE referrals SET referral_count = referral_count + 1 WHERE referral_code = ?");
        $stmt->bind_param("s", $referralCode);
        $stmt->execute();

        sendMessage($chatId, "Referral registered successfully!");
    }
}

// Закрытие соединения с базой данных
$conn->close();
?>
