<?php
require_once '../api/config.php';

echo "<h2>繰り返し予約機能テスト</h2>";

try {
    $db = getDatabase();
    
    // テスト用の繰り返し予約データを作成
    echo "<h3>テスト用繰り返し予約を作成中...</h3>";
    
    // 今日から1週間おきに4回の会議
    $startDate = new DateTime();
    $endDate = clone $startDate;
    $endDate->add(new DateInterval('P3W')); // 3週間後まで
    
    // reservation_groups テーブルにグループを作成
    $stmt = $db->prepare("
        INSERT INTO reservation_groups (title, description, user_id, repeat_type, repeat_interval, start_date, end_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        '毎週定例会議',
        '週次の定例会議です',
        1, // Admin User
        'weekly',
        1,
        $startDate->format('Y-m-d'),
        $endDate->format('Y-m-d')
    ]);
    
    $groupId = $db->lastInsertId();
    echo "<p>✅ 繰り返しグループを作成しました (ID: {$groupId})</p>";
    
    // 繰り返し予約を作成
    $currentDate = clone $startDate;
    $reservationIds = [];
    $count = 0;
    
    while ($currentDate <= $endDate && $count < 4) {
        $dateStr = $currentDate->format('Y-m-d');
        $startDatetime = $dateStr . ' 10:00:00';
        $endDatetime = $dateStr . ' 11:00:00';
        
        // 予約作成
        $stmt = $db->prepare("
            INSERT INTO reservations (user_id, title, description, date, start_datetime, end_datetime, group_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            1, // Admin User
            '毎週定例会議',
            '週次の定例会議です',
            $dateStr,
            $startDatetime,
            $endDatetime,
            $groupId
        ]);
        
        $reservationId = $db->lastInsertId();
        $reservationIds[] = $reservationId;
        
        // リレーション作成
        $stmt = $db->prepare("
            INSERT INTO reservation_group_relations (reserve_id, group_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([$reservationId, $groupId]);
        
        echo "<p>✅ 予約を作成しました: {$dateStr} (ID: {$reservationId})</p>";
        
        // 次の週
        $currentDate->add(new DateInterval('P7D'));
        $count++;
    }
    
    echo "<h3>作成された繰り返し予約一覧</h3>";
    
    // 作成された予約を表示
    $stmt = $db->prepare("
        SELECT r.*, rg.repeat_type, rg.repeat_interval 
        FROM reservations r 
        LEFT JOIN reservation_groups rg ON r.group_id = rg.id 
        WHERE r.group_id = ? 
        ORDER BY r.start_datetime ASC
    ");
    $stmt->execute([$groupId]);
    $reservations = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th style='padding: 8px;'>ID</th>";
    echo "<th style='padding: 8px;'>タイトル</th>";
    echo "<th style='padding: 8px;'>日付</th>";
    echo "<th style='padding: 8px;'>時間</th>";
    echo "<th style='padding: 8px;'>グループID</th>";
    echo "<th style='padding: 8px;'>繰り返しタイプ</th>";
    echo "</tr>";
    
    foreach ($reservations as $reservation) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>" . $reservation['id'] . "</td>";
        echo "<td style='padding: 8px;'>" . $reservation['title'] . "</td>";
        echo "<td style='padding: 8px;'>" . $reservation['date'] . "</td>";
        echo "<td style='padding: 8px;'>" . substr($reservation['start_datetime'], 11, 5) . " - " . substr($reservation['end_datetime'], 11, 5) . "</td>";
        echo "<td style='padding: 8px;'>" . $reservation['group_id'] . "</td>";
        echo "<td style='padding: 8px;'>" . $reservation['repeat_type'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // リレーションテーブルの内容を表示
    echo "<h3>リレーションテーブルの内容</h3>";
    $stmt = $db->prepare("SELECT * FROM reservation_group_relations WHERE group_id = ?");
    $stmt->execute([$groupId]);
    $relations = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th style='padding: 8px;'>ID</th>";
    echo "<th style='padding: 8px;'>予約ID</th>";
    echo "<th style='padding: 8px;'>グループID</th>";
    echo "</tr>";
    
    foreach ($relations as $relation) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>" . $relation['id'] . "</td>";
        echo "<td style='padding: 8px;'>" . $relation['reserve_id'] . "</td>";
        echo "<td style='padding: 8px;'>" . $relation['group_id'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><p><strong>テスト完了！</strong> カレンダーで繰り返し予約を確認してください。</p>";
    echo "<p>繰り返し予約は緑色で表示され、♻マークが付きます。</p>";
    echo "<p><a href='../index.html'>メインページで確認する</a></p>";
    
} catch (Exception $e) {
    echo "<h2>エラーが発生しました</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>