<?php
// API Detail Endpoint Root Structure Check
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/base_practical_auto_manager.php';

echo '<pre>';
try {
    $manager = new BasePracticalAutoManager();
    $target_order_id = '630D93D6D9511DE5'; // User provided ID
    
    echo "Fetching detail for: {$target_order_id}\n";
    $response = $manager->getDataWithAutoAuth('read_orders', '/orders/detail/' . $target_order_id, []);
    
    echo "Top Level Keys:\n";
    if (is_array($response)) {
        print_r(array_keys($response));
        
        if (isset($response['order'])) {
            echo "\nIt seems response is wrapped in 'order' key.\n";
            echo "Keys inside 'order':\n";
            print_r(array_keys($response['order']));
        } else {
            echo "\nDirect Order Object? Checking 'unique_key' existence...\n";
            if (isset($response['unique_key'])) {
                echo "unique_key exists at root.\n";
            } else {
                echo "unique_key NOT found at root.\n";
            }
        }
    } else {
        echo "Response is not an array.\n";
        var_dump($response);
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
echo '</pre>';
