<?php
require_once 'config.php';

header('Content-Type: application/json');

function getDepartments() {
    $pdo = getDatabase();
    try {
        $stmt = $pdo->prepare("SELECT id, name, display_order FROM departments ORDER BY display_order, id");
        $stmt->execute();
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'departments' => $departments];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()];
    }
}

function addDepartment($name, $displayOrder = null) {
    $pdo = getDatabase();
    try {
        if (empty($name)) {
            return ['success' => false, 'message' => '部署名は必須です'];
        }
        
        if ($displayOrder === null) {
            $stmt = $pdo->prepare("SELECT MAX(display_order) as max_order FROM departments");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $displayOrder = ($result['max_order'] ?? 0) + 1;
        }
        
        $stmt = $pdo->prepare("INSERT INTO departments (name, display_order) VALUES (?, ?)");
        $stmt->execute([$name, $displayOrder]);
        
        return ['success' => true, 'message' => '部署が追加されました', 'id' => $pdo->lastInsertId()];
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            return ['success' => false, 'message' => 'この部署名は既に存在します'];
        }
        return ['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()];
    }
}

function updateDepartment($id, $name, $displayOrder = null) {
    $pdo = getDatabase();
    try {
        if (empty($name)) {
            return ['success' => false, 'message' => '部署名は必須です'];
        }
        
        $sql = "UPDATE departments SET name = ?, updated_at = CURRENT_TIMESTAMP";
        $params = [$name];
        
        if ($displayOrder !== null) {
            $sql .= ", display_order = ?";
            $params[] = $displayOrder;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => '部署が更新されました'];
        } else {
            return ['success' => false, 'message' => '該当する部署が見つかりません'];
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            return ['success' => false, 'message' => 'この部署名は既に存在します'];
        }
        return ['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()];
    }
}

function deleteDepartment($id) {
    $pdo = getDatabase();
    try {
        // Check if department is in use
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE department = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            return ['success' => false, 'message' => 'この部署は使用中のため削除できません'];
        }
        
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => '部署が削除されました'];
        } else {
            return ['success' => false, 'message' => '該当する部署が見つかりません'];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()];
    }
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        echo json_encode(getDepartments());
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            echo json_encode(['success' => false, 'message' => '無効なJSONデータです']);
            break;
        }
        
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'add':
                echo json_encode(addDepartment($input['name'] ?? '', $input['display_order'] ?? null));
                break;
            case 'update':
                echo json_encode(updateDepartment($input['id'] ?? 0, $input['name'] ?? '', $input['display_order'] ?? null));
                break;
            case 'delete':
                echo json_encode(deleteDepartment($input['id'] ?? 0));
                break;
            default:
                echo json_encode(['success' => false, 'message' => '無効なアクションです']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'サポートされていないHTTPメソッドです']);
}
?>