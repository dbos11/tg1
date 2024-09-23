<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin.php');
    exit();
}

// Подключение к базе данных
$conn = db_connect();

// Обработка формы для создания нового задания
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    $task_name = $_POST['task_name'];
    $description = $_POST['description'];
    $reward = $_POST['reward'];
    $ton_reward = $_POST['ton_reward'];
    $link = $_POST['link'];
    $image_url = $_POST['image_url'];
    $direct_link = $_POST['direct_link'];
    $task_type = $_POST['task_type'];
    $validation_code = $_POST['validation_code'];
    $status = $_POST['status'];
    $cooldown = $_POST['cooldown'];
    
    $stmt = $conn->prepare("INSERT INTO tasks (task_name, description, reward, ton_reward, link, image_url, direct_link, task_type, validation_code, status, cooldown) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssissssssis', $task_name, $description, $reward, $ton_reward, $link, $image_url, $direct_link, $task_type, $validation_code, $status, $cooldown);

    if ($stmt->execute()) {
        echo "New task created successfully!";
    } else {
        echo "Error creating task: " . $stmt->error;
    }

    $stmt->close();
    header('Location: task_settings.php'); // Перезагрузка страницы после создания задания
    exit();
}


// SQL-запрос для получения информации о заданиях и количестве выполнений
$sql = "
    SELECT 
        t.id AS task_id, 
        t.task_name, 
        t.description, 
        t.reward, 
        t.ton_reward, 
        t.link, 
        t.image_url, 
        t.direct_link, 
        t.task_type, 
        t.validation_code, 
        t.status, 
        t.cooldown, 
        COUNT(ut.id) AS times_completed
    FROM 
        tasks t
    LEFT JOIN 
        user_tasks ut ON t.id = ut.task_id
    GROUP BY 
        t.id
";

$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks Settings</title>
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
            margin-bottom: 20px;
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
        .form-group {
            margin-bottom: 10px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="text"], input[type="number"], textarea, select {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        input[type="submit"] {
            padding: 10px 15px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #218838;
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
        <h2 style="margin-left: 20px;">Tasks Settings</h2>

        <!-- Таблица с заданиями -->
        <table style="margin-left: 20px;">
            <thead>
                <tr>
                    <th>Task ID</th>
                    <th>Task Name</th>
                    <th>Description</th>
                    <th>Reward</th>
                    <th>TON Reward</th>
                    <th>Link</th>
                    <th>Image URL</th>
                    <th>Direct Link</th>
                    <th>Task Type</th>
                    <th>Validation Code</th>
                    <th>Status</th>
                    <th>Cooldown</th>
                    <th>Times Completed</th>
                </tr>
            </thead>
            <tbody >
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['task_id']); ?></td>
                            <td><?= htmlspecialchars($row['task_name']); ?></td>
                            <td><?= htmlspecialchars($row['description']); ?></td>
                            <td><?= htmlspecialchars($row['reward']); ?></td>
                            <td><?= htmlspecialchars($row['ton_reward']); ?></td>
                            <td><a href="<?= htmlspecialchars($row['link']); ?>" target="_blank">Go to Link</a></td>
                            <td><a href="<?= htmlspecialchars($row['image_url']); ?>" target="_blank"><?= htmlspecialchars($row['image_url']); ?></a></td>
                            <td><a href="<?= htmlspecialchars($row['direct_link']); ?>" target="_blank"><?= htmlspecialchars($row['direct_link']); ?></a></td>
                            <td><?= htmlspecialchars($row['task_type']); ?></td>
                            <td><?= htmlspecialchars($row['validation_code']); ?></td>
                            <td><?= htmlspecialchars($row['status']); ?></td>
                            <td><?= htmlspecialchars($row['cooldown']); ?></td>
                            <td><?= htmlspecialchars($row['times_completed']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="13">No tasks found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>


        <!-- Форма создания нового задания -->
        <h3 style="margin-left: 20px;">Create New Task</h3>
        <form method="POST" style="margin-left: 20px;">
            <div class="form-group">
                <label for="task_name">Task Name</label>
                <input type="text" id="task_name" name="task_name" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"></textarea>
            </div>
            <div class="form-group">
                <label for="reward">Reward</label>
                <input type="number" id="reward" name="reward" required>
            </div>
            <div class="form-group">
                <label for="ton_reward">TON Reward</label>
                <input type="number" step="0.001" id="ton_reward" name="ton_reward">
            </div>
            <div class="form-group">
                <label for="link">Link</label>
                <input type="text" id="link" name="link" required>
            </div>
            <div class="form-group">
                <label for="image_url">Image URL</label>
                <input type="text" id="image_url" name="image_url">
            </div>
            <div class="form-group">
                <label for="direct_link">Direct Link</label>
                <input type="text" id="direct_link" name="direct_link">
            </div>
            <div class="form-group">
                <label for="task_type">Task Type</label>
                <select id="task_type" name="task_type" required>
                    <option value="subscribe">Subscribe</option>
                    <option value="code_auto">Code Auto</option>
                    <option value="code_manual">Code Manual</option>
                    <option value="cooldown">Cooldown</option>
                </select>
            </div>
            <div class="form-group">
                <label for="validation_code">Validation Code</label>
                <input type="text" id="validation_code" name="validation_code">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <option value="Active">Active</option>
                    <option value="Unactive">Unactive</option>
                </select>
            </div>
            <div class="form-group">
                <label for="cooldown">Cooldown</label>
                <input type="number" id="cooldown" name="cooldown">
            </div>
        
            <input type="submit" name="create_task" value="Create Task">
        </form>


    </div>

</body>
</html>

<?php
$conn->close();
?>
