// 予約API通信機能

// 予約データ読み込み（平日表示期間の全予約を取得）
async function loadReservations() {
    try {
        const monthStart = getMonthStart(currentDate);
        const monthEnd = getMonthEnd(currentDate);
        const startDate = formatDate(getWeekdayStart(new Date(monthStart)));
        const endDate = formatDate(getWeekdayEnd(new Date(monthEnd)));
        
        console.log('予約データを読み込み中:', startDate, 'から', endDate);
        
        const response = await fetch(`api/reservations.php?start_date=${startDate}&end_date=${endDate}`);
        const result = await response.json();
        
        console.log('読み込み結果:', result);
        
        if (result.reservations) {
            reservations = result.reservations;
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
        is_recurring: formData.get('is_recurring') === 'on',
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
            renderCalendar();
        } else {
            const message = editId ? '予約の更新に失敗しました' : '予約の作成に失敗しました';
            showMessage(result.error || message, 'error');
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
            renderCalendar();
        } else {
            showMessage(result.error || '予約の削除に失敗しました', 'error');
        }
    } catch (error) {
        console.error('予約削除エラー:', error);
        showMessage('予約の削除に失敗しました', 'error');
    } finally {
        showLoading(false);
    }
}