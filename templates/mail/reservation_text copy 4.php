<?= $action_emoji ?>  [<?= $action_label ?>]

🗓️ 日付: <?= $date_formatted ?>
🕐 時間: <?= $start_time ?>～<?= $end_time ?>
📋 件名: <?= $title ?>
👤 予約: <?= $user_name ?><?= $department ? ' (' . $department . ')' : '' ?>
<?php if ($description): ?>
📝 内容: <?= $description ?>
<?php endif; ?>


🖥️ 会議室予約システム
🔗 http://intra2.jama.co.jp/meeting-room-reservation-05/
