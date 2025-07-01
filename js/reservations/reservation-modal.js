// 予約モーダル機能

// 現在表示中の予約詳細データを保存
let currentReservationDetail = null;

// モーダル表示関数
function showModal(modal) {
    modal.style.display = 'flex';
}

// モーダル非表示関数
function hideModal(modal) {
    modal.style.display = 'none';
}

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
    }
    
    // 指定された時間を設定、なければデフォルト時間を設定
    if (startTime && endTime) {
        // HH:MM形式の時間文字列から時と分を分離
        const [startHour, startMinute] = startTime.split(':').map(Number);
        const [endHour, endMinute] = endTime.split(':').map(Number);
        
        // 新しいフォーム要素に値を設定
        document.getElementById('start-hour').value = startHour;
        document.getElementById('start-minute').value = startMinute;
        document.getElementById('end-hour').value = endHour;
        document.getElementById('end-minute').value = endMinute;
    } else {
        setDefaultTimes();
    }
    
    // 繰り返し設定を初期化
    resetRecurringOptions();
    
    // 新規予約では繰り返し設定を表示
    showRecurringOptionsForNewReservation();
    
    // モーダルを画面中央に配置
    positionModalCenter(modal);
    modal.style.display = 'flex';
    
    // 文字数カウンターを更新
    if (window.refreshCharCounters) {
        window.refreshCharCounters();
    }
}

