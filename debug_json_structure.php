<?php
/**
 * BASE API JSONデータ構造確認スクリプト
 * 注文データの詳細な構造を表示して、キャスト名とニックネームの場所を特定
 */
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api/base_practical_auto_manager.php';

echo "<h1>BASE API JSONデータ構造確認</h1>";
echo "<p>注文データの詳細な構造を表示して、キャスト名とニックネームの場所を特定します</p>";

try {
    $practical_manager = new BasePracticalAutoManager();
    $combined_data = $practical_manager->getCombinedOrderData(1); // 1件のみ取得
    
    if (empty($combined_data['merged_orders'])) {
        echo "<div style='color: red;'>注文データが取得できませんでした</div>";
        exit;
    }
    
    $order = $combined_data['merged_orders'][0];
    
    echo "<h2>1. 注文データの基本構造</h2>";
    echo "<div style='background: #f0f0f0; padding: 15px; border: 1px solid #ccc; border-radius: 5px;'>";
    echo "<h3>利用可能なキー一覧</h3>";
    echo "<ul>";
    foreach (array_keys($order) as $key) {
        echo "<li><strong>{$key}</strong></li>";
    }
    echo "</ul>";
    echo "</div>";
    
    echo "<h2>2. 各キーの詳細データ</h2>";
    foreach ($order as $key => $value) {
        echo "<h3>{$key}</h3>";
        echo "<div style='background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 3px; margin-bottom: 10px;'>";
        
        if (is_array($value)) {
            echo "<strong>型:</strong> 配列 (要素数: " . count($value) . ")<br>";
            echo "<strong>内容:</strong><br>";
            echo "<pre style='background: white; padding: 10px; border: 1px solid #ccc; border-radius: 3px; overflow-x: auto;'>";
            echo htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "</pre>";
        } else {
            echo "<strong>型:</strong> " . gettype($value) . "<br>";
            echo "<strong>値:</strong> " . htmlspecialchars($value) . "<br>";
        }
        
        echo "</div>";
    }
    
    echo "<h2>3. 商品情報の詳細構造</h2>";
    if (isset($order['order_items']) && is_array($order['order_items'])) {
        foreach ($order['order_items'] as $index => $item) {
            echo "<h3>商品 " . ($index + 1) . "</h3>";
            echo "<div style='background: #e8f4fd; padding: 10px; border: 1px solid #b3d9ff; border-radius: 3px; margin-bottom: 10px;'>";
            
            if (isset($item['item_detail']) && is_array($item['item_detail'])) {
                echo "<h4>商品詳細情報</h4>";
                echo "<ul>";
                foreach ($item['item_detail'] as $detail_key => $detail_value) {
                    echo "<li><strong>{$detail_key}:</strong> ";
                    if (is_array($detail_value)) {
                        echo "配列 (" . count($detail_value) . "要素)";
                        if ($detail_key === 'variations' && !empty($detail_value)) {
                            echo "<br><strong>バリエーション詳細:</strong><br>";
                            foreach ($detail_value as $var_index => $variation) {
                                echo "&nbsp;&nbsp;バリエーション " . ($var_index + 1) . ": ";
                                if (is_array($variation)) {
                                    echo "<br>";
                                    foreach ($variation as $var_key => $var_value) {
                                        echo "&nbsp;&nbsp;&nbsp;&nbsp;<strong>{$var_key}:</strong> " . htmlspecialchars($var_value) . "<br>";
                                    }
                                } else {
                                    echo htmlspecialchars($variation) . "<br>";
                                }
                            }
                        }
                    } else {
                        echo htmlspecialchars($detail_value);
                    }
                    echo "</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>商品詳細情報がありません</p>";
            }
            
            echo "</div>";
        }
    } else {
        echo "<p>商品情報がありません</p>";
    }
    
    echo "<h2>4. 全データのJSON表示</h2>";
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
    echo "<h3>完全なJSONデータ</h3>";
    echo "<pre style='background: white; padding: 15px; border: 1px solid #ccc; border-radius: 3px; overflow-x: auto; max-height: 500px;'>";
    echo htmlspecialchars(json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "</pre>";
    echo "</div>";
    
    echo "<h2>5. 次のステップ</h2>";
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<p>上記のデータ構造を確認して、以下を特定してください：</p>";
    echo "<ul>";
    echo "<li><strong>キャスト名</strong>がどのキーに含まれているか</li>";
    echo "<li><strong>お客様のニックネーム</strong>がどのキーに含まれているか</li>";
    echo "<li>商品のオプション情報（variations）に含まれているか</li>";
    echo "</ul>";
    echo "<p>特定できましたら、その情報を教えてください。表示機能を実装します。</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h3>エラーが発生しました</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
