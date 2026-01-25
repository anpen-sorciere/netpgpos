<?php
require_once '../../../common/config.php';
require_once '../../../common/dbconnect.php';

try {
    $pdo = connect();
    echo "<h1>Repair Cast Handled Status</h1>";
    
    // Check count first
    $checkSql = "
        SELECT Count(*) as cnt
        FROM base_order_items
        WHERE cast_id IS NOT NULL 
        AND cast_id > 0 
        AND cast_handled = 2
    ";
    $count = $pdo->query($checkSql)->fetchColumn();
    echo "<p>Found <strong>{$count}</strong> items incorrectly marked as handled (2) despite having a cast assigned.</p>";

    if ($count > 0) {
        $updateSql = "
            UPDATE base_order_items
            SET cast_handled = 0
            WHERE cast_id IS NOT NULL 
            AND cast_id > 0 
            AND cast_handled = 2
        ";
        $stmt = $pdo->prepare($updateSql);
        $stmt->execute();
        $updated = $stmt->rowCount();
        echo "<p style='color:green; font-weight:bold;'>Successfully repaired {$updated} items. They should now appear on the dashboard.</p>";
    } else {
        echo "<p>No repair needed.</p>";
    }
    
    echo "<a href='diagnose_cast_handled.php'>Check Diagnostic Again</a>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
