// 時間関連のユーティリティ関数

// 15分単位の時間選択肢を生成（9:00-18:00）
function generateTimeOptions() {
    const options = [];
    const startHour = 9;
    const endHour = 18;
    
    for (let hour = startHour; hour <= endHour; hour++) {
        for (let minute = 0; minute < 60; minute += 15) {
            const timeString = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
            const displayString = `${hour}:${minute.toString().padStart(2, '0')}`;
            options.push({
                value: timeString,
                display: displayString
            });
        }
    }
    
    return options;
}

// セレクトボックスに時間選択肢を設定
function populateTimeSelect(selectElement, selectedValue = null) {
    if (!selectElement) return;
    
    const options = generateTimeOptions();
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
    const startTimeSelect = document.getElementById('start-time');
    const endTimeSelect = document.getElementById('end-time');
    
    if (startTimeSelect) {
        populateTimeSelect(startTimeSelect);
        
        // 開始時間変更時のイベントリスナー
        startTimeSelect.addEventListener('change', function() {
            const startTime = this.value;
            const endTime = addOneHour(startTime);
            
            if (endTimeSelect) {
                populateTimeSelect(endTimeSelect, endTime);
            }
        });
    }
    
    if (endTimeSelect) {
        populateTimeSelect(endTimeSelect);
    }
    
    // グループ編集フォームの時間選択
    const groupStartTimeSelect = document.getElementById('group-start-time');
    const groupEndTimeSelect = document.getElementById('group-end-time');
    
    if (groupStartTimeSelect) {
        populateTimeSelect(groupStartTimeSelect);
        
        // グループ編集の開始時間変更時のイベントリスナー
        groupStartTimeSelect.addEventListener('change', function() {
            const startTime = this.value;
            const endTime = addOneHour(startTime);
            
            if (groupEndTimeSelect) {
                populateTimeSelect(groupEndTimeSelect, endTime);
            }
        });
    }
    
    if (groupEndTimeSelect) {
        populateTimeSelect(groupEndTimeSelect);
    }
}

// デフォルト時間を設定（新規予約時）
function setDefaultTimes() {
    const now = new Date();
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
    
    const startTime = `${startHour.toString().padStart(2, '0')}:${roundedMinute.toString().padStart(2, '0')}`;
    const endTime = addOneHour(startTime);
    
    const startTimeSelect = document.getElementById('start-time');
    const endTimeSelect = document.getElementById('end-time');
    
    if (startTimeSelect) {
        populateTimeSelect(startTimeSelect, startTime);
    }
    
    if (endTimeSelect) {
        populateTimeSelect(endTimeSelect, endTime);
    }
}