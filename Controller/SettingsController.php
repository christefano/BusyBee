<?php

namespace Kanboard\Plugin\BusyBee\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Plugin\BusyBee\Helper\BusyBeeHelper;

/**
 * The BusyBee settings page: which questions show. Admins only (see Plugin.php).
 */
class SettingsController extends BaseController
{
    public function index()
    {
        $questions = $this->helper->busyBee->questionTexts();

        $this->response->html($this->helper->layout->config('BusyBee:settings/index', array(
            'title' => t('Settings').' &gt; BusyBee',
            'questions' => $questions,
            'icons_on' => $this->helper->busyBee->iconsEnabled(),
            'question_icon' => $this->helper->busyBee->iconKey('question'),
            'answer_icon' => $this->helper->busyBee->iconKey('answer'),
            'question_on' => array_flip(array_filter(array_keys($questions), array($this->helper->busyBee, 'questionEnabled'))),
        )));
    }

    public function save()
    {
        $this->checkCSRFForm();

        $values = $this->request->getValues();
        $asks = array();

        foreach (array_keys($this->helper->busyBee->questionTexts()) as $question) {
            $asks[BusyBeeHelper::ASK_KEY.'_'.$question] = empty($values['ask_'.$question]) ? '0' : '1';
        }

        $asks[BusyBeeHelper::ICONS_KEY] = empty($values['icons']) ? '0' : '1';

        foreach (array('question' => array(BusyBeeHelper::QUESTION_ICON_KEY, BusyBeeHelper::questionIcons()), 'answer' => array(BusyBeeHelper::ANSWER_ICON_KEY, BusyBeeHelper::answerIcons())) as $which => $pair) {
            $asked = isset($values[$which.'_icon']) ? (string) $values[$which.'_icon'] : '';
            $asks[$pair[0]] = array_key_exists($asked, $pair[1]) ? $asked : key($pair[1]);
        }

        if ($this->configModel->save($asks)) {
            $this->flash->success(t('Settings saved successfully.'));
        } else {
            $this->flash->failure(t('Unable to save your settings.'));
        }

        $this->response->redirect($this->helper->url->to('SettingsController', 'index', array('plugin' => 'BusyBee')));
    }
}
