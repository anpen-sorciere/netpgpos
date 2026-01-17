<?php
// 管理者用ツールメニュー（サブメニュー）
session_start();
$utype = $_SESSION['utype'] ?? 1024;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BASE関連ツール・管理メニュー</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; padding: 20px; }
        .card { margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: none; }
        .card-header { font-weight: bold; background-color: #343a40; color: white; }
        .btn-menu { width: 100%; margin-bottom: 10px; text-align: left; padding: 15px; font-weight: bold; }
        .btn-menu i { margin-right: 10px; width: 25px; text-align: center; }
        h1 { margin-bottom: 30px; border-bottom: 2px solid #343a40; padding-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-tools"></i> BASE関連ツール・管理メニュー</h1>
            <a href="../index.php?utype=<?= htmlspecialchars($utype) ?>" class="btn btn-outline-dark"><i class="fas fa-home"></i> メインメニューへ戻る</a>
        </div>

        <div class="row">
            <!-- 運用管理系 -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><i class="fas fa-users-cog"></i> 運用・管理機能</div>
                    <div class="card-body">
                        <a href="cast/admin_cast_manager_v2.php" class="btn btn-primary btn-menu">
                            <i class="fas fa-sitemap"></i> キャストポータル運用管理
                            <div class="small fw-normal ms-4">キャストへの商品紐付けルール設定など</div>
                        </a>
                        <a href="cast/admin_cast_progress.php" class="btn btn-block btn-success btn-menu" style="background-color: #fd7e14; border-color: #fd7e14; color:white;">
                            <i class="fas fa-tasks"></i> キャスト進捗モニター
                            <div class="small fw-normal ms-4">未対応件数確認・承認タスク実行</div>
                        </a>
                        <a href="admin/message_template_manager.php" class="btn btn-info text-white btn-menu">
                            <i class="fas fa-envelope-open-text"></i> 定型文管理
                            <div class="small fw-normal ms-4">承認時の送信メッセージ編集</div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- デバッグ・修復ツール系 -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger"><i class="fas fa-bug"></i> 修復・診断ツール (Advanced)</div>
                    <div class="card-body">
                        <p class="text-danger small mb-3"><i class="fas fa-exclamation-triangle"></i> 取扱注意：データの書き換えを行うツールが含まれます。</p>
                        
                        <a href="debug/login_as_cast.php" class="btn btn-outline-dark btn-menu">
                            <i class="fas fa-user-secret"></i> キャストなりすましログイン
                            <div class="small fw-normal text-muted ms-4">指定キャストの視点でダッシュボードを確認</div>
                        </a>

                        <div class="row g-2">
                            <div class="col-6">
                                <a href="debug/diagnose_cast_dashboard.php" class="btn btn-outline-secondary btn-sm w-100 text-start mb-2">
                                    <i class="fas fa-stethoscope"></i> ダッシュボード表示診断
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="debug/repair_japanese_status.php" class="btn btn-outline-danger btn-sm w-100 text-start mb-2">
                                    <i class="fas fa-band-aid"></i> 日本語ステータス修復
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="debug/sync_status_tool.php" class="btn btn-outline-warning btn-sm w-100 text-start mb-2 text-dark">
                                    <i class="fas fa-sync"></i> ステータス同期ツール
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="debug/resync_order.php" class="btn btn-outline-danger btn-sm w-100 text-start mb-2">
                                    <i class="fas fa-sync-alt"></i> 強制再同期 (API取得)
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
