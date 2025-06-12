// グループ編集機能

// グループ全体編集
async function editGroupReservations(groupId) {
    try {
        showLoading(true);
        
        // グループ情報と予約一覧を取得
        const response = await fetch(`api/group_edit.php?group_id=${groupId}`);
        const result = await response.json();
        
        if (result.error) {
            showMessage(result.error, 'error');
            return;
        }
        
        // 詳細モーダルを閉じる
        document.getElementById('reservation-detail-modal').style.display = 'none';
        
        // グループ編集モーダルを表示
        showGroupEditModal(result.group, result.reservations);
        
    } catch (error) {
        console.error('グループ編集データ取得エラー:', error);
        showMessage('グループ編集データの取得に失敗しました', 'error');
    } finally {
        showLoading(false);
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
        const startTime = normalizeTimeString(firstReservation.start_datetime.split(' ')[1].substring(0, 5));
        const endTime = normalizeTimeString(firstReservation.end_datetime.split(' ')[1].substring(0, 5));
        
        populateTimeSelect(document.getElementById('group-start-time'), startTime);
        populateTimeSelect(document.getElementById('group-end-time'), endTime);
    }
    
    // グループIDを保存
    form.setAttribute('data-group-id', group.id);
    
    // 予約リスト生成
    const reservationsList = document.getElementById('group-reservations-list');
    reservationsList.innerHTML = '';
    
    reservations.forEach(reservation => {
        const startTime = reservation.start_datetime.split(' ')[1].substring(0, 5);
        const endTime = reservation.end_datetime.split(' ')[1].substring(0, 5);
        
        const itemDiv = document.createElement('div');
        itemDiv.className = 'reservation-display-item';
        itemDiv.innerHTML = `
            <div class="reservation-info">
                <div class="reservation-date">${reservation.date}</div>
                <div class="reservation-time">${startTime} - ${endTime}</div>
            </div>
        `;
        
        reservationsList.appendChild(itemDiv);
    });
    
    modal.style.display = 'flex';
}

// グループ編集フォーム送信
async function handleGroupEditSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const groupId = form.getAttribute('data-group-id');
    const formData = new FormData(form);
    
    const newStartTime = formData.get('start_time');
    const newEndTime = formData.get('end_time');
    
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
        showLoading(true);
        
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
            showMessage(result.error || 'グループ編集に失敗しました', 'error');
        }
        
    } catch (error) {
        console.error('グループ編集エラー:', error);
        showMessage('グループ編集に失敗しました', 'error');
    } finally {
        showLoading(false);
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