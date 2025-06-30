// カレンダー表示機能
let currentView = 'month';
let currentDate = getJapanTime();
let reservations = [];

// 指定日の予約を取得
function getDayReservations(date) {
    const dateStr = formatDate(date);
    return reservations.filter(res => res.date === dateStr);
}

// カレンダー表示
async function renderCalendar() {
    const calendarView = document.getElementById('calendar-view');
    if (!calendarView) return;
    
    updateCurrentPeriod();
    
    if (currentView === 'month') {
        await renderMonthView(calendarView);
    } else if (currentView === 'week') {
        await renderWeekView(calendarView);
    } else if (currentView === 'list') {
        renderListView(calendarView);
    } else if (currentView === 'day') {
        renderDayView(calendarView);
    }
}

// 月表示（平日のみ、祝日除外）
async function renderMonthView(container) {
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
    
    // 日付セル（平日のみ、祝日除外）
    let currentDay = new Date(calendarStart);
    while (currentDay <= calendarEnd) {
        // 平日のみ処理（月曜=1, 火曜=2, ..., 金曜=5）
        if (currentDay.getDay() >= 1 && currentDay.getDay() <= 5) {
            // 現在の日付のコピーを作成（参照問題を回避）
            const displayDate = new Date(currentDay);
            const dayReservations = getDayReservations(displayDate);
            const isToday = isSameDay(displayDate, getJapanTime());
            const isCurrentMonth = displayDate.getMonth() === currentDate.getMonth();
            
            // 平日のみ表示なので全日予約可能
            const isAvailable = true;
            
            html += `
                <div class="calendar-day ${isToday ? 'today' : ''} ${!isCurrentMonth ? 'other-month' : ''}" 
                     data-date="${formatDate(displayDate)}" 
                     onclick="selectDate('${formatDate(displayDate)}')">
                    <div class="day-number">
                        <span>${displayDate.getDate()}</span>
                    </div>
                    <div class="reservations">
                        ${dayReservations.map(res => {
                            const isPast = new Date(res.end_datetime) < getJapanTime();
                            const canEdit = res.can_edit ? 'editable' : '';
                            const departmentClass = res.department ? `dept--${String(res.department).padStart(2, '0')}` : 'dept--00';
                            return `
                            <div class="reservation-item ${res.group_id ? 'recurring' : ''} ${isPast ? 'past' : ''} ${canEdit} ${departmentClass}" onclick="event.stopPropagation(); showReservationDetail(${res.id})" title="${res.title} (${res.start_datetime.split(' ')[1].substring(0,5)}-${res.end_datetime.split(' ')[1].substring(0,5)})${res.group_id ? ' - 繰り返し予約' : ''}">
                                <div class="reservation-time">${res.start_datetime.split(' ')[1].substring(0,5)}-${res.end_datetime.split(' ')[1].substring(0,5)}</div>
                                <div class="reservation-title">${res.title}</div>
                            </div>
                        `;}).join('')}
                    </div>
                </div>
            `;
        }
        // 日付を1日進める（新しいDateオブジェクトを作成）
        currentDay = new Date(currentDay.getTime() + 24 * 60 * 60 * 1000);
    }
    
    html += '</div>';
    container.innerHTML = html;
    
    // カレンダー描画完了後にテーマカラーを適用
    setTimeout(() => {
        if (typeof applyDepartmentThemeColors === 'function') {
            applyDepartmentThemeColors();
        }
    }, 50);
}

