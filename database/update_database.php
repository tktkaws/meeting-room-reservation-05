<?php
require_once '../api/config.php';

try {
    $db = getDatabase();
    
    echo "<h2>データベーステーブル更新</h2>";
    
    // 新しいテーブルの存在確認
    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='reservation_group_relations'");
    
    if ($tableCheck->fetch()) {
        echo "<p>テーブル 'reservation_group_relations' は既に存在します。</p>";
    } else {
        // 新しいテーブルを作成
        $updateSql = file_get_contents('update_schema.sql');
        $db->exec($updateSql);
        echo "<p>✅ テーブル 'reservation_group_relations' を作成しました。</p>";
    }
    
    // 現在のテーブル構造を表示
    echo "<h3>現在のテーブル一覧</h3>";
    echo "<ul>";
    
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    while ($table = $tables->fetch()) {
        echo "<li>" . $table['name'] . "</li>";
    }
    echo "</ul>";
    
    // reservation_group_relations テーブルの構造を表示
    echo "<h3>reservation_group_relations テーブル構造</h3>";
    echo "<table border='1' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th style='padding: 8px;'>Column</th>";
    echo "<th style='padding: 8px;'>Type</th>";
    echo "<th style='padding: 8px;'>Not Null</th>";
    echo "<th style='padding: 8px;'>Default</th>";
    echo "</tr>";
    
    $columns = $db->query("PRAGMA table_info(reservation_group_relations)");
    while ($column = $columns->fetch()) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>" . $column['name'] . "</td>";
        echo "<td style='padding: 8px;'>" . $column['type'] . "</td>";
        echo "<td style='padding: 8px;'>" . ($column['notnull'] ? 'YES' : 'NO') . "</td>";
        echo "<td style='padding: 8px;'>" . ($column['dflt_value'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><p><strong>次のステップ:</strong> 繰り返し予約機能の実装を続行します。</p>";
    echo "<p><a href='../index.html'>メインページに戻る</a></p>";
    
} catch (Exception $e) {
    echo "<h2>エラーが発生しました</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>