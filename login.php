<?php
require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');
require_once(__DIR__ . '/../common/functions.php');
// ログイン処理
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = htmlspecialchars($_POST['username'], ENT_QUOTES);
  $password = htmlspecialchars($_POST['password'], ENT_QUOTES);

	$user_data = user_get($username); 

  if ($password == $user_data['password']) {
    $_SESSION['user_id'] = $row['user_id'];
    $_SESSION['user_type'] = $row['type'];		//1:admin 2:user
    header('Location: index.php');
    exit();
  } else {
    echo "<script>alert('ログイン失敗');</script>";
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>ログイン</title>
</head>
<body>
  <h1>ログイン</h1>
  <form method="POST">
    <label for="username">ユーザー名:</label>
    <input type="text" name="username" required>
    <br>
    <label for="password">パスワード:</label>
    <input type="password" name="password" required>
    <br>
    <button type="submit">ログイン</button>
  </form>
</body>
</html>
