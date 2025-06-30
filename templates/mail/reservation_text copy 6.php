<?= $action_emoji ?>  [<?= $action_label ?>]


🗓️ 日付: <?= $date_formatted ?>
<?= "\n\n" ?>
🕐 時間: <?= $start_time ?>～<?= $end_time ?>
<?= "\n\n" ?>
📋 件名: <?= $title ?>
<?= "\n\n" ?>
👤 予約者: <?= $user_name ?><?= $department ? ' (' . $department . ')' : '' ?>
<?= "\n\n" ?>
<?php if ($description): ?>
📝 内容: <?= $description ?>
<?php endif; ?>


🖥️ 会議室予約システム
🔗 http://intra2.jama.co.jp/meeting-room-reservation-05/
