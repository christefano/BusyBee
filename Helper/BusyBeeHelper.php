<?php

namespace Kanboard\Plugin\BusyBee\Helper;

use Kanboard\Core\Base;

/**
 * Registered as $this->busyBee. The questions, which of them the admin left on, which pages show which, and the
 * answers. With SortaFolders loaded, the folder term, the user links and the hours text come from its helper. Without it,
 * each of those has a plain version here, so BusyBee works without SortaFolders.
 */
class BusyBeeHelper extends Base
{
    const ASK_KEY = 'busybee_ask';  // prefix of the per-question keys, busybee_ask_<question>
    const ICONS_KEY = 'busybee_icons';  // 1 (the default) shows the question and answer icons, 0 hides them
    const QUESTION_ICON_KEY = 'busybee_question_icon';  // one of questionIcons(), 'question-circle-o' by default
    const ANSWER_ICON_KEY = 'busybee_answer_icon';  // one of answerIcons(), 'check-circle-o' by default
    const OLD_ASK_KEY = 'sortafolders_ask';  // where SortaFolders saved them before the questions moved here

    private $sorta = false;

    /**
     * SortaFolders' helper, or null when it is not loaded or lacks a method BusyBee uses.
     */
    private function sorta()
    {
        if ($this->sorta === false) {
            $this->sorta = null;

            try {
                $helper = $this->helper->getHelper('sortaFolders');

                if (method_exists($helper, 't') && method_exists($helper, 'userLink') && method_exists($helper, 'hoursText') && method_exists($helper, 'projectPaths')) {
                    $this->sorta = $helper;
                }
            } catch (\Throwable $e) {
                $this->sorta = null;
            }
        }

        return $this->sorta;
    }

    /**
     * Same as t(), with "folder" swapped for the term the admin picked in SortaFolders. Without SortaFolders there are no
     * folders, so the word becomes "project".
     */
    public function t($text)
    {
        $args = func_get_args();

        if ($this->sorta() !== null) {
            return call_user_func_array(array($this->sorta(), 't'), $args);
        }

        $args[0] = strtr($text, array('folders' => 'projects', 'folder' => 'project', 'Folders' => 'Projects', 'Folder' => 'Project'));

        return call_user_func_array('t', $args);
    }

    /**
     * A person's name as a link to their profile, when the viewer may open it.
     */
    public function userLink($userId, $name)
    {
        if ($this->sorta() !== null) {
            return $this->sorta()->userLink($userId, $name);
        }

        $name = $this->helper->text->e($name);

        if ((int) $userId > 0 && $this->helper->user->hasAccess('UserViewController', 'profile')) {
            return $this->helper->url->link($name, 'UserViewController', 'profile', array('user_id' => (int) $userId));
        }

        return $name;
    }

    /**
     * The folder path of each project, id => label, for the blockers outside the page scope. Empty strings without SortaFolders, since
     * there are no folders.
     */
    public function paths(array $ids)
    {
        if ($this->sorta() !== null) {
            return $this->sorta()->projectPaths($ids);
        }

        return array_fill_keys(array_map('intval', $ids), '');
    }

    /**
     * Ids of the projects SortaFolders hides from the signed-in user (a project in a hidden folder, for anyone but an admin). Empty without
     * SortaFolders, or with a version of it that has no hidden folders.
     */
    public function hiddenProjectIds()
    {
        if ($this->sorta() !== null && method_exists($this->sorta(), 'hiddenProjectIds')) {
            return array_map('intval', $this->sorta()->hiddenProjectIds());
        }

        return array();
    }

    /**
     * "3:30h spent and 12.5h estimated". Empty when neither was logged.
     */
    public function hoursText(array $totals)
    {
        if ($this->sorta() !== null) {
            return $this->sorta()->hoursText($totals);
        }

        $parts = array();
        $spent = isset($totals['hours']) ? (float) $totals['hours'] : 0.0;
        $estimated = isset($totals['estimated']) ? (float) $totals['estimated'] : 0.0;

        if ($spent > 0) {
            $minutes = (int) round($spent * 60);
            $parts[] = t('%s spent', ($minutes % 60 === 0 ? (int) ($minutes / 60) : sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60)).'h');
        }

        if ($estimated > 0) {
            $parts[] = t('%s estimated', rtrim(rtrim(number_format($estimated, 2, '.', ''), '0'), '.').'h');
        }

