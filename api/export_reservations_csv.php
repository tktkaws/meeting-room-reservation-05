<?php
/**
 * 予約一覧CSV出力API
 * 指定された期間の予約データをCSV形式で出力
 */

require_once 'config.php';

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// 認証チェック
requireAuth();

// パラメータ取得
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$format = $_GET['format'] ?? 'csv';
$includeSingle = $_GET['include_single'] ?? '1';
$includeRecurring = $_GET['include_recurring'] ?? '1';
$includeFuture = $_GET['include_future'] ?? '1';
$includePast = $_GET['include_past'] ?? '0';

// デフォルト期間設定（今月）
if (!$startDate) {
    $startDate = date('Y-m-01'); // 今月の1日
}

if (!$endDate) {
    $endDate = date('Y-m-t'); // 今月の最終日
}

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
    
    // 予約データを取得（部署名も含む）
    $sql = "
        SELECT 
            r.id,
            r.title,
            r.description,
            r.date,
            r.start_datetime,
            r.end_datetime,
            r.created_at,
            r.updated_at,
            u.name as user_name,
            u.email as user_email,
            d.name as department_name,
            CASE 
                WHEN r.group_id IS NOT NULL THEN '繰り返し'
                ELSE '単発'
            END as reservation_type,
            rg.repeat_type,
            rg.repeat_interval
        FROM reservations r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN departments d ON u.department = d.id
        LEFT JOIN reservation_groups rg ON r.group_id = rg.id
    ";
    
    if (!empty($whereConditions)) {
        $sql .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $sql .= " ORDER BY r.start_datetime ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ファイル名と拡張子を設定
    $extension = ($format === 'tsv') ? 'tsv' : 'csv';
    $filename = "reservations_{$startDate}_to_{$endDate}.{$extension}";
    
    // HTTPヘッダーを設定
    if ($format === 'tsv') {
        header('Content-Type: text/tab-separated-values; charset=UTF-8');
    } else {
        header('Content-Type: text/csv; charset=UTF-8');
    }
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // BOM付きUTF-8で出力（Excelで文字化けを防ぐ）
    echo "\xEF\xBB\xBF";
    
    // 区切り文字を設定
    $delimiter = ($format === 'tsv') ? "\t" : ",";
    
    // CSV出力ストリームを開く
    $output = fopen('php://output', 'w');
    
    // CSVヘッダーを出力
    $headers = [
        'ID',
        'タイトル',
        '説明',
        '日付',
        '開始時間',
        '終了時間',
        '予約者',
        'メールアドレス',
        '部署',
        '予約タイプ',
        '繰り返しパターン',
        '繰り返し間隔',
        '作成日時',
        '更新日時'
    ];
    
    fputcsv($output, $headers, $delimiter);
    
    // データ行を出力
    foreach ($reservations as $reservation) {
        // 日時フォーマット
        $date = date('Y年n月j日', strtotime($reservation['date']));
        $startTime = date('H:i', strtotime($reservation['start_datetime']));
        $endTime = date('H:i', strtotime($reservation['end_datetime']));
        $createdAt = date('Y-m-d H:i:s', strtotime($reservation['created_at']));
        $updatedAt = date('Y-m-d H:i:s', strtotime($reservation['updated_at']));
        
        // 繰り返しパターンの日本語化
        $repeatTypeMap = [
            'daily' => '毎日',
            'weekly' => '毎週',
            'biweekly' => '隔週',
            'monthly' => '毎月'
        ];
        $repeatType = isset($repeatTypeMap[$reservation['repeat_type']]) 
            ? $repeatTypeMap[$reservation['repeat_type']] 
            : '';
        
        $row = [
            $reservation['id'],
            $reservation['title'],
            $reservation['description'] ?? '',
            $date,
            $startTime,
            $endTime,
            $reservation['user_name'],
            $reservation['user_email'],
            $reservation['department_name'] ?? '未設定',
            $reservation['reservation_type'],
            $repeatType,
            $reservation['repeat_interval'] ?? '',
            $createdAt,
            $updatedAt
        ];
        
        fputcsv($output, $row, $delimiter);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    // エラーの場合はJSONレスポンスを返す
    sendJsonResponse(false, 'CSV出力に失敗しました: ' . $e->getMessage(), null, 500);
}
?>