<?php
// Конфигурация базы данных
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'telegram_clicker');

// Подключение к базе данных
function db_connect() {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Токен вашего бота
define('BOT_TOKEN', '7471028351:AAFW6SX9hFGeHMUNc1AxKGyBjomdZkLC_fs');
define('BOT_CHECKER_TOKEN', '7065299522:AAG7Z_MzHnR0gzj2YnOlC8O2coAemY3ptVI');
define('WEBSITE', 'https://api.telegram.org/bot' . BOT_TOKEN);

// URL вашего мини-приложения
define('MINI_APP_URL', 'https://0de5-85-190-189-74.ngrok-free.app/telegram/index.html');
?>
