<?php
require_once 'config.php';

// テスト用の予約API直接呼び出し
echo "=== 予約API テスト ===\n";

// パラメータ設定
$_GET['start_date'] = '2025-06-02';
$_GET['end_date'] = '2025-07-04';
$_SERVER['REQUEST_METHOD'] = 'GET';

echo "テスト開始日: " . $_GET['start_date'] . "\n";
echo "テスト終了日: " . $_GET['end_date'] . "\n";
echo "リクエストメソッド: " . $_SERVER['REQUEST_METHOD'] . "\n";

// 出力バッファリングを開始
ob_start();

try {
    // reservations.phpの処理を模倣
    require_once 'reservations.php';
} catch (Exception $e) {
    echo "エラー発生: " . $e->getMessage() . "\n";
    echo "スタックトレース:\n" . $e->getTraceAsString() . "\n";
}

// 出力を取得
$output = ob_get_contents();
ob_end_clean();

echo "=== API出力結果 ===\n";
echo $output . "\n";

// JSONの妥当性をチェック
$json = json_decode($output, true);
if ($json === null) {
    echo "=== JSON解析エラー ===\n";
    echo "JSON解析エラー: " . json_last_error_msg() . "\n";
} else {
    echo "=== JSON解析成功 ===\n";
    echo "success: " . var_export($json['success'], true) . "\n";
    echo "message: " . var_export($json['message'], true) . "\n";
    if (isset($json['data'])) {
        echo "data: " . var_export($json['data'], true) . "\n";
    }
}
?>