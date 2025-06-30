<?php
/**
 * CSV予約データ取り込みAPI
 * 検証済みのCSVデータを実際に取り込む
 */

// エラー出力を抑制
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// JSONレスポンスヘッダー設定
header('Content-Type: application/json; charset=utf-8');

// 認証チェック
requireAuth();

try {
    // POSTデータ取得
    $csvDataJson = $_POST['csv_data'] ?? null;
    $duplicateHandling = $_POST['duplicate_handling'] ?? 'skip';
    $validateConflicts = $_POST['validate_conflicts'] ?? '1';
    $sendNotifications = $_POST['send_notifications'] ?? '0';
    
    if (!$csvDataJson) {
        sendJsonResponse(false, 'CSVデータが送信されていません', null, 400);
        exit;
    }
    
    $csvData = json_decode($csvDataJson, true);
    if (!$csvData) {
        sendJsonResponse(false, 'CSVデータの解析に失敗しました', null, 400);
        exit;
    }
    
    $db = getDatabase();
    $db->beginTransaction();
    
    // 統計情報
    $imported = 0;
    $skipped = 0;
    $updated = 0;
    $errors = [];
    
    // 既存ユーザーのメールアドレス一覧を取得
    $userEmails = [];
    $stmt = $db->prepare("SELECT email, id, name FROM users");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $userEmails[strtolower($row['email'])] = $row;
    }
    
    // 各行を処理
    foreach ($csvData['rows'] as $index => $row) {
        $lineNumber = $row['_lineNumber'] ?? ($index + 2);
        
        try {
            // 基本的なバリデーション（再チェック）
            $requiredFields = ['title', 'date', 'start_time', 'end_time', 'user_email'];
            $isValid = true;
            
            foreach ($requiredFields as $field) {
                if (empty($row[$field])) {
                    $isValid = false;
                    break;
                }
            }
            
            if (!$isValid) {
                $errors[] = "行 {$lineNumber}: 必須フィールドが不足しています";
                continue;
            }
            
            // ユーザー情報取得
            $emailKey = strtolower($row['user_email']);
            if (!isset($userEmails[$emailKey])) {
                $errors[] = "行 {$lineNumber}: ユーザーが見つかりません ({$row['user_email']})";
                continue;
            }
            
            $user = $userEmails[$emailKey];
            
            // 日付の正規化
            $dateObj = null;
            $normalizedDate = '';
            
            // 複数の日付形式に対応
            $dateFormats = [
                'Y-m-d',     // 2025-01-15
                'Y/m/d',     // 2025/1/15, 2025/10/16
                'Y/n/j',     // 2025/1/5
                'm/d/Y',     // 1/15/2025, 10/16/2025
                'n/j/Y'      // 1/5/2025
            ];
            
            foreach ($dateFormats as $format) {
                $dateObj = DateTime::createFromFormat($format, $row['date']);
                if ($dateObj && $dateObj->format($format) === $row['date']) {
                    $normalizedDate = $dateObj->format('Y-m-d');
                    break;
                }
            }
            
            if (!$dateObj) {
                $errors[] = "行 {$lineNumber}: 日付形式が正しくありません ({$row['date']})";
                continue;
            }
            
            // 日時データ準備
            $date = $normalizedDate;
            $startDateTime = $date . ' ' . $row['start_time'] . ':00';
            $endDateTime = $date . ' ' . $row['end_time'] . ':00';
            
            // 重複チェック
            if ($validateConflicts === '1') {
                $conflictStmt = $db->prepare("
                    SELECT COUNT(*) as count FROM reservations 
                    WHERE date = ? 
                    AND (
                        (start_datetime < ? AND end_datetime > ?) OR
                        (start_datetime < ? AND end_datetime > ?) OR
                        (start_datetime >= ? AND end_datetime <= ?)
                    )
                ");
                $conflictStmt->execute([
                    $date,
                    $startDateTime, $startDateTime,
                    $endDateTime, $endDateTime,
                    $startDateTime, $endDateTime
                ]);
                
                $conflictResult = $conflictStmt->fetch(PDO::FETCH_ASSOC);
                $hasConflict = $conflictResult && $conflictResult['count'] > 0;
                
                if ($hasConflict) {
                    if ($duplicateHandling === 'error') {
                        throw new Exception("行 {$lineNumber}: 時間重複があります");
                    } elseif ($duplicateHandling === 'skip') {
                        $skipped++;
                        continue;
                    }
                    // 'update'の場合は既存データを削除してから新規作成
                }
            }
            
            // 繰り返し予約の処理
            if (!empty($row['repeat_type']) && !empty($row['repeat_end_date'])) {
                // 繰り返し予約グループを作成
                $groupStmt = $db->prepare("
                    INSERT INTO reservation_groups (repeat_type, repeat_interval, created_at, updated_at) 
                    VALUES (?, 1, datetime('now'), datetime('now'))
                ");
                $groupStmt->execute([$row['repeat_type']]);
                $groupId = $db->lastInsertId();
                
                // 繰り返し終了日の正規化
                $endDateObj = null;
                $normalizedEndDate = '';
                
                foreach ($dateFormats as $format) {
                    $endDateObj = DateTime::createFromFormat($format, $row['repeat_end_date']);
                    if ($endDateObj && $endDateObj->format($format) === $row['repeat_end_date']) {
                        $normalizedEndDate = $endDateObj->format('Y-m-d');
                        break;
                    }
                }
                
                if (!$endDateObj) {
                    $errors[] = "行 {$lineNumber}: 繰り返し終了日の形式が正しくありません ({$row['repeat_end_date']})";
                    continue;
                }
                
                // 繰り返し日程を生成
                $dates = generateRecurringDates(
                    new DateTime($date),
                    new DateTime($normalizedEndDate),
                    $row['repeat_type']
                );
                
                $recurringImported = 0;
                foreach ($dates as $recurringDate) {
                    $recurringDateStr = $recurringDate->format('Y-m-d');
                    $recurringStartDateTime = $recurringDateStr . ' ' . $row['start_time'] . ':00';
                    $recurringEndDateTime = $recurringDateStr . ' ' . $row['end_time'] . ':00';
                    
                    // 各日程で重複チェック
                    if ($validateConflicts === '1') {
                        $recurringConflictStmt = $db->prepare("
                            SELECT COUNT(*) as count FROM reservations 
                            WHERE date = ? 
                            AND (
                                (start_datetime < ? AND end_datetime > ?) OR
                                (start_datetime < ? AND end_datetime > ?) OR
                                (start_datetime >= ? AND end_datetime <= ?)
                            )
                        ");
                        $recurringConflictStmt->execute([
                            $recurringDateStr,
                            $recurringStartDateTime, $recurringStartDateTime,
                            $recurringEndDateTime, $recurringEndDateTime,
                            $recurringStartDateTime, $recurringEndDateTime
                        ]);
                        
                        $recurringConflictResult = $recurringConflictStmt->fetch(PDO::FETCH_ASSOC);
                        $hasRecurringConflict = $recurringConflictResult && $recurringConflictResult['count'] > 0;
                        
                        if ($hasRecurringConflict) {
                            if ($duplicateHandling === 'skip') {
                                continue;
                            } elseif ($duplicateHandling === 'update') {
                                // 既存の予約を削除
                                $deleteStmt = $db->prepare("
                                    DELETE FROM reservations 
                                    WHERE date = ? AND start_datetime = ? AND end_datetime = ?
                                ");
                                $deleteStmt->execute([$recurringDateStr, $recurringStartDateTime, $recurringEndDateTime]);
                            }
                        }
                    }
                    
                    // 予約を作成
                    $reservationStmt = $db->prepare("
                        INSERT INTO reservations (
                            title, description, date, start_datetime, end_datetime, 
                            user_id, group_id, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
                    ");
                    
                    $reservationStmt->execute([
                        $row['title'],
                        $row['description'] ?? '',
                        $recurringDateStr,
                        $recurringStartDateTime,
                        $recurringEndDateTime,
                        $user['id'],
                        $groupId
                    ]);
                    
                    $recurringImported++;
                }
                
                $imported += $recurringImported;
                
            } else {
                // 単発予約の処理
                
                // 重複時の処理
                if ($validateConflicts === '1' && $hasConflict && $duplicateHandling === 'update') {
                    // 既存の予約を削除
                    $deleteStmt = $db->prepare("
                        DELETE FROM reservations 
                        WHERE date = ? AND start_datetime = ? AND end_datetime = ?
                    ");
                    $deleteStmt->execute([$date, $startDateTime, $endDateTime]);
                    $updated++;
                }
                
                // 予約を作成
                $reservationStmt = $db->prepare("
                    INSERT INTO reservations (
                        title, description, date, start_datetime, end_datetime, 
                        user_id, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
                ");
                
                $reservationStmt->execute([
                    $row['title'],
                    $row['description'] ?? '',
                    $date,
                    $startDateTime,
                    $endDateTime,
                    $user['id']
                ]);
                
                $imported++;
            }
            
        } catch (Exception $e) {
            $errors[] = "行 {$lineNumber}: " . $e->getMessage();
        }
    }
    
    // エラーがある場合はロールバック
    if (!empty($errors) && $duplicateHandling === 'error') {
        $db->rollBack();
        sendJsonResponse(false, '取り込み中にエラーが発生しました', ['errors' => $errors], 400);
        exit;
    }
    
    $db->commit();
    
    // メール通知送信（有効化されている場合）
    if ($sendNotifications === '1' && $imported > 0) {
        try {
            // 管理者にまとめて通知
            $adminUsers = [];
            $stmt = $db->prepare("SELECT * FROM users WHERE role = 'admin'");
            $stmt->execute();
            while ($admin = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $adminUsers[] = $admin;
            }
            
            foreach ($adminUsers as $admin) {
                $subject = "[CSV取り込み] {$imported}件の予約が登録されました";
                $message = "CSV取り込みが完了しました。\n\n";
                $message .= "登録件数: {$imported}件\n";
                $message .= "スキップ件数: {$skipped}件\n";
                $message .= "更新件数: {$updated}件\n";
                
                if (!empty($errors)) {
                    $message .= "\nエラー:\n" . implode("\n", $errors);
                }
                
                // メール送信（簡易版）
                mail($admin['email'], $subject, $message, 'From: noreply@reservation-system.com');
            }
        } catch (Exception $e) {
            // メール送信エラーは無視
            error_log("CSV Import notification error: " . $e->getMessage());
        }
    }
    
    // レスポンス
    $responseData = [
        'imported' => $imported,
        'skipped' => $skipped,
        'updated' => $updated,
        'errors' => $errors
    ];
    
    sendJsonResponse(true, 'CSV取り込みが完了しました', $responseData);
    
} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("CSV Import Error: " . $e->getMessage());
    sendJsonResponse(false, 'CSV取り込みに失敗しました: ' . $e->getMessage(), null, 500);
}

/**
 * 繰り返し日程を生成
 */
function generateRecurringDates($startDate, $endDate, $repeatType) {
    $dates = [];
    $currentDate = clone $startDate;
    $maxDates = 100; // 安全制限
    $count = 0;
    
    while ($currentDate <= $endDate && $count < $maxDates) {
        // 平日のみ（月-金）
        $dayOfWeek = (int)$currentDate->format('w');
        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
            $dates[] = clone $currentDate;
        }
        
        // 次の日程を計算
        switch ($repeatType) {
            case 'daily':
                $currentDate->add(new DateInterval('P1D'));
                break;
            case 'weekly':
                $currentDate->add(new DateInterval('P7D'));
                break;
            case 'biweekly':
                $currentDate->add(new DateInterval('P14D'));
                break;
            case 'monthly':
                $currentDate->add(new DateInterval('P1M'));
                break;
            default:
                break 2; // ループを抜ける
        }
        
        $count++;
    }
    
    return $dates;
}
?>