// 週表示
async function renderWeekView(container) {
    const weekStart = getWeekStart(currentDate);
    const weekEnd = getWeekEnd(currentDate);
    const timeSlots = generateTimeSlots();
    
    let html = '<div class="week-view">';
    html += '<div class="week-grid">';
    
    // ヘッダー行
    html += '<div class="week-header-row">';
    html += '<div class="week-time-header"></div>';
    
    for (let dayIndex = 0; dayIndex < 5; dayIndex++) {
        const date = new Date(weekStart);
        date.setDate(date.getDate() + dayIndex);
        const dayNames = ['月', '火', '水', '木', '金'];
        const isToday = isSameDay(date, getJapanTime());
        
        // 平日のみ表示なので祝日判定不要
        html += `<div class="week-day-header ${isToday ? 'today' : ''}">
            <div class="day-name">${dayNames[dayIndex]}</div>
            <div class="day-date"><span>${date.getDate()}</span></div>
        </div>`;
    }
    html += '</div>';
    
    // コンテンツグリッド
    html += '<div class="week-content-grid">';
    
    // 時間軸カラム
    html += '<div class="week-time-column">';
    timeSlots.forEach(timeSlot => {
        const isHourMark = timeSlot.endsWith(':00') || timeSlot.endsWith(':30');
        html += `<div class="week-time-label ${isHourMark ? 'hour-mark' : ''}">${timeSlot}</div>`;
    });
    html += '</div>';
    
    // 各日のカラム
    for (let dayIndex = 0; dayIndex < 5; dayIndex++) {
        const date = new Date(weekStart);
        date.setDate(date.getDate() + dayIndex);
        const dateStr = formatDate(date);
        
        // 平日のみ表示なので全日予約可能
        html += `<div class="week-day-column">`;
        
        // 時間セル
        timeSlots.forEach((timeSlot, timeIndex) => {
            html += `<div class="week-cell" 
                         data-date="${dateStr}" 
                         data-time="${timeSlot}"
                         data-slot-index="${timeIndex}"
                         onclick="selectWeekTimeSlot('${dateStr}', '${timeSlot}')">
                    </div>`;
        });
        
        // その日の予約を配置
        const dayReservations = getDayReservations(date);
        dayReservations.forEach(reservation => {
            const reservationHtml = createReservationComponent(reservation, timeSlots);
            html += reservationHtml;
        });
        
        // 今日の場合は現在時刻ラインを追加
        const isToday = isSameDay(date, getJapanTime());
        if (isToday) {
            const currentTimeLine = createCurrentTimeLine();
            if (currentTimeLine) {
                html += currentTimeLine;
            }
        }
        
        html += '</div>';
    }
    
    html += '</div>';
    html += '</div>';
    html += '</div>';
    container.innerHTML = html;
    
    // カレンダー描画完了後にテーマカラーを適用
    setTimeout(() => {
        if (typeof applyDepartmentThemeColors === 'function') {
            applyDepartmentThemeColors();
        }
    }, 50);
}

// 動的な高さを取得する関数
function getWeekTimeHeaderHeight() {
    // 画面高さから動的に計算（CSSと同じロジック）
    const viewportHeight = window.innerHeight;
    const availableHeight = viewportHeight - 200; // ヘッダーやマージン分を除外
    const calculatedHeight = availableHeight / 37; // 37スロット分
    const minHeight = 20; // 最小高さ
    
    return Math.max(minHeight, calculatedHeight);
}

// 週間ビューの予約配置を更新する関数
function updateWeekViewReservations() {
    if (currentView !== 'week') return;
    
    const weekViewContainer = document.querySelector('.week-view');
    if (!weekViewContainer) return;
    
    // 現在の予約要素をすべて削除
    const existingReservations = weekViewContainer.querySelectorAll('.week-reservation');
    existingReservations.forEach(el => el.remove());
    
    // 予約を再配置
    const timeSlots = generateTimeSlots();
    const weekStart = getWeekStart(currentDate);
    
    for (let i = 0; i < 5; i++) {
        const date = new Date(weekStart);
        date.setDate(date.getDate() + i);
        
        const dayReservations = getDayReservations(date);
        const dayColumn = weekViewContainer.querySelector(`.week-day-column:nth-child(${i + 2})`); // ヘッダー列分+1
        
        if (dayColumn) {
            dayReservations.forEach(reservation => {
                const reservationHtml = createReservationComponent(reservation, timeSlots);
                if (reservationHtml) {
                    dayColumn.insertAdjacentHTML('beforeend', reservationHtml);
                }
            });
        }
    }
    
    // テーマカラーを再適用
    setTimeout(() => {
        if (typeof applyDepartmentThemeColors === 'function') {
            applyDepartmentThemeColors();
        }
    }, 10);
}

