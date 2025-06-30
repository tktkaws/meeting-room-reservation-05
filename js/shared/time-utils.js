// 時間関連のユーティリティ関数

// 日本時間を取得する関数
function getJapanTime() {
    // 日本時間（JST）で現在時刻を取得
    const now = new Date();
    // 日本時間のオプション
    const japanTimeOptions = {
        timeZone: 'Asia/Tokyo',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    };
    
    // 日本時間の文字列を取得
    const japanTimeString = now.toLocaleString('ja-JP', japanTimeOptions);
    
    // 新しいDateオブジェクトを日本時間で作成
    return new Date(japanTimeString.replace(/\//g, '-'));
}

// 15分単位の時間選択肢を生成（9:00-18:00）
function generateTimeOptions(isEndTime = false) {
    const options = [];
    const startHour = 9;
    const endHour = isEndTime ? 18 : 17; // 終了時間は18:00まで、開始時間は17:45まで
    
    for (let hour = startHour; hour <= endHour; hour++) {
        const maxMinute = (hour === 17 && !isEndTime) ? 45 : // 開始時間で17時の場合は45分まで
                         (hour === 18 && isEndTime) ? 0 : 60; // 終了時間で18時の場合は00分のみ
        
        for (let minute = 0; minute < maxMinute; minute += 15) {
            const timeString = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
            const displayString = `${hour}:${minute.toString().padStart(2, '0')}`;
            options.push({
                value: timeString,
                display: displayString
            });
        }
        
        // 18:00の場合は00分のみ追加（終了時間用）
        if (hour === 18 && isEndTime) {
            options.push({
                value: '18:00',
                display: '18:00'
            });
            break;
        }
    }
    
    return options;
}

// セレクトボックスに時間選択肢を設定
function populateTimeSelect(selectElement, selectedValue = null, isEndTime = false) {
    if (!selectElement) return;
    
    const options = generateTimeOptions(isEndTime);
    selectElement.innerHTML = '';
    
    options.forEach(option => {
        const optionElement = document.createElement('option');
        optionElement.value = option.value;
        optionElement.textContent = option.display;
        
        if (selectedValue && option.value === selectedValue) {
            optionElement.selected = true;
        }
        
        selectElement.appendChild(optionElement);
    });
}

// 時間文字列に1時間を追加
function addOneHour(timeString) {
    if (!timeString) return '';
    
    const [hours, minutes] = timeString.split(':').map(Number);
    let newHours = hours + 1;
    
    // 18:00を超える場合は18:00に制限
    if (newHours > 18) {
        newHours = 18;
    }
    
    return `${newHours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
}

// 時間文字列をHH:MM形式に変換（表示用）
function formatTimeForDisplay(timeString) {
    if (!timeString) return '';
    
    const [hours, minutes] = timeString.split(':');
    return `${parseInt(hours)}:${minutes}`;
}

// HH:MM形式の時間文字列をHH:MM形式に正規化（value用）
function normalizeTimeString(timeString) {
    if (!timeString) return '';
    
    const [hours, minutes] = timeString.split(':');
    return `${hours.padStart(2, '0')}:${minutes.padStart(2, '0')}`;
}

// 全ての時間セレクトボックスを初期化
function initializeTimeSelects() {
    // 予約フォームの時間選択
    const startHourSelect = document.getElementById('start-hour');
    const startMinuteSelect = document.getElementById('start-minute');
    const endHourSelect = document.getElementById('end-hour');
    const endMinuteSelect = document.getElementById('end-minute');
    
    // 開始時間変更時のイベントリスナー
    if (startHourSelect || startMinuteSelect) {
        const updateEndTime = function() {
            const startHour = parseInt(startHourSelect?.value || 9);
            const startMinute = parseInt(startMinuteSelect?.value || 0);
            
            // 1時間後を計算
            let endHour = startHour + 1;
            let endMinute = startMinute;
            
            // 18:00を超える場合、または17:15以降の開始時間の場合は18:00に制限
            if (endHour > 18 || (startHour === 17 && startMinute >= 15)) {
                endHour = 18;
                endMinute = 0;
            }
            
            if (endHourSelect) {
                endHourSelect.value = endHour;
            }
            if (endMinuteSelect) {
                endMinuteSelect.value = endMinute;
            }
        };
        
        startHourSelect?.addEventListener('change', updateEndTime);
        startMinuteSelect?.addEventListener('change', updateEndTime);
    }
    
    // グループ編集フォームの時間選択
    const groupStartHourSelect = document.getElementById('group-start-hour');
    const groupStartMinuteSelect = document.getElementById('group-start-minute');
    const groupEndHourSelect = document.getElementById('group-end-hour');
    const groupEndMinuteSelect = document.getElementById('group-end-minute');
    
    // グループ編集の開始時間変更時のイベントリスナー
    if (groupStartHourSelect || groupStartMinuteSelect) {
        const updateGroupEndTime = function() {
            const startHour = parseInt(groupStartHourSelect?.value || 9);
            const startMinute = parseInt(groupStartMinuteSelect?.value || 0);
            
            // 1時間後を計算
            let endHour = startHour + 1;
            let endMinute = startMinute;
            
            // 18:00を超える場合、または17:15以降の開始時間の場合は18:00に制限
            if (endHour > 18 || (startHour === 17 && startMinute >= 15)) {
                endHour = 18;
                endMinute = 0;
            }
            
            if (groupEndHourSelect) {
                groupEndHourSelect.value = endHour;
            }
            if (groupEndMinuteSelect) {
                groupEndMinuteSelect.value = endMinute;
            }
        };
        
        groupStartHourSelect?.addEventListener('change', updateGroupEndTime);
        groupStartMinuteSelect?.addEventListener('change', updateGroupEndTime);
    }
}

// デフォルト時間を設定（新規予約時）
function setDefaultTimes() {
    const now = getJapanTime();
    const currentHour = now.getHours();
    const currentMinute = now.getMinutes();
    
    // 現在時刻を15分単位に丸める
    let roundedMinute = Math.ceil(currentMinute / 15) * 15;
    let startHour = currentHour;
    
    if (roundedMinute >= 60) {
        roundedMinute = 0;
        startHour += 1;
    }
    
    // 営業時間内に調整
    if (startHour < 9) {
        startHour = 9;
        roundedMinute = 0;
    } else if (startHour >= 18) {
        startHour = 17;
        roundedMinute = 0;
    }
    
    // 1時間後の終了時間を計算
    let endHour = startHour + 1;
    let endMinute = roundedMinute;
    
    // 17:15以降の開始時間、または18:00を超える場合は18:00に制限
    if (startHour >= 17 && roundedMinute >= 15) {
        endHour = 18;
        endMinute = 0;
    } else if (endHour > 18) {
        endHour = 18;
        endMinute = 0;
    }
    
    // 新しいフォーム要素に値を設定
    const startHourSelect = document.getElementById('start-hour');
    const startMinuteSelect = document.getElementById('start-minute');
    const endHourSelect = document.getElementById('end-hour');
    const endMinuteSelect = document.getElementById('end-minute');
    
    if (startHourSelect) {
        startHourSelect.value = startHour;
    }
    if (startMinuteSelect) {
        startMinuteSelect.value = roundedMinute;
    }
    if (endHourSelect) {
        endHourSelect.value = endHour;
    }
    if (endMinuteSelect) {
        endMinuteSelect.value = endMinute;
    }
}

// 日付を「MM月DD日（曜日）」形式でフォーマット
function formatDateWithDayOfWeek(dateStr) {
    const date = new Date(dateStr);
    const month = date.getMonth() + 1;
    const day = date.getDate();
    
    const dayNames = ['日', '月', '火', '水', '木', '金', '土'];
    const dayOfWeek = dayNames[date.getDay()];
    
    return `${month}月${day}日（${dayOfWeek}）`;
}