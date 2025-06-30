<?= $action_emoji ?>  [<?= $action_label ?>]


🗓️ <?= $date_formatted ?>
<?= "\n\n" ?>
🕐 <?= $start_time ?>～<?= $end_time ?>
<?= "\n\n" ?>
📋 <?= $title ?>
<?= "\n\n" ?>
👤 <?= $user_name ?><?= $department ? ' (' . $department . ')' : '' ?>
<?= "\n\n" ?>
<?php if ($description): ?>
📝 <?= $description ?>
<?php endif; ?>


🖥️ 会議室予約システム
🔗 http://intra2.jama.co.jp/meeting-room-reservation-05/
