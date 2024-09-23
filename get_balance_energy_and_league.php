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

// Подключаемся к базе данных
$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

function generateReferralCode($length = 8) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

function getLeagueByLevel($level) {
    if ($level >= 1 && $level <= 4) {
        return 'Bronze';
    } elseif ($level >= 5 && $level <= 8) {
        return 'Silver';
    } elseif ($level >= 9 && $level <= 12) {
        return 'Gold';
    } elseif ($level >= 13 && $level <= 16) {
        return 'Platinum';
    } elseif ($level >= 17 && $level <= 21) {
        return 'Diamond';
    } else {
        return 'Unknown';
    }
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


if (isset($_GET['telegram_id'])) {
    $telegram_id = intval($_GET['telegram_id']);
    error_log("Processing user with telegram_id: " . $telegram_id);

    $stmt = $conn->prepare("
        SELECT 
            u.balance, 
            u.league, 
            u.level,
            u.spent_coin,  
            u.income_per_hour,
            u.ton_balance,
            u.tapped_coins,
            u.untaken_reward,
            b.current_energy, 
            b.damage_level, 
            b.energy_level, 
            b.energy_recovery_level,
            b.daily_full_energy_count,
            b.daily_turbo_count,
            b.daily_boosts_last_reset,
            b.mining_bot_status,
            b.last_energy_update,
            r.referral_code,
            dr.last_login_date,
            dr.cards_purchased,
            dr.combo_date,
            dr.login_streak
        FROM users u
        JOIN boosts b ON u.telegram_id = b.telegram_id
        LEFT JOIN referrals r ON u.telegram_id = r.telegram_id
        LEFT JOIN daily_rewards dr ON u.telegram_id = dr.telegram_id
        WHERE u.telegram_id = ?
    ");
    $stmt->bind_param("i", $telegram_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        error_log("User found in database.");

        $row = $result->fetch_assoc();

        $new_balance = $row['balance'] + $row['untaken_reward'];

        $updateStmt = $conn->prepare("UPDATE users SET balance = ?, untaken_reward = 0 WHERE telegram_id = ?");
        $updateStmt->bind_param("di", $new_balance, $telegram_id);
        $updateStmt->execute();
        $updateStmt->close();

        $row['balance'] = $new_balance;

        // Обновляем лигу на основе уровня
        $currentLevel = $row['level'];
        $row['league'] = getLeagueByLevel($currentLevel);

        $moscowTime = new DateTime('now', new DateTimeZone('Europe/Moscow'));

        // Проверяем, что текущее время больше 18:00
        if ($moscowTime->format('H:i') >= '18:00') {
            // Выбираем комбо для следующего дня
            $comboRewardStmt = $conn->prepare("
                SELECT reward 
                FROM daily_combos 
                WHERE combo_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            ");
        } else {
            // Выбираем комбо для текущего дня
            $comboRewardStmt = $conn->prepare("
                SELECT reward 
                FROM daily_combos 
                WHERE combo_date = CURDATE()
            ");
        }

        $comboRewardStmt->execute();
        $comboRewardStmt->bind_result($combo_reward);
        $comboRewardStmt->fetch();
        $comboRewardStmt->close();


        $taskStmt = $conn->prepare("
            SELECT ut.task_id, t.reward, t.ton_reward, t.task_type, t.link
            FROM user_tasks ut
            JOIN tasks t ON ut.task_id = t.id
            WHERE ut.user_id = ?
        ");
        $taskStmt->bind_param("i", $telegram_id);
        $taskStmt->execute();
        $taskResult = $taskStmt->get_result();

        while ($taskRow = $taskResult->fetch_assoc()) {
            $task_id = intval($taskRow['task_id']);
            $reward = $taskRow['reward'];
            $ton_reward = $taskRow['ton_reward'];
            $link = $taskRow['link'];
            $channel_username = trim(parse_url($link, PHP_URL_PATH), '/');
            if ($taskRow['task_type'] === 'subscribe') {
            // Проверяем подписку пользователя
                if (!checkSubscription($telegram_id, $channel_username)) {
                    // Уменьшаем баланс, если пользователь не подписан
                    $new_balance = max(0, $row['balance'] - $reward);
                    $new_ton_balance = max(0, $row['ton_balance'] - $ton_reward);
    
                    $row['balance'] = $new_balance;
                    $row['ton_balance'] = $new_ton_balance;
    
                    // Обновляем баланс в базе данных
                    $updateStmt = $conn->prepare("UPDATE users SET balance = ?, ton_balance = ? WHERE telegram_id = ?");
                    $updateStmt->bind_param("ddi", $new_balance, $new_ton_balance, $telegram_id);
                    $updateStmt->execute();
                    $updateStmt->close();
    
                    $deleteTaskStmt = $conn->prepare("DELETE FROM user_tasks WHERE user_id = ? AND task_id = ?");
                    $deleteTaskStmt->bind_param("ii", $telegram_id, $task_id);
                    $deleteTaskStmt->execute();
                    $deleteTaskStmt->close();
                }

            }
        }
        $taskStmt->close();

        // Сброс ежедневных бустов, если последний сброс был не сегодня
        $last_reset_date = new DateTime($row['daily_boosts_last_reset']);
        $current_date = new DateTime();
        if ($last_reset_date->format('Y-m-d') !== $current_date->format('Y-m-d')) {
            $row['daily_full_energy_count'] = 3;
            $row['daily_turbo_count'] = 3;
            $stmt_update = $conn->prepare("UPDATE boosts SET daily_full_energy_count = 3, daily_turbo_count = 3, daily_boosts_last_reset = NOW() WHERE telegram_id = ?");
            $stmt_update->bind_param("i", $telegram_id);
            $stmt_update->execute();
            $stmt_update->close();
        }

        // Получение друзей
        $friendsStmt = $conn->prepare("
            SELECT u.full_name, u.profile_img_url, u.league, r.reward
            FROM referrals r
            JOIN users u ON r.telegram_id = u.telegram_id
            WHERE r.invited_by_code = (
                SELECT referral_code FROM referrals WHERE telegram_id = ?
            )
        ");
        $friendsStmt->bind_param("i", $telegram_id);
        $friendsStmt->execute();
        $friendsResult = $friendsStmt->get_result();
        $friends = [];

        while ($friendRow = $friendsResult->fetch_assoc()) {
            // Разделяем значение reward на два
            $rewards = explode('|', $friendRow['reward']);
            $reward1 = $rewards[0];
            $reward2 = isset($rewards[1]) ? $rewards[1] : null;

            // Добавляем данные в массив
            $friends[] = [
                'full_name' => $friendRow['full_name'],
                'profile_img_url' => $friendRow['profile_img_url'],
                'league' => $friendRow['league'],
                'reward1' => $reward1,
                'reward2' => $reward2,
            ];
        }

        $friendsStmt->close();

        // Получение реферального кода
        $referral_code = $row['referral_code'];
        if (!$referral_code) {
            $referral_code = generateReferralCode();
            $insertReferralStmt = $conn->prepare("INSERT INTO referrals (telegram_id, referral_code) VALUES (?, ?)");
            $insertReferralStmt->bind_param("is", $telegram_id, $referral_code);
            $insertReferralStmt->execute();
            $insertReferralStmt->close();
        }

        // Получение данных о лигах и игроках
        $leagues = ["Bronze", "Silver", "Gold", "Platinum", "Diamond"];
        $players = [];
                
        foreach ($leagues as $league) {
            $leagueStmt = $conn->prepare("
                SELECT telegram_id, full_name, balance, spent_coin, (balance + spent_coin) AS total_balance, profile_img_url 
                FROM users 
                WHERE league = ? 
                ORDER BY total_balance DESC 
                LIMIT 100
            ");
            $leagueStmt->bind_param("s", $league);
            $leagueStmt->execute();
            $leagueResult = $leagueStmt->get_result();
            
            $players[$league] = [];
            while ($playerRow = $leagueResult->fetch_assoc()) {
                $players[$league][] = [
                    'telegram_id' => $playerRow['telegram_id'],
                    'full_name' => $playerRow['full_name'],
                    'balance' => $playerRow['balance'],
                    'spent_coin' => $playerRow['spent_coin'],
                    'total_balance' => $playerRow['total_balance'],
                    'profile_img_url' => $playerRow['profile_img_url']
                ];
            }
            $leagueStmt->close();
        }

        // Запрос на получение данных из таблицы `cards` и соответствующих уровней пользователя
        $sql = "
            SELECT 
                c.id, 
                c.name, 
                c.type, 
                c.description, 
                c.colour, 
                c.start_price,  
                c.start_income, 
                c.price_step, 
                c.income_step, 
                c.max_level,
                c.img,
                c.requirements,
                c.cooldown,
                IFNULL(uc.level, 1) AS user_level -- Если уровень не найден, устанавливаем его в 0
            FROM 
                cards c
            LEFT JOIN 
                user_cards uc ON c.id = uc.card_id AND uc.telegram_id = ?
        ";

        $cardStmt = $conn->prepare($sql);
        $cardStmt->bind_param("i", $telegram_id);
        $cardStmt->execute();
        $cardResult = $cardStmt->get_result();

        $cards = [];

        // Заполняем массив карточек с уровнями пользователя
        while ($cardRow = $cardResult->fetch_assoc()) {
            $requirements = json_decode($cardRow['requirements'], true);
            $cooldown = json_decode($cardRow['cooldown'], true);
            $cards[] = [
                'id' => $cardRow['id'],
                'name' => $cardRow['name'],
                'type' => $cardRow['type'],
                'description' => $cardRow['description'],
                'colour' => $cardRow['colour'],
                'start_price' => $cardRow['start_price'],
                'start_income' => $cardRow['start_income'],
                'price_step' => $cardRow['price_step'],
                'income_step' => $cardRow['income_step'],
                'img' => $cardRow['img'],
                'max_level' => $cardRow['max_level'],
                'level' => $cardRow['user_level'], // Уровень пользователя для этой карточки, если есть
                'requirements' => $requirements,
                'cooldown' => $cooldown
            ];
        }

        $cardStmt->close();



        $tasks = [];
        $taskStmt = $conn->prepare("
            SELECT 
                t.id, 
                t.task_name, 
                t.description, 
                t.reward, 
                t.ton_reward, 
                t.link, 
                t.code_length,
                t.direct_link,
                t.image_url,
                t.task_type,
                t.cooldown,
                ut.status,
                ut.completed_at,
                IF(ut.completed_at IS NULL, 0, 1) AS completed,
                IF(ut.status = 'pending_reward', 1, 0) AS reward_pending
            FROM tasks t
            LEFT JOIN user_tasks ut ON t.id = ut.task_id AND ut.user_id = ?
            WHERE t.status = 'Active'
            AND (t.active_from IS NULL OR t.active_from <= NOW())
            AND (t.active_until IS NULL OR t.active_until >= NOW())
            ORDER BY ISNULL(t.priority) ASC, t.priority ASC, t.created_at DESC
        ");
        $taskStmt->bind_param("i", $telegram_id);
        
        if ($taskStmt->execute()) {
            $taskResult = $taskStmt->get_result();
            while ($taskRow = $taskResult->fetch_assoc()) {

                if ($taskRow['status'] === 'rejected') {
                    // Удаление записи из user_tasks
                    $deleteStmt = $conn->prepare("DELETE FROM user_tasks WHERE task_id = ? AND user_id = ?");
                    $deleteStmt->bind_param("ii", $taskRow['id'], $telegram_id);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                    
                    $taskRow['status'] = null;
                    $taskRow['completed'] = 0;
                    $taskRow['reward_pending'] = 0;
                }
                
                if ($taskRow['task_type'] === 'cooldown' && $taskRow['status'] === 'cooldown' && $taskRow['completed']) {
                    $completedAt = new DateTime($taskRow['completed_at']);
                    $currentDateTime = new DateTime();
                    $interval = $completedAt->diff($currentDateTime);
                    $minutesPassed = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
        
                    if ($minutesPassed >= $taskRow['cooldown']) {
                        // Начисляем награду пользователю, если cooldown завершен
                        $rewardAmount = $taskRow['reward'];
                        $tonReward = $taskRow['ton_reward'];
        
                        $rewardUpdateStmt = $conn->prepare("
                            UPDATE users SET balance = balance + ?, ton_balance = ton_balance + ? WHERE telegram_id = ?
                        ");
                        $rewardUpdateStmt->bind_param("ddi", $rewardAmount, $tonReward, $telegram_id);
                        $rewardUpdateStmt->execute();
                        $rewardUpdateStmt->close();
        
                        // Обновляем статус задания в БД на "approved"
                        $updateStmt = $conn->prepare("UPDATE user_tasks SET status = 'approved', completed_at = NOW() WHERE task_id = ? AND user_id = ?");
                        $updateStmt->bind_param("ii", $taskRow['id'], $telegram_id);
                        $updateStmt->execute();
                        $updateStmt->close();
        
                        // Обновляем состояние задачи в массиве задач
                        $taskRow['completed'] = 1;
                        $taskRow['reward_pending'] = 0;
        
                        // Обновляем баланс в массиве
                        $row['balance'] += $rewardAmount;
                        $row['ton_balance'] += $tonReward;
                    }
                }

                // Если награда в ожидании начисления, обработайте это состояние
                if ($taskRow['reward_pending']) {
                    $rewardAmount = $taskRow['reward'];
                    $tonReward = $taskRow['ton_reward'];
        
                    // Обновляем баланс пользователя в базе данных
                    $rewardUpdateStmt = $conn->prepare("
                        UPDATE users SET balance = balance + ?, ton_balance = ton_balance + ? WHERE telegram_id = ?
                    ");
                    $rewardUpdateStmt->bind_param("ddi", $rewardAmount, $tonReward, $telegram_id);
                    $rewardUpdateStmt->execute();
                    $rewardUpdateStmt->close();
        
                    // Обновляем статус задания в БД на "approved"
                    $updateStmt = $conn->prepare("UPDATE user_tasks SET status = 'approved', completed_at = NOW() WHERE task_id = ? AND user_id = ?");
                    $updateStmt->bind_param("ii", $taskRow['id'], $telegram_id);
                    $updateStmt->execute();
                    $updateStmt->close();
        
                    // Обновляем состояние задачи в массиве задач
                    $taskRow['completed'] = 1;
                    $taskRow['reward_pending'] = 0;
        
                    // Можно обновить и баланс в массиве, если потребуется позже использовать
                    $row['balance'] += $rewardAmount;
                    $row['ton_balance'] += $tonReward;
                }
        
                if ($taskRow['completed'] == 0 || $taskRow['id'] == 8) {
                    $tasks[] = $taskRow;
                }
            }
        }
        $taskStmt->close();

        $combo_date = new DateTime($row['combo_date']);
        $current_date = new DateTime();
        $moscowTime = new DateTime('now', new DateTimeZone('Europe/Moscow'));

        // Проверяем, если combo_date не совпадает с текущей датой или текущее время больше 18:00
        if ($combo_date->format('Y-m-d') !== $current_date->format('Y-m-d') || $moscowTime->format('H:i') >= '18:00') {
            // Если combo_date не совпадает с текущей датой, сбрасываем cards_purchased и обновляем combo_date
            $updateStmt = $conn->prepare("
                UPDATE daily_rewards 
                SET cards_purchased = NULL, combo_date = CURDATE() 
                WHERE telegram_id = ?
            ");
            $updateStmt->bind_param("i", $telegram_id);
            $updateStmt->execute();
            $updateStmt->close();

            // Обновляем данные в массиве
            $row['cards_purchased'] = null;
            
            // Если текущее время больше 18:00, обновляем combo_date на следующий день
            if ($moscowTime->format('H:i') >= '18:00') {
                $row['combo_date'] = $current_date->modify('+1 day')->format('Y-m-d');
            } else {
                $row['combo_date'] = $current_date->format('Y-m-d');
            }
        }
        
        // Логика для обработки данных о карточках
        if (is_null($row['cards_purchased'])) {
            // Если cards_purchased равно NULL, возвращаем [false, false, false]
            $cardsPurchased = [false, false, false];
        } else {
            // Если cards_purchased содержит JSON, преобразуем его в массив
            $cardsPurchasedJson = json_decode($row['cards_purchased'], true);

            // Получаем текущее время в Москве
            $moscowTime = new DateTime('now', new DateTimeZone('Europe/Moscow'));

            // Проверяем, что текущее время больше 18:00
            if ($moscowTime->format('H:i') >= '18:00') {
                // Если текущее время больше 18:00, выбираем комбо для следующего дня
                $comboStmt = $conn->prepare("SELECT card_1_id, card_2_id, card_3_id FROM daily_combos WHERE combo_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
            } else {
                // Если текущее время меньше 18:00, выбираем комбо для текущего дня
                $comboStmt = $conn->prepare("SELECT card_1_id, card_2_id, card_3_id FROM daily_combos WHERE combo_date = CURDATE()");
            }

            $comboStmt->execute();
            $comboStmt->bind_result($card_1_id, $card_2_id, $card_3_id);
            $comboStmt->fetch();
            $comboStmt->close();

            // Проверяем, куплены ли карточки, и если да — возвращаем их ID, иначе false
            $cardsPurchased = [
                isset($cardsPurchasedJson['card_1']) && $cardsPurchasedJson['card_1'] ? $card_1_id : false,
                isset($cardsPurchasedJson['card_2']) && $cardsPurchasedJson['card_2'] ? $card_2_id : false,
                isset($cardsPurchasedJson['card_3']) && $cardsPurchasedJson['card_3'] ? $card_3_id : false
            ];
        }





        // Возвращаем всю информацию в ответе
        echo json_encode([
            'success' => true,
            'balance' => $row['balance'],
            'league' => $row['league'],
            'level' => $row['level'],
            'spent_coin' => $row['spent_coin'], 
            'income_per_hour' => $row['income_per_hour'],
            'tapped_coins' => $row['tapped_coins'],
            'ton_balance' => $row['ton_balance'],
            'current_energy' => $row['current_energy'],
            'damage_level' => $row['damage_level'],
            'energy_level' => $row['energy_level'],
            'energy_recovery_level' => $row['energy_recovery_level'],
            'daily_full_energy_count' => $row['daily_full_energy_count'],
            'daily_turbo_count' => $row['daily_turbo_count'],
            'mining_bot_status' => $row['mining_bot_status'],
            'last_energy_update' => $row['last_energy_update'],
            'referral_code' => $referral_code,
            'friends' => $friends,
            'leagues' => $leagues,
            'players' => $players,
            'daily_reward_day' => intval($row['login_streak']), // Добавляем login_streak как daily_reward_day
            'last_login_date' => $row['last_login_date'], // Добавляем last_login_date
            'tasks' => $tasks,
            'cards' => $cards,
            'combo_reward' => $combo_reward,
            'cards_purchased' => $cardsPurchased,
            'server_time' => gmdate('Y-m-d H:i:s')
        ]);
    } else {
        error_log("User not found.");
        echo json_encode(['success' => false, 'error' => 'User not found']);
    }
    $stmt->close();
} else {
    error_log("Invalid parameters.");
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
error_log("Script completed.");
?>
