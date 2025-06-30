<?= $action_emoji ?> 会議室予約通知 - 【<?= $action_label ?>】


■タイトル
<?= $title ?>


■日時
 <?= $date_formatted ?> <?= $start_time ?>～<?= $end_time ?>


■予約者
 <?= $user_name ?><?= $department ? ' (' . $department . ')' : '' ?>

 
<?php if ($description): ?>
■内容
<?= $description ?>

<?php endif; ?>


会議室予約システム
http://intra2.jama.co.jp/meeting-room-reservation-05/

送信日時
<?= $send_datetime ?>
