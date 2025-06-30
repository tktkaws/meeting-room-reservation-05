// グループ編集機能

// グループ全体編集
async function editGroupReservations(groupId) {
    try {
        // ローディング表示
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.style.display = 'flex';
        }
        
        // グループ情報と予約一覧を取得
        const response = await fetch(`api/group_edit.php?group_id=${groupId}`);
        const result = await response.json();
        
        if (!result.success) {
            showMessage(result.message || 'グループ情報の取得に失敗しました', 'error');
            return;
        }
        
        // 詳細モーダルを閉じる
        document.getElementById('reservation-detail-modal').style.display = 'none';
        
        // グループ編集モーダルを表示
        showGroupEditModal(result.data.group, result.data.reservations);
        
    } catch (error) {
        console.error('グループ編集データ取得エラー:', error);
        showMessage('グループ編集データの取得に失敗しました', 'error');
    } finally {
        // ローディング非表示
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.style.display = 'none';
        }
    }
}

// グループ編集モーダル表示
function showGroupEditModal(group, reservations) {
    const modal = document.getElementById('group-edit-modal');
    const form = document.getElementById('group-edit-form');
    
    // 基本情報設定
    document.getElementById('group-title').value = group.title;
    document.getElementById('group-description').value = group.description || '';
    
    // 時間セレクトボックスを初期化
    initializeTimeSelects();
    
    // 時間フォームの初期化（最初の予約の時間を設定）
    if (reservations.length > 0) {
        const firstReservation = reservations[0];
        const startTimeStr = firstReservation.start_datetime.split(' ')[1].substring(0, 5);
        const endTimeStr = firstReservation.end_datetime.split(' ')[1].substring(0, 5);
        const [startHour, startMinute] = startTimeStr.split(':').map(Number);
        const [endHour, endMinute] = endTimeStr.split(':').map(Number);
        
        // 新しいフォーム要素に値を設定
        document.getElementById('group-start-hour').value = startHour;
        document.getElementById('group-start-minute').value = startMinute;
        document.getElementById('group-end-hour').value = endHour;
        document.getElementById('group-end-minute').value = endMinute;
    }
    
    // グループIDを保存
    form.setAttribute('data-group-id', group.id);
    
    // 予約リスト生成（予約詳細と同じ体裁）
    const reservationsList = document.getElementById('group-reservations-list');
    reservationsList.innerHTML = '<div class="group-list">' + 
        reservations.map(reservation => {
            const date = new Date(reservation.date);
            const dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][date.getDay()];
            const monthDay = `${date.getMonth() + 1}/${date.getDate()}`;
            const startTime = reservation.start_datetime.split(' ')[1].substring(0, 5);
            const endTime = reservation.end_datetime.split(' ')[1].substring(0, 5);
            
            return `
                <div class="group-item">
                    <div class="group-item-info">
                        <span class="group-date">${monthDay}</span>
                        <span class="group-day">(${dayOfWeek})</span>
                        <span class="group-time">${startTime}-${endTime}</span>
                    </div>
                </div>
            `;
        }).join('') + '</div>';
    
    modal.style.display = 'flex';
    
    // 文字数カウンターを更新
    if (window.refreshCharCounters) {
        window.refreshCharCounters();
    }
}

// グループ編集フォーム送信
async function handleGroupEditSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const groupId = form.getAttribute('data-group-id');
    const formData = new FormData(form);
    
    // 時間を組み立て
    const startHour = formData.get('start_hour') || document.getElementById('group-start-hour')?.value;
    const startMinute = formData.get('start_minute') || document.getElementById('group-start-minute')?.value;
    const endHour = formData.get('end_hour') || document.getElementById('group-end-hour')?.value;
    const endMinute = formData.get('end_minute') || document.getElementById('group-end-minute')?.value;
    
    const newStartTime = `${startHour.toString().padStart(2, '0')}:${startMinute.toString().padStart(2, '0')}`;
    const newEndTime = `${endHour.toString().padStart(2, '0')}:${endMinute.toString().padStart(2, '0')}`;
    
    // 入力検証
    if (newStartTime && newEndTime) {
        if (newStartTime >= newEndTime) {
            showMessage('終了時間は開始時間より後にしてください', 'error');
            return;
        }
    }
    
    const data = {
        group_id: parseInt(groupId),
        title: formData.get('title'),
        description: formData.get('description'),
        bulk_time_update: {
            start_time: newStartTime,
            end_time: newEndTime
        }
    };
    
    try {
        // ローディング表示
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.style.display = 'flex';
        }
        
        const response = await fetch('api/group_edit.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage(result.message, 'success');
            closeGroupEditModal();
            await loadReservations();
            renderCalendar();
        } else {
            showMessage(result.message || 'グループ編集に失敗しました', 'error');
        }
        
    } catch (error) {
        console.error('グループ編集エラー:', error);
        showMessage('グループ編集に失敗しました', 'error');
    } finally {
        // ローディング非表示
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.style.display = 'none';
        }
    }
}

// グループ編集モーダルを閉じる
function closeGroupEditModal() {
    const modal = document.getElementById('group-edit-modal');
    const form = document.getElementById('group-edit-form');
    
    form.reset();
    form.removeAttribute('data-group-id');
    document.getElementById('group-reservations-list').innerHTML = '';
    
    modal.style.display = 'none';
}