// 予約詳細表示
async function showReservationDetail(reservationId) {
    try {
        // ui-utils.jsのshowLoading関数を直接参照
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.style.display = 'flex';
        }
        const response = await fetch(`api/reservation_detail.php?id=${reservationId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        // console.log('Reservation detail API response:', result);
        
        if (!result.success) {
            showMessage(result.message || '予約詳細の取得に失敗しました', 'error');
            const loadingEl = document.getElementById('loading');
            if (loadingEl) {
                loadingEl.style.display = 'none';
            }
            return;
        }
        
        // 新しいAPIレスポンス形式に対応
        const data = result.data || result;
        const reservation = data.reservation;
        const canEdit = data.can_edit;
        const groupReservations = data.group_reservations;
        
        // console.log('Reservation data:', reservation);
        
        if (!reservation) {
            showMessage('予約データが取得できませんでした', 'error');
            const loadingEl = document.getElementById('loading');
            if (loadingEl) {
                loadingEl.style.display = 'none';
            }
            return;
        }
        
        // 現在の予約詳細データを保存
        currentReservationDetail = {
            reservation: reservation,
            canEdit: canEdit,
            groupReservations: groupReservations
        };
        
        // 詳細情報を表示
        const detailUser = document.getElementById('detail-user');
        const detailTitle = document.getElementById('detail-title');
        
        if (!detailUser || !detailTitle) {
            console.error('Required DOM elements not found');
            showMessage('予約詳細モーダルの表示に失敗しました', 'error');
            const loadingEl = document.getElementById('loading');
            if (loadingEl) {
                loadingEl.style.display = 'none';
            }
            return;
        }
        
        detailUser.textContent = `${reservation.user_name} (${reservation.department_name || '部署未設定'})`;
        detailTitle.textContent = reservation.title;
        
        // 日付をMM月DD日（曜日）形式で表示
        const detailDate = document.getElementById('detail-date');
        const detailTime = document.getElementById('detail-time');
        const detailDescription = document.getElementById('detail-description');
        
        if (!detailDate || !detailTime || !detailDescription) {
            console.error('Detail display elements not found');
            showMessage('予約詳細モーダルの表示に失敗しました', 'error');
            const loadingEl = document.getElementById('loading');
            if (loadingEl) {
                loadingEl.style.display = 'none';
            }
            return;
        }
        
        const date = new Date(reservation.date);
        const month = date.getMonth() + 1;
        const day = date.getDate();
        const dayNames = ['日', '月', '火', '水', '木', '金', '土'];
        const dayOfWeek = dayNames[date.getDay()];
        detailDate.textContent = `${month}月${day}日（${dayOfWeek}）`;
        
        detailTime.textContent = `${reservation.start_datetime.split(' ')[1].substring(0,5)} - ${reservation.end_datetime.split(' ')[1].substring(0,5)}`;
        detailDescription.textContent = reservation.description || '説明なし';
        
        // 繰り返し予約情報表示
        const recurringSection = document.getElementById('recurring-section');
        if (!recurringSection) {
            console.error('recurring-section element not found');
            showMessage('予約詳細モーダルの表示に失敗しました', 'error');
            const loadingEl = document.getElementById('loading');
            if (loadingEl) {
                loadingEl.style.display = 'none';
            }
            return;
        }
        
        if (reservation.group_id) {
            recurringSection.style.display = 'block';
            
            // 繰り返し予約用のアクションボタンを追加
            const recurringDetailActions = document.getElementById('recurring-detail-actions');
            if (canEdit && recurringDetailActions) {
                recurringDetailActions.innerHTML = `
                    <button class="detail-actions-btn" title="全ての予約を編集" onclick="editGroupReservations(${reservation.group_id})"><img src="images/edit.svg" alt="" class="material-icon"></button>
                    <button class="detail-actions-btn" title="全ての予約を削除" onclick="deleteAllGroupReservations(${reservation.group_id})"><img src="images/delete.svg" alt="" class="material-icon"></button>
                `;
            }
            
            // グループ内の全ての予約を表示（現在の予約も含む）
            const groupList = document.getElementById('group-list');
            if (!groupList) {
                console.error('group-list element not found');
                showMessage('予約詳細モーダルの表示に失敗しました', 'error');
                const loadingEl = document.getElementById('loading');
                if (loadingEl) {
                    loadingEl.style.display = 'none';
                }
                return;
            }
            
            if (groupReservations.length > 0) {
                groupList.innerHTML = '<div class="group-list">' + 
                    groupReservations.map(gr => {
                        const date = new Date(gr.date);
                        const dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][date.getDay()];
                        const monthDay = `${date.getMonth() + 1}/${date.getDate()}`;
                        const startTime = gr.start_datetime.split(' ')[1].substring(0,5);
                        const endTime = gr.end_datetime.split(' ')[1].substring(0,5);
                        const isCurrent = gr.id == reservationId;
                        return `
                            <div class="group-item ${isCurrent ? 'current' : ''}">
                                <div class="group-item-info">
                                    <span class="group-date">${monthDay}</span>
                                    <span class="group-day">(${dayOfWeek})</span>
                                    <span class="group-time">${startTime}-${endTime}</span>
                                </div>
                            </div>
                        `;
                    }).join('') + '</div>';
            } else {
                groupList.innerHTML = '<p>関連予約はありません</p>';
            }
        } else {
            recurringSection.style.display = 'none';
            const recurringDetailActions = document.getElementById('recurring-detail-actions');
            if (recurringDetailActions) {
                recurringDetailActions.innerHTML = '';
            }
        }
        
        // 編集ボタンの表示
        const detailActions = document.getElementById('detail-actions');
        if (!detailActions) {
            console.error('detail-actions element not found');
            showMessage('予約詳細モーダルの表示に失敗しました', 'error');
            const loadingEl = document.getElementById('loading');
            if (loadingEl) {
                loadingEl.style.display = 'none';
            }
            return;
        }
        
        detailActions.innerHTML = '';
        
        if (canEdit) {
            if (reservation.group_id) {
                // 繰り返し予約の場合は編集ボタンを上段、削除ボタンを下段に配置
                detailActions.innerHTML = `
                    <button class="detail-actions-btn" title="この予約を編集" onclick="editSingleReservation(${reservationId})">
                            <img src="images/edit.svg" alt="" class="material-icon">
                        </button>
                        <button class="detail-actions-btn" title="この予約を削除" onclick="deleteReservation(${reservationId})">
                            <img src="images/delete.svg" alt="" class="material-icon">
                        </button>
                `;
            } else {
                // 単発予約の場合は編集ボタンと削除ボタンを1列に配置
                detailActions.innerHTML = `
                   <button class="detail-actions-btn" title="編集" onclick="editSingleReservation(${reservationId})">
                            <img src="images/edit.svg" alt="" class="material-icon">
                        </button>
                        <button class="detail-actions-btn" title="削除" onclick="deleteReservation(${reservationId})">
                            <img src="images/delete.svg" alt="" class="material-icon">
                        </button>
                `;
            }
        }
        
        // 詳細モーダルを表示
        const modal = document.getElementById('reservation-detail-modal');
        if (!modal) {
            console.error('reservation-detail-modal element not found');
            showMessage('予約詳細モーダルの表示に失敗しました', 'error');
            const loadingEl = document.getElementById('loading');
            if (loadingEl) {
                loadingEl.style.display = 'none';
            }
            return;
        }
        
        // モーダルを画面中央に配置
        positionModalCenter(modal);
        modal.style.display = 'flex';
        // console.log('Reservation detail modal displayed successfully'); 
        
    } catch (error) {
        console.error('予約詳細取得エラー:', error);
        showMessage('予約詳細の取得に失敗しました', 'error');
    } finally {
        // console.log('Hiding loading in finally block'); 
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.style.display = 'none';
        }
    }
}

// 単一予約編集（繰り返しグループから除外）
async function editSingleReservation(reservationId) {
    if (!currentReservationDetail || !currentReservationDetail.reservation) {
        showMessage('予約データが見つかりません', 'error');
        return;
    }
    
    const reservation = currentReservationDetail.reservation;
    const canEdit = currentReservationDetail.canEdit;
    
    // 権限チェック
    if (!canEdit) {
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
    
    // 時間文字列から時と分を分離
    const startTimeStr = reservation.start_datetime.split(' ')[1].substring(0,5);
    const endTimeStr = reservation.end_datetime.split(' ')[1].substring(0,5);
    const [startHour, startMinute] = startTimeStr.split(':').map(Number);
    const [endHour, endMinute] = endTimeStr.split(':').map(Number);
    
    // 新しいフォーム要素に値を設定
    document.getElementById('start-hour').value = startHour;
    document.getElementById('start-minute').value = startMinute;
    document.getElementById('end-hour').value = endHour;
    document.getElementById('end-minute').value = endMinute;
    
    // 編集モードでは繰り返し予約設定を完全に非表示
    const recurringGroup = document.querySelector('.form-group:has([name="is_recurring"])');
    if (recurringGroup) {
        recurringGroup.style.display = 'none';
    }
    document.getElementById('recurring-options').style.display = 'none';
    
    // フォームに編集用のIDを設定
    form.setAttribute('data-edit-id', reservationId);
    form.setAttribute('data-edit-type', 'single');
    
    // モーダルを画面中央に配置
    positionModalCenter(modal);
    modal.style.display = 'flex';
    
    // 文字数カウンターを更新
    if (window.refreshCharCounters) {
        window.refreshCharCounters();
    }
}

// 新規予約モーダルを開く際は繰り返し設定を表示
function showRecurringOptionsForNewReservation() {
    const recurringGroup = document.querySelector('.form-group:has([name="is_recurring"])');
    if (recurringGroup) {
        recurringGroup.style.display = 'block';
    }
}

// 繰り返し設定を初期化
function resetRecurringOptions() {
    // 繰り返さないをデフォルトに設定
    const recurringNo = document.getElementById('recurring-no');
    const recurringYes = document.getElementById('recurring-yes');
    if (recurringNo) {
        recurringNo.checked = true;
    }
    if (recurringYes) {
        recurringYes.checked = false;
    }
    
    // 繰り返しオプションを非表示
    const options = document.getElementById('recurring-options');
    if (options) {
        options.style.display = 'none';
    }
    
    // プレビューを非表示
    const preview = document.getElementById('recurring-preview');
    if (preview) {
        preview.style.display = 'none';
    }
    
    // 終了日をクリア
    const endDateInput = document.getElementById('repeat-end-date');
    if (endDateInput) {
        endDateInput.value = '';
    }
    
    // 繰り返しタイプをデフォルトに戻す
    const repeatTypeSelect = document.getElementById('repeat-type');
    if (repeatTypeSelect) {
        repeatTypeSelect.value = 'weekly';
    }
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
    
    // 繰り返し設定を初期化
    resetRecurringOptions();
    
    // 繰り返し設定表示を復元
    showRecurringOptionsForNewReservation();
    
    const modal = document.getElementById('reservation-modal');
    if (modal) {
        modal.style.display = 'none';
    }
    
}

// 詳細モーダルを閉じる
function closeDetailModal() {
    document.getElementById('reservation-detail-modal').style.display = 'none';
    // 保存された予約詳細データをクリア
    currentReservationDetail = null;
}


// 繰り返し予約オプション切り替え
function toggleRecurringOptions() {
    const recurringYes = document.getElementById('recurring-yes');
    const options = document.getElementById('recurring-options');
    
    if (recurringYes && recurringYes.checked) {
        options.style.display = 'block';
        setupRecurringEndDateDefault();
    } else {
        options.style.display = 'none';
        // プレビューを非表示
        document.getElementById('recurring-preview').style.display = 'none';
    }
}

// 繰り返し予約の終了日デフォルト設定
function setupRecurringEndDateDefault() {
    const dateInput = document.getElementById('reservation-date');
    const endDateInput = document.getElementById('repeat-end-date');
    
    if (dateInput && endDateInput && dateInput.value) {
        // 選択された日付から1週間後を設定
        const startDate = new Date(dateInput.value);
        const endDate = new Date(startDate);
        endDate.setDate(endDate.getDate() + 7);
        
        // YYYY-MM-DD形式に変換
        const formattedEndDate = endDate.toISOString().split('T')[0];
        endDateInput.value = formattedEndDate;
        
        // 予約日一覧を更新
        updateRecurringPreview();
    }
}

// 繰り返し予約プレビューの更新
function updateRecurringPreview() {
    const startDateInput = document.getElementById('reservation-date');
    const endDateInput = document.getElementById('repeat-end-date');
    const repeatTypeSelect = document.getElementById('repeat-type');
    const previewContainer = document.getElementById('recurring-preview');
    const datesList = document.getElementById('recurring-dates-list');
    
    if (!startDateInput.value || !endDateInput.value || !repeatTypeSelect.value) {
        previewContainer.style.display = 'none';
        return;
    }
    
    const startDate = new Date(startDateInput.value);
    const endDate = new Date(endDateInput.value);
    const repeatType = repeatTypeSelect.value;
    
    // 予約日一覧を生成
    const dates = generateRecurringDates(startDate, endDate, repeatType);
    
    if (dates.length === 0) {
        previewContainer.style.display = 'none';
        return;
    }
    
    // プレビューを表示
    previewContainer.style.display = 'block';
    
    // 日付リストを生成
    let html = '';
    dates.forEach((date, index) => {
        const dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][date.getDay()];
        const formattedDate = `${date.getMonth() + 1}月${date.getDate()}日（${dayOfWeek}）`;
        
        html += `<div class="recurring-date-item">
            <span>${formattedDate}</span>
            <span>#${index + 1}</span>
        </div>`;
    });
    
    datesList.innerHTML = html;
}

// 繰り返し日付の生成
function generateRecurringDates(startDate, endDate, repeatType) {
    const dates = [];
    let currentDate = new Date(startDate);
    let count = 0;
    const maxDates = 50; // 最大50件まで
    
    while (currentDate <= endDate && count < maxDates) {
        // 土日をスキップ
        const dayOfWeek = currentDate.getDay();
        if (dayOfWeek !== 0 && dayOfWeek !== 6) { // 0=日曜, 6=土曜
            dates.push(new Date(currentDate));
            count++;
        }
        
        // 次の日付を計算
        switch (repeatType) {
            case 'daily':
                currentDate.setDate(currentDate.getDate() + 1);
                break;
            case 'weekly':
                currentDate.setDate(currentDate.getDate() + 7);
                break;
            case 'biweekly':
                currentDate.setDate(currentDate.getDate() + 14);
                break;
            case 'monthly':
                currentDate.setMonth(currentDate.getMonth() + 1);
                break;
        }
    }
    
    return dates;
}

// 繰り返し予約の個別削除
async function deleteSingleReservation(reservationId) {
    // 既存のdeleteReservation関数を使用
    await deleteReservation(reservationId);
}

// 繰り返し予約のグループ全削除
async function deleteAllGroupReservations(groupId) {
    if (!confirm('このグループのすべての繰り返し予約を削除しますか？\nこの操作は取り消せません。')) {
        return;
    }
    
    try {
        // ローディング表示
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.style.display = 'flex';
        }
        const response = await fetch(`api/group_edit.php?group_id=${groupId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('すべての繰り返し予約を削除しました', 'success');
            
            // 開いているモーダルを全て閉じる
            closeDetailModal();
            
            await loadReservations();
            // リスト表示の場合は今日以降の予約データも更新
            if (currentView === 'list') {
                await loadAllFutureReservations();
            }
            renderCalendar();
        } else {
            showMessage(result.message || 'すべての予約の削除に失敗しました', 'error');
        }
    } catch (error) {
        console.error('グループ削除エラー:', error);
        showMessage('すべての予約の削除に失敗しました', 'error');
    } finally {
        // ローディング非表示
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.style.display = 'none';
        }
    }
}

