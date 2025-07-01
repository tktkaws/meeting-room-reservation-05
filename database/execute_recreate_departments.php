<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>部署テーブル再作成</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
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
            padding: 10px 20px;
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
            padding: 10px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 部署テーブル再作成（colorカラム付き）</h1>
        
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
                $sqlFile = __DIR__ . '/recreate_departments_table.sql';
                
                if (!file_exists($sqlFile)) {
                    throw new Exception("SQLファイルが見つかりません: $sqlFile");
                }
                
                $sqlContent = file_get_contents($sqlFile);
                if ($sqlContent === false) {
                    throw new Exception("SQLファイルの読み込みに失敗しました");
                }
                
                // SQLを行ごとに分割して実行
                $sqlStatements = explode(';', $sqlContent);
                
                $successCount = 0;
                $errorCount = 0;
                
                foreach ($sqlStatements as $statement) {
                    $statement = trim($statement);
                    
                    // 空の行やコメント行をスキップ
                    if (empty($statement) || strpos($statement, '--') === 0) {
                        continue;
                    }
                    
                    try {
                        $stmt = $pdo->prepare($statement);
                        $stmt->execute();
                        
                        $results[] = [
                            'type' => 'success',
                            'sql' => $statement,
                            'message' => '実行成功'
                        ];
                        $successCount++;
                        
                    } catch (PDOException $e) {
                        $results[] = [
                            'type' => 'error',
                            'sql' => $statement,
                            'message' => 'エラー: ' . $e->getMessage()
                        ];
                        $errorCount++;
                    }
                }
                
                // 実行結果サマリー
                if ($errorCount === 0) {
                    echo '<div class="status success">';
                    echo '<h3>✅ 部署テーブル再作成完了</h3>';
                    echo '<p>すべてのSQL文が正常に実行されました。</p>';
                    echo '<p>成功: ' . $successCount . '件</p>';
                    echo '</div>';
                    
                    // 新しいテーブル構造とデータを表示
                    echo '<div class="status info">';
                    echo '<h3>📊 新しいテーブル構造</h3>';
                    try {
                        $stmt = $pdo->query("PRAGMA table_info(departments)");
                        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        echo '<pre>';
                        foreach ($columns as $column) {
                            echo $column['name'] . ' (' . $column['type'] . ')' . "\n";
                        }
                        echo '</pre>';
                        
                        echo '<h3>📊 新しいデータ</h3>';
                        $stmt = $pdo->query("SELECT * FROM departments ORDER BY id");
                        $newData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        echo '<pre>' . print_r($newData, true) . '</pre>';
                    } catch (Exception $e) {
                        echo '<p>新しいデータ取得失敗: ' . htmlspecialchars($e->getMessage()) . '</p>';
                    }
                    echo '</div>';
                    
                } else {
                    echo '<div class="status error">';
                    echo '<h3>❌ 部署テーブル再作成失敗</h3>';
                    echo '<p>成功: ' . $successCount . '件, エラー: ' . $errorCount . '件</p>';
                    echo '</div>';
                }
                
            } catch (Exception $e) {
                echo '<div class="status error">';
                echo '<h3>❌ 再作成失敗</h3>';
                echo '<p>エラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
        }
        
        if (!$executed) {
            // 実行前の確認画面
            echo '<div class="status warning">';
            echo '<h3>⚠️ 重要な注意事項</h3>';
            echo '<ul>';
            echo '<li><strong>この操作は部署テーブルを完全に削除・再作成します</strong></li>';
            echo '<li>既存の部署データは自動的に復元されますが、念のためバックアップを推奨します</li>';
            echo '<li>colorカラムが追加された新しい構造で再作成されます</li>';
            echo '<li>デフォルトカラーが各部署に設定されます</li>';
            echo '</ul>';
            echo '</div>';
            
            echo '<div class="status info">';
            echo '<h3>📋 実行予定のSQL</h3>';
            echo '<p>以下のSQL文を実行して部署テーブルを再作成します：</p>';
            
            $sqlFile = __DIR__ . '/recreate_departments_table.sql';
            if (file_exists($sqlFile)) {
                $sqlContent = file_get_contents($sqlFile);
                echo '<pre>' . htmlspecialchars($sqlContent) . '</pre>';
                
                echo '<form method="post">';
                echo '<button type="submit" name="execute" class="btn btn-danger">🚀 部署テーブル再作成を実行</button>';
                echo '</form>';
            } else {
                echo '<div class="status error">';
                echo '<p>SQLファイルが見つかりません: ' . htmlspecialchars($sqlFile) . '</p>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            // 実行結果の詳細表示
            echo '<h3>📝 実行ログ</h3>';
            
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