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