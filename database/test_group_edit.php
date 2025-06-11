<?php
require_once '../api/config.php';

echo "<h2>グループ編集機能テスト</h2>";

try {
    $db = getDatabase();
    
    // 現在のグループと予約の状況を表示
    echo "<h3>現在の繰り返し予約グループ</h3>";
    
    $stmt = $db->query("
        SELECT rg.id, rg.title, rg.description, rg.repeat_type, 
               u.name as user_name, COUNT(r.id) as reservation_count
        FROM reservation_groups rg
        JOIN users u ON rg.user_id = u.id
        LEFT JOIN reservations r ON rg.id = r.group_id
        GROUP BY rg.id
        ORDER BY rg.id
    ");
    $groups = $stmt->fetchAll();
    
    if (empty($groups)) {
        echo "<p>繰り返し予約グループがありません。</p>";
        echo "<p><a href='test_recurring.php'>テスト用の繰り返し予約を作成</a></p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th style='padding: 8px;'>グループID</th>";
        echo "<th style='padding: 8px;'>タイトル</th>";
        echo "<th style='padding: 8px;'>説明</th>";
        echo "<th style='padding: 8px;'>繰り返しタイプ</th>";
        echo "<th style='padding: 8px;'>作成者</th>";
        echo "<th style='padding: 8px;'>予約数</th>";
        echo "<th style='padding: 8px;'>アクション</th>";
        echo "</tr>";
        
        foreach ($groups as $group) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>" . $group['id'] . "</td>";
            echo "<td style='padding: 8px;'>" . $group['title'] . "</td>";
            echo "<td style='padding: 8px;'>" . ($group['description'] ?: '説明なし') . "</td>";
            echo "<td style='padding: 8px;'>" . $group['repeat_type'] . "</td>";
            echo "<td style='padding: 8px;'>" . $group['user_name'] . "</td>";
            echo "<td style='padding: 8px;'>" . $group['reservation_count'] . "</td>";
            echo "<td style='padding: 8px;'>";
            echo "<a href='../api/group_edit.php?group_id=" . $group['id'] . "' target='_blank'>API確認</a>";
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 各グループの詳細な予約情報
    if (!empty($groups)) {
        echo "<h3>各グループの詳細予約情報</h3>";
        
        foreach ($groups as $group) {
            echo "<h4>グループ: " . $group['title'] . " (ID: " . $group['id'] . ")</h4>";
            
            $stmt = $db->prepare("
                SELECT r.id, r.title, r.description, r.date, r.start_datetime, r.end_datetime
                FROM reservations r
                WHERE r.group_id = ?
                ORDER BY r.start_datetime ASC
            ");
            $stmt->execute([$group['id']]);
            $reservations = $stmt->fetchAll();
            
            if (empty($reservations)) {
                echo "<p>このグループには予約がありません。</p>";
                continue;
            }
            
            echo "<table border='1' style='border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; margin-bottom: 20px;'>";
            echo "<tr style='background-color: #f8f9fa;'>";
            echo "<th style='padding: 6px;'>予約ID</th>";
            echo "<th style='padding: 6px;'>タイトル</th>";
            echo "<th style='padding: 6px;'>日付</th>";
            echo "<th style='padding: 6px;'>開始時間</th>";
            echo "<th style='padding: 6px;'>終了時間</th>";
            echo "<th style='padding: 6px;'>説明</th>";
            echo "</tr>";
            
            foreach ($reservations as $reservation) {
                echo "<tr>";
                echo "<td style='padding: 6px;'>" . $reservation['id'] . "</td>";
                echo "<td style='padding: 6px;'>" . $reservation['title'] . "</td>";
                echo "<td style='padding: 6px;'>" . $reservation['date'] . "</td>";
                echo "<td style='padding: 6px;'>" . substr($reservation['start_datetime'], 11, 5) . "</td>";
                echo "<td style='padding: 6px;'>" . substr($reservation['end_datetime'], 11, 5) . "</td>";
                echo "<td style='padding: 6px;'>" . ($reservation['description'] ?: '説明なし') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
    // テスト手順
    echo "<h3>グループ編集機能のテスト手順</h3>";
    echo "<ol>";
    echo "<li><a href='../index.html'>メインページ</a>にアクセス</li>";
    echo "<li>繰り返し予約（緑色・♻マーク）をクリックして詳細表示</li>";
    echo "<li>「全ての繰り返し予約を編集」ボタンをクリック</li>";
    echo "<li>基本情報（タイトル・説明）を変更</li>";
    echo "<li>必要に応じて個別の予約の日付・時間を調整</li>";
    echo "<li>「すべて更新」ボタンで保存</li>";
    echo "<li>この画面で結果を確認</li>";
    echo "</ol>";
    
    echo "<h3>テスト可能な編集内容</h3>";
    echo "<ul>";
    echo "<li><strong>基本情報の変更:</strong> タイトルと説明の一括変更</li>";
    echo "<li><strong>時間調整:</strong> 各予約の開始時間・終了時間の調整</li>";
    echo "<li><strong>日付保持:</strong> 日付は変更されず元の日付が維持される</li>";
    echo "<li><strong>重複チェック:</strong> 既存予約との時間重複の検証</li>";
    echo "</ul>";
    
    echo "<p><strong>期待される動作:</strong></p>";
    echo "<ul>";
    echo "<li>タイトルと説明が全ての関連予約に反映される</li>";
    echo "<li>各予約の時間が個別に調整される</li>";
    echo "<li>日付は変更されず、元の日付が保持される</li>";
    echo "<li>時間重複がある場合はエラーメッセージが表示される</li>";
    echo "<li>すべての変更が正常に保存される</li>";
    echo "</ul>";
    
    echo "<h3>⚠️ 変更点（prompt03対応）</h3>";
    echo "<ul style='color: #d63384;'>";
    echo "<li><strong>日付変更機能を削除:</strong> 個別の日付変更はできなくなりました</li>";
    echo "<li><strong>時間のみ調整:</strong> 開始時間と終了時間のみ変更可能</li>";
    echo "<li><strong>全予約表示:</strong> 同じグループの全ての予約が表示されます</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<h2>エラーが発生しました</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>