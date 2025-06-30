-- 繰り返し予約のためのリレーションテーブルを追加
CREATE TABLE reservation_group_relations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reserve_id INTEGER NOT NULL,
    group_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reserve_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES reservation_groups(id) ON DELETE CASCADE,
    UNIQUE(reserve_id, group_id)
);

-- 既存のテーブル更新
ALTER TABLE users ADD COLUMN department_id INTEGER DEFAULT NULL;
ALTER TABLE users ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE users ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP;

-- 祝日テーブルの作成
CREATE TABLE IF NOT EXISTS holidays (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    holiday_date DATE NOT NULL UNIQUE,
    holiday_name TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 祝日テーブルのインデックス
CREATE INDEX IF NOT EXISTS idx_holidays_date ON holidays(holiday_date);
CREATE INDEX IF NOT EXISTS idx_holidays_year ON holidays(YEAR(holiday_date));