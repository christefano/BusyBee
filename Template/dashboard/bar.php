<?php
$page = $this->busyBee->page();
?>
<?php if ($page !== ''): ?>
    <?php $viewed = $this->busyBee->pageUserId() ?>
    <?php $ask = $this->busyBee->build($page, $this->busyBee->projects($viewed), $this->busyBee->mine($page, $viewed)) ?>
    <?php if ($ask !== null): ?>
        <?= $this->render('BusyBee:ask', array(
            'ask' => $ask,
            'url_params' => $this->busyBee->linkParams(array('user_id' => $user['id'])),
            'ask_controller' => 'DashboardController',
            'ask_action' => $page === 'overview' ? 'show' : $page,
        )) ?>
    <?php endif ?>
<?php endif ?>
