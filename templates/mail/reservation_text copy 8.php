==========================================================
<?= $action_emoji ?> <?= $action_label ?><?= "\n" ?>
==========================================================

日　時： <?= $date_formatted ?><?= $start_time ?>～<?= $end_time ?><?= "\n" ?>
件　名： <?= $title ?><?= "\n" ?>
予約者： <?= $user_name ?><?= $department ? ' (' . $department . ')' : '' ?>
<?php if ($description): ?>
<?= "\n" ?>    
説　明：<?= "\n" ?>
<?= $description ?>
<?= "\n" ?>
<?php endif; ?>
<?= "\n" ?>
==========================================================

会議室予約システム
http://intra2.jama.co.jp/meeting-room-reservation-05/
