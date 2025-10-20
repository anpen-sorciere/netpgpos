<?php
// 最小到達確認（500切り分け）
error_reporting(E_ALL);
ini_set('display_errors', 1);

$logBase = __DIR__ . '/../logs';
@mkdir($logBase, 0775, true);
$probe = $logBase . '/probe_cb.txt';
@file_put_contents($probe, '['.date('c')."] reached\n", FILE_APPEND);

header('Content-Type: text/plain; charset=UTF-8');
echo "OK - base_callback_debug.php reached\n";
echo "Probe: " . (file_exists($probe) ? realpath($probe) : '(not created)') . "\n";
echo "GET: " . json_encode($_GET, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n";
exit;