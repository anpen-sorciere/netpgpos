<?php
// CRON生存確認用スクリプト
// このファイルが実行されると、同階層の cron_alive.log に日時が追記されます。

$log_file = __DIR__ . '/cron_alive.log';
$now = date('Y-m-d H:i:s');
$message = "[{$now}] CRON execution confirmed.\n";

// ファイルに追記
if (file_put_contents($log_file, $message, FILE_APPEND | LOCK_EX) !== false) {
    echo "Log updated: {$now}";
} else {
    echo "Failed to write to {$log_file}";
}
?>
