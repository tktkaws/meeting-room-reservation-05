<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>user_theme_colors.php デバッグ情報</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .section {
            border: 1px solid #ddd;
            margin: 10px 0;
            padding: 15px;
            border-radius: 5px;
        }
        .section h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        pre {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            white-space: pre-wrap;
        }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
        .warning { background-color: #fff3cd; border-color: #ffeaa7; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 user_theme_colors.php デバッグ情報</h1>
        
        <?php
        require_once 'api/config.php';
        
        // セッション開始
        if (!isset($_SESSION)) {
            session_start();
        }
        
        echo '<div class="section info">';
        echo '<h3>📋 基本情報</h3>';
        echo '<pre>';
        echo "リクエストメソッド: " . $_SERVER['REQUEST_METHOD'] . "\n";
        echo "リクエストURI: " . $_SERVER['REQUEST_URI'] . "\n";
        echo "現在時刻: " . date('Y-m-d H:i:s') . "\n";
        echo '</pre>';
        echo '</div>';
        
        echo '<div class="section info">';
        echo '<h3>🔐 セッション情報</h3>';
        echo '<pre>';
        if (isset($_SESSION) && !empty($_SESSION)) {
            echo "セッションID: " . session_id() . "\n";
            echo "セッションデータ:\n";
            print_r($_SESSION);
        } else {
            echo "セッションが開始されていないか、データがありません\n";
        }
        echo '</pre>';
        echo '</div>';
        
        echo '<div class="section info">';
        echo '<h3>📥 GETパラメータ</h3>';
        echo '<pre>';
        if (!empty($_GET)) {
            print_r($_GET);
        } else {
            echo "GETパラメータはありません\n";
        }
        echo '</pre>';
        echo '</div>';
        
        echo '<div class="section info">';
        echo '<h3>📤 POSTデータ</h3>';
        echo '<pre>';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $rawInput = file_get_contents('php://input');
            echo "Raw Input: " . $rawInput . "\n\n";
            
            $parsedInput = json_decode($rawInput, true);
            echo "Parsed JSON:\n";
            print_r($parsedInput);
            
            if (!empty($_POST)) {
                echo "\n\$_POST データ:\n";
                print_r($_POST);
            }
        } else {
            echo "POSTリクエストではありません\n";
        }
        echo '</pre>';
        echo '</div>';
        
        // 認証テスト
        echo '<div class="section">';
        echo '<h3>🔑 認証テスト</h3>';
        
        if (!isset($_SESSION['user_id'])) {
            echo '<div class="error">';
            echo '<p><strong>❌ 認証エラー</strong></p>';
            echo '<p>$_SESSION[\'user_id\'] が設定されていません</p>';
            echo '</div>';
        } else {
            echo '<div class="success">';
            echo '<p><strong>✅ 認証成功</strong></p>';
            echo '<p>ユーザーID: ' . $_SESSION['user_id'] . '</p>';
            echo '<p>ロール: ' . ($_SESSION['role'] ?? '未設定') . '</p>';
            echo '</div>';
        }
        echo '</div>';
        
        // API呼び出しシミュレーション
        if (isset($_SESSION['user_id'])) {
            echo '<div class="section info">';
            echo '<h3>🧪 API呼び出しシミュレーション</h3>';
            
            // GETテスト
            if (isset($_GET['department_id']) && isset($_GET['user_id'])) {
                echo '<h4>GET リクエストテスト</h4>';
                echo '<pre>';
                
                $departmentId = $_GET['department_id'];
                $userId = $_GET['user_id'];
                $currentUserId = $_SESSION['user_id'];
                
                echo "パラメータ:\n";
                echo "  department_id: $departmentId\n";
                echo "  user_id: $userId\n";
                echo "  current_user_id: $currentUserId\n\n";
                
                // 権限チェック
                if ($userId != $currentUserId && ($_SESSION['role'] ?? '') !== 'admin') {
                    echo "❌ アクセス権限がありません\n";
                } else if (!$departmentId) {
                    echo "❌ 部署IDが必要です\n";
                } else {
                    echo "✅ パラメータチェック通過\n";
                    
                    // 実際のgetUserThemeColor関数を呼び出し
                    try {
                        // getUserThemeColor関数の簡易版を実装
                        $pdo = getDatabase();
                        $stmt = $pdo->prepare("SELECT color FROM user_department_colors WHERE user_id = ? AND department_id = ?");
                        $stmt->execute([$userId, $departmentId]);
                        $userColor = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($userColor) {
                            echo "✅ ユーザー個別カラー取得: " . $userColor['color'] . "\n";
                        } else {
                            echo "ℹ️ ユーザー個別カラーなし、デフォルト取得中...\n";
                            
                            $stmt = $pdo->prepare("SELECT color FROM departments WHERE id = ?");
                            $stmt->execute([$departmentId]);
                            $deptColor = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($deptColor) {
                                echo "✅ 部署デフォルトカラー取得: " . ($deptColor['color'] ?? '#718096') . "\n";
                            } else {
                                echo "ℹ️ 部署カラーなし、システムデフォルト使用: #718096\n";
                            }
                        }
                        
                    } catch (Exception $e) {
                        echo "❌ データベースエラー: " . $e->getMessage() . "\n";
                    }
                }
                echo '</pre>';
            }
            
            // POSTテスト
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                echo '<h4>POST リクエストテスト</h4>';
                echo '<pre>';
                
                $rawInput = file_get_contents('php://input');
                $input = json_decode($rawInput, true);
                
                if (!$input) {
                    echo "❌ 無効なJSONデータです\n";
                } else {
                    $action = $input['action'] ?? '';
                    $departmentId = $input['department_id'] ?? null;
                    $userId = $input['user_id'] ?? $_SESSION['user_id'];
                    $color = $input['color'] ?? null;
                    
                    echo "パラメータ:\n";
                    echo "  action: '$action'\n";
                    echo "  department_id: $departmentId\n";
                    echo "  user_id: $userId\n";
                    echo "  color: $color\n\n";
                    
                    if ($action === 'save') {
                        if (!$color) {
                            echo "❌ カラーが必要です\n";
                        } else if (!$departmentId) {
                            echo "❌ 部署IDが必要です\n";
                        } else {
                            echo "✅ saveアクション - パラメータチェック通過\n";
                        }
                    } else if ($action === 'reset') {
                        if (!$departmentId) {
                            echo "❌ 部署IDが必要です\n";
                        } else {
                            echo "✅ resetアクション - パラメータチェック通過\n";
                        }
                    } else {
                        echo "❌ 無効なアクションです: '$action'\n";
                        echo "利用可能なアクション: 'save', 'reset'\n";
                    }
                }
                echo '</pre>';
            }
            echo '</div>';
        }
        
        // テスト用リンク
        if (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
            echo '<div class="section warning">';
            echo '<h3>🔗 テスト用リンク</h3>';
            echo '<p><a href="debug_user_theme_colors.php?user_id=' . $userId . '&department_id=1">部署ID=1でGETテスト</a></p>';
            echo '<p><a href="debug_user_theme_colors.php?user_id=' . $userId . '&department_id=2">部署ID=2でGETテスト</a></p>';
            echo '<p><a href="debug_user_theme_colors.php?user_id=' . $userId . '&department_id=3">部署ID=3でGETテスト</a></p>';
            echo '</div>';
        }
        ?>
        
        <div class="section info">
            <h3>📝 使用方法</h3>
            <p><strong>GETテスト:</strong> URLパラメータに user_id と department_id を追加</p>
            <p><strong>POSTテスト:</strong> 以下のJSONをPOSTで送信</p>
            <pre>
{
    "action": "save",
    "user_id": 25,
    "department_id": 2,
    "color": "#ff0000"
}
            </pre>
        </div>
    </div>
</body>
</html>