// 予約API通信機能

// 今日以降の全予約データを読み込み（リスト表示用）
let allFutureReservations = [];

async function loadAllFutureReservations() {
    try {
        console.log('今日以降の全予約データを読み込み中...');
        
        const response = await fetch('api/reservations.php?future_only=true');
        const result = await response.json();
        
        console.log('読み込み結果:', result);
        
        if (result.success && result.data && result.data.reservations) {
            allFutureReservations = result.data.reservations;
            console.log('今日以降の予約データ:', allFutureReservations.length, '件');
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
        
        console.log('予約データを読み込み中:', startDate, 'から', endDate);
        
        const response = await fetch(`api/reservations.php?start_date=${startDate}&end_date=${endDate}`);
        const result = await response.json();
        
        console.log('読み込み結果:', result);
        
        if (result.success && result.data && result.data.reservations) {
            reservations = result.data.reservations;
            console.log('予約データ:', reservations.length, '件');
        } else {
            reservations = [];
            console.log('予約データが空です');
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
    
    const form = e.target;
    const editId = form.getAttribute('data-edit-id');
    const formData = new FormData(form);
    
    const data = {
        title: formData.get('title'),
        description: formData.get('description'),
        date: formData.get('date'),
        start_time: formData.get('start_time'),
        end_time: formData.get('end_time'),
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
        showLoading(true);
        
        const url = 'api/reservations.php';
        const method = editId ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
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
        } else {
            const message = editId ? '予約の更新に失敗しました' : '予約の作成に失敗しました';
            showMessage(result.message || message, 'error');
        }
    } catch (error) {
        console.error('予約処理エラー:', error);
        const message = editId ? '予約の更新に失敗しました' : '予約の作成に失敗しました';
        showMessage(message, 'error');
    } finally {
        showLoading(false);
    }
}

// 予約削除
async function deleteReservation(reservationId) {
    if (!confirm('この予約を削除しますか？')) {
        return;
    }
    
    try {
        showLoading(true);
        const response = await fetch(`api/reservations.php?id=${reservationId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('予約を削除しました', 'success');
            closeModal();
            await loadReservations();
            // リスト表示の場合は今日以降の予約データも更新
            if (currentView === 'list') {
                await loadAllFutureReservations();
            }
            renderCalendar();
        } else {
            showMessage(result.message || '予約の削除に失敗しました', 'error');
        }
    } catch (error) {
        console.error('予約削除エラー:', error);
        showMessage('予約の削除に失敗しました', 'error');
    } finally {
        showLoading(false);
    }
}