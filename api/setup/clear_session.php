<?php
session_start();

// セッションをクリア
session_destroy();
session_start();

echo "<h1>セッションクリア完了</h1>";
echo "<p>すべての認証情報がクリアされました。</p>";
echo "<a href='scope_manager.php'>スコープ管理に戻る</a>";
?>
