@echo off
chcp 65001
echo ========================================
echo NetPGPOS デプロイスクリプト
echo ========================================
echo.

echo 1. XAMPPからdevelopフォルダへのコピーを開始...
echo.

REM XAMPPフォルダからdevelopフォルダへのコピー
robocopy "C:\xampp\htdocs\netpgpos" "C:\develop\netpgpos" /E /XD .git /XF *.bat /R:3 /W:1

if %ERRORLEVEL% LEQ 1 (
    echo コピー完了: 成功
) else (
    echo コピー完了: 警告またはエラー (エラーコード: %ERRORLEVEL%)
)

echo.
echo 2. Gitステータス確認...
cd /d "C:\develop\netpgpos"
git status

echo.
echo 3. 変更をコミットしますか？ (Y/N)
set /p choice="選択してください: "

if /i "%choice%"=="Y" (
    echo.
    echo Gitコミットを実行...
    git add .
    git commit -m "Update: %date% %time%"
    echo コミット完了
) else (
    echo コミットをスキップしました
)

echo.
echo ========================================
echo デプロイ準備完了
echo ========================================
echo.
echo 次の手順:
echo 1. FTPでC:\develop\netpgposの内容をアップロード
echo 2. 本番環境で動作確認
echo.
pause
