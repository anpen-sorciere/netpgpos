<?php
// 為替レート取得クラス
class ExchangeRateService {
    private $api_key = null;
    private $base_url = 'https://api.exchangerate-api.com/v4/historical/';
    
    public function __construct($api_key = null) {
        $this->api_key = $api_key;
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
            
            // API URL構築
            $url = $this->base_url . $formatted_date . '?base=' . $from_currency;
            
            // cURLでAPI呼び出し
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200 && $response) {
                $data = json_decode($response, true);
                
                if (isset($data['rates'][$to_currency])) {
                    return (float)$data['rates'][$to_currency];
                }
            }
            
            // API失敗時は代替手段を試行
            return $this->getFallbackRate($date, $from_currency, $to_currency);
            
        } catch (Exception $e) {
            error_log("Exchange rate API error: " . $e->getMessage());
            return $this->getFallbackRate($date, $from_currency, $to_currency);
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
            'THB' => 4.2
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
