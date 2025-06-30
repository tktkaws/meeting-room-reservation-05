let departmentColors = {};
let themeColorObserver = null;

async function loadDepartmentThemeColors() {
    try {
        const response = await fetch('api/theme_colors.php', {
            method: 'GET',
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            departmentColors = data.colors;
            return departmentColors;
        } else {
            departmentColors = {
                "1": "#4299E1",
                "2": "#48BB78",
                "3": "#ED8936", 
                "4": "#9F7AEA",
                "5": "#38B2AC"
            };
            return departmentColors;
        }
    } catch (error) {
        departmentColors = {
            "1": "#4299E1",
            "2": "#48BB78", 
            "3": "#ED8936",
            "4": "#9F7AEA",
            "5": "#38B2AC"
        };
        return departmentColors;
    }
}

function applyDepartmentThemeColors() {
    
    
    
    // 月間表示の予約アイテム（ボーダーレフトのみ）
    const monthReservations = document.querySelectorAll('.reservation-item');
    monthReservations.forEach(item => {
        const departmentClass = Array.from(item.classList).find(cls => cls.startsWith('dept--'));
        if (departmentClass) {
            const deptId = departmentClass.replace('dept--', '').replace(/^0+/, '') || '0';
            const color = departmentColors[deptId] || '#718096';

            // 予約アイテムの左ボーダーにテーマカラーを適用
            item.style.borderLeftColor = color;

            // 編集可能な予約の場合は、全体のボーダーカラーもテーマカラーに更新
            if (item.classList.contains('editable')) {
                item.style.borderColor = color;
            }
        }
    });

    // 週間表示の予約アイテム（バックグラウンドカラーとフォントカラー調整）
    const weekReservations = document.querySelectorAll('.week-reservation');
    weekReservations.forEach(item => {
        const departmentClass = Array.from(item.classList).find(cls => cls.startsWith('dept--'));
        if (departmentClass) {
            const deptId = departmentClass.replace('dept--', '').replace(/^0+/, '') || '0';
            const color = departmentColors[deptId] || '#718096';

            // 予約アイテムの左ボーダーにテーマカラーを適用
            item.style.borderLeftColor = color;
            item.style.borderColor = color;

            // バックグラウンドカラーを設定
            item.style.backgroundColor = color;

            // バックグラウンドカラーの明度に応じてフォントカラーを調整
            const fontColor = getContrastColor(color);
            item.style.color = fontColor;

            // 編集可能な予約の場合は、全体のボーダーカラーもテーマカラーに更新
            if (item.classList.contains('editable')) {
                item.style.borderColor = color;
            }
        }
    });
}
function getDepartmentColor(deptId) {
    return departmentColors[String(deptId)] || '#718096';
}

// 色の明度を計算してコントラストの良いフォントカラーを返す関数
function getContrastColor(hexColor) {
    // #を除去
    const color = hexColor.replace('#', '');
    
    // RGBに変換
    const r = parseInt(color.substr(0, 2), 16);
    const g = parseInt(color.substr(2, 2), 16);
    const b = parseInt(color.substr(4, 2), 16);
    
    // 相対輝度を計算（W3C推奨式）
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    
    // 明度が0.8より大きい場合のみ黒、それ以外は白を返す（ほとんどの場合白になる）
    return luminance > 0.8 ? '#000000' : '#FFFFFF';
}

function setupThemeColorObserver() {
    if (themeColorObserver) return;
    
    const calendarView = document.getElementById('calendar-view');
    if (!calendarView) return;
    
    themeColorObserver = new MutationObserver((mutations) => {
        let shouldApplyColors = false;
        
        mutations.forEach((mutation) => {
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        if (node.classList?.contains('calendar-grid') || 
                            node.classList?.contains('week-view') || 
                            node.classList?.contains('list-view') ||
                            node.querySelector?.('.reservation-item, .week-reservation, .list-item')) {
                            shouldApplyColors = true;
                        }
                    }
                });
            }
        });
        
        if (shouldApplyColors) {
            setTimeout(() => {
                applyDepartmentThemeColors();
            }, 100);
        }
    });
    
    themeColorObserver.observe(calendarView, {
        childList: true,
        subtree: true
    });
}

document.addEventListener('DOMContentLoaded', function() {
    loadDepartmentThemeColors();
    
    setTimeout(() => {
        setupThemeColorObserver();
    }, 1000);
});