// モーダル位置設定とドラッグ機能
let isDragging = false;
let dragStartX = 0;
let dragStartY = 0;
let modalStartX = 0;
let modalStartY = 0;

// モーダルを画面中央に配置する関数
function positionModalCenter(modal) {
    const modalContent = modal.querySelector('.modal-content') || modal.querySelector('.modal-dialog') || modal;
    
    // モーダルを一時的に表示して寸法を取得
    const originalDisplay = modal.style.display;
    modal.style.display = 'flex';
    modal.style.visibility = 'hidden';
    
    const rect = modalContent.getBoundingClientRect();
    const centerX = (window.innerWidth - rect.width) / 2;
    const centerY = (window.innerHeight - rect.height) / 2;
    
    // 画面外に出ないよう制限
    const x = Math.max(20, Math.min(centerX, window.innerWidth - rect.width - 20));
    const y = Math.max(20, Math.min(centerY, window.innerHeight - rect.height - 20));
    
    modalContent.style.position = 'absolute';
    modalContent.style.left = `${x}px`;
    modalContent.style.top = `${y}px`;
    
    // 表示状態を復元
    modal.style.display = originalDisplay;
    modal.style.visibility = 'visible';
}


// ドラッグ機能を設定する関数
function setupModalDrag(modal) {
    const modalHeader = modal.querySelector('.modal-header') || modal.querySelector('h3') || modal.querySelector('h2');
    if (!modalHeader) return;
    
    const modalContent = modal.querySelector('.modal-content') || modal.querySelector('.modal-dialog') || modal;
    
    // ヘッダーにドラッグカーソルを設定
    modalHeader.style.cursor = 'move';
    modalHeader.style.userSelect = 'none';
    
    // ドラッグ開始
    modalHeader.addEventListener('mousedown', (e) => {
        if (e.target.tagName === 'BUTTON' || e.target.closest('button')) return; // ボタンクリック時は無視
        
        isDragging = true;
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        
        const rect = modalContent.getBoundingClientRect();
        modalStartX = rect.left;
        modalStartY = rect.top;
        
        modalContent.style.cursor = 'grabbing';
        modalHeader.style.cursor = 'grabbing';
        modalContent.style.transition = 'none';
        modalContent.style.userSelect = 'none';
        
        e.preventDefault();
    });
    
    // タッチデバイス対応
    modalHeader.addEventListener('touchstart', (e) => {
        if (e.target.tagName === 'BUTTON' || e.target.closest('button')) return;
        
        isDragging = true;
        const touch = e.touches[0];
        dragStartX = touch.clientX;
        dragStartY = touch.clientY;
        
        const rect = modalContent.getBoundingClientRect();
        modalStartX = rect.left;
        modalStartY = rect.top;
        
        modalContent.style.transition = 'none';
        e.preventDefault();
    });
}