// リサイズイベントのデバウンス処理
let resizeTimeout;
function handleResize() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
        updateWeekViewReservations();
    }, 150); // 150ms後に実行
}

// 時間形式を正規化する関数（HH:MM形式に統一）
function normalizeTimeFormat(timeString) {
    // "HH:MM:SS" または "H:MM" → "HH:MM"
    const timePart = timeString.split(':');
    const hour = timePart[0].padStart(2, '0');
    const minute = timePart[1].padStart(2, '0');
    return `${hour}:${minute}`;
}

// 予約コンポーネントを作成
function createReservationComponent(reservation, timeSlots) {
    const rawStartTime = reservation.start_datetime.split(' ')[1].substring(0, 5);
    const rawEndTime = reservation.end_datetime.split(' ')[1].substring(0, 5);
    
    // 時間形式を正規化
    const startTime = normalizeTimeFormat(rawStartTime);
    const endTime = normalizeTimeFormat(rawEndTime);
    
    // 開始時間のスロットインデックスを取得
    const startSlotIndex = timeSlots.indexOf(startTime);
    if (startSlotIndex === -1) {
        console.warn(`時間スロットが見つかりません: ${startTime}`, {
            rawStartTime,
            normalizedStartTime: startTime,
            availableSlots: timeSlots.slice(0, 5) // デバッグ用に最初の5スロットを表示
        });
        return '';
    }
    
    // 予約の継続時間（分）を計算
    const startMinutes = timeToMinutes(startTime);
    const endMinutes = timeToMinutes(endTime);
    const durationMinutes = endMinutes - startMinutes;
    
    // 動的な高さを取得
    const cellHeight = getWeekTimeHeaderHeight();
    
    // 高さを計算（15分 = 1セル分）
    const height = ((durationMinutes / 15) * cellHeight) - 4;

    
    // 開始位置を計算
    // 動的なセル高さに基づいて位置を計算
    const topPosition = startSlotIndex * cellHeight; // セル位置 + マージン
    
    // 継続時間に応じて表示内容を変更
    let displayContent = '';
    if (durationMinutes <= 15) {
        // 15分の場合：タイトルのみ
        displayContent = `${reservation.title}`;
    } else if (durationMinutes <= 30) {
        // 30分の場合：時間とタイトル
        displayContent = `<div class="reservation-time">${startTime}～${endTime}</div><div class="reservation-title">${reservation.title}</div>`;
    } else {
        // 45分以上の場合：時間、タイトル、予約者
        displayContent = `<div class="reservation-time">${startTime}～${endTime}</div><div class="reservation-title">${reservation.title}</div><div class="reservation-user">${reservation.user_name || '予約者不明'}</div>`;
    }

    const isPast = new Date(reservation.end_datetime) < getJapanTime();
    const canEdit = reservation.can_edit ? 'editable' : '';
    const departmentClass = reservation.department ? `dept--${String(reservation.department).padStart(2, '0')}` : 'dept--00';
    
    return `<div class="week-reservation ${reservation.group_id ? 'recurring' : ''} ${isPast ? 'past' : ''} ${canEdit} ${departmentClass}" 
                 style="top: ${topPosition}px; height: ${height}px;"
                 onclick="showReservationDetail(${reservation.id})"
                 title="${reservation.title} (${startTime}-${endTime})${reservation.group_id ? ' - 繰り返し予約' : ''}">
                ${displayContent}
            </div>`;
}

// 時間を分に変換
function timeToMinutes(timeString) {
    const [hours, minutes] = timeString.split(':').map(Number);
    return hours * 60 + minutes;
}