        return implode(' '.t('and').' ', $parts);
    }

    /**
     * The icons for the front of the question row: key => label, each a Font Awesome class that Kanboard ships.
     */
    public static function questionIcons()
    {
        return array(
            'question-circle-o'    => 'Question',
            'search'               => 'Search',
            'flag-o'               => 'Milestone',
            'calendar-o'           => 'Deadline',
            'clock-o'              => 'Time',
            'users'                => 'Team',
            'tasks'                => 'Tasks',
            'bar-chart'            => 'Report',
            'briefcase'            => 'Project',
            'exclamation-triangle' => 'Risk',
            'bell-o'               => 'Alert',
            'bullhorn'             => 'Announcement',
            'eye'                  => 'Review',
            'envelope-o'           => 'Message',
            'usd'                  => 'Budget',
            'comments-o'           => 'Discussion',
        );
    }

    /**
     * The icons for the front of an answer: key => label.
     */
    public static function answerIcons()
    {
        return array(
            'check-circle-o'   => 'Answer',
            'lightbulb-o'      => 'Insight',
            'trophy'           => 'Achievement',
            'calendar-check-o' => 'On schedule',
            'hourglass-half'   => 'In progress',
            'handshake-o'      => 'Agreement',
            'check-square-o'   => 'Completed',
            'line-chart'       => 'Progress',
            'thumbs-o-up'      => 'Approved',
            'shield'           => 'Safe',
            'flag-checkered'   => 'Finished',
            'comment-o'        => 'Reply',
            'star-o'           => 'Rated',
            'paper-plane-o'    => 'Sent',
            'money'            => 'Funded',
            'smile-o'          => 'Satisfied',
        );
    }

    /**
     * Which answer icon goes with which question icon, for the pairs the settings page points out (question and answer, search and
     * insight). The two lists are in the same order, so the pairs line up.
     */
    public static function iconPairs()
    {
        return array_combine(array_keys(self::questionIcons()), array_keys(self::answerIcons()));
    }

    /**
     * The saved icon key for "question" or "answer", or the default when nothing valid was saved.
     */
    public function iconKey($which)
    {
        $question = $which === 'question';
        $key = (string) $this->configModel->get($question ? self::QUESTION_ICON_KEY : self::ANSWER_ICON_KEY, '');
        $known = $question ? self::questionIcons() : self::answerIcons();

        return array_key_exists($key, $known) ? $key : key($known);
    }

    /**
     * The markup of one icon. $key picks a choice for the settings page, else the saved one shows. It is decoration, so
     * screen readers skip it.
     */
    public function icon($which, $key = null)
    {
        $key = $key === null ? $this->iconKey($which) : $key;
        $class = $which === 'question' ? 'busybee-qicon' : 'busybee-aicon';

        return '<i class="fa fa-'.$key.' '.$class.'" aria-hidden="true"></i>';
    }

    /**
     * Whether the icon in front of the questions and the one in front of an answer show. On until an admin turns it off.
     */
    public function iconsEnabled()
    {
        return (string) $this->configModel->get(self::ICONS_KEY, '1') !== '0';
    }

    /**
     * The page open now: "overview", "projects", "tasks", "subtasks", or "" for anything else.
     */
    public function page()
    {
        if ($this->router->getController() !== 'DashboardController') {
            return '';
        }

        $action = $this->router->getAction();

        return $action === 'show' ? 'overview' : (in_array($action, array('projects', 'tasks', 'subtasks'), true) ? $action : '');
    }

    /**
     * The user whose dashboard is open: the user_id in the address, else the signed-in user.
     */
    public function pageUserId()
    {
        $id = $this->request->getIntegerParam('user_id');

        return $id > 0 ? $id : (int) $this->userSession->getId();
    }

    /**
     * Narrows the tasks to the page's user, in the form AnswerModel::build reads: null on My projects, array('tasks' => id) on
     * My tasks and array('subtasks' => id) on My subtasks.
     */
    public function mine($page, $userId)
    {
        return array('tasks' => array('tasks' => $userId), 'subtasks' => array('subtasks' => $userId))[$page] ?? null;
    }

    /**
     * The visible, active projects of one user keyed by id, for the pages BusyBee fills without SortaFolders. There are no folders, so
     * "sf_path" is empty.
     */
    public function projects($userId)
    {
        $projects = array();

        foreach ($this->projectModel->getAllByIds($this->projectPermissionModel->getActiveProjectIds($userId)) as $project) {
            if ((int) $project['is_active'] === 1) {
                $projects[(int) $project['id']] = $project + array('sf_path' => '');
            }
        }

        return $projects;
    }

    /**
     * The address parameters of a question link: $params plus the sort order, direction and page of the list under the bar, so
     * asking a question does not send the list back to page one in its default order.
     */
    public function linkParams(array $params)
    {
        foreach (array('order', 'direction', 'page') as $name) {
            $value = $this->request->getStringParam($name, '');

            if ($value !== '' && ! array_key_exists($name, $params)) {
                $params[$name] = $value;
            }
        }

        return $params;
    }

    /**
     * Every question in button order: key => array(label, one-line description).
     */
    public function questionTexts()
    {
        return array(
            'late'    => array($this->t('What is late?'), $this->t('Open tasks past their due date.')),
            'done'    => array($this->t('What finished this month?'), $this->t('Tasks closed since the first of this month.')),
            'load'    => array($this->t('Who is overloaded?'), $this->t('Open and overdue tasks and active projects for each user.')),
            'blocked' => array($this->t('What is blocked?'), $this->t('Open tasks waiting on an open task through the "is blocked by" link.')),
            'due'     => array($this->t('What is due within 30 days?'), $this->t('Open tasks due from now to 30 days ahead.')),
            'unowned' => array($this->t('What has no owner?'), $this->t('Open tasks with no assignee.')),
            'over'    => array($this->t('What is over its estimate?'), $this->t('Open tasks with more hours spent than estimated.')),
            'stuck'   => array($this->t('What has not moved in 30 days?'), $this->t('Open tasks whose last move is more than 30 days old.')),
            'week'    => array($this->t('What changed this week?'), $this->t('Tasks created and tasks finished in the last 7 days.')),
            'active'  => array($this->t("What's being worked on?"), $this->t('Tasks changed in the last 30 days: edited, moved, commented on, and so on. Newest first.')),
        );
    }

    /**
     * How the "What's being worked on?" answer words each kind of change: Kanboard's activity event name (or moved, edited or created for a
     * task whose newest change left no event) => text.
     */
    public function changeTexts()
    {
        return array(
            'task.create'                      => $this->t('Created'),
            'task.update'                      => $this->t('Edited'),
            'task.close'                       => $this->t('Closed'),
            'task.open'                        => $this->t('Reopened'),
            'task.move.column'                 => $this->t('Moved to another column'),
            'task.move.position'               => $this->t('Moved within its column'),
            'task.move.swimlane'               => $this->t('Moved to another swimlane'),
            'task.move.project'                => $this->t('Moved to another project'),
            'task.assignee_change'             => $this->t('Owner changed'),
            'task.user.mention'                => $this->t('Someone was mentioned'),
            'subtask.create'                   => $this->t('New subtask'),
            'subtask.update'                   => $this->t('Subtask updated'),
            'subtask.delete'                   => $this->t('Subtask deleted'),
            'comment.create'                   => $this->t('New comment'),
            'comment.update'                   => $this->t('Comment edited'),
            'comment.delete'                   => $this->t('Comment deleted'),
            'comment.user.mention'             => $this->t('Someone was mentioned in a comment'),
            'task.file.create'                 => $this->t('File attached'),
            'task.file.destroy'                => $this->t('File removed'),
            'task_internal_link.create_update' => $this->t('Link added or changed'),
            'task_internal_link.delete'        => $this->t('Link removed'),
            'moved'                            => $this->t('Moved'),
            'edited'                           => $this->t('Edited'),
            'created'                          => $this->t('Created'),
        );
    }

    /**
     * The text for one change code, "Updated" for a code this list does not know.
     */
    public function changeText($code)
    {
        $texts = $this->changeTexts();

        return isset($texts[$code]) ? $texts[$code] : $this->t('Updated');
    }

    /**
     * Whether one question is switched on. A question is on until an admin turns it off. A setting saved here wins. Until one
     * is saved, the key SortaFolders used to save is read, so a question an admin turned off there stays off.
     */
    public function questionEnabled($key)
    {
        $value = (string) $this->configModel->get(self::ASK_KEY.'_'.$key, '');

        if ($value === '') {
            $value = (string) $this->configModel->get(self::OLD_ASK_KEY.'_'.$key, '1');
        }

        return $value !== '0';
    }

    /**
     * The keys of the questions that show, in button order. Empty when every question is off, which hides the bar.
     */
    public function enabledQuestions()
    {
        return array_values(array_filter(array_keys($this->questionTexts()), array($this, 'questionEnabled')));
    }

    /**
     * Which questions make sense on each page. The folder page can ask all of them. The Overview and My projects pages can too. My tasks is one
     * person's tasks, so "Who is overloaded?" and "What has no owner?" have nothing to say. A subtask has no due date,
     * hours or finish date of its own here, so My subtasks keeps the questions that read the parent task's dates and links.
     */
    public static function getQuestionPages()
    {
        return array(
            'folder'   => array('late', 'done', 'due', 'load', 'blocked', 'unowned', 'over', 'stuck', 'week', 'active'),
            'overview' => array('late', 'done', 'due', 'load', 'blocked', 'unowned', 'over', 'stuck', 'week', 'active'),
            'projects' => array('late', 'done', 'due', 'load', 'blocked', 'unowned', 'over', 'stuck', 'week', 'active'),
            'tasks'    => array('late', 'done', 'due', 'blocked', 'over', 'stuck', 'week', 'active'),
            'subtasks' => array('late', 'due', 'blocked', 'stuck', 'active'),
        );
    }

    /**
     * "My folders, Overview, My projects, My tasks, and My subtasks" for one question, for the settings page.
     */
    public function shownOn($question)
    {
        $labels = array(
            'folder'   => $this->sorta() === null ? null : $this->t('My folders'),
            'overview' => t('Overview'),
            'projects' => t('My projects'),
            'tasks'    => t('My tasks'),
            'subtasks' => t('My subtasks'),
        );
        $names = array();

        foreach (self::getQuestionPages() as $page => $keys) {
            if ($labels[$page] !== null && in_array($question, $keys, true)) {
                $names[] = $labels[$page];
            }
        }

        $last = array_pop($names);

        return empty($names) ? $last : (count($names) === 1 ? $names[0].' '.t('and').' '.$last : implode(', ', $names).', '.t('and').' '.$last);
    }

    /**
     * The questions the admin left on that make sense on the page, in button order.
     */
    public function questions($page)
    {
        $pages = self::getQuestionPages();

        return isset($pages[$page]) ? array_values(array_intersect($this->enabledQuestions(), $pages[$page])) : array();
    }

    /**
     * The data Template/ask.php needs for the open page, or null when the page has no questions or the admin turned them all
     * off. $page is "folder", "overview", "projects", "tasks" or "subtasks". $scope is the visible projects keyed by id, each with "name",
     * "sf_path" and, with SortaFolders, "sf_folder_id". $mine narrows the tasks to one user's (see AnswerModel::build). The "ask" address parameter picks the question.
     */
    public function build($page, array $scope, $mine = null)
    {
        $questions = $this->questions($page);

        if (empty($questions)) {
            return null;
        }

        foreach ($scope as $id => $project) {
            $scope[$id] = (is_array($project) ? $project : array()) + array('name' => '', 'sf_path' => '');
        }

        return $this->busyBeeAnswerModel->build($scope, $questions, $this->request->getStringParam('ask'), is_array($mine) ? $mine : null, (int) $this->userSession->getId(), $this->request->getIntegerParam('ask_page', 1));
    }

    /**
     * The pager under an answer's table, in the markup Kanboard core's list pager uses (so core's CSS styles it): "Showing 1-25 of 36",
     * Previous and Next. It is empty when every row fits on one page. $params are the address parameters of the page (they carry the
     * list's own order, direction and page, see linkParams()); the asked question and the answer's page are added. The answer's page is
     * "ask_page" because "page" belongs to the list under the bar.
     */
    public function pager(array $ask, array $params, $controller, $action)
    {
        if ($ask['pages'] <= 1) {
            return '';
        }

        $link = function ($label, $page) use ($ask, $params, $controller, $action) {
            return $this->helper->url->link($label, $controller, $action, array_merge($params, array('ask' => $ask['asked'], 'ask_page' => $page > 1 ? $page : null)), false);
        };
        $first = ($ask['page'] - 1) * $ask['per_page'] + 1;

        return '<div class="pagination busybee-pagination">'
            .'<span class="pagination-showing">'.t('Showing %d-%d of %d', $first, min($ask['rows_total'], $ask['page'] * $ask['per_page']), $ask['rows_total']).'</span>'
            .'<span class="pagination-previous">'.($ask['page'] > 1 ? $link('&larr; '.t('Previous'), $ask['page'] - 1) : '&larr; '.t('Previous')).'</span>'
            .'<span class="pagination-next">'.($ask['page'] < $ask['pages'] ? $link(t('Next').' &rarr;', $ask['page'] + 1) : t('Next').' &rarr;').'</span>'
            .'</div>';
    }
}
