<?php
/**
 * CSV出力プレビューAPI
 * 指定された条件での予約件数を返す
 */

require_once 'config.php';

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// 認証チェック
requireAuth();

// パラメータ取得
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$includeSingle = $_GET['include_single'] ?? '1';
$includeRecurring = $_GET['include_recurring'] ?? '1';
$includeFuture = $_GET['include_future'] ?? '1';
$includePast = $_GET['include_past'] ?? '0';

// 日付バリデーション
if (!validateInput($startDate, 'date') || !validateInput($endDate, 'date')) {
    sendJsonResponse(false, '有効な日付を指定してください', null, 400);
    exit;
}

// 開始日が終了日より後の場合はエラー
if (strtotime($startDate) > strtotime($endDate)) {
    sendJsonResponse(false, '開始日は終了日以前にしてください', null, 400);
    exit;
}

try {
    $db = getDatabase();
    
    // 条件に基づくWHERE句を構築
    $whereConditions = [];
    $params = [];
    
    // 日付範囲
    $whereConditions[] = "r.date BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    
    // 予約タイプフィルター
    $typeConditions = [];
    if ($includeSingle === '1') {
        $typeConditions[] = "r.group_id IS NULL";
    }
    if ($includeRecurring === '1') {
        $typeConditions[] = "r.group_id IS NOT NULL";
    }
    
    if (!empty($typeConditions)) {
        $whereConditions[] = "(" . implode(" OR ", $typeConditions) . ")";
    }
    
    // 予約状態フィルター（現在時刻との比較）
    $now = date('Y-m-d H:i:s');
    $statusConditions = [];
    
    if ($includeFuture === '1') {
        $statusConditions[] = "r.end_datetime > ?";
        $params[] = $now;
    }
    if ($includePast === '1') {
        $statusConditions[] = "r.end_datetime <= ?";
        $params[] = $now;
    }
    
    if (!empty($statusConditions)) {
        $whereConditions[] = "(" . implode(" OR ", $statusConditions) . ")";
    }
    
    // SQLクエリ構築
    $sql = "
        SELECT 
            COUNT(*) as count,
            MIN(r.date) as min_date,
            MAX(r.date) as max_date
        FROM reservations r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN departments d ON u.department = d.id
        LEFT JOIN reservation_groups rg ON r.group_id = rg.id
    ";
    
    if (!empty($whereConditions)) {
        $sql .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // レスポンスデータ
    $responseData = [
        'count' => (int)$result['count'],
        'start_date' => $startDate,
        'end_date' => $endDate,
        'actual_start_date' => $result['min_date'],
        'actual_end_date' => $result['max_date']
    ];
    
    sendJsonResponse(true, 'プレビューを取得しました', $responseData);
    
} catch (Exception $e) {
    sendJsonResponse(false, 'プレビューの取得に失敗しました: ' . $e->getMessage(), null, 500);
}
?>