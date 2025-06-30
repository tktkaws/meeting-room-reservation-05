class ChatPost {
    constructor() {
        this.initializeEventListeners();
        this.setTodayAsDefault();
    }

    initializeEventListeners() {
        const chatPostBtn = document.getElementById('chat-post-btn');
        if (chatPostBtn) {
            chatPostBtn.addEventListener('click', () => this.handleChatPost());
        }
    }

    setTodayAsDefault() {
        const dateInput = document.getElementById('chat-post-date');
        if (dateInput) {
            const today = new Date();
            const dateString = today.toISOString().split('T')[0];
            dateInput.value = dateString;
        }
    }

    async handleChatPost() {
        const dateInput = document.getElementById('chat-post-date');
        const chatPostBtn = document.getElementById('chat-post-btn');
        
        if (!dateInput || !dateInput.value) {
            showMessage('日付を選択してください', 'error');
            return;
        }

        const selectedDate = dateInput.value;
        
        // ボタンを無効化してローディング状態に
        const originalText = chatPostBtn.textContent;
        chatPostBtn.disabled = true;
        chatPostBtn.textContent = '投稿中...';

        try {
            const response = await fetch('api/chat_post.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    date: selectedDate
                })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                showMessage('Google Chatに投稿しました！', 'success');
                console.log('投稿したメッセージ:', result.posted_message);
            } else {
                showMessage(result.error || 'Google Chatへの投稿に失敗しました', 'error');
                console.error('投稿エラー:', result);
            }
        } catch (error) {
            console.error('Chat post error:', error);
            showMessage('ネットワークエラーが発生しました', 'error');
        } finally {
            // ボタンを元の状態に戻す
            chatPostBtn.disabled = false;
            chatPostBtn.textContent = originalText;
        }
    }
}

// ページ読み込み時に初期化
document.addEventListener('DOMContentLoaded', () => {
    new ChatPost();
});