// 現在時刻ラインを作成
function createCurrentTimeLine() {
    const now = getJapanTime();
    const currentHours = now.getHours();
    const currentMinutes = now.getMinutes();
    
    // 営業時間外の場合は表示しない
    if (currentHours < 9 || currentHours >= 18) {
        return null;
    }
    
    // 現在時刻を15分単位に調整
    const totalMinutes = (currentHours - 9) * 60 + currentMinutes;
    const slotIndex = Math.floor(totalMinutes / 15);
    const slotOffset = (totalMinutes % 15) / 15;
    
    // 位置を計算（20px per slot + offset）
    const position = slotIndex * 20 + (slotOffset * 20);
    
    return `<div class="current-time-line" style="top: ${position}px;">
                <div class="current-time-marker"></div>
            </div>`;
}



// 週間表示のセルクリック処理
function selectWeekTimeSlot(dateStr, timeSlot) {
    // 非ログインユーザーの場合は何もしない
    if (!currentUser) {
        return;
    }
    
    // 過去の日付の場合は何もしない
    const selectedDate = new Date(dateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0); // 時刻をリセット
    
    if (selectedDate < today) {
        return;
    }
    
    // 終了時間を1時間後に設定
    const [hours, minutes] = timeSlot.split(':').map(Number);
    let endHours = hours + 1;
    let endMinutes = minutes;
    
    // 17:15以降の場合、または18:00を超える場合は18:00に制限
    if (hours >= 17 && minutes >= 15) {
        endHours = 18;
        endMinutes = 0;
    } else if (endHours > 18) {
        endHours = 18;
        endMinutes = 0;
    }
    
    const endTime = `${endHours.toString().padStart(2, '0')}:${endMinutes.toString().padStart(2, '0')}`;
    
    openNewReservationModal(dateStr, timeSlot, endTime);
}

// リスト表示
function renderListView(container) {
    // 全ての今日以降の予約をソート
    const futureReservations = allFutureReservations.sort((a, b) => {
        // 日付と開始時間でソート
        const dateA = new Date(a.start_datetime);
        const dateB = new Date(b.start_datetime);
        return dateA - dateB;
    });
    
    let html = '<div class="list-view">';
    
    
    if (futureReservations.length === 0) {
        html += '<div class="list-empty">今日以降の予約はありません</div>';
    } else {
        // 固定ヘッダー
        html += '<div class="list-header-fixed">';
        html += '<div class="list-header-item">日付</div>';
        html += '<div class="list-header-item">時間</div>';
        html += '<div class="list-header-item list-title">タイトル</div>';
        html += '<div class="list-header-item list-department">部署</div>';
        html += '<div class="list-header-item list-user">予約者</div>';
        html += '</div>';
        
        // スクロール可能なリストコンテナ
        html += '<div class="list-content-scrollable">';
        
        futureReservations.forEach(res => {
            const date = new Date(res.date);
            const dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][date.getDay()];
            const formattedDate = `${date.getMonth() + 1}/${date.getDate()}(${dayOfWeek})`;
            const startTime = res.start_datetime.split(' ')[1].substring(0,5);
            const endTime = res.end_datetime.split(' ')[1].substring(0,5);
            const timeRange = `${startTime}-${endTime}`;
            const titleWithIcon = `${res.title}`;
            
            // 今日の日付かどうかをチェック
            const today = getJapanTime();
            const isToday = isSameDay(date, today);
            
            // 過去の予約かどうかをチェック
            const isPast = new Date(res.end_datetime) < today;
            
            // 編集可能な予約の場合は予約者名に✏️アイコンを追加
            const canEdit = res.can_edit ? 'editable' : '';
            // const userNameWithIcon = res.can_edit ? `${res.user_name || '不明'} ✏️` : (res.user_name || '不明');
            
            html += `
                <div class="list-item ${isToday ? 'today' : ''} ${isPast ? 'past' : ''}" onclick="showReservationDetail(${res.id})" data-reservation-id="${res.id}">
                    <div class="list-item-cell list-date">${formattedDate}</div>
                    <div class="list-item-cell list-time">${timeRange}</div>
                    <div class="list-item-cell list-title">${titleWithIcon}</div>
                    <div class="list-item-cell list-department">${res.department_name || '未設定'}</div>
                    <div class="list-item-cell list-user ${canEdit}">${res.user_name}</div>
                </div>
            `;
        });
        
        html += '</div>'; // list-content-scrollable end
    }
    
    html += '</div>';
    container.innerHTML = html;
    
    // カレンダー描画完了後にテーマカラーを適用
    setTimeout(() => {
        if (typeof applyDepartmentThemeColors === 'function') {
            applyDepartmentThemeColors();
        }
    }, 50);
}

