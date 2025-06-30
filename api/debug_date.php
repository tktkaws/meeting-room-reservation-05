<?php
require_once 'config.php';

// 日付検証のテスト
$testDates = [
    '2025-06-02',
    '2025-07-04',
    '2025-6-2',
    '2025-06-2',
    '2025-6-02'
];

echo "=== 日付検証テスト ===\n";

foreach ($testDates as $date) {
    echo "\n--- テスト日付: '$date' ---\n";
    
    // パターンマッチテスト
    $pattern = '/^\d{4}-\d{2}-\d{2}$/';
    $pattern_match = preg_match($pattern, $date);
    echo "パターンマッチ ($pattern): " . ($pattern_match ? 'true' : 'false') . "\n";
    
    // strtotime テスト
    $strtotime_result = strtotime($date);
    $strtotime_valid = $strtotime_result !== false;
    echo "strtotime: " . ($strtotime_valid ? 'true' : 'false') . " (結果: $strtotime_result)\n";
    
    // validateInput テスト
    $validate_result = validateInput($date, 'date');
    echo "validateInput: " . ($validate_result ? 'true' : 'false') . "\n";
    
    if ($strtotime_valid) {
        echo "日付変換結果: " . date('Y-m-d', $strtotime_result) . "\n";
    }
}

echo "\n=== 実際のAPIパラメータテスト ===\n";
$_GET['start_date'] = '2025-06-02';
$_GET['end_date'] = '2025-07-04';

$startDate = $_GET['start_date'];
$endDate = $_GET['end_date'];

echo "startDate: '$startDate'\n";
echo "startDate validation: " . (validateInput($startDate, 'date') ? 'true' : 'false') . "\n";
echo "endDate: '$endDate'\n";
echo "endDate validation: " . (validateInput($endDate, 'date') ? 'true' : 'false') . "\n";
?>