<li data-busybee-menu <?= $this->app->checkMenuSelection('SettingsController', '', 'BusyBee') ?>>
    <?= $this->url->link('BusyBee', 'SettingsController', 'index', array('plugin' => 'BusyBee')) ?>
</li>
