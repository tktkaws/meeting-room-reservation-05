<?php
require_once '../api/config.php';

try {
    $db = getDatabase();
    
    // サンプル予約データを挿入
    $sampleReservations = [
        [
            'user_id' => 1, // Admin User
            'title' => '朝の会議',
            'description' => '部署の朝の定例会議',
            'date' => date('Y-m-d'),
            'start_datetime' => date('Y-m-d') . ' 09:00:00',
            'end_datetime' => date('Y-m-d') . ' 10:00:00'
        ],
        [
            'user_id' => 2, // Test User
            'title' => 'プロジェクト打合せ',
            'description' => '新プロジェクトの企画会議',
            'date' => date('Y-m-d'),
            'start_datetime' => date('Y-m-d') . ' 14:00:00',
            'end_datetime' => date('Y-m-d') . ' 16:00:00'
        ],
        [
            'user_id' => 1,
            'title' => '明日の会議',
            'description' => '明日の予定',
            'date' => date('Y-m-d', strtotime('+1 day')),
            'start_datetime' => date('Y-m-d', strtotime('+1 day')) . ' 13:00:00',
            'end_datetime' => date('Y-m-d', strtotime('+1 day')) . ' 14:30:00'
        ],
        [
            'user_id' => 2,
            'title' => '来週の打合せ',
            'description' => '来週の企画会議',
            'date' => date('Y-m-d', strtotime('+7 days')),
            'start_datetime' => date('Y-m-d', strtotime('+7 days')) . ' 10:00:00',
            'end_datetime' => date('Y-m-d', strtotime('+7 days')) . ' 11:00:00'
        ]
    ];
    
    $stmt = $db->prepare("
        INSERT INTO reservations (user_id, title, description, date, start_datetime, end_datetime) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($sampleReservations as $reservation) {
        $stmt->execute([
            $reservation['user_id'],
            $reservation['title'],
            $reservation['description'],
            $reservation['date'],
            $reservation['start_datetime'],
            $reservation['end_datetime']
        ]);
    }
    
    echo "<h2>サンプルデータ挿入完了</h2>";
    echo "<p>" . count($sampleReservations) . "件の予約データを挿入しました。</p>";
    
    // 現在の予約データを表示
    $stmt = $db->prepare("
        SELECT r.*, u.name as user_name 
        FROM reservations r 
        JOIN users u ON r.user_id = u.id 
        ORDER BY r.start_datetime ASC
    ");
    $stmt->execute();
    $reservations = $stmt->fetchAll();
    
    echo "<h3>現在の予約データ (" . count($reservations) . "件)</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th style='padding: 8px;'>ID</th>";
    echo "<th style='padding: 8px;'>タイトル</th>";
    echo "<th style='padding: 8px;'>予約者</th>";
    echo "<th style='padding: 8px;'>日付</th>";
    echo "<th style='padding: 8px;'>開始時間</th>";
    echo "<th style='padding: 8px;'>終了時間</th>";
    echo "</tr>";
    
    foreach ($reservations as $reservation) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>" . $reservation['id'] . "</td>";
        echo "<td style='padding: 8px;'>" . $reservation['title'] . "</td>";
        echo "<td style='padding: 8px;'>" . $reservation['user_name'] . "</td>";
        echo "<td style='padding: 8px;'>" . $reservation['date'] . "</td>";
        echo "<td style='padding: 8px;'>" . substr($reservation['start_datetime'], 11, 5) . "</td>";
        echo "<td style='padding: 8px;'>" . substr($reservation['end_datetime'], 11, 5) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><p><a href='../index.html'>メインページに戻る</a></p>";
    
} catch (Exception $e) {
    echo "<h2>エラーが発生しました</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>