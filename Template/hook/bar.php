<?php
// SortaFolders renders this hook above its tab bars and passes the page, the visible projects and the narrowing. A
// SortaFolders that is missing or has no hooks never renders it, and BusyBee's copy of the dashboard layout takes over.
if (isset($sf_page, $sf_scope) && is_array($sf_scope)) {
    $ask = $this->busyBee->build($sf_page, $sf_scope, isset($sf_mine) ? $sf_mine : null);

    if ($ask !== null) {
        echo $this->render('BusyBee:ask', array(
            'ask' => $ask,
            'url_params' => $this->busyBee->linkParams(isset($url_params) ? $url_params : array()),
            'ask_controller' => isset($ask_controller) ? $ask_controller : 'FolderController',
            'ask_action' => isset($ask_action) ? $ask_action : 'show',
        ));
    }
}
