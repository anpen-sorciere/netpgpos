<?php
$host = "mysql748.db.sakura.ne.jp";    // ホスト名
$user = "purplelion51";      // ユーザー名
$password = "-6r_am73";  // パスワード
$dbname = "purplelion51_sorciere";    // データベース名


try {
  $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $password);
  echo "接続に成功しました";
} catch (PDOException $e) {
  echo "接続に失敗しました: " . $e->getMessage();
}
?>
