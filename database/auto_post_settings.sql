-- 自動投稿設定テーブル
CREATE TABLE IF NOT EXISTS auto_post_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL DEFAULT 'デフォルト設定',
    is_enabled INTEGER DEFAULT 1, -- 0: 無効, 1: 有効
    post_frequency INTEGER DEFAULT 60, -- 投稿頻度（分）
    post_time_start TEXT DEFAULT '09:00', -- 投稿開始時間
    post_time_end TEXT DEFAULT '18:00', -- 投稿終了時間
    webhook_url TEXT NOT NULL,
    last_post_datetime DATETIME, -- 最後の投稿日時
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- デフォルト設定を挿入
INSERT OR REPLACE INTO auto_post_settings (
    id, name, is_enabled, post_frequency, post_time_start, post_time_end, webhook_url
) VALUES (
    1, 
    '会議室予定自動投稿', 
    1, 
    60, 
    '09:00', 
    '18:00', 
    'https://chat.googleapis.com/v1/spaces/AAQAW4CXATk/messages?key=AIzaSyDdI0hCZtE6vySjMm-WEfRq3CPzqKqqsHI&token=a_50V7YZ5Ix3hbh-sF-ez8apzMnrB_mbbxAaQDwB_ZQ'
);