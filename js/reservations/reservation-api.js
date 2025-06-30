// 予約API通信機能

// 今日以降の全予約データを読み込み（リスト表示用）
let allFutureReservations = [];

async function loadAllFutureReservations() {
    try {
        // console.log('今日以降の全予約データを読み込み中...');
        
        const response = await fetch('api/reservations.php?future_only=true');
        const result = await response.json();
        
        // console.log('読み込み結果:', result);
        
        if (result.success && result.data && result.data.reservations) {
            allFutureReservations = result.data.reservations;
            // console.log('今日以降の予約データ:', allFutureReservations.length, '件');
        } else {
            allFutureReservations = [];
            console.log('今日以降の予約データが空です');
        }
    } catch (error) {
        console.error('今日以降の予約データ読み込みエラー:', error);
        showMessage('予約データの読み込みに失敗しました', 'error');
        allFutureReservations = [];
    }
}

// 予約データ読み込み（表示期間の全予約を取得）
async function loadReservations() {
    try {
        let startDate, endDate;
        
        if (currentView === 'week') {
            // 週間表示の場合は表示される週の範囲
            const weekStart = getWeekStart(currentDate);
            const weekEnd = getWeekEnd(currentDate);
            startDate = formatDate(weekStart);
            endDate = formatDate(weekEnd);
        } else {
            // 月表示の場合は従来通り
            const monthStart = getMonthStart(currentDate);
            const monthEnd = getMonthEnd(currentDate);
            startDate = formatDate(getWeekdayStart(new Date(monthStart)));
            endDate = formatDate(getWeekdayEnd(new Date(monthEnd)));
        }
        
        // console.log('予約データを読み込み中:', startDate, 'から', endDate);
        
        const url = `api/reservations.php?start_date=${startDate}&end_date=${endDate}`;
        // console.log('API URL:', url);
        const response = await fetch(url);
        // console.log('API Response status:', response.status);
        
        // レスポンステキストも取得して確認
        const responseText = await response.text();
        // console.log('API Response text:', responseText);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('JSON parse error:', e);
            console.log('Invalid JSON response:', responseText);
            return;
        }
        
        // console.log('読み込み結果:', result);
        // console.log('success field type:', typeof result.success, 'value:', result.success);
        // console.log('message field:', result.message);
        // console.log('data field:', result.data);
        
        if (result.success && result.data && result.data.reservations) {
            reservations = result.data.reservations;
            // console.log('予約データ:', reservations.length, '件');
        } else {
            reservations = [];
            // console.log('予約データが空です - API結果:', result);
        }
    } catch (error) {
        console.error('予約データ読み込みエラー:', error);
        showMessage('予約データの読み込みに失敗しました', 'error');
        reservations = [];
    }
}

