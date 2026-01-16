# テストモード機能 - 使い方

## セットアップ

### STEP 1: cast_complete_order.phpをアップロード

修正版をアップロードしてください：
```
c:\xampp\htdocs\netpgpos\api\ajax\cast_complete_order.php
→ /home/purplelion51/www/netpgpos/api/ajax/cast_complete_order.php
```

## 使い方

### テストモードで実行

キャストダッシュボードのブラウザのURLを以下に変更：

```
https://purplelion51.sakura.ne.jp/netpgpos/api/cast/cast_dashboard.php?test_mode=1
```

URLに `?test_mode=1` を追加するだけで、テストモードになります。

### テストモードの動作

1. **「完了」ボタンをクリック**
2. **定型文を選択**
3. **処理実行**
   - ✅ 定型文の取得
   - ✅ 変数置換
   - ✅ BASE APIには送信**しない**
   - ✅ 送信される予定の内容を表示

### 確認できること

- 定型文が正しく選択できるか
- 変数（{customer_name}等）が正しく置換されるか
- BASE APIに送信される予定の内容
- エラーハンドリング

### 本番実行

通常のURLでアクセス：
```
https://purplelion51.sakura.ne.jp/netpgpos/api/cast/cast_dashboard.php
```

`?test_mode=1` なしで実行すると、実際にBASE APIが叩かれます。

---

これで実注文データを使って、安全にテストできます！
