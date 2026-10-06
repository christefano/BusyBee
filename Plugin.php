<?php

namespace Kanboard\Plugin\BusyBee;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Security\Role;
use Kanboard\Plugin\BusyBee\Helper\BusyBeeHelper;
use Kanboard\Plugin\BusyBee\Model\AnswerModel;

class Plugin extends Base
{
    public function initialize()
    {
        $this->container['busyBeeAnswerModel'] = function ($c) {
            return new AnswerModel($c);
        };

        $this->helper->register('busyBee', BusyBeeHelper::class);

        $this->applicationAccessMap->add('SettingsController', '*', Role::APP_ADMIN);

        $this->hook->on('template:layout:css', array('template' => 'plugins/BusyBee/Assets/css/busybee.css'));

        // Pretty URL. The query-string form (?controller=SettingsController&action=index&plugin=BusyBee) still works.
        $this->route->addRoute('/settings/busybee', 'SettingsController', 'index', 'BusyBee');

        // On core's My projects, My tasks and My subtasks pages the bar goes above the page content. Core's pages share one
        // layout with no hook there, so BusyBee ships a copy of it. SortaFolders ships its own copy of the same layout and renders
        // the hooks below, so with SortaFolders in the plugins folder BusyBee leaves the layout alone and only fills the hooks.
        if (!is_file(PLUGINS_DIR.'/SortaFolders/Plugin.php')) {
            $this->template->setTemplateOverride('dashboard/layout', 'BusyBee:dashboard/layout');
        }

        // SortaFolders renders these two hooks where the question bar goes, so with it the bar also shows above its
        // folder page and its My projects, My tasks and My subtasks pages. A build without the hooks never renders them.
        $this->template->hook->attach('template:sortafolders:folder:before-tabs', 'BusyBee:hook/bar');
        $this->template->hook->attach('template:sortafolders:dashboard:before-tabs', 'BusyBee:hook/bar');
        $this->template->hook->attach('template:config:sidebar', 'BusyBee:hook/config_sidebar');
    }

    public function getPluginName()
    {
        return 'BusyBee';
    }

    public function getPluginDescription()
    {
        return t('Nine one-click questions about your tasks on My projects, My tasks and My subtasks');
    }

    public function getPluginAuthor()
    {
        return 'Christefano Reyes';
    }

    public function getPluginVersion()
    {
        return '1.0.0';
    }

    public function getPluginHomepage()
    {
        return '';  // set when the plugin has a published home
    }

    public function getCompatibleVersion()
    {
        return '>=1.2.54';
    }
}
