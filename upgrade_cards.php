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
    echo json_encode(['success' => false, 'error' => $validation_result]);
    error_log("Validation failed: " . $validation_result);
    exit;
}

// Подключение к базе данных
$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

if (isset($_GET['telegram_id']) && isset($_GET['card_id']) && isset($_GET['new_level']) && isset($_GET['cost']) && isset($_GET['spent_coin']) && isset($_GET['income_per_hour'])) {
    $telegram_id = intval($_GET['telegram_id']);
    $card_id = intval($_GET['card_id']);
    $new_level = intval($_GET['new_level']);
    $cost = intval($_GET['cost']);
    $spent_coin = intval($_GET['spent_coin']);
    $income_per_hour = floatval($_GET['income_per_hour']); // Предполагается, что income_per_hour передается в запросе

    // Обновляем баланс, spent_coin и income_per_hour в таблице users
    $stmt_balance = $conn->prepare("UPDATE users SET balance = balance - ?, spent_coin = ?, income_per_hour = ? WHERE telegram_id = ?");
    $stmt_balance->bind_param("iidi", $cost, $spent_coin, $income_per_hour, $telegram_id);
    $stmt_balance->execute();

    if ($stmt_balance->affected_rows > 0) {
        error_log("Attempting to upgrade or create card: telegram_id = $telegram_id, card_id = $card_id, new_level = $new_level");

        // Проверяем, существует ли запись с данной комбинацией card_id и telegram_id
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM user_cards WHERE card_id = ? AND telegram_id = ?");
        $stmt_check->bind_param("ii", $card_id, $telegram_id);
        $stmt_check->execute();
        $stmt_check->bind_result($count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($count > 0) {
            // Если запись существует, обновляем уровень карты
            $stmt_card = $conn->prepare("UPDATE user_cards SET level = ?, purchased_at = NOW() WHERE card_id = ? AND telegram_id = ?");
            $stmt_card->bind_param("iii", $new_level, $card_id, $telegram_id);
            $stmt_card->execute();
        } else {
            // Если записи нет, создаем новую запись
            $stmt_card = $conn->prepare("INSERT INTO user_cards (telegram_id, card_id, level) VALUES (?, ?, ?)");
            $stmt_card->bind_param("iii", $telegram_id, $card_id, $new_level);
            $stmt_card->execute();
        }

        // Проверяем, входит ли купленная карточка в текущее комбо
        $stmt_combo = $conn->prepare("SELECT card_1_id, card_2_id, card_3_id, reward FROM daily_combos WHERE combo_date = CURDATE()");
        $stmt_combo->execute();
        $stmt_combo->bind_result($card_1_id, $card_2_id, $card_3_id, $reward);
        $stmt_combo->fetch();
        $stmt_combo->close();

        // Проверка, является ли купленная карточка частью текущего комбо и её индекс
        $isComboCard = false;
        $cardIndex = -1;

        if ($card_id == $card_1_id) {
            $isComboCard = true;
            $cardIndex = 0; // Индекс для первой карты
        } elseif ($card_id == $card_2_id) {
            $isComboCard = true;
            $cardIndex = 1; // Индекс для второй карты
        } elseif ($card_id == $card_3_id) {
            $isComboCard = true;
            $cardIndex = 2; // Индекс для третьей карты
        }

        if ($isComboCard) {
            // Получаем текущие данные из daily_rewards
            $stmt_rewards = $conn->prepare("SELECT cards_purchased FROM daily_rewards WHERE telegram_id = ?");
            $stmt_rewards->bind_param("i", $telegram_id);
            $stmt_rewards->execute();
            $stmt_rewards->bind_result($cards_purchased_json);
            $stmt_rewards->fetch();
            $stmt_rewards->close();

            // Преобразуем JSON в массив
            $cardsPurchased = json_decode($cards_purchased_json, true);

            // Инициализируем отсутствующие ключи в массиве
            if (!isset($cardsPurchased['card_1'])) {
                $cardsPurchased['card_1'] = false;
            }
            if (!isset($cardsPurchased['card_2'])) {
                $cardsPurchased['card_2'] = false;
            }
            if (!isset($cardsPurchased['card_3'])) {
                $cardsPurchased['card_3'] = false;
            }

            // Обновляем информацию о купленных картах
            if ($card_id == $card_1_id) {
                $cardsPurchased['card_1'] = true;
            } elseif ($card_id == $card_2_id) {
                $cardsPurchased['card_2'] = true;
            } elseif ($card_id == $card_3_id) {
                $cardsPurchased['card_3'] = true;
            }

            // Преобразуем обратно в JSON
            $updatedCardsPurchased = json_encode($cardsPurchased);

            // Обновляем daily_rewards с новой информацией о купленных карточках
            $stmt_update_rewards = $conn->prepare("UPDATE daily_rewards SET cards_purchased = ? WHERE telegram_id = ?");
            $stmt_update_rewards->bind_param("si", $updatedCardsPurchased, $telegram_id);
            $stmt_update_rewards->execute();
            $stmt_update_rewards->close();

            if ($cardsPurchased['card_1'] && $cardsPurchased['card_2'] && $cardsPurchased['card_3']) {
                // Добавляем награду на баланс пользователя
                $stmt_add_reward = $conn->prepare("UPDATE users SET balance = balance + ? WHERE telegram_id = ?");
                $stmt_add_reward->bind_param("ii", $reward, $telegram_id);
                $stmt_add_reward->execute();
                $stmt_add_reward->close();
            }
        }

        // Возвращаем успех, ID карточки и её индекс в комбо
        echo json_encode(['success' => true, 'card_id' => $card_id, 'isComboCard' => $isComboCard, 'cardIndex' => $cardIndex]);

    } else {
        echo json_encode(['success' => false, 'error' => 'Insufficient balance or failed to update balance']);
    }

    $stmt_balance->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
?>
