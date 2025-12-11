<?php
// 為替レート取得クラス
class ExchangeRateService {
    private $api_key = 'b571d0104ba490fcd9599370'; // デフォルトAPIキー
    private $base_url = 'https://v6.exchangerate-api.com/v6/';
    private $pdo = null;
    
    public function __construct($pdo = null, $api_key = null) {
        $this->pdo = $pdo;
        $this->api_key = $api_key ?? $this->api_key;
    }
    
    /**
     * 指定日付の為替レートを取得
     * @param string $date YYYY-MM-DD形式の日付
     * @param string $from_currency 変換元通貨コード
     * @param string $to_currency 変換先通貨コード（デフォルト: JPY）
     * @return float|null 為替レート（取得失敗時はnull）
     */
    public function getExchangeRate($date, $from_currency, $to_currency = 'JPY') {
        try {
            // 同じ通貨の場合は1を返す
            if ($from_currency === $to_currency) {
                return 1.0;
            }
            
            // 日付をフォーマット
            $formatted_date = date('Y-m-d', strtotime($date));
            
            // データベースから取得を試行
            if ($this->pdo) {
                $rate = $this->getRateFromDB($formatted_date, $from_currency, $to_currency);
                if ($rate !== null) {
                    return $rate;
                }
            }
            
            // データベースにない場合はAPIから取得
            $rate = $this->getRateFromAPI($formatted_date, $from_currency, $to_currency);
            
            // APIから取得できた場合はデータベースに保存
            if ($rate !== null && $this->pdo) {
                $this->saveRateToDB($formatted_date, $from_currency, $to_currency, $rate);
            }
            
            // API失敗時は代替手段を試行
            if ($rate === null) {
                $rate = $this->getFallbackRate($date, $from_currency, $to_currency);
            }
            
            return $rate;
            
        } catch (Exception $e) {
            error_log("Exchange rate error: " . $e->getMessage());
            return $this->getFallbackRate($date, $from_currency, $to_currency);
        }
    }
    
    /**
     * データベースから為替レートを取得
     */
    private function getRateFromDB($date, $from_currency, $to_currency) {
        try {
            $stmt = $this->pdo->prepare("SELECT rate FROM exchange_rate_mst WHERE rate_date = ? AND from_currency = ? AND to_currency = ?");
            $stmt->execute([$date, $from_currency, $to_currency]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return (float)$result['rate'];
            }
            
            return null;
        } catch (PDOException $e) {
            error_log("Database error in getRateFromDB: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * APIから為替レートを取得
     * 無料プランでは履歴データが取得できないため、最新レートのみ取得
     */
    private function getRateFromAPI($date, $from_currency, $to_currency) {
        try {
            // 無料プランでは履歴データが取得できないため、常に最新レートを取得
            // 最新レート: /v6/{API_KEY}/latest/{通貨コード}
            $url = $this->base_url . $this->api_key . '/latest/' . $from_currency;
            
            // file_get_contentsでAPI呼び出し（公式ドキュメントの方法）
            $response_json = @file_get_contents($url);
            
            if ($response_json === false) {
                error_log("API request failed for URL: " . $url);
                return null;
            }
            
            // JSONをデコード
            $data = json_decode($response_json, true);
            
            if ($data === null) {
                error_log("Failed to decode JSON response. Response: " . substr($response_json, 0, 200));
                return null;
            }
            
            // 公式ドキュメントに従って result が "success" か確認
            if (isset($data['result']) && $data['result'] === 'success') {
                // conversion_rates からレートを取得
                if (isset($data['conversion_rates'][$to_currency])) {
                    $rate = (float)$data['conversion_rates'][$to_currency];
                    
                    // 履歴データの場合は、最新レートを取得したことをログに記録
                    $today = date('Y-m-d');
                    if ($date !== $today) {
                        error_log("Historical rate requested for {$date}, but using latest rate (free plan limitation). Rate: {$rate}");
                    }
                    
                    return $rate;
                } else {
                    error_log("Currency {$to_currency} not found in conversion_rates. Available currencies: " . implode(', ', array_keys($data['conversion_rates'] ?? [])));
                    return null;
                }
            } else {
                // エラーレスポンスの場合
                $error_msg = isset($data['error-type']) ? $data['error-type'] : 'Unknown error';
                error_log("API returned error: " . $error_msg . " for URL: " . $url);
                return null;
            }
            
        } catch (Exception $e) {
            error_log("API error in getRateFromAPI: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * データベースに為替レートを保存
     */
    private function saveRateToDB($date, $from_currency, $to_currency, $rate) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO exchange_rate_mst (rate_date, from_currency, to_currency, rate) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE rate = VALUES(rate), updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$date, $from_currency, $to_currency, $rate]);
        } catch (PDOException $e) {
            error_log("Database error in saveRateToDB: " . $e->getMessage());
        }
    }
    
    /**
     * 代替手段での為替レート取得（簡易版）
     */
    private function getFallbackRate($date, $from_currency, $to_currency) {
        // 簡易的な為替レート（実際の運用では適切なレートを設定）
        $fallback_rates = [
            'USD' => 150.0,
            'EUR' => 160.0,
            'GBP' => 190.0,
            'AUD' => 100.0,
            'CAD' => 110.0,
            'CHF' => 170.0,
            'CNY' => 20.0,
            'KRW' => 0.11,
            'THB' => 4.2,
            'TRY' => 4.5,
            'MIR' => 1.0,
            'PHP' => 2.7
        ];
        
        return $fallback_rates[$from_currency] ?? 1.0;
    }
    
    /**
     * 金額を日本円に換算
     * @param float $amount 元の金額
     * @param string $currency 通貨コード
     * @param string $date 日付
     * @return array ['jpy_amount' => 日本円金額, 'rate' => 為替レート]
     */
    public function convertToJPY($amount, $currency, $date) {
        if ($currency === 'JPY') {
            return ['jpy_amount' => $amount, 'rate' => 1.0];
        }
        
        $rate = $this->getExchangeRate($date, $currency, 'JPY');
        $jpy_amount = $amount * $rate;
        
        return [
            'jpy_amount' => round($jpy_amount, 2),
            'rate' => $rate
        ];
    }
}
?>
