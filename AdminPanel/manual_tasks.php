<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin.php');
    exit();
}

// Подключение к базе данных
$conn = db_connect();

// Обработка запросов на изменение статуса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taskId = $_POST['task_id'];
    $newStatus = $_POST['new_status'];

    $stmt = $conn->prepare("UPDATE user_tasks SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $newStatus, $taskId);

    if ($stmt->execute()) {
        echo "Status updated successfully!";
    } else {
        echo "Error updating status: " . $stmt->error;
    }

    $stmt->close();
    header('Location: manual_tasks.php'); // Обновляем страницу после изменения статуса
    exit();
}

// SQL-запрос для получения данных о заданиях типа 'code_manual'
$sql = "
    SELECT 
        ut.id AS user_task_id, 
        u.username AS user_name, 
        t.task_name, 
        ut.completed_at, 
        ut.status, 
        ut.input_code 
    FROM 
        user_tasks ut
    JOIN 
        users u ON ut.user_id = u.telegram_id
    JOIN 
        tasks t ON ut.task_id = t.id
    WHERE 
        t.task_type = 'code_manual' 
    AND 
        (ut.status = 'pending' OR ut.status IS NULL)
";

$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Code Tasks Moderation</title>
    <style>
        body {
            display: flex;
            margin: 0;
            font-family: Arial, sans-serif;
        }
        .sidebar {
            width: 250px;
            background-color: #f4f4f4;
            padding: 15px;
            box-shadow: 2px 0px 5px rgba(0, 0, 0, 0.1);
        }
        .content {
            flex-grow: 1;
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .btn-approve {
            background-color: green;
            color: white;
            padding: 5px 10px;
            border: none;
            cursor: pointer;
        }
        .btn-reject {
            background-color: red;
            color: white;
            padding: 5px 10px;
            border: none;
            cursor: pointer;
        }
    </style>
</head>
<body>

    <!-- Включаем sidebar -->
    <div class="sidebar">
        <?php include 'sidebar.php'; ?>
    </div>

    <!-- Основной контент -->
    <div class="content">
        <h2 style="margin-left: 20px;">Manual Code Tasks Moderation</h2>


        <table>
            <thead>
                <tr>
                    <th>Task ID</th>
                    <th>Username</th>
                    <th>Task Name</th>
                    <th>Completed At</th>
                    <th>Status</th>
                    <th>Input Code</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['user_task_id']); ?></td>
                            <td><?= htmlspecialchars($row['user_name']); ?></td>
                            <td><?= htmlspecialchars($row['task_name']); ?></td>
                            <td><?= htmlspecialchars($row['completed_at']); ?></td>
                            <td><?= htmlspecialchars($row['status']); ?></td>
                            <td><?= htmlspecialchars($row['input_code']); ?></td>
                            <td>
                                <!-- Форма для изменения статуса на pending_reward -->
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="task_id" value="<?= $row['user_task_id']; ?>">
                                    <input type="hidden" name="new_status" value="pending_reward">
                                    <button type="submit" class="btn-approve">✔</button>
                                </form>
                                <!-- Форма для изменения статуса на rejected -->
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="task_id" value="<?= $row['user_task_id']; ?>">
                                    <input type="hidden" name="new_status" value="rejected">
                                    <button type="submit" class="btn-reject">✘</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">No pending manual code tasks found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>

<?php
$conn->close();
?>
