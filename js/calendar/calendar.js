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
function renderCalendar() {
    const calendarView = document.getElementById('calendar-view');
    if (!calendarView) return;
    
    updateCurrentPeriod();
    
    if (currentView === 'month') {
        renderMonthView(calendarView);
    } else if (currentView === 'week') {
        renderWeekView(calendarView);
    } else if (currentView === 'list') {
        renderListView(calendarView);
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
            // 現在の日付のコピーを作成（参照問題を回避）
            const displayDate = new Date(currentDay);
            const dayReservations = getDayReservations(displayDate);
            const isToday = isSameDay(displayDate, getJapanTime());
            const isCurrentMonth = displayDate.getMonth() === currentDate.getMonth();
            
            html += `
                <div class="calendar-day ${isToday ? 'today' : ''} ${!isCurrentMonth ? 'other-month' : ''}" 
                     data-date="${formatDate(displayDate)}" onclick="selectDate('${formatDate(displayDate)}')">
                    <div class="day-number">${displayDate.getDate()}</div>
                    <div class="reservations">
                        ${dayReservations.map(res => `
                            <div class="reservation-item ${res.group_id ? 'recurring' : ''}" onclick="event.stopPropagation(); showReservationDetail(${res.id})" title="${res.title} (${res.start_datetime.split(' ')[1].substring(0,5)}-${res.end_datetime.split(' ')[1].substring(0,5)})${res.group_id ? ' - 繰り返し予約' : ''}">
                                <div class="reservation-time">${res.start_datetime.split(' ')[1].substring(0,5)}-${res.end_datetime.split(' ')[1].substring(0,5)}</div>
                                <div class="reservation-title">${res.title}${res.group_id ? ' ♻' : ''}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }
        // 日付を1日進める（新しいDateオブジェクトを作成）
        currentDay = new Date(currentDay.getTime() + 24 * 60 * 60 * 1000);
    }
    
    html += '</div>';
    container.innerHTML = html;
}

// 週表示
function renderWeekView(container) {
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
        
        html += `<div class="week-day-header ${isToday ? 'today' : ''}">
            ${dayNames[dayIndex]} ${date.getMonth() + 1}/${date.getDate()}
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
}

// 予約コンポーネントを作成
function createReservationComponent(reservation, timeSlots) {
    const startTime = reservation.start_datetime.split(' ')[1].substring(0, 5);
    const endTime = reservation.end_datetime.split(' ')[1].substring(0, 5);
    
    // 開始時間のスロットインデックスを取得
    const startSlotIndex = timeSlots.indexOf(startTime);
    if (startSlotIndex === -1) return '';
    
    // 予約の継続時間（分）を計算
    const startMinutes = timeToMinutes(startTime);
    const endMinutes = timeToMinutes(endTime);
    const durationMinutes = endMinutes - startMinutes;
    
    // 高さを計算（15分 = 20px）
    const height = ((durationMinutes / 15) * 20) - 4;

    
    // 開始位置を計算
    // 10:00 (スロットインデックス 4) の場合、4 * 20 = 80px の位置
    const topPosition = startSlotIndex * 20 + 2; // セル位置 + マージン
    
    return `<div class="week-reservation" 
                 style="top: ${topPosition}px; height: ${height}px;"
                 onclick="showReservationDetail(${reservation.id})"
                 title="${reservation.title} (${startTime}-${endTime})">
                ${reservation.title}
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
    // 終了時間を1時間後に設定
    const [hours, minutes] = timeSlot.split(':').map(Number);
    let endHours = hours + 1;
    let endMinutes = minutes;
    
    // 18:00を超える場合は18:00に制限
    if (endHours > 18) {
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
        html += '<div class="list-header">';
        html += '<div class="list-header-item">日付</div>';
        html += '<div class="list-header-item">時間</div>';
        html += '<div class="list-header-item">タイトル</div>';
        html += '<div class="list-header-item">予約者</div>';
        html += '<div class="list-header-item">種別</div>';
        html += '</div>';
        
        futureReservations.forEach(res => {
            const date = new Date(res.date);
            const dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][date.getDay()];
            const formattedDate = `${date.getMonth() + 1}/${date.getDate()}(${dayOfWeek})`;
            const startTime = res.start_datetime.split(' ')[1].substring(0,5);
            const endTime = res.end_datetime.split(' ')[1].substring(0,5);
            const timeRange = `${startTime}-${endTime}`;
            const reservationType = res.group_id ? '繰り返し' : '単発';
            
            html += `
                <div class="list-item" onclick="showReservationDetail(${res.id})" data-reservation-id="${res.id}">
                    <div class="list-item-cell list-date">${formattedDate}</div>
                    <div class="list-item-cell list-time">${timeRange}</div>
                    <div class="list-item-cell list-title">${res.title}${res.group_id ? ' ♻' : ''}</div>
                    <div class="list-item-cell list-user">${res.user_name || '不明'}</div>
                    <div class="list-item-cell list-type">
                        <span class="type-badge ${res.group_id ? 'type-recurring' : 'type-single'}">${reservationType}</span>
                    </div>
                </div>
            `;
        });
    }
    
    html += '</div>';
    container.innerHTML = html;
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
    }
}

// 日付ナビゲーション
function navigateDate(direction) {
    if (currentView === 'month') {
        currentDate.setMonth(currentDate.getMonth() + direction);
    } else if (currentView === 'week') {
        currentDate.setDate(currentDate.getDate() + (direction * 7));
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
    openNewReservationModal(dateStr);
}