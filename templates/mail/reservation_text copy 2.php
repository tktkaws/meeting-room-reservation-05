<?= $action_emoji ?> <?= $action_label ?>
<?= "\n\n" ?>
🕐日時
<?= $date_formatted ?> <?= $start_time ?>～<?= $end_time ?>
<?= "\n\n" ?>
📋タイトル
<?= $title ?>
<?= "\n\n" ?>
👤予約者
<?= $user_name ?><?= $department ? ' (' . $department . ')' : '' ?>
<?= "\n\n" ?>
<?php if ($description): ?>
📝説明
<?= $description ?>
<?php endif; ?>
<?= "\n\n" ?>
----------------------------------------------------
<?= "\n\n" ?>
会議室予約システム
http://intra2.jama.co.jp/meeting-room-reservation-05/
