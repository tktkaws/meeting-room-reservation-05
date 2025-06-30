// Chat通知キュー
let chatNotificationQueue = [];

// Chat通知機能のみを提供するファイル

// 認証ページの初期化
function initAuthPage() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const showRegisterBtn = document.getElementById('show-register');
    const showLoginBtn = document.getElementById('show-login');
    
    // フォーム切り替え
    showRegisterBtn?.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('login-form').style.display = 'none';
        document.getElementById('register-form').style.display = 'block';
        clearMessage();
    });
    
    showLoginBtn?.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('register-form').style.display = 'none';
        document.getElementById('login-form').style.display = 'block';
        clearMessage();
    });
    
    // ログインフォーム送信
    loginForm?.addEventListener('submit', function(e) {
        e.preventDefault();
        handleLogin();
    });
    
    // 登録フォーム送信
    registerForm?.addEventListener('submit', function(e) {
        e.preventDefault();
        handleRegister();
    });
}

// メインページの初期化
async function initMainPage() {
    // 認証チェック
    const authStatus = await checkAuth();
    if (!authStatus.logged_in) {
        window.location.href = 'auth.html';
        return;
    }
    
    currentUser = authStatus.user;
    updateUserInfo();
    
    // イベントリスナー設定
    setupEventListeners();
    
    // カレンダー初期表示
    await loadReservations();
    
    // テーマカラーを読み込み
    await loadDepartmentThemeColors();
    
    // カレンダーを描画
    renderCalendar();
    
    // MutationObserver を設定（まだ設定されていない場合）
    setTimeout(() => {
        if (typeof setupThemeColorObserver === 'function') {
            setupThemeColorObserver();
        }
    }, 500);
}

// 認証状態チェック
async function checkAuth() {
    try {
        const response = await fetch('api/auth.php');
        return await response.json();
    } catch (error) {
        console.error('認証チェックエラー:', error);
        return { logged_in: false };
    }
}

// ログイン処理
async function handleLogin() {
    const formData = new FormData(document.getElementById('loginForm'));
    formData.append('action', 'login');
    
    try {
        showLoading(true);
        const response = await fetch('api/auth.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('ログインしました', 'success');
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 1000);
        } else {
            showMessage(result.error || 'ログインに失敗しました', 'error');
        }
    } catch (error) {
        console.error('ログインエラー:', error);
        showMessage('ログインに失敗しました', 'error');
    } finally {
        showLoading(false);
    }
}

// ユーザー登録処理
async function handleRegister() {
    const formData = new FormData(document.getElementById('registerForm'));
    formData.append('action', 'register');
    
    try {
        showLoading(true);
        const response = await fetch('api/auth.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('ユーザー登録が完了しました。ログインしてください。', 'success');
            // ログインフォームに切り替え
            document.getElementById('register-form').style.display = 'none';
            document.getElementById('login-form').style.display = 'block';
            document.getElementById('registerForm').reset();
        } else {
            showMessage(result.error || 'ユーザー登録に失敗しました', 'error');
        }
    } catch (error) {
        console.error('登録エラー:', error);
        showMessage('ユーザー登録に失敗しました', 'error');
    } finally {
        showLoading(false);
    }
}

// ログアウト処理
async function handleLogout() {
    try {
        const formData = new FormData();
        formData.append('action', 'logout');
        
        const response = await fetch('api/auth.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.location.href = 'auth.html';
        }
    } catch (error) {
        console.error('ログアウトエラー:', error);
    }
}

