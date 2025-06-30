// 文字数カウンター機能

/**
 * 文字数カウンターを初期化
 */
function initCharCounters() {
    // 予約フォームのタイトル
    const titleInput = document.getElementById('reservation-title');
    const titleCounter = document.getElementById('title-counter');
    if (titleInput && titleCounter) {
        setupCharCounter(titleInput, titleCounter, 50);
    }
    
    // 予約フォームの説明
    const descriptionTextarea = document.getElementById('reservation-description');
    const descriptionCounter = document.getElementById('description-counter');
    if (descriptionTextarea && descriptionCounter) {
        setupCharCounter(descriptionTextarea, descriptionCounter, 400);
    }
    
    // グループ編集フォームのタイトル
    const groupTitleInput = document.getElementById('group-title');
    const groupTitleCounter = document.getElementById('group-title-counter');
    if (groupTitleInput && groupTitleCounter) {
        setupCharCounter(groupTitleInput, groupTitleCounter, 50);
    }
    
    // グループ編集フォームの説明
    const groupDescriptionTextarea = document.getElementById('group-description');
    const groupDescriptionCounter = document.getElementById('group-description-counter');
    if (groupDescriptionTextarea && groupDescriptionCounter) {
        setupCharCounter(groupDescriptionTextarea, groupDescriptionCounter, 400);
    }
}

/**
 * 文字数カウンターを設定
 * @param {HTMLElement} input - 入力要素
 * @param {HTMLElement} counter - カウンター表示要素
 * @param {number} maxLength - 最大文字数
 */
function setupCharCounter(input, counter, maxLength) {
    function updateCounter() {
        const currentLength = input.value.length;
        counter.textContent = `${currentLength}/${maxLength}`;
        
        // 色の設定（エラー色のみ）
        counter.classList.remove('error');
        if (currentLength > maxLength) {
            counter.classList.add('error');
        }
    }
    
    // 初回実行
    updateCounter();
    
    // イベントリスナー
    input.addEventListener('input', updateCounter);
    input.addEventListener('paste', () => {
        // ペースト後の値を反映するため遅延実行
        setTimeout(updateCounter, 0);
    });
}

// DOMが読み込まれた時に初期化
document.addEventListener('DOMContentLoaded', function() {
    initCharCounters();
});

/**
 * フォームがリセットされた時やモーダルが開かれた時に文字数カウンターを更新
 */
function refreshCharCounters() {
    initCharCounters();
}

// グローバルに公開（他のファイルから呼び出せるように）
window.refreshCharCounters = refreshCharCounters;