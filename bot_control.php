<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newStatus = ($_POST['bot_status'] ?? '') === "on" ? 1 : 0;
    $stmt = $conn->prepare("UPDATE bot_control SET is_active = ? WHERE id = 1");
    $stmt->bind_param("i", $newStatus);
    $stmt->execute();
    $stmt->close();
}

$res = $conn->query("SELECT is_active FROM bot_control WHERE id = 1");
$row = $res->fetch_assoc();
$currentStatus = $row['is_active'] ? "on" : "off";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bot Control Panel</title>
    <style>
        body {
            background-color: #0b0c10;
            color: #f5c518;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 40px;
            text-align: center;
        }

        h1 {
            margin-bottom: 30px;
            font-size: 32px;
            color: #f5c518;
        }

        form {
            display: inline-block;
            background-color: #1a1a1a;
            padding: 25px 35px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(245, 197, 24, 0.3);
        }

        label {
            font-size: 18px;
            margin-right: 15px;
        }

        select {
            padding: 10px 15px;
            font-size: 16px;
            border-radius: 5px;
            border: none;
            background-color: #333;
            color: #f5c518;
        }

        button {
            margin-left: 15px;
            padding: 10px 22px;
            background-color: #f5c518;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            color: #0b0c10;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #e6b800;
        }

        .status {
            margin-top: 25px;
            font-size: 20px;
        }

        .status span {
            font-weight: bold;
            color: #00ff99;
        }

        .status span.off {
            color: #ff4c4c;
        }

        a.back-link {
            display: inline-block;
            margin-top: 40px;
            text-decoration: none;
            font-size: 16px;
            color: #f5c518;
            transition: color 0.3s;
        }

        a.back-link:hover {
            color: #fff;
        }
    </style>
</head>
<body>

    <h1>SMA Bot Control Panel</h1>

    <form method="POST">
        <label for="bot_status">Bot Status:</label>
        <select name="bot_status" id="bot_status">
            <option value="on" <?= $currentStatus === "on" ? "selected" : "" ?>>ON</option>
            <option value="off" <?= $currentStatus === "off" ? "selected" : "" ?>>OFF</option>
        </select>
        <button type="submit">Update</button>
    </form>

    <div class="status">
        Current Status: <span class="<?= $currentStatus === 'off' ? 'off' : '' ?>"><?= strtoupper($currentStatus) ?></span>
    </div>

    <a href="dashboard.php" class="back-link">⬅ Back to Dashboard</a>

</body>
</html>