// イベントリスナー設定
function setupEventListeners() {
    // ログアウトボタン
    document.getElementById('logout-btn')?.addEventListener('click', handleLogout);
    
    // 新規予約ボタン
    document.getElementById('new-reservation-btn')?.addEventListener('click', openNewReservationModal);
    
    // モーダル関連
    document.getElementById('close-modal')?.addEventListener('click', closeModal);
    document.getElementById('cancel-btn')?.addEventListener('click', closeModal);
    document.getElementById('close-detail-modal')?.addEventListener('click', closeDetailModal);
    document.getElementById('close-group-edit-modal')?.addEventListener('click', closeGroupEditModal);
    document.getElementById('group-cancel-btn')?.addEventListener('click', closeGroupEditModal);
    
    // 予約フォーム送信
    document.getElementById('reservation-form')?.addEventListener('submit', handleReservationSubmit);
    
    // グループ編集フォーム送信
    document.getElementById('group-edit-form')?.addEventListener('submit', handleGroupEditSubmit);
    
    // 繰り返し予約チェックボックス
    document.getElementById('is-recurring')?.addEventListener('change', toggleRecurringOptions);
    
    // モーダル外クリックで閉じる
    document.getElementById('reservation-modal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
}

// ユーザー情報更新
function updateUserInfo() {
    const userInfo = document.getElementById('user-info');
    if (userInfo && currentUser) {
        userInfo.textContent = `${currentUser.name} (${currentUser.department || '部署未設定'})`;
    }
}

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

// カレンダー表示
function renderCalendar() {
    const calendarView = document.getElementById('calendar-view');
    if (!calendarView) return;
    
    updateCurrentPeriod();
    
    if (currentView === 'month') {
        renderMonthView(calendarView);
    } else if (currentView === 'week') {
        renderWeekView(calendarView);
    } else if (currentView === 'day') {
        renderDayView(calendarView);
    }
}

// 月表示（平日のみ）
function renderMonthView(container) {
    const monthStart = getMonthStart(currentDate);
    const monthEnd = getMonthEnd(currentDate);
    const calendarStart = getWeekdayStart(new Date(monthStart));
    const calendarEnd = getWeekdayEnd(new Date(monthEnd));
    
    let html = '<div class="calendar-grid weekdays-only">';
    
    // ヘッダー（平日のみ）
    const weekdays = ['月', '火', '水', '木', '金'];
    weekdays.forEach(day => {
        html += `<div class="calendar-header">${day}</div>`;
    });
    
    // 日付セル（平日のみ）
    let currentDay = new Date(calendarStart);
    while (currentDay <= calendarEnd) {
        // 平日のみ処理（月曜=1, 火曜=2, ..., 金曜=5）
        if (currentDay.getDay() >= 1 && currentDay.getDay() <= 5) {
            const dayReservations = getDayReservations(currentDay);
            const isToday = isSameDay(currentDay, new Date());
            const isCurrentMonth = currentDay.getMonth() === currentDate.getMonth();
            
            html += `
                <div class="calendar-day ${isToday ? 'today' : ''} ${!isCurrentMonth ? 'other-month' : ''}" 
                     data-date="${formatDate(currentDay)}" onclick="selectDate('${formatDate(currentDay)}')">
                    <div class="day-number">${currentDay.getDate()}</div>
                    <div class="reservations">
                        ${dayReservations.map(res => `
                            <div class="reservation-item ${res.group_id ? 'recurring' : ''}" onclick="event.stopPropagation(); showReservationDetail(${res.id})" title="${res.title} (${res.start_datetime.split(' ')[1].substring(0,5)}-${res.end_datetime.split(' ')[1].substring(0,5)})${res.group_id ? ' - 繰り返し予約' : ''}">
                                <div class="reservation-title">${res.title}</div>
                                <div class="reservation-time">${res.start_datetime.split(' ')[1].substring(0,5)}-${res.end_datetime.split(' ')[1].substring(0,5)}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }
        currentDay.setDate(currentDay.getDate() + 1);
    }
    
    html += '</div>';
    container.innerHTML = html;
}

// 週表示（簡易版）
function renderWeekView(container) {
    container.innerHTML = '<div class="week-view"><p>週表示は今後実装予定です</p></div>';
}

// 日表示（簡易版）
function renderDayView(container) {
    container.innerHTML = '<div class="day-view"><p>日表示は今後実装予定です</p></div>';
}

// 現在の期間表示更新
function updateCurrentPeriod() {
    const periodElement = document.getElementById('current-period');
    if (!periodElement) return;
    
    if (currentView === 'month') {
        periodElement.textContent = `${currentDate.getFullYear()}年 ${currentDate.getMonth() + 1}月`;
    }
}

// 日付ナビゲーション
function navigateDate(direction) {
    if (currentView === 'month') {
        currentDate.setMonth(currentDate.getMonth() + direction);
    }
    loadReservations().then(() => {
        renderCalendar();
        setTimeout(() => {
            applyDepartmentThemeColors();
        }, 100);
    });
}

// 今日に移動
function goToToday() {
    currentDate = new Date();
    loadReservations().then(() => {
        renderCalendar();
        setTimeout(() => {
            applyDepartmentThemeColors();
        }, 100);
    });
}

// ビュー切り替え
function switchView(view) {
    currentView = view;
    
    // ボタンのアクティブ状態更新
    document.querySelectorAll('.btn-view').forEach(btn => btn.classList.remove('active'));
    document.getElementById(`${view}-view`).classList.add('active');
    
    renderCalendar();
    
    // ビュー切り替え後にテーマカラーを再適用
    setTimeout(() => {
        applyDepartmentThemeColors();
    }, 100);
}

// 新規予約モーダルを開く
function openNewReservationModal(selectedDate = null) {
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
    
    // 選択された日付を設定
    if (selectedDate) {
        document.getElementById('reservation-date').value = selectedDate;
    } else {
        // 今日の日付を設定
        document.getElementById('reservation-date').value = formatDate(new Date());
    }
    
    modal.style.display = 'flex';
}

// 日付選択
function selectDate(dateStr) {
    openNewReservationModal(dateStr);
}

// 予約詳細表示
async function showReservationDetail(reservationId) {
    try {
        showLoading(true);
        const response = await fetch(`api/reservation_detail.php?id=${reservationId}`);
        const result = await response.json();
        
        if (result.error) {
            showMessage(result.error, 'error');
            return;
        }
        
        const reservation = result.reservation;
        const canEdit = result.can_edit;
        const groupReservations = result.group_reservations;
        
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
                    groupReservations.map(gr => {
                        const formattedDate = formatDateWithDayOfWeek(gr.date);
                        const startTime = gr.start_datetime.split(' ')[1].substring(0,5);
                        const endTime = gr.end_datetime.split(' ')[1].substring(0,5);
                        return `
                            <div class="group-item">
                                <div class="group-item-info">${formattedDate} ${startTime}-${endTime}</div>
                            </div>
                        `;
                    }).join('') + '</div>';
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

// 予約編集（単発または個別編集）
async function editReservation(reservationId) {
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
    
    document.getElementById('modal-title').textContent = '予約編集';
    document.getElementById('reservation-title').value = reservation.title;
    document.getElementById('reservation-description').value = reservation.description || '';
    document.getElementById('reservation-date').value = reservation.date;
    document.getElementById('start-time').value = reservation.start_datetime.split(' ')[1].substring(0,5);
    document.getElementById('end-time').value = reservation.end_datetime.split(' ')[1].substring(0,5);
    
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
    document.getElementById('start-time').value = reservation.start_datetime.split(' ')[1].substring(0,5);
    document.getElementById('end-time').value = reservation.end_datetime.split(' ')[1].substring(0,5);
    
    // 繰り返し予約オプションを非表示
    document.getElementById('is-recurring').checked = false;
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
    
    // 時間フォームの初期化（最初の予約の時間を設定）
    if (reservations.length > 0) {
        const firstReservation = reservations[0];
        const startTime = firstReservation.start_datetime.split(' ')[1].substring(0, 5);
        const endTime = firstReservation.end_datetime.split(' ')[1].substring(0, 5);
        
        document.getElementById('group-start-time').value = startTime;
        document.getElementById('group-end-time').value = endTime;
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

// resetReservationTime関数は削除されました（個別時間調整機能を削除）

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
            
            // カレンダー再描画後にテーマカラーを再適用
            setTimeout(() => {
                applyDepartmentThemeColors();
            }, 100);
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

// 予約削除
async function deleteReservation(reservationId) {
    if (!confirm('この予約のみ削除しますか？')) {
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
            
            // カレンダー再描画後にテーマカラーを再適用
            setTimeout(() => {
                applyDepartmentThemeColors();
            }, 100);
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
    const checkbox = document.getElementById('is-recurring');
    const options = document.getElementById('recurring-options');
    
    if (checkbox.checked) {
        options.style.display = 'block';
    } else {
        options.style.display = 'none';
    }
}

// handleReservationSubmit は reservation-api.js で定義されています

// ユーティリティ関数
function formatDate(date) {
    return date.toISOString().split('T')[0];
}

function isSameDay(date1, date2) {
    return date1.toDateString() === date2.toDateString();
}

function getMonthStart(date) {
    return new Date(date.getFullYear(), date.getMonth(), 1);
}

function getMonthEnd(date) {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0);
}

function getWeekStart(date) {
    const day = date.getDay();
    const diff = date.getDate() - day;
    return new Date(date.setDate(diff));
}

function getWeekEnd(date) {
    const day = date.getDay();
    const diff = date.getDate() + (6 - day);
    return new Date(date.setDate(diff));
}

// 平日表示用の開始日取得（その月の最初の平日またはその前の週の月曜日）
function getWeekdayStart(date) {
    const monthStart = new Date(date.getFullYear(), date.getMonth(), 1);
    const dayOfWeek = monthStart.getDay(); // 0=日曜日, 1=月曜日, ..., 6=土曜日
    
    if (dayOfWeek === 0) {
        // 月初が日曜日の場合、翌日（月曜日）から開始
        return new Date(monthStart.getFullYear(), monthStart.getMonth(), 2);
    } else if (dayOfWeek === 1) {
        // 月初が月曜日の場合、そのまま月初から開始
        return monthStart;
    } else {
        // 月初が火曜日以降の場合、前の週の月曜日から開始
        const daysBack = dayOfWeek - 1;
        return new Date(monthStart.getFullYear(), monthStart.getMonth(), monthStart.getDate() - daysBack);
    }
}

// 平日表示用の終了日取得（その月の最後の平日またはその次の週の金曜日）
function getWeekdayEnd(date) {
    const monthEnd = new Date(date.getFullYear(), date.getMonth() + 1, 0);
    const dayOfWeek = monthEnd.getDay(); // 0=日曜日, 1=月曜日, ..., 6=土曜日
    
    if (dayOfWeek === 5) {
        // 月末が金曜日の場合、そのまま月末で終了
        return monthEnd;
    } else if (dayOfWeek === 6) {
        // 月末が土曜日の場合、前日（金曜日）で終了
        return new Date(monthEnd.getFullYear(), monthEnd.getMonth(), monthEnd.getDate() - 1);
    } else if (dayOfWeek === 0) {
        // 月末が日曜日の場合、前々日（金曜日）で終了
        return new Date(monthEnd.getFullYear(), monthEnd.getMonth(), monthEnd.getDate() - 2);
    } else {
        // 月末が平日の場合、次の金曜日まで延長
        const daysForward = 5 - dayOfWeek;
        return new Date(monthEnd.getFullYear(), monthEnd.getMonth(), monthEnd.getDate() + daysForward);
    }
}

function getDayReservations(date) {
    const dateStr = formatDate(date);
    return reservations.filter(res => res.date === dateStr);
}

function showMessage(message, type = 'success') {
    const messageEl = document.getElementById('message');
    if (!messageEl) return;
    
    messageEl.textContent = message;
    messageEl.className = `message ${type}`;
    messageEl.classList.add('show');
    
    setTimeout(() => {
        messageEl.classList.remove('show');
    }, 3000);
}

function clearMessage() {
    const messageEl = document.getElementById('message');
    if (messageEl) {
        messageEl.classList.remove('show');
    }
}

function showLoading(show) {
    const loadingEl = document.getElementById('loading');
    if (loadingEl) {
        loadingEl.style.display = show ? 'flex' : 'none';
    }
}

// =====================
// Chat通知関連の機能
// =====================

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

// 他のウィンドウからのテーマカラー変更メッセージを受信
window.addEventListener('message', function(event) {
    if (event.data.type === 'themeColorUpdate') {
        // departmentColors を更新
        if (typeof departmentColors !== 'undefined') {
            departmentColors[String(event.data.deptId)] = event.data.color;
        }
        
        // 特定の部署のみカラー適用（最適化）
        if (typeof applyColorToDepartment === 'function') {
            applyColorToDepartment(event.data.deptId, event.data.color);
        } else if (typeof applyDepartmentThemeColors === 'function') {
            // フォールバック: 全体適用
            applyDepartmentThemeColors();
        }
    }
});

// LocalStorage イベントでテーマカラー変更を検知
window.addEventListener('storage', function(event) {
    if (event.key === 'themeColorUpdate' && event.newValue) {
        try {
            const themeUpdate = JSON.parse(event.newValue);
            if (themeUpdate.type === 'themeColorUpdate') {
                // departmentColors を更新
                if (typeof departmentColors !== 'undefined') {
                    departmentColors[String(themeUpdate.deptId)] = themeUpdate.color;
                }
                
                // 特定の部署のみカラー適用（最適化）
                if (typeof applyColorToDepartment === 'function') {
                    applyColorToDepartment(themeUpdate.deptId, themeUpdate.color);
                } else if (typeof applyDepartmentThemeColors === 'function') {
                    // フォールバック: 全体適用
                    applyDepartmentThemeColors();
                }
                
                console.log('他のタブからテーマカラー更新を受信:', themeUpdate);
            }
        } catch (e) {
            console.warn('テーマカラー更新の解析に失敗:', e);
        }
    }
});