// ドラッグ中の処理
document.addEventListener('mousemove', (e) => {
    if (!isDragging) return;
    
    const deltaX = e.clientX - dragStartX;
    const deltaY = e.clientY - dragStartY;
    
    let newX = modalStartX + deltaX;
    let newY = modalStartY + deltaY;
    
    // 現在ドラッグ中のモーダルを取得
    const activeModal = document.querySelector('#reservation-modal[style*="flex"], #reservation-detail-modal[style*="flex"]');
    if (!activeModal) return;
    
    const modalContent = activeModal.querySelector('.modal-content') || activeModal.querySelector('.modal-dialog') || activeModal;
    const modalRect = modalContent.getBoundingClientRect();
    
    // 画面外に出ないよう制限
    const maxX = window.innerWidth - modalRect.width;
    const maxY = window.innerHeight - modalRect.height;
    
    newX = Math.max(0, Math.min(newX, maxX));
    newY = Math.max(0, Math.min(newY, maxY));
    
    modalContent.style.left = `${newX}px`;
    modalContent.style.top = `${newY}px`;
});

// タッチデバイスのドラッグ中処理
document.addEventListener('touchmove', (e) => {
    if (!isDragging) return;
    
    const touch = e.touches[0];
    const deltaX = touch.clientX - dragStartX;
    const deltaY = touch.clientY - dragStartY;
    
    let newX = modalStartX + deltaX;
    let newY = modalStartY + deltaY;
    
    const activeModal = document.querySelector('#reservation-modal[style*="flex"], #reservation-detail-modal[style*="flex"]');
    if (!activeModal) return;
    
    const modalContent = activeModal.querySelector('.modal-content') || activeModal.querySelector('.modal-dialog') || activeModal;
    const modalRect = modalContent.getBoundingClientRect();
    
    const maxX = window.innerWidth - modalRect.width;
    const maxY = window.innerHeight - modalRect.height;
    
    newX = Math.max(0, Math.min(newX, maxX));
    newY = Math.max(0, Math.min(newY, maxY));
    
    modalContent.style.left = `${newX}px`;
    modalContent.style.top = `${newY}px`;
    
    e.preventDefault();
});