// 日表示（簡易版）
function renderDayView(container) {
    container.innerHTML = '<div class="day-view"><p>日表示は今後実装予定です</p></div>';
}

// 現在の期間表示更新
function updateCurrentPeriod() {
    // console.log('updateCurrentPeriod called, currentView:', currentView); // デバッグ用
    const periodElement = document.getElementById('current-period');
    if (!periodElement) {
        console.error('current-period element not found'); // デバッグ用
        return;
    }
    
    if (currentView === 'month') {
        periodElement.textContent = `${currentDate.getFullYear()}年 ${currentDate.getMonth() + 1}月`;
    } else if (currentView === 'week') {
        const weekStart = getWeekStart(currentDate);
        const weekEnd = getWeekEnd(currentDate);
        const startMonth = weekStart.getMonth() + 1;
        const startDay = weekStart.getDate();
        const endMonth = weekEnd.getMonth() + 1;
        const endDay = weekEnd.getDate();
        
        if (startMonth === endMonth) {
            periodElement.textContent = `${currentDate.getFullYear()}年 ${startMonth}月 ${startDay}日 - ${endDay}日`;
        } else {
            periodElement.textContent = `${currentDate.getFullYear()}年 ${startMonth}月${startDay}日 - ${endMonth}月${endDay}日`;
        }
    } else if (currentView === 'list') {
        // リスト表示の場合は今日以降の予約を表示していることを示す
        const today = new Date();
        periodElement.textContent = `${today.getFullYear()}年${today.getMonth() + 1}月${today.getDate()}日以降の予約`;
    }
    
    // console.log('Current period set to:', periodElement.textContent); // デバッグ用
}

// 日付ナビゲーション
function navigateDate(direction) {
    if (currentView === 'month') {
        currentDate.setMonth(currentDate.getMonth() + direction);
    } else if (currentView === 'week') {
        currentDate.setDate(currentDate.getDate() + (direction * 7));
    } else if (currentView === 'list') {
        // リスト表示では日付ナビゲーションは不要（常に今日以降を表示）
        return;
    }
    loadReservations().then(() => {
        renderCalendar();
        updateCurrentPeriod();
    });
}

// 今日に移動
function goToToday() {
    currentDate = getJapanTime();
    loadReservations().then(() => renderCalendar());
}

// ビュー切り替え
async function switchView(view) {
    currentView = view;
    
    // ビュー状態を保存
    saveCurrentView(view);
    
    // ボタンのアクティブ状態更新
    document.querySelectorAll('.btn-view').forEach(btn => btn.classList.remove('active'));
    document.getElementById(`${view}-view`).classList.add('active');
    
    // リスト表示の場合は今日以降の全予約データを読み込み
    if (view === 'list') {
        await loadAllFutureReservations();
    }
    
    renderCalendar();
}

// 日付選択
function selectDate(dateStr) {
    // 非ログインユーザーの場合は何もしない
    if (!currentUser) {
        return;
    }
    
    // 過去の日付の場合は何もしない
    const selectedDate = new Date(dateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0); // 時刻をリセット
    
    if (selectedDate < today) {
        return;
    }
    
    openNewReservationModal(dateStr);
}