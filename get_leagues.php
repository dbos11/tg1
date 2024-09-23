<?php
require_once 'config.php';

// Подключение к базе данных
$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

if (isset($_GET['telegram_id'])) {
    $telegram_id = intval($_GET['telegram_id']);

    // Fetch user balance and current league
    $stmt = $conn->prepare("SELECT balance, league FROM users WHERE telegram_id = ?");
    if ($stmt === false) {
        die(json_encode(['success' => false, 'error' => 'Prepare statement failed: ' . $conn->error]));
    }
    $stmt->bind_param("i", $telegram_id);
    $stmt->execute();
    $stmt->bind_result($balance, $currentLeague);
    $stmt->fetch();
    $stmt->close();

    // Проверка на случай, если пользователь не найден
    if (!isset($balance) || !isset($currentLeague)) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }

    // Fetch leagues and players
    $leagues = ["Bronze", "Silver", "Gold", "Platinum", "Diamond"];
    $players = [];
    
    foreach ($leagues as $league) {
        $stmt = $conn->prepare("SELECT telegram_id, full_name, balance, profile_img_url FROM users WHERE league = ? ORDER BY balance DESC LIMIT 100");
        if ($stmt === false) {
            die(json_encode(['success' => false, 'error' => 'Prepare statement failed: ' . $conn->error]));
        }
        $stmt->bind_param("s", $league);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $players[$league] = [];
        while ($row = $result->fetch_assoc()) {
            $players[$league][] = $row;
        }
        $stmt->close();
    }

    echo json_encode(['success' => true, 'balance' => $balance, 'currentLeague' => $currentLeague, 'leagues' => $leagues, 'players' => $players]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

$conn->close();
?>
