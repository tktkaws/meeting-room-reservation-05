<?php
require_once 'api/config.php';

try {
    $pdo = getDatabase();
    
    echo "<h1>Departments Debug</h1>";
    
    // テーブル構造確認
    echo "<h2>Departments Table Structure</h2>";
    $stmt = $pdo->query("PRAGMA table_info(departments)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    foreach ($columns as $column) {
        echo $column['name'] . " (" . $column['type'] . ")" . ($column['dflt_value'] ? " DEFAULT " . $column['dflt_value'] : "") . "\n";
    }
    echo "</pre>";
    
    // 現在のデータ確認
    echo "<h2>Current Departments Data</h2>";
    $stmt = $pdo->query("SELECT * FROM departments ORDER BY id");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($departments);
    echo "</pre>";
    
    // colorカラムが存在するかテスト
    echo "<h2>Color Column Test</h2>";
    $colorColumnExists = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'color') {
            $colorColumnExists = true;
            break;
        }
    }
    echo "Color column exists: " . ($colorColumnExists ? 'YES' : 'NO') . "\n";
    
    // 手動でカラー更新テスト
    if ($colorColumnExists) {
        echo "<h2>Manual Color Update Test</h2>";
        $testSql = "UPDATE departments SET color = ? WHERE id = 1";
        $testStmt = $pdo->prepare($testSql);
        $result = $testStmt->execute(['#FF0000']);
        echo "Test update result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";
        echo "Affected rows: " . $testStmt->rowCount() . "\n";
        
        // 更新後のデータ確認
        $stmt = $pdo->query("SELECT id, name, color FROM departments WHERE id = 1");
        $dept = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "After update: ";
        print_r($dept);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>