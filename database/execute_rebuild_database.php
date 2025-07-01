<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>データベース完全再構築</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .status {
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .btn {
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        pre {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 15px;
            overflow-x: auto;
            font-size: 14px;
        }
        .table-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 データベース完全再構築</h1>
        
        <?php
        require_once '../api/config.php';
        
        $executed = false;
        $results = [];
        
        if (isset($_POST['execute'])) {
            $executed = true;
            
            try {
                // データベース接続
                $pdo = getDatabase();
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // SQLファイルを読み込み
                $sqlFile = __DIR__ . '/rebuild_database.sql';
                
                if (!file_exists($sqlFile)) {
                    throw new Exception("SQLファイルが見つかりません: $sqlFile");
                }
                
                $sqlContent = file_get_contents($sqlFile);
                if ($sqlContent === false) {
                    throw new Exception("SQLファイルの読み込みに失敗しました");
                }
                
                // ステップごとに分割して実行
                $steps = [];
                $currentStep = '';
                $stepNumber = 0;
                
                $lines = explode("\n", $sqlContent);
                foreach ($lines as $line) {
                    $line = trim($line);
                    
                    if (strpos($line, '-- ========== STEP') === 0) {
                        if (!empty($currentStep)) {
                            $steps[$stepNumber] = trim($currentStep);
                        }
                        $stepNumber++;
                        $currentStep = '';
                        continue;
                    }
                    
                    if (strpos($line, '-- ========== END_STEP') === 0) {
                        continue;
                    }
                    
                    if (!empty($line) && strpos($line, '--') !== 0) {
                        $currentStep .= $line . ' ';
                    }
                }
                
                // 最後のステップを追加
                if (!empty($currentStep)) {
                    $steps[$stepNumber] = trim($currentStep);
                }
                
                $successCount = 0;
                $errorCount = 0;
                
                foreach ($steps as $stepNum => $sql) {
                    if (empty($sql)) continue;
                    
                    try {
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute();
                        
                        $results[] = [
                            'type' => 'success',
                            'sql' => $sql,
                            'message' => 'ステップ' . ($stepNum + 1) . ' 実行成功'
                        ];
                        $successCount++;
                        
                    } catch (PDOException $e) {
                        $results[] = [
                            'type' => 'error',
                            'sql' => $sql,
                            'message' => 'ステップ' . ($stepNum + 1) . ' エラー: ' . $e->getMessage()
                        ];
                        $errorCount++;
                        
                        // エラーが発生した場合、続行するかどうかをログに記録
                        error_log("Database rebuild error at step " . ($stepNum + 1) . ": " . $e->getMessage());
                    }
                }
                
                // 実行結果サマリー
                if ($errorCount === 0) {
                    echo '<div class="status success">';
                    echo '<h3>✅ データベース再構築完了</h3>';
                    echo '<p>すべてのSQL文が正常に実行されました。</p>';
                    echo '<p>成功: ' . $successCount . '件</p>';
                    echo '</div>';
                    
                    // 新しいテーブル構造とデータを表示
                    echo '<div class="status info">';
                    echo '<h3>📊 新しいテーブル構造</h3>';
                    
                    try {
                        // departmentsテーブル構造
                        echo '<h4>departments テーブル</h4>';
                        $stmt = $pdo->query("PRAGMA table_info(departments)");
                        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        echo '<div class="table-info">';
                        echo '<strong>カラム構造:</strong><br>';
                        foreach ($columns as $column) {
                            echo $column['name'] . ' (' . $column['type'] . ') ' . 
                                 ($column['dflt_value'] ? 'DEFAULT ' . $column['dflt_value'] : '') . '<br>';
                        }
                        echo '</div>';
                        
                        // departmentsデータ
                        echo '<h4>departments データ</h4>';
                        $stmt = $pdo->query("SELECT * FROM departments ORDER BY id");
                        $deptData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        echo '<pre>' . print_r($deptData, true) . '</pre>';
                        
                        // user_department_colorsテーブル構造
                        echo '<h4>user_department_colors テーブル</h4>';
                        $stmt = $pdo->query("PRAGMA table_info(user_department_colors)");
                        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        echo '<div class="table-info">';
                        echo '<strong>カラム構造:</strong><br>';
                        foreach ($columns as $column) {
                            echo $column['name'] . ' (' . $column['type'] . ') ' . 
                                 ($column['dflt_value'] ? 'DEFAULT ' . $column['dflt_value'] : '') . '<br>';
                        }
                        echo '</div>';
                        
                        // インデックス情報
                        echo '<h4>作成されたインデックス</h4>';
                        $stmt = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='index' AND tbl_name IN ('departments', 'user_department_colors')");
                        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        echo '<div class="table-info">';
                        foreach ($indexes as $index) {
                            if ($index['sql']) {
                                echo '<strong>' . $index['name'] . ':</strong> ' . $index['sql'] . '<br>';
                            }
                        }
                        echo '</div>';
                        
                    } catch (Exception $e) {
                        echo '<p>新しいデータ取得失敗: ' . htmlspecialchars($e->getMessage()) . '</p>';
                    }
                    echo '</div>';
                    
                } else {
                    echo '<div class="status error">';
                    echo '<h3>❌ データベース再構築失敗</h3>';
                    echo '<p>成功: ' . $successCount . '件, エラー: ' . $errorCount . '件</p>';
                    echo '</div>';
                }
                
            } catch (Exception $e) {
                echo '<div class="status error">';
                echo '<h3>❌ 再構築失敗</h3>';
                echo '<p>エラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
        }
        
        if (!$executed) {
            // 実行前の確認画面
            echo '<div class="status warning">';
            echo '<h3>⚠️ 重要な警告</h3>';
            echo '<ul>';
            echo '<li><strong>この操作はdepartmentsとuser_department_colorsテーブルを完全に削除・再作成します</strong></li>';
            echo '<li>既存のデータは全て失われます</li>';
            echo '<li>基本的な部署データは自動的に挿入されます</li>';
            echo '<li>実行前に必要なデータのバックアップを取ってください</li>';
            echo '</ul>';
            echo '</div>';
            
            echo '<div class="status info">';
            echo '<h3>📋 実行予定のSQL</h3>';
            echo '<p>以下のSQL文を実行してデータベースを再構築します：</p>';
            
            $sqlFile = __DIR__ . '/rebuild_database.sql';
            if (file_exists($sqlFile)) {
                $sqlContent = file_get_contents($sqlFile);
                echo '<pre>' . htmlspecialchars($sqlContent) . '</pre>';
                
                echo '<form method="post">';
                echo '<button type="submit" name="execute" class="btn btn-danger">🚀 データベース再構築を実行</button>';
                echo '</form>';
            } else {
                echo '<div class="status error">';
                echo '<p>SQLファイルが見つかりません: ' . htmlspecialchars($sqlFile) . '</p>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            // 実行結果の詳細表示
            echo '<h3>📝 実行ログ詳細</h3>';
            
            foreach ($results as $result) {
                $statusClass = $result['type'];
                echo "<div class='status $statusClass'>";
                echo "<h4>" . ucfirst($result['type']) . "</h4>";
                echo "<p><strong>メッセージ:</strong> " . htmlspecialchars($result['message']) . "</p>";
                echo "<p><strong>SQL:</strong></p>";
                echo "<pre>" . htmlspecialchars($result['sql']) . "</pre>";
                echo "</div>";
            }
            
            echo '<div style="margin-top: 30px;">';
            echo '<a href="' . $_SERVER['PHP_SELF'] . '" class="btn">🔄 再実行</a>';
            echo '<a href="../admin/department_management.html" class="btn">🏢 部署管理へ</a>';
            echo '<a href="../index.html" class="btn">🏠 ホームへ</a>';
            echo '</div>';
        }
        ?>
        
    </div>
</body>
</html>