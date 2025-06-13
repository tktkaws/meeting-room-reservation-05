// 予約モーダル機能

// 新規予約モーダルを開く
function openNewReservationModal(selectedDate = null, startTime = null, endTime = null) {
    const modal = document.getElementById('reservation-modal');
    const form = document.getElementById('reservation-form');
    const deleteBtn = document.getElementById('delete-btn');
    
    // フォームリセット
    form.reset();
    form.removeAttribute('data-edit-id');
    document.getElementById('modal-title').textContent = '新規予約';
    
    // 削除ボタンを非表示
    if (deleteBtn) {
        deleteBtn.style.display = 'none';
    }
    
    // 時間セレクトボックスを初期化
    initializeTimeSelects();
    
    // 選択された日付を設定
    if (selectedDate) {
        document.getElementById('reservation-date').value = selectedDate;
    } else {
        // 今日の日付を設定
        document.getElementById('reservation-date').value = formatDate(getJapanTime());
    }
    
    // 指定された時間を設定、なければデフォルト時間を設定
    if (startTime && endTime) {
        const startTimeSelect = document.getElementById('start-time');
        const endTimeSelect = document.getElementById('end-time');
        
        if (startTimeSelect) {
            populateTimeSelect(startTimeSelect, startTime);
        }
        
        if (endTimeSelect) {
            populateTimeSelect(endTimeSelect, endTime);
        }
    } else {
        setDefaultTimes();
    }
    
    modal.style.display = 'flex';
}

// 予約詳細表示
async function showReservationDetail(reservationId) {
    try {
        showLoading(true);
        const response = await fetch(`api/reservation_detail.php?id=${reservationId}`);
        const result = await response.json();
        
        if (!result.success) {
            showMessage(result.message || '予約詳細の取得に失敗しました', 'error');
            return;
        }
        
        const reservation = result.data.reservation;
        const canEdit = result.data.can_edit;
        const groupReservations = result.data.group_reservations;
        
        // 詳細情報を表示
        document.getElementById('detail-user').textContent = `${reservation.user_name} (${reservation.department || '部署未設定'})`;
        document.getElementById('detail-title').textContent = reservation.title;
        document.getElementById('detail-date').textContent = reservation.date;
        document.getElementById('detail-time').textContent = `${reservation.start_datetime.split(' ')[1].substring(0,5)} - ${reservation.end_datetime.split(' ')[1].substring(0,5)}`;
        document.getElementById('detail-description').textContent = reservation.description || '説明なし';
        
        // 繰り返し予約情報表示
        const recurringSection = document.getElementById('recurring-section');
        if (reservation.group_id) {
            recurringSection.style.display = 'block';
            
            const repeatTypes = {
                'daily': '毎日',
                'weekly': '毎週',
                'monthly': '毎月'
            };
            document.getElementById('detail-repeat-type').textContent = repeatTypes[reservation.repeat_type] || reservation.repeat_type;
            
            // グループ内の他の予約を表示
            const groupList = document.getElementById('group-list');
            if (groupReservations.length > 0) {
                groupList.innerHTML = '<div class="group-list">' + 
                    groupReservations.map(gr => `
                        <div class="group-item">
                            <div>
                                <div class="group-item-date">${gr.date}</div>
                                <div class="group-item-time">${gr.start_datetime.split(' ')[1].substring(0,5)} - ${gr.end_datetime.split(' ')[1].substring(0,5)}</div>
                            </div>
                        </div>
                    `).join('') + '</div>';
            } else {
                groupList.innerHTML = '<p>他の関連予約はありません</p>';
            }
        } else {
            recurringSection.style.display = 'none';
        }
        
        // 編集ボタンの表示
        const detailActions = document.getElementById('detail-actions');
        detailActions.innerHTML = '';
        
        if (canEdit) {
            if (reservation.group_id) {
                // 繰り返し予約の場合は2つのボタン
                detailActions.innerHTML = `
                    <button class="btn-edit-single" onclick="editSingleReservation(${reservationId})">この予約のみ編集</button>
                    <button class="btn-edit-group" onclick="editGroupReservations(${reservation.group_id})">全ての繰り返し予約を編集</button>
                `;
            } else {
                // 単発予約の場合は1つのボタン
                detailActions.innerHTML = `
                    <button class="btn-edit-single" onclick="editSingleReservation(${reservationId})">編集</button>
                `;
            }
        }
        
        // 詳細モーダルを表示
        document.getElementById('reservation-detail-modal').style.display = 'flex';
        
    } catch (error) {
        console.error('予約詳細取得エラー:', error);
        showMessage('予約詳細の取得に失敗しました', 'error');
    } finally {
        showLoading(false);
    }
}