// ドラッグ終了
document.addEventListener('mouseup', () => {
    if (isDragging) {
        isDragging = false;
        
        const activeModal = document.querySelector('#reservation-modal[style*="flex"], #reservation-detail-modal[style*="flex"]');
        if (activeModal) {
            const modalContent = activeModal.querySelector('.modal-content') || activeModal.querySelector('.modal-dialog') || activeModal;
            const modalHeader = activeModal.querySelector('.modal-header') || activeModal.querySelector('h3') || activeModal.querySelector('h2');
            
            modalContent.style.cursor = '';
            if (modalHeader) modalHeader.style.cursor = 'move';
            modalContent.style.transition = '';
            modalContent.style.userSelect = '';
        }
    }
});

// タッチ終了
document.addEventListener('touchend', () => {
    if (isDragging) {
        isDragging = false;
        
        const activeModal = document.querySelector('#reservation-modal[style*="flex"], #reservation-detail-modal[style*="flex"]');
        if (activeModal) {
            const modalContent = activeModal.querySelector('.modal-content') || activeModal.querySelector('.modal-dialog') || activeModal;
            modalContent.style.transition = '';
        }
    }
});

// ESCキーでモーダルを閉じる
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const reservationModal = document.getElementById('reservation-modal');
        const detailModal = document.getElementById('reservation-detail-modal');
        
        if (reservationModal && reservationModal.style.display === 'flex') {
            closeModal();
        } else if (detailModal && detailModal.style.display === 'flex') {
            closeDetailModal();
        }
    }
});

