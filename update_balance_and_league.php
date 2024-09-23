<?php
require_once 'config.php';

// Подключение к базе данных
$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

function updateBalanceAndLeague($conn, $telegram_id, $new_balance, $energy_used) {
    $leagues = [
        'Bronze' => 0,
        'Silver' => 10,
        'Gold' => 50,
        'Platinum' => 100,
        'Diamond' => 200
    ];

    $stmt = $conn->prepare("SELECT balance, league, current_energy, energy_level FROM users JOIN boosts ON users.telegram_id = boosts.telegram_id WHERE users.telegram_id = ?");
    $stmt->bind_param("i", $telegram_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $current_league = $user['league'];
        $current_energy = $user['current_energy'];
        $energy_level = $user['energy_level'];
        $max_energy = 100 + 10 * ($energy_level - 1);

        $new_energy = max(0, $current_energy - $energy_used);
        if ($new_energy > $max_energy) {
            $new_energy = $max_energy;
        }

        $new_league = $current_league; // По умолчанию лига остается неизменной

        foreach ($leagues as $league => $min_balance) {
            if ($new_balance >= $min_balance) {
                $new_league = $league;
            } else {
                break; // Прерываем цикл, как только найдена текущая лига
            }
        }

        // Обновляем лигу только если новая лига выше или равна текущей
        if (array_search($new_league, array_keys($leagues)) >= array_search($current_league, array_keys($leagues))) {
            $stmt = $conn->prepare("UPDATE users SET balance = ?, league = ? WHERE telegram_id = ?");
            $stmt->bind_param("isi", $new_balance, $new_league, $telegram_id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("UPDATE users SET balance = ? WHERE telegram_id = ?");
            $stmt->bind_param("ii", $new_balance, $telegram_id);
            $stmt->execute();
        }

        $stmt = $conn->prepare("UPDATE boosts SET current_energy = ?, last_energy_update = NOW() WHERE telegram_id = ?");
        $stmt->bind_param("ii", $new_energy, $telegram_id);
        $stmt->execute();

        $stmt = $conn->prepare("SELECT invited_by_code FROM referrals WHERE telegram_id = ?");
        $stmt->bind_param("i", $telegram_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $referral = $result->fetch_assoc();
            $invited_by_code = $referral['invited_by_code'];

            if ($invited_by_code) {
                $reward = 0;
                switch ($new_league) {
                    case 'Silver':
                        $reward = 10;
                        break;
                    case 'Gold':
                        $reward = 20;
                        break;
                    case 'Platinum':
                        $reward = 30;
                        break;
                    case 'Diamond':
                        $reward = 40;
                        break;
                }

                if ($reward > 0) {
                    $stmt = $conn->prepare("SELECT telegram_id FROM referrals WHERE referral_code = ?");
                    $stmt->bind_param("s", $invited_by_code);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $inviter = $result->fetch_assoc();
                        $inviter_telegram_id = $inviter['telegram_id'];

                        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE telegram_id = ?");
                        $stmt->bind_param("ii", $reward, $inviter_telegram_id);
                        $stmt->execute();
                    }
                }
            }
        }
    }
}

if (isset($_GET['telegram_id']) && isset($_GET['balance']) && isset($_GET['energy_used'])) {
    $telegram_id = intval($_GET['telegram_id']);
    $new_balance = intval($_GET['balance']);
    $energy_used = intval($_GET['energy_used']);
    updateBalanceAndLeague($conn, $telegram_id, $new_balance, $energy_used);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
?>
