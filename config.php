<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

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
define('BOT_TOKEN', '7279502726:AAHCpz4uYfI4M2hS2y8YhHN51VLCPf03V7M');
define('BOT_CHECKER_TOKEN', '7065299522:AAG7Z_MzHnR0gzj2YnOlC8O2coAemY3ptVI');
define('WEBSITE', 'https://api.telegram.org/bot' . BOT_TOKEN);

// URL вашего мини-приложения
define('MINI_APP_URL', 'https://43cc1970b761.ngrok.ap');
?>
