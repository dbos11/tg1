<?php
require_once 'config.php';

// Подключение к базе данных
$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

if (isset($_GET['telegram_id'])) {
    $telegram_id = intval($_GET['telegram_id']);
    
    $stmt = $conn->prepare("
        SELECT u.full_name, u.profile_img_url, u.league
        FROM referrals r
        JOIN users u ON r.telegram_id = u.telegram_id
        WHERE r.invited_by_code = (
            SELECT referral_code FROM referrals WHERE telegram_id = ?
        )
    ");
    $stmt->bind_param("i", $telegram_id);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $friends = [];
        while ($row = $result->fetch_assoc()) {
            $friends[] = $row;
        }
        echo json_encode(['success' => true, 'friends' => $friends]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

// Закрытие соединения с базой данных
$conn->close();
?>
