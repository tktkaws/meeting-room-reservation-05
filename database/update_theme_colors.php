<?php
/**
 * データベース更新スクリプト - 部署テーマカラー機能追加
 */

$db_path = __DIR__ . '/meeting_room.db';

try {
    $pdo = new PDO("sqlite:$db_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // カラムが既に存在するかチェック
    $check_sql = "PRAGMA table_info(users)";
    $stmt = $pdo->query($check_sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $column_exists = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'department_theme_colors') {
            $column_exists = true;
            break;
        }
    }
    
    if (!$column_exists) {
        // カラム追加
        $sql = "ALTER TABLE users ADD COLUMN department_theme_colors TEXT";
        $pdo->exec($sql);
        echo "✓ department_theme_colors カラムを追加しました<br>";
    } else {
        echo "✓ department_theme_colors カラムは既に存在します<br>";
    }
    
    // デフォルトテーマカラーを設定
    $default_colors = json_encode([
        "1" => "#4299E1", // 青
        "2" => "#48BB78", // 緑  
        "3" => "#ED8936", // オレンジ
        "4" => "#9F7AEA", // 紫
        "5" => "#38B2AC"  // ティール
    ]);
    
    // 管理者ユーザーにデフォルトテーマカラーを設定
    $update_sql = "UPDATE users SET department_theme_colors = ? WHERE role = 'admin' AND department_theme_colors IS NULL";
    $stmt = $pdo->prepare($update_sql);
    $stmt->execute([$default_colors]);
    
    echo "✓ デフォルトテーマカラーを設定しました<br>";
    echo "✓ データベース更新完了<br>";
    
} catch (PDOException $e) {
    echo "エラー: " . $e->getMessage() . "<br>";
}
?>