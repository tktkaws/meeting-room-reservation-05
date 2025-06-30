// =====================
// Email通知専用ファイル
// =====================

// Email通知キュー
let emailNotificationQueue = [];

/**
 * Email通知をキューに追加
 * @param {number} reservationId 予約ID
 * @param {string} action アクション種別（created, updated, deleted）
 */
function queueEmailNotification(reservationId, action) {
    console.log(`[Email通知] キューに追加: ID=${reservationId}, Action=${action}`);
    
    emailNotificationQueue.push({
        reservationId: reservationId,
        action: action,
        timestamp: Date.now()
    });
    
    console.log(`[Email通知] キューサイズ: ${emailNotificationQueue.length}`);
    
    // 削除以外は非同期で処理（削除は同期処理）
    if (action === 'deleted') {
        // 削除は即座に処理（同期）
        console.log(`[Email通知] 削除通知は同期処理`);
        processEmailNotificationQueue();
    } else {
        // 作成・更新は非同期処理
        setTimeout(() => {
            processEmailNotificationQueue();
        }, 500);
    }
}

/**
 * Email通知キューを処理
 */
async function processEmailNotificationQueue() {
    console.log(`[Email通知] キュー処理開始 - 残り件数: ${emailNotificationQueue.length}`);
    
    if (emailNotificationQueue.length === 0) {
        console.log(`[Email通知] キューが空のため処理終了`);
        return;
    }
    
    const notification = emailNotificationQueue.shift();
    console.log(`[Email通知] 処理中: ID=${notification.reservationId}, Action=${notification.action}`);
    
    try {
        await sendAsyncEmailNotification(notification.reservationId, notification.action);
        console.log(`[Email通知] 送信完了: ID=${notification.reservationId}, Action=${notification.action}`);
    } catch (error) {
        console.error('[Email通知] 送信エラー:', error);
    }
    
    // 次の通知を処理（重複を避けるため少し間隔を空ける）
    if (emailNotificationQueue.length > 0) {
        console.log(`[Email通知] 次の通知を1秒後に処理 - 残り: ${emailNotificationQueue.length}件`);
        setTimeout(() => {
            processEmailNotificationQueue();
        }, 1000);
    }
}

/**
 * 非同期でEmail通知を送信
 * @param {number} reservationId 予約ID  
 * @param {string} action アクション種別
 */
async function sendAsyncEmailNotification(reservationId, action) {
    try {
        console.log(`[Email通知] API呼び出し開始: ID=${reservationId}, Action=${action}`);
        
        const response = await fetch('api/send_email_notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                reservation_id: reservationId,
                action: action
            })
        });
        
        console.log(`[Email通知] APIレスポンス: ${response.status} ${response.statusText}`);
        
        const result = await response.json();
        console.log(`[Email通知] APIレスポンス内容:`, result);
        
        if (!result.success) {
            throw new Error(result.message || 'Email通知の送信に失敗しました');
        }
        
        return result;
        
    } catch (error) {
        console.error('[Email通知] 送信例外:', error);
        throw error;
    }
}