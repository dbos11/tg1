<?php
require_once 'config.php';

// Подключение к базе данных
$conn = db_connect();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => $conn->connect_error]));
}

function generateReferralCode($length = 8) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

if (isset($_GET['telegram_id'])) {
    $telegram_id = intval($_GET['telegram_id']);
    $invited_by_code = $_GET['invited_by_code'] ?? null;
    
    $stmt = $conn->prepare("SELECT referral_code FROM referrals WHERE telegram_id = ?");
    $stmt->bind_param("i", $telegram_id);
    $stmt->execute();
    $result = $stmt->get_result();
     
    if ($result->num_rows > 0) {
        $referral = $result->fetch_assoc();
        $referral_code = $referral['referral_code'];
    } else {
        $referral_code = generateReferralCode();
        $stmt = $conn->prepare("INSERT INTO referrals (telegram_id, referral_code, invited_by_code) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $telegram_id, $referral_code, $invited_by_code);
        $stmt->execute();

        // Update referral count for the inviter
        if ($invited_by_code) {
            $stmt = $conn->prepare("UPDATE referrals SET referral_count = referral_count + 1 WHERE referral_code = ?");
            $stmt->bind_param("s", $invited_by_code);
            $stmt->execute();
        }
    }
    
    echo json_encode(['success' => true, 'referral_code' => $referral_code]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}

// Закрытие соединения с базой данных
$conn->close();
?>
