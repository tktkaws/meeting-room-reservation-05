<?= $action_emoji ?> 会議室予約通知 - 【<?= $action_label ?>】

【日時】
 <?= $date_formatted ?> <?= $start_time ?>～<?= $end_time ?>

 【タイトル】
<?= $title ?>

【予約者】
 <?= $user_name ?><?= $department ? ' (' . $department . ')' : '' ?>

 
<?php if ($description): ?>
【内容】
<?= $description ?>

<?php endif; ?>

会議室予約システム
http://intra2.jama.co.jp/meeting-room-reservation-05/

