<?php

require_once 'config.php';

class TelegramDataValidator
{
    /**
     * Validate initData to ensure that it is from Telegram.
     *
     * @param string $botToken Your bot token
     * @param string $initData Init data from Telegram (`Telegram.WebApp.initData`)
     *
     * @return bool Returns true if the data is valid, otherwise false
     */
    public static function isSafe(string $botToken, string $initData): bool
    {
        [$checksum, $sortedInitData] = self::convertInitData($initData);
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $hash = bin2hex(hash_hmac('sha256', $sortedInitData, $secretKey, true));

        return 0 === strcmp($hash, $checksum);
    }

    /**
     * Convert init data to `key=value` format and sort it alphabetically.
     *
     * @param string $initData Init data from Telegram (`Telegram.WebApp.initData`)
     *
     * @return array Returns hash and sorted init data
     */
    private static function convertInitData(string $initData): array
    {
        $initDataArray = explode('&', rawurldecode($initData));
        $needle = 'hash=';
        $hash = '';

        foreach ($initDataArray as &$data) {
            if (substr($data, 0, strlen($needle)) === $needle) {
                $hash = substr_replace($data, '', 0, strlen($needle));
                $data = null;
            }
        }
        $initDataArray = array_filter($initDataArray);
        sort($initDataArray);

        return [$hash, implode("\n", $initDataArray)];
    }
}

// Использование валидации

function validate_telegram_data($data) {
    $bot_token = BOT_TOKEN;

    // Проверка наличия initData
    if (!isset($data['initData'])) {
        error_log("Error: 'initData' key is missing from the data.");
        return 'Missing initData key.';
    }

    // Парсинг initData и получение отдельных параметров
    parse_str($data['initData'], $parsed_init_data);
    $data = array_merge($data, $parsed_init_data);

    // Проверка наличия hash
    if (!isset($data['hash'])) {
        error_log("Error: 'hash' key is missing from the data.");
        return 'Missing hash key.';
    }

    // Валидация данных с использованием метода isSafe
    $isSafe = TelegramDataValidator::isSafe($bot_token, $data['initData']);

    if ($isSafe) {
        // Проверка наличия auth_date
        if (!isset($data['auth_date'])) {
            error_log("Error: 'auth_date' key is missing from the data.");
            return 'Missing auth_date key.';
        }

        $auth_date = intval($data['auth_date']);
        $current_time = time();
        $time_difference = $current_time - $auth_date;
        $max_time_diff = 86400; // 24 часа

        if ($time_difference < $max_time_diff) {
            error_log("Data is valid and up-to-date.");
            return 'Data is up-to-date.';
        } else {
            error_log("Data is outdated.");
            return 'Data is outdated.';
        }
    } else {
        error_log("Data is invalid.");
        return 'Data is invalid.';
    }
}



?>
