// =====================
// Chat通知専用ファイル
// =====================

// Chat通知キュー
let chatNotificationQueue = [];

/**
 * Chat通知をキューに追加
 * @param {number} reservationId 予約ID
 * @param {string} action アクション種別（created, updated, deleted）
 */
function queueChatNotification(reservationId, action) {
    console.log(`[Chat通知] キューに追加: ID=${reservationId}, Action=${action}`);
    
    chatNotificationQueue.push({
        reservationId: reservationId,
        action: action,
        timestamp: Date.now()
    });
    
    console.log(`[Chat通知] キューサイズ: ${chatNotificationQueue.length}`);
    
    // 少し遅延を入れてから処理開始
    setTimeout(() => {
        processChatNotificationQueue();
    }, 500);
}

/**
 * Chat通知キューを処理
 */
async function processChatNotificationQueue() {
    console.log(`[Chat通知] キュー処理開始 - 残り件数: ${chatNotificationQueue.length}`);
    
    if (chatNotificationQueue.length === 0) {
        console.log(`[Chat通知] キューが空のため処理終了`);
        return;
    }
    
    const notification = chatNotificationQueue.shift();
    console.log(`[Chat通知] 処理中: ID=${notification.reservationId}, Action=${notification.action}`);
    
    try {
        await sendAsyncChatNotification(notification.reservationId, notification.action);
        console.log(`[Chat通知] 送信完了: ID=${notification.reservationId}, Action=${notification.action}`);
    } catch (error) {
        console.error('[Chat通知] 送信エラー:', error);
    }
    
    // 次の通知を処理（重複を避けるため少し間隔を空ける）
    if (chatNotificationQueue.length > 0) {
        console.log(`[Chat通知] 次の通知を1秒後に処理 - 残り: ${chatNotificationQueue.length}件`);
        setTimeout(() => {
            processChatNotificationQueue();
        }, 1000);
    }
}

/**
 * 非同期でChat通知を送信
 * @param {number} reservationId 予約ID  
 * @param {string} action アクション種別
 */
async function sendAsyncChatNotification(reservationId, action) {
    try {
        console.log(`[Chat通知] API呼び出し開始: ID=${reservationId}, Action=${action}`);
        
        const response = await fetch('api/send_chat_notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                reservation_id: reservationId,
                action: action
            })
        });
        
        console.log(`[Chat通知] APIレスポンス: ${response.status} ${response.statusText}`);
        
        const result = await response.json();
        console.log(`[Chat通知] APIレスポンス内容:`, result);
        
        if (!result.success) {
            throw new Error(result.message || 'Chat通知の送信に失敗しました');
        }
        
        return result;
        
    } catch (error) {
        console.error('[Chat通知] 送信例外:', error);
        throw error;
    }
}