// 予約フォーム送信
async function handleReservationSubmit(e) {
    e.preventDefault();
    console.log('[予約API] handleReservationSubmit開始 - フォーム送信処理');
    
    const form = e.target;
    const editId = form.getAttribute('data-edit-id');
    const formData = new FormData(form);
    
    console.log('[予約API] フォーム情報:', {
        formId: form.id,
        editId: editId,
        action: form.action || 'なし'
    });
    
    // 時間を組み立て
    const startHour = formData.get('start_hour') || document.getElementById('start-hour')?.value;
    const startMinute = formData.get('start_minute') || document.getElementById('start-minute')?.value;
    const endHour = formData.get('end_hour') || document.getElementById('end-hour')?.value;
    const endMinute = formData.get('end_minute') || document.getElementById('end-minute')?.value;
    
    const startTime = `${startHour.toString().padStart(2, '0')}:${startMinute.toString().padStart(2, '0')}`;
    const endTime = `${endHour.toString().padStart(2, '0')}:${endMinute.toString().padStart(2, '0')}`;

    const data = {
        title: formData.get('title'),
        description: formData.get('description'),
        date: formData.get('date'),
        start_time: startTime,
        end_time: endTime,
        is_recurring: formData.get('is_recurring') === 'yes',
        repeat_type: formData.get('repeat_type'),
        repeat_end_date: formData.get('repeat_end_date')
    };
    
    // 編集の場合はIDと編集タイプを追加
    if (editId) {
        data.id = editId;
        data.edit_type = form.getAttribute('data-edit-type') || 'single';
    }
    
    try {
        // ローディング表示
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.style.display = 'flex';
        }
        
        const url = 'api/reservations.php';
        const method = editId ? 'PUT' : 'POST';
        
        console.log('[予約API] リクエスト送信:', {
            url: url,
            method: method,
            data: data
        });
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        console.log('[予約API] レスポンスステータス:', response.status);
        
        // レスポンステキストを先に取得
        const responseText = await response.text();
        console.log('[予約API] レスポンステキスト:', responseText);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('[予約API] JSON解析エラー:', e);
            console.error('[予約API] 受信したテキスト:', responseText);
            showMessage('サーバーエラーが発生しました', 'error');
            return;
        }
        
        if (result.success) {
            const message = editId ? '予約を更新しました' : '予約を作成しました';
            showMessage(message, 'success');
            closeModal();
            await loadReservations();
            // リスト表示の場合は今日以降の予約データも更新
            if (currentView === 'list') {
                await loadAllFutureReservations();
            }
            renderCalendar();
            
            // Chat通知を非同期で送信（コメントアウト）
            /*
            try {
                const reservationId = editId || result.reservation_id || (result.data && result.data.reservation_id);
                const action = editId ? 'updated' : 'created';
                console.log(`[予約API] Chat通知準備: reservationId=${reservationId}, action=${action}, editId=${editId}`);
                console.log('[予約API] 完全なresult:', result);
                
                if (reservationId) {
                    console.log(`[予約API] Chat通知をキューに追加開始`);
                    if (typeof queueChatNotification === 'function') {
                        queueChatNotification(reservationId, action);
                    } else {
                        console.error('[予約API] queueChatNotification関数が見つかりません');
                    }
                } else {
                    console.warn(`[予約API] 予約IDが取得できませんでした - editId=${editId}, result:`, result);
                }
            } catch (chatError) {
                console.error('[予約API] Chat通知キューエラー:', chatError);
            }
            */
            
            // Email通知を非同期で送信
            try {
                const reservationId = editId || result.reservation_id || (result.data && result.data.reservation_id);
                const action = editId ? 'updated' : 'created';
                console.log(`[予約API] Email通知準備: reservationId=${reservationId}, action=${action}, editId=${editId}`);
                
                if (reservationId) {
                    console.log(`[予約API] Email通知をキューに追加開始`);
                    if (typeof queueEmailNotification === 'function') {
                        queueEmailNotification(reservationId, action);
                    } else {
                        console.error('[予約API] queueEmailNotification関数が見つかりません');
                    }
                } else {
                    console.warn(`[予約API] Email通知用の予約IDが取得できませんでした - editId=${editId}, result:`, result);
                }
            } catch (emailError) {
                console.error('[予約API] Email通知キューエラー:', emailError);
            }
        } else {
            const message = editId ? '予約の更新に失敗しました' : '予約の作成に失敗しました';
            showMessage(result.message || message, 'error');
        }
    } catch (error) {
        console.error('予約処理エラー:', error);
        const message = editId ? '予約の更新に失敗しました' : '予約の作成に失敗しました';
        showMessage(message, 'error');
    } finally {
        // ローディング非表示
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.style.display = 'none';
        }
    }
}

// 予約削除
async function deleteReservation(reservationId) {
    
    try {
        // ローディング表示
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.style.display = 'flex';
        }
        const response = await fetch(`api/reservations.php?id=${reservationId}`, {
            method: 'DELETE'
        });
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('HTTP エラー:', response.status, response.statusText, errorText);
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const responseText = await response.text();
        console.log('削除API レスポンス (生テキスト):', responseText);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON パースエラー:', parseError);
            console.error('レスポンス内容:', responseText);
            throw new Error('サーバーからの応答が無効な形式です');
        }
        
        if (result.success) {
            showMessage('予約を削除しました', 'success');
            
            // 開いているモーダルを全て閉じる
            closeModal(); // 予約作成・編集モーダル
            closeDetailModal(); // 予約詳細モーダル
            
            await loadReservations();
            // リスト表示の場合は今日以降の予約データも更新
            if (currentView === 'list') {
                await loadAllFutureReservations();
            }
            renderCalendar();
            
            // 削除の場合はPHP側で同期処理済み（削除前データを使用）（Chat通知はコメントアウト）
            console.log(`[予約API] 削除Chat通知はコメントアウト済み、Email通知はPHP側で処理済み: reservationId=${reservationId}`);
        } else {
            console.error('削除エラー:', result);
            showMessage(result.message || '予約の削除に失敗しました', 'error');
        }
    } catch (error) {
        console.error('予約削除エラー:', error);
        showMessage('予約の削除に失敗しました', 'error');
    } finally {
        // ローディング非表示
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.style.display = 'none';
        }
    }
}