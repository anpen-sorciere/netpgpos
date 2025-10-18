<?php
// 動作するファイルの簡略版
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>動作テスト</h1>";
echo "<p>現在時刻: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>テスト完了</p>";
?>
