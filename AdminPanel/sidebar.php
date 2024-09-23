<?php
// sidebar.php
?>

<div class="sidebar">
    <div>
        <a href="overview.php">Overview</a>
        <a href="task_settings.php">Task Settings</a>
        <a href="manual_tasks.php">Tasks Moderation</a>
    </div>
    <a href="logout.php" style="margin-top: auto;">Logout</a>
</div>

<style>
.sidebar {
    height: 100vh;
    width: 250px;
    background-color: #333;
    color: white;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    position: fixed;
    top: 0;
    left: 0;
    padding-top: 0;
    z-index: 1000;
}

.sidebar a {
    padding: 15px;
    text-decoration: none;
    color: white;
    display: block;
    text-align: center;
}

.sidebar a:hover {
    background-color: #575757;
}

.content {
    margin-left: 250px; /* это соответствует ширине боковой панели */
    padding: 20px;
    width: calc(100% - 250px); /* ширина контента с учетом ширины боковой панели */
}
</style>