// ウィンドウリサイズ時の対応
window.addEventListener('resize', () => {
    const activeModal = document.querySelector('#reservation-modal[style*="flex"], #reservation-detail-modal[style*="flex"]');
    if (activeModal) {
        const modalContent = activeModal.querySelector('.modal-content') || activeModal.querySelector('.modal-dialog') || activeModal;
        const rect = modalContent.getBoundingClientRect();
        const maxX = window.innerWidth - rect.width;
        const maxY = window.innerHeight - rect.height;
        
        let currentX = parseInt(modalContent.style.left) || 0;
        let currentY = parseInt(modalContent.style.top) || 0;
        
        currentX = Math.max(0, Math.min(currentX, maxX));
        currentY = Math.max(0, Math.min(currentY, maxY));
        
        modalContent.style.left = `${currentX}px`;
        modalContent.style.top = `${currentY}px`;
    }
});

// モーダル表示時にドラッグ機能を設定
function initializeModalFeatures() {
    const reservationModal = document.getElementById('reservation-modal');
    const detailModal = document.getElementById('reservation-detail-modal');
    
    if (reservationModal) {
        setupModalDrag(reservationModal);
    }
    
    if (detailModal) {
        setupModalDrag(detailModal);
    }
}

// DOMContentLoaded時に初期化
document.addEventListener('DOMContentLoaded', () => {
    initializeModalFeatures();
});

// グローバルスコープで関数を利用可能にする
window.showReservationDetail = showReservationDetail;
window.closeDetailModal = closeDetailModal;