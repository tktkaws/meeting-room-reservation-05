<?php
require_once '../api/config.php';

echo "<h2>編集機能テスト</h2>";

try {
    $db = getDatabase();
    
    // 現在の繰り返し予約グループの状況を表示
    echo "<h3>現在の繰り返し予約グループ</h3>";
    
    $stmt = $db->query("
        SELECT rg.id, rg.title, rg.repeat_type, COUNT(r.id) as reservation_count
        FROM reservation_groups rg
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
        echo "<th style='padding: 8px;'>繰り返しタイプ</th>";
        echo "<th style='padding: 8px;'>予約数</th>";
        echo "</tr>";
        
        foreach ($groups as $group) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>" . $group['id'] . "</td>";
            echo "<td style='padding: 8px;'>" . $group['title'] . "</td>";
            echo "<td style='padding: 8px;'>" . $group['repeat_type'] . "</td>";
            echo "<td style='padding: 8px;'>" . $group['reservation_count'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // グループリレーションの状況
    echo "<h3>グループリレーション</h3>";
    $stmt = $db->query("
        SELECT rgr.*, r.title, r.date
        FROM reservation_group_relations rgr
        JOIN reservations r ON rgr.reserve_id = r.id
        ORDER BY rgr.group_id, r.date
    ");
    $relations = $stmt->fetchAll();
    
    if (empty($relations)) {
        echo "<p>グループリレーションがありません。</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th style='padding: 8px;'>予約ID</th>";
        echo "<th style='padding: 8px;'>グループID</th>";
        echo "<th style='padding: 8px;'>タイトル</th>";
        echo "<th style='padding: 8px;'>日付</th>";
        echo "</tr>";
        
        foreach ($relations as $relation) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>" . $relation['reserve_id'] . "</td>";
            echo "<td style='padding: 8px;'>" . $relation['group_id'] . "</td>";
            echo "<td style='padding: 8px;'>" . $relation['title'] . "</td>";
            echo "<td style='padding: 8px;'>" . $relation['date'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 予約詳細機能のテスト用リンク
    echo "<h3>機能テスト</h3>";
    echo "<p>以下のリンクから各機能をテストできます：</p>";
    echo "<ul>";
    echo "<li><a href='../index.html'>メインページ（予約詳細表示テスト）</a></li>";
    echo "<li><a href='../api/reservation_detail.php?id=1' target='_blank'>予約詳細API（ID:1）</a></li>";
    echo "<li><a href='test_recurring.php'>新しい繰り返し予約作成</a></li>";
    echo "</ul>";
    
    echo "<h3>テスト手順</h3>";
    echo "<ol>";
    echo "<li>メインページで繰り返し予約（緑色・♻マーク）をクリック</li>";
    echo "<li>予約詳細モーダルが表示される</li>";
    echo "<li>「この予約のみ編集」をクリックして個別編集をテスト</li>";
    echo "<li>編集後、この画面で結果を確認</li>";
    echo "</ol>";
    
    echo "<p><strong>期待される動作：</strong></p>";
    echo "<ul>";
    echo "<li>個別編集後、その予約はグループから除外される</li>";
    echo "<li>グループに残った予約が1件以下の場合、グループとリレーションが削除される</li>";
    echo "<li>予約のgroup_idがNULLになる（繰り返し予約でなくなる）</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<h2>エラーが発生しました</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>