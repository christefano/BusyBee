<?php

namespace Kanboard\Plugin\BusyBee\Model;

use Kanboard\Core\Base;

/**
 * Answers to the questions on the folder page and the dashboard pages, and the workload by person. Every method takes
 * the ids of the projects the viewer can see on that page and reads nothing else, so a hidden project never
 * counts. Closed projects are never in that list. The one exception is the blocker in a "What is blocked?" row: it can
 * live in any project, and build() shows it when the viewer may open that project (see openable()).
 */
class AnswerModel extends Base
{
    const ROWS = 25;
    const DUE_DAYS = 30;
    const STUCK_DAYS = 30;
    const WEEK_DAYS = 7;
    const ACTIVE_DAYS = 30;

    public static function getQuestions()
    {
        return array('late', 'done', 'due', 'load', 'blocked', 'unowned', 'over', 'stuck', 'week', 'active');
    }

    /**
     * The LIMIT clause for $limit rows from $offset, nothing for a limit of 0. Written into the SQL as integers (see LinkCountModel).
     */
    private function slice($limit, $offset)
    {
        return $limit > 0 ? ' LIMIT '.(int) $limit.((int) $offset > 0 ? ' OFFSET '.(int) $offset : '') : '';
    }

    private function marks(array $ids)
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }

    /**
     * SQL for when a task last changed: the latest of its modified, moved and created dates and its newest activity event (a comment,
     * an edit, a move, a subtask, a file, and so on). The tasks table must be aliased "t".
     */
    private function touched()
    {
        $latest = function ($a, $b) {
            return '(CASE WHEN '.$a.' >= '.$b.' THEN '.$a.' ELSE '.$b.' END)';
        };
        $own = $latest($latest('t.date_modification', 't.date_moved'), 't.date_creation');
        $event = '(SELECT COALESCE(MAX(pa.date_creation), 0) FROM project_activities pa WHERE pa.task_id = t.id)';

        return $latest($own, $event);
    }

    /**
     * Narrows the tasks to one user's, for the dashboard pages. $mine is null (every task in the projects),
     * array('owner' => id) (tasks assigned to the user), array('tasks' => id) (what My tasks lists: tasks the user owns or has a subtask in) or array('subtasks' => id)
     * (what My subtasks lists: tasks with an open subtask assigned to the user). Returns array(sql, params), with the
     * sql starting with " AND " or empty. The tasks table must be aliased "t".
     */
    private function mine($mine)
    {
        if (empty($mine)) {
            return array('', array());
        }

        if (isset($mine['owner'])) {
            return array(' AND t.owner_id = ?', array((int) $mine['owner']));
        }

        if (isset($mine['subtasks'])) {
            return array(' AND EXISTS (SELECT 1 FROM subtasks s WHERE s.task_id = t.id AND s.user_id = ? AND s.status < 2)', array((int) $mine['subtasks']));
        }

        return array(' AND (t.owner_id = ? OR EXISTS (SELECT 1 FROM subtasks s WHERE s.task_id = t.id AND s.user_id = ?))', array((int) $mine['tasks'], (int) $mine['tasks']));
    }

    /**
     * Everything Template/ask.php needs: the counts for every button, and the answer to the asked question. $scope is the visible
     * projects keyed by id, each with "name" and "sf_path", and "sf_folder_id" when SortaFolders sorted the project into a folder.
     * $mine narrows the tasks to one user's (see mine()). $viewerId is the signed-in user, who decides which blockers outside the
     * scope show. $page is the page of the answer's table, 1 for the first ROWS rows; a page past the last one reads the last.
     */
    public function build(array $scope, array $questions, $asked, $mine = null, $viewerId = 0, $page = 1)
    {
        $ids = array_keys($scope);
        $asked = in_array($asked, $questions, true) ? $asked : '';
        $ask = array('counts' => $this->counts($ids, $mine), 'asked' => $asked, 'rows' => array(), 'total' => 0, 'folders' => 0, 'people' => array(), 'since' => $this->monthStart(), 'created' => 0, 'finished' => 0, 'rows_total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => self::ROWS, 'questions' => $questions);

        if ($asked === 'load') {
            $ask['people'] = $this->workload($ids);
        } elseif ($asked === 'blocked') {
            $totals = $this->blockedTotals($ids, $mine);
            $page = $this->pageOf($page, $totals['rows']);
            $rows = $this->blocked($ids, $mine, self::ROWS, ($page - 1) * self::ROWS);
            $outside = array();

            foreach ($rows as $row) {
                if (! isset($scope[(int) $row['blocker_project_id']])) {
                    $outside[] = (int) $row['blocker_project_id'];
                }
            }

            $outside = $this->openable($outside, $viewerId);

            foreach ($rows as $i => $row) {
                $rows[$i]['path'] = $scope[(int) $row['project_id']]['sf_path'];
                $rows[$i]['project'] = $scope[(int) $row['project_id']]['name'];
                $blockerProject = (int) $row['blocker_project_id'];
                $seen = isset($scope[$blockerProject]) ? $scope[$blockerProject] : (isset($outside[$blockerProject]) ? $outside[$blockerProject] : null);
                $rows[$i]['blocker_visible'] = $seen !== null;
                $rows[$i]['blocker_closed'] = $seen !== null && isset($seen['is_active']) && (int) $seen['is_active'] !== 1;

                if ($seen !== null) {
                    $rows[$i]['blocker_path'] = $seen['sf_path'];
                    $rows[$i]['blocker_project'] = $seen['name'];
                } else {
                    // A project the viewer cannot open leaks nothing: no title, owner, path or project name.
                    $rows[$i]['blocker_title'] = '';
                    $rows[$i]['blocker_id'] = 0;
                    $rows[$i]['blocker_owner_id'] = 0;
                    $rows[$i]['blocker_owner_name'] = '';
                    $rows[$i]['blocker_owner_username'] = '';
                }
            }

            $ask['total'] = $totals['tasks'];
            $ask['rows_total'] = $totals['rows'];
            $ask['page'] = $page;
            $ask['pages'] = max(1, (int) ceil($totals['rows'] / self::ROWS));
            $ask['rows'] = $rows;
        } elseif ($asked !== '') {
            $perProject = $this->taskTotals($asked, $ids, $mine);
            $page = $this->pageOf($page, $perProject['total']);
            $rows = $this->tasks($asked, $ids, $mine, self::ROWS, ($page - 1) * self::ROWS);
            $folders = array();

            foreach ($rows as $i => $row) {
                $rows[$i]['path'] = $scope[(int) $row['project_id']]['sf_path'];
                $rows[$i]['project'] = $scope[(int) $row['project_id']]['name'];
            }

            foreach ($perProject['projects'] as $projectId => $n) {
                $folders[$this->placeKey($scope[$projectId], $projectId)] = true;
            }

            $ask['total'] = $perProject['total'];
            $ask['rows_total'] = $perProject['total'];
            $ask['page'] = $page;
            $ask['pages'] = max(1, (int) ceil($perProject['total'] / self::ROWS));
            $ask['folders'] = count($folders);
            $ask['rows'] = $rows;

            if ($asked === 'week') {
                $ask['created'] = $perProject['created'];
                $ask['finished'] = $perProject['finished'];
            }
        }

        return $ask;
    }

    /**
     * The page to read: $page, at least 1 and at most the last page of $rows rows.
     */
    private function pageOf($page, $rows)
    {
        return max(1, min((int) $page, (int) ceil($rows / self::ROWS)));
    }

    /**
     * What counts as one place in "N tasks in N folders": the folder id when SortaFolders sorted the project, else the project itself.
     */
    private function placeKey(array $project, $projectId)
    {
        return empty($project['sf_folder_id']) ? '#'.(int) $projectId : 'f'.(int) $project['sf_folder_id'];
    }

    /**
     * Of these project ids, the ones the viewer may open that are outside the page scope (another folder, or a closed project),
     * keyed by id with "name", "is_active" and "sf_path". An app admin may open every project, anyone else the projects they belong to,
     * active or closed. A project the viewer cannot open, or that SortaFolders hides from the viewer, is left out.
     */
    private function openable(array $ids, $viewerId)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (empty($ids)) {
            return array();
        }

        if (! $this->userSession->isAdmin()) {
            $ids = array_values(array_intersect($ids, array_map('intval', $this->projectPermissionModel->getProjectIds((int) $viewerId))));
        }

        // A project SortaFolders hides from the viewer is left out like one the viewer cannot open.
        $ids = array_values(array_diff($ids, $this->helper->busyBee->hiddenProjectIds()));

        if (empty($ids)) {
            return array();
        }

        $paths = $this->helper->busyBee->paths($ids);
        $open = array();

        foreach ($this->projectModel->getAllByIds($ids) as $project) {
            $open[(int) $project['id']] = array('name' => $project['name'], 'is_active' => (int) $project['is_active'], 'sf_path' => isset($paths[(int) $project['id']]) ? $paths[(int) $project['id']] : '');
        }

        return $open;
    }

    /**
     * Start of the current month as a timestamp.
     */
    public function monthStart()
    {
        return mktime(0, 0, 0, (int) date('n'), 1, (int) date('Y'));
    }

    /**
     * Button counts: late, done, due, load (people holding an open task), blocked (open tasks waiting on an open task),
     * unowned (open tasks with no owner), over (open tasks past their estimate), stuck (open tasks not moved for 30 days)
     * week (tasks created plus tasks finished in the last 7 days) and active (tasks changed in the last 30 days, open or closed).
     */
    public function counts(array $ids, $mine = null)
    {
        $counts = array('late' => 0, 'done' => 0, 'due' => 0, 'load' => 0, 'blocked' => 0, 'unowned' => 0, 'over' => 0, 'stuck' => 0, 'week' => 0, 'active' => 0);
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (empty($ids)) {
            return $counts;
        }

        $now = time();
        $extra = $this->mine($mine);
        $sql = 'SELECT
                    SUM(CASE WHEN is_active = 1 AND date_due > 0 AND date_due < ? THEN 1 ELSE 0 END) AS n_late,
                    SUM(CASE WHEN is_active = 0 AND date_completed >= ? THEN 1 ELSE 0 END) AS n_done,
                    SUM(CASE WHEN is_active = 1 AND date_due >= ? AND date_due <= ? THEN 1 ELSE 0 END) AS n_due,
                    COUNT(DISTINCT CASE WHEN is_active = 1 AND owner_id > 0 THEN owner_id ELSE NULL END) AS n_people,
                    SUM(CASE WHEN is_active = 1 AND owner_id = 0 THEN 1 ELSE 0 END) AS n_unowned,
                    SUM(CASE WHEN is_active = 1 AND time_estimated > 0 AND time_spent > time_estimated THEN 1 ELSE 0 END) AS n_over,
                    SUM(CASE WHEN is_active = 1 AND (CASE WHEN date_moved > 0 THEN date_moved ELSE date_creation END) BETWEEN 1 AND '.((int) ($now - self::STUCK_DAYS * 86400)).' THEN 1 ELSE 0 END) AS n_stuck,
                    SUM(CASE WHEN date_creation >= ? THEN 1 ELSE 0 END) + SUM(CASE WHEN is_active = 0 AND date_completed >= ? THEN 1 ELSE 0 END) AS n_week,
                    SUM(CASE WHEN '.$this->touched().' >= '.((int) ($now - self::ACTIVE_DAYS * 86400)).' THEN 1 ELSE 0 END) AS n_active
                FROM tasks t WHERE t.project_id IN ('.$this->marks($ids).')'.$extra[0];
        $row = $this->db->execute($sql, array_merge(array($now, $this->monthStart(), $now, $now + self::DUE_DAYS * 86400, $now - self::WEEK_DAYS * 86400, $now - self::WEEK_DAYS * 86400), $ids, $extra[1]))->fetch(\PDO::FETCH_ASSOC);

        return array('late' => (int) $row['n_late'], 'done' => (int) $row['n_done'], 'due' => (int) $row['n_due'], 'load' => (int) $row['n_people'], 'blocked' => $this->blockedCount($ids, $mine), 'unowned' => (int) $row['n_unowned'], 'over' => (int) $row['n_over'], 'stuck' => (int) $row['n_stuck'], 'week' => (int) $row['n_week'], 'active' => (int) $row['n_active']);
    }

    /**
     * The WHERE, its parameters, the stamp column and the ORDER BY of each question, for tasks() and taskTotals().
     */
    private function spec($question)
    {
        $now = time();

        if ($question === 'late') {
            return array('t.is_active = 1 AND t.date_due > 0 AND t.date_due < ?', array($now), 't.date_due', 't.date_due ASC');
        }

        if ($question === 'done') {
            return array('t.is_active = 0 AND t.date_completed >= ?', array($this->monthStart()), 't.date_completed', 't.date_completed DESC');
        }

        if ($question === 'unowned') {
            return array('t.is_active = 1 AND t.owner_id = 0', array(), 't.date_creation', 't.date_creation ASC');
        }

        if ($question === 'over') {
            return array('t.is_active = 1 AND t.time_estimated > 0 AND t.time_spent > t.time_estimated', array(), 't.date_creation', '(t.time_spent - t.time_estimated) DESC');
        }

        if ($question === 'active') {
            $touched = $this->touched();

            return array($touched.' >= '.((int) ($now - self::ACTIVE_DAYS * 86400)), array(), $touched, $touched.' DESC');
        }

        if ($question === 'stuck') {
            $moved = '(CASE WHEN t.date_moved > 0 THEN t.date_moved ELSE t.date_creation END)';

            return array('t.is_active = 1 AND '.$moved.' BETWEEN 1 AND '.((int) ($now - self::STUCK_DAYS * 86400)), array(), $moved, $moved.' ASC');
        }

        return array('t.is_active = 1 AND t.date_due >= ? AND t.date_due <= ?', array($now, $now + self::DUE_DAYS * 86400), 't.date_due', 't.date_due ASC');
    }

    /**
     * Tasks for the late, done, due, unowned, over, stuck, week and active questions, in order, $limit of them from $offset (a limit of 0 is all). Each row: id, title,
     * project_id, owner_id, owner_name, owner_username, stamp, date_due, time_spent, time_estimated, and for week also kind
     * ("created" or "finished").
     */
    public function tasks($question, array $ids, $mine = null, $limit = 0, $offset = 0)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (empty($ids)) {
            return array();
        }

        if ($question === 'week') {
            return $this->week($ids, $mine, $limit, $offset);
        }

        list($where, $params, $stamp, $order) = $this->spec($question);
        $extra = $this->mine($mine);
        $sql = 'SELECT t.id, t.title, t.project_id, t.owner_id, t.date_due, t.time_spent, t.time_estimated, t.date_creation, t.date_modification, t.date_moved, '.$stamp.' AS stamp, u.name AS owner_name, u.username AS owner_username
                FROM tasks t LEFT JOIN users u ON u.id = t.owner_id
                WHERE '.$where.' AND t.project_id IN ('.$this->marks($ids).')'.$extra[0].' ORDER BY '.$order.', t.id ASC'.$this->slice($limit, $offset);
        $rows = $this->db->execute($sql, array_merge($params, $ids, $extra[1]))->fetchAll(\PDO::FETCH_ASSOC);

        return $question === 'active' ? $this->withChange($rows) : $rows;
    }

    /**
     * Adds "change" to each row of the active question: the code of the newest activity event of the task (a comment, an edit, a move, and
     * so on, see BusyBeeHelper::changeTexts()). When no event is as new as the task's last change, which happens to a task changed
     * outside the web interface, the code comes from the task's own dates: "moved", "edited" or "created". Of several events in the
     * same second, a column, swimlane or project move wins over a mere change of position.
     */
    private function withChange(array $rows)
    {
        if (empty($rows)) {
            return $rows;
        }

        $ids = array_map('intval', array_column($rows, 'id'));
        $sql = 'SELECT pa.task_id, pa.event_name, pa.date_creation FROM project_activities pa
                WHERE pa.task_id IN ('.$this->marks($ids).') AND pa.date_creation = (SELECT MAX(p2.date_creation) FROM project_activities p2 WHERE p2.task_id = pa.task_id)
                ORDER BY pa.id DESC';
        $latest = array();

        foreach ($this->db->execute($sql, $ids)->fetchAll(\PDO::FETCH_ASSOC) as $event) {
            $id = (int) $event['task_id'];

            if (! isset($latest[$id]) || ($latest[$id]['event_name'] === 'task.move.position' && $event['event_name'] !== 'task.move.position')) {
                $latest[$id] = $event;
            }
        }

        foreach ($rows as $i => $row) {
            $id = (int) $row['id'];
            $stamp = (int) $row['stamp'];

            if (isset($latest[$id]) && (int) $latest[$id]['date_creation'] >= $stamp - 2) {
                $rows[$i]['change'] = $latest[$id]['event_name'];
            } elseif ((int) $row['date_moved'] === $stamp) {
                $rows[$i]['change'] = 'moved';
            } elseif ((int) $row['date_modification'] === $stamp) {
                $rows[$i]['change'] = 'edited';
            } else {
                $rows[$i]['change'] = 'created';
            }
        }

        return $rows;
    }

    /**
     * How many tasks answer a question, and in which projects: array('total' => n, 'projects' => project id => n, and for week
     * also 'created' and 'finished'). One aggregate query, so the answer does not need every row.
     */
    public function taskTotals($question, array $ids, $mine = null)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $out = array('total' => 0, 'projects' => array(), 'created' => 0, 'finished' => 0);

        if (empty($ids)) {
            return $out;
        }

        $extra = $this->mine($mine);

        if ($question === 'week') {
            $since = time() - self::WEEK_DAYS * 86400;

            foreach (array('created' => 't.date_creation >= ?', 'finished' => 't.is_active = 0 AND t.date_completed >= ?') as $kind => $where) {
                $sql = 'SELECT t.project_id, COUNT(*) AS n FROM tasks t WHERE '.$where.' AND t.project_id IN ('.$this->marks($ids).')'.$extra[0].' GROUP BY t.project_id';

                foreach ($this->db->execute($sql, array_merge(array($since), $ids, $extra[1]))->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $out[$kind] += (int) $row['n'];
                    $out['total'] += (int) $row['n'];
                    $out['projects'][(int) $row['project_id']] = true;
                }
            }

            return $out;
        }

        list($where, $params) = $this->spec($question);
        $sql = 'SELECT t.project_id, COUNT(*) AS n FROM tasks t WHERE '.$where.' AND t.project_id IN ('.$this->marks($ids).')'.$extra[0].' GROUP BY t.project_id';

        foreach ($this->db->execute($sql, array_merge($params, $ids, $extra[1]))->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $out['total'] += (int) $row['n'];
            $out['projects'][(int) $row['project_id']] = (int) $row['n'];
        }

        return $out;
    }

    /**
     * Tasks created and tasks finished in the last 7 days, newest first, $limit of them from $offset (a limit of 0 is all). A task created and finished in the window shows twice.
     */
    private function week(array $ids, $mine = null, $limit = 0, $offset = 0)
    {
        $since = time() - self::WEEK_DAYS * 86400;
        $rows = array();
        $extra = $this->mine($mine);

        foreach (array('created' => array('t.date_creation >= ?', 't.date_creation'), 'finished' => array('t.is_active = 0 AND t.date_completed >= ?', 't.date_completed')) as $kind => $def) {
            $sql = 'SELECT t.id, t.title, t.project_id, t.owner_id, t.date_due, t.time_spent, t.time_estimated, '.$def[1].' AS stamp, \''.$kind.'\' AS kind, u.name AS owner_name, u.username AS owner_username
                    FROM tasks t LEFT JOIN users u ON u.id = t.owner_id
                    WHERE '.$def[0].' AND t.project_id IN ('.$this->marks($ids).')'.$extra[0].' ORDER BY '.$def[1].' DESC, t.id ASC'.$this->slice($limit > 0 ? $limit + $offset : 0, 0);
            $rows = array_merge($rows, $this->db->execute($sql, array_merge(array($since), $ids, $extra[1]))->fetchAll(\PDO::FETCH_ASSOC));
        }

        usort($rows, function ($a, $b) {
            return array((int) $b['stamp'], (int) $a['id']) <=> array((int) $a['stamp'], (int) $b['id']);
        });

        return $limit > 0 ? array_slice($rows, (int) $offset, $limit) : $rows;
    }

    /**
     * Open tasks that wait on an open task, one row for each link, oldest waiting task first, $limit rows from $offset (a limit of 0 is all). The link
     * type is found by its label ("is blocked by") because an admin can rename links. The waiting task is always in a visible project.
     * The blocker can be in any project, so build() hides every blocker whose project the viewer cannot open.
     * Each row: id, title, project_id, owner_id, blocker_id, blocker_title, blocker_project_id, blocker_owner_id,
     * blocker_owner_name, blocker_owner_username.
     */
    public function blocked(array $ids, $mine = null, $limit = 0, $offset = 0)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (empty($ids)) {
            return array();
        }

        $extra = $this->mine($mine);
        $sql = 'SELECT t.id, t.title, t.project_id, t.owner_id, b.id AS blocker_id, b.title AS blocker_title, b.project_id AS blocker_project_id,
                    b.owner_id AS blocker_owner_id, u.name AS blocker_owner_name, u.username AS blocker_owner_username
                FROM task_has_links l
                JOIN links k ON k.id = l.link_id
                JOIN tasks t ON t.id = l.task_id
                JOIN tasks b ON b.id = l.opposite_task_id
                LEFT JOIN users u ON u.id = b.owner_id
                WHERE k.label = ? AND t.is_active = 1 AND b.is_active = 1 AND t.project_id IN ('.$this->marks($ids).')'.$extra[0].'
                ORDER BY t.date_creation ASC, t.id ASC, b.id ASC'.$this->slice($limit, $offset);

        return $this->db->execute($sql, array_merge(array('is blocked by'), $ids, $extra[1]))->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * How many tasks wait ("tasks") and how many links they wait on ("rows"), for the answer line and the pager.
     */
    private function blockedTotals(array $ids, $mine = null)
    {
        $extra = $this->mine($mine);
        $sql = 'SELECT COUNT(DISTINCT t.id) AS n_tasks, COUNT(*) AS n_rows FROM task_has_links l
                JOIN links k ON k.id = l.link_id
                JOIN tasks t ON t.id = l.task_id
                JOIN tasks b ON b.id = l.opposite_task_id
                WHERE k.label = ? AND t.is_active = 1 AND b.is_active = 1 AND t.project_id IN ('.$this->marks($ids).')'.$extra[0];
        $row = $this->db->execute($sql, array_merge(array('is blocked by'), $ids, $extra[1]))->fetch(\PDO::FETCH_ASSOC);

        return array('tasks' => (int) $row['n_tasks'], 'rows' => (int) $row['n_rows']);
    }

    private function blockedCount(array $ids, $mine = null)
    {
        $totals = $this->blockedTotals($ids, $mine);

        return $totals['tasks'];
    }

    /**
     * Open tasks per person (0 = unassigned): open, overdue, active projects. Most overdue first, then most open.
     */
    public function workload(array $ids)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (empty($ids)) {
            return array();
        }

        $sql = 'SELECT t.owner_id, u.name AS owner_name, u.username AS owner_username, COUNT(*) AS n_open,
                    SUM(CASE WHEN t.date_due > 0 AND t.date_due < ? THEN 1 ELSE 0 END) AS n_overdue,
                    COUNT(DISTINCT t.project_id) AS n_projects
                FROM tasks t LEFT JOIN users u ON u.id = t.owner_id
                WHERE t.is_active = 1 AND t.project_id IN ('.$this->marks($ids).')
                GROUP BY t.owner_id, u.name, u.username';
        $rows = $this->db->execute($sql, array_merge(array(time()), $ids))->fetchAll(\PDO::FETCH_ASSOC);

        usort($rows, function ($a, $b) {
            if ((int) $a['owner_id'] === 0 || (int) $b['owner_id'] === 0) {
                return (int) $a['owner_id'] === 0 ? 1 : -1;
            }

            return array((int) $b['n_overdue'], (int) $b['n_open'], $a['owner_name']) <=> array((int) $a['n_overdue'], (int) $a['n_open'], $b['owner_name']);
        });

        return $rows;
    }
}
