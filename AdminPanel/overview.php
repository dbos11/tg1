<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin.php');
    exit();
}

$conn = db_connect();

// Получение общего количества игроков
$total_players_query = "SELECT COUNT(*) AS total_players FROM users";
$total_players_result = $conn->query($total_players_query);
$total_players = $total_players_result->fetch_assoc()['total_players'];

// Получение количества новых игроков за сегодня
$new_players_query = "SELECT COUNT(*) AS new_players_today FROM users WHERE DATE(created_at) = CURDATE()";
$new_players_result = $conn->query($new_players_query);
$new_players_today = $new_players_result->fetch_assoc()['new_players_today'];

// Получение общего количества выполненных заданий
$total_tasks_query = "SELECT COUNT(*) AS total_tasks FROM user_tasks";
$total_tasks_result = $conn->query($total_tasks_query);
$total_tasks = $total_tasks_result->fetch_assoc()['total_tasks'];

// Получение количества выполненных заданий за сегодня
$tasks_today_query = "SELECT COUNT(*) AS tasks_today FROM user_tasks WHERE DATE(completed_at) = CURDATE()";
$tasks_today_result = $conn->query($tasks_today_query);
$tasks_today = $tasks_today_result->fetch_assoc()['tasks_today'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overview</title>
    <style>
        body {
            display: flex;
            margin: 0;
            font-family: Arial, sans-serif;
        }

        .block {
            background-color: #f1f1f1;
            padding: 20px;
            margin: 10px 0;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .block h3 {
            margin: 0;
            font-size: 1.5em;
        }

        .block p {
            margin: 10px 0 0;
            font-size: 1.2em;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="content">
        <h2>Overview</h2>
        <div class="block">
            <h3>Total Players</h3>
            <p><?= $total_players ?></p>
        </div>
        <div class="block">
            <h3>New Players Today</h3>
            <p><?= $new_players_today ?></p>
        </div>
        <div class="block">
            <h3>Total Tasks Completed</h3>
            <p><?= $total_tasks ?></p>
        </div>
        <div class="block">
            <h3>Tasks Completed Today</h3>
            <p><?= $tasks_today ?></p>
        </div>
    </div>
</body>
</html>
