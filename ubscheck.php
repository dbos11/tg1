<?php

// Токен вашего бота
$botToken = '7491240952:AAGvH_-JIIbn24XQucA1XD6Jg-tIDruRcXc';

// Юзернейм канала без @
$channelUsername = 'cryptolamer_news';

// Путь к файлу с ID пользователей (если файл на локальном сервере)
$filename = 'telegram_ids.txt';

// Попробуем загрузить содержимое файла
$fileContent = @file_get_contents($filename);

if ($fileContent === false) {
    die("Не удалось загрузить файл telegram_ids.txt!\n");
}

// Преобразуем содержимое файла в массив
$userIds = explode("\n", $fileContent);

// Фильтруем массив, удаляя пустые строки и возможные пробелы
$userIds = array_filter(array_map('trim', $userIds));

// Функция для проверки, подписан ли пользователь на канал
function isUserMemberOfChannel($botToken, $channelUsername, $userId) {
    // Формируем URL для запроса
    $url = "https://api.telegram.org/bot$botToken/getChatMember?chat_id=" . urlencode("@$channelUsername") . "&user_id=" . urlencode($userId);

    // Выполняем запрос с обработкой ошибок
    $response = @file_get_contents($url);

    // Декодируем JSON-ответ
    $data = json_decode($response, true);

    // Проверяем, успешен ли запрос и есть ли данные о пользователе
    if (isset($data['ok']) && $data['ok']) {
        // Проверяем статус подписки пользователя
        $status = $data['result']['status'];
        return in_array($status, ['member', 'administrator', 'creator']);
    }

    return false;
}

// Инициализируем счётчики
$subscribedCount = 0;
$notSubscribedCount = 0;
$totalProcessed = 0;
$batchSize = 10; // Количество запросов в секунду

// Начинаем обработку
for ($i = 0; $i < count($userIds); $i += $batchSize) {
    // Пакет из 10 пользователей
    $batch = array_slice($userIds, $i, $batchSize);

    foreach ($batch as $userId) {
        // Если пользователь подписан на канал
        if (isUserMemberOfChannel($botToken, $channelUsername, $userId)) {
            $subscribedCount++;
        } else {
            $notSubscribedCount++;
        }
        $totalProcessed++;
    }

    // Обновляем статистику каждую секунду
    echo "\nПодписаны: $subscribedCount | Не подписаны: $notSubscribedCount | Пройдено: $totalProcessed\n";

    // Задержка в 1 секунду перед следующими 10 запросами
    sleep(1);
}

// Вывод финальных результатов
echo "\nВсего подписаны: $subscribedCount\n";
echo "Всего не подписаны: $notSubscribedCount\n";
echo "Общее количество обработанных пользователей: $totalProcessed\n";

?>