// 単一予約編集（繰り返しグループから除外）
async function editSingleReservation(reservationId) {
    const reservation = reservations.find(r => r.id == reservationId);
    if (!reservation) {
        showMessage('予約が見つかりません', 'error');
        return;
    }
    
    // 権限チェック
    if (reservation.user_id != currentUser.id && currentUser.role !== 'admin') {
        showMessage('この予約を編集する権限がありません', 'error');
        return;
    }
    
    // 詳細モーダルを閉じる
    document.getElementById('reservation-detail-modal').style.display = 'none';
    
    // 編集モーダルを開く
    const modal = document.getElementById('reservation-modal');
    const form = document.getElementById('reservation-form');
    
    document.getElementById('modal-title').textContent = reservation.group_id ? '個別予約編集（グループから除外）' : '予約編集';
    document.getElementById('reservation-title').value = reservation.title;
    document.getElementById('reservation-description').value = reservation.description || '';
    document.getElementById('reservation-date').value = reservation.date;
    
    // 時間セレクトボックスを初期化してから値を設定
    initializeTimeSelects();
    const startTime = normalizeTimeString(reservation.start_datetime.split(' ')[1].substring(0,5));
    const endTime = normalizeTimeString(reservation.end_datetime.split(' ')[1].substring(0,5));
    
    populateTimeSelect(document.getElementById('start-time'), startTime);
    populateTimeSelect(document.getElementById('end-time'), endTime);
    
    // 繰り返し予約オプションを非表示
    document.getElementById('recurring-no').checked = true;
    document.getElementById('recurring-options').style.display = 'none';
    
    // フォームに編集用のIDを設定
    form.setAttribute('data-edit-id', reservationId);
    form.setAttribute('data-edit-type', 'single');
    
    // 既存の削除ボタンを削除
    const existingDeleteBtn = document.getElementById('delete-btn');
    if (existingDeleteBtn) {
        existingDeleteBtn.remove();
    }
    
    // 削除ボタンを新しく作成
    const deleteBtn = document.createElement('button');
    deleteBtn.id = 'delete-btn';
    deleteBtn.type = 'button';
    deleteBtn.className = 'btn-danger';
    deleteBtn.textContent = '削除';
    deleteBtn.onclick = () => deleteReservation(reservationId);
    
    // modal-actions要素を取得して削除ボタンを追加
    const modalActions = document.querySelector('#reservation-modal .modal-actions');
    const cancelBtn = document.getElementById('cancel-btn');
    
    if (modalActions && cancelBtn) {
        modalActions.insertBefore(deleteBtn, cancelBtn);
    } else if (modalActions) {
        modalActions.appendChild(deleteBtn);
    }
    
    modal.style.display = 'flex';
}

// モーダルを閉じる
function closeModal() {
    const form = document.getElementById('reservation-form');
    const deleteBtn = document.getElementById('delete-btn');
    
    // 編集フラグをクリア
    if (form) {
        form.removeAttribute('data-edit-id');
        form.removeAttribute('data-edit-type');
    }
    
    // 削除ボタンを削除
    if (deleteBtn) {
        deleteBtn.remove();
    }
    
    // フォームリセット
    if (form) {
        form.reset();
    }
    
    const modalTitle = document.getElementById('modal-title');
    if (modalTitle) {
        modalTitle.textContent = '新規予約';
    }
    
    const modal = document.getElementById('reservation-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// 詳細モーダルを閉じる
function closeDetailModal() {
    document.getElementById('reservation-detail-modal').style.display = 'none';
}

// 繰り返し予約オプション切り替え
function toggleRecurringOptions() {
    const recurringYes = document.getElementById('recurring-yes');
    const options = document.getElementById('recurring-options');
    
    if (recurringYes && recurringYes.checked) {
        options.style.display = 'block';
    } else {
        options.style.display = 'none';
    }
}