<?php

namespace Kanboard\Plugin\BusyBee\Model;

use Kanboard\Core\Base;

/**
 * Answers to the questions on the folder page and the dashboard pages, and the workload by person. Every method takes
 * the ids of the projects the viewer can see on that page and reads nothing else, so a hidden project never
 * counts. Closed projects are never in that list.
 */
class AnswerModel extends Base
{
    const ROWS = 25;
    const DUE_DAYS = 30;
    const STUCK_DAYS = 30;
    const WEEK_DAYS = 7;

    public static function getQuestions()
    {
        return array('late', 'done', 'due', 'load', 'blocked', 'unowned', 'over', 'stuck', 'week');
    }

    private function marks(array $ids)
    {
        return implode(',', array_fill(0, count($ids), '?'));
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
     * projects keyed by id, each with "name" and "sf_path". $mine narrows the tasks to one user's (see mine()).
     */
    public function build(array $scope, array $questions, $asked, $mine = null)
    {
        $ids = array_keys($scope);
        $asked = in_array($asked, $questions, true) ? $asked : '';
        $ask = array('counts' => $this->counts($ids, $mine), 'asked' => $asked, 'rows' => array(), 'total' => 0, 'folders' => 0, 'people' => array(), 'since' => $this->monthStart(), 'created' => 0, 'finished' => 0, 'rows_total' => 0, 'questions' => $questions);

        if ($asked === 'load') {
            $ask['people'] = $this->workload($ids);
        } elseif ($asked === 'blocked') {
            $rows = $this->blocked($ids, $mine);
            $waiting = array();

            foreach ($rows as $i => $row) {
                $rows[$i]['path'] = $scope[(int) $row['project_id']]['sf_path'];
                $rows[$i]['project'] = $scope[(int) $row['project_id']]['name'];
                $seen = isset($scope[(int) $row['blocker_project_id']]);
                $rows[$i]['blocker_visible'] = $seen;

                if ($seen) {
                    $rows[$i]['blocker_path'] = $scope[(int) $row['blocker_project_id']]['sf_path'];
                    $rows[$i]['blocker_project'] = $scope[(int) $row['blocker_project_id']]['name'];
                } else {
                    // A project the viewer cannot see leaks nothing: no title, owner, path or project name.
                    $rows[$i]['blocker_title'] = '';
                    $rows[$i]['blocker_id'] = 0;
                    $rows[$i]['blocker_owner_id'] = 0;
                    $rows[$i]['blocker_owner_name'] = '';
                    $rows[$i]['blocker_owner_username'] = '';
                }

                $waiting[(int) $row['id']] = true;
            }

            $ask['total'] = count($waiting);
            $ask['rows_total'] = count($rows);
            $ask['rows'] = array_slice($rows, 0, self::ROWS);
        } elseif ($asked !== '') {
            $rows = $this->tasks($asked, $ids, $mine);
            $folders = array();

            foreach ($rows as $i => $row) {
                $rows[$i]['path'] = $scope[(int) $row['project_id']]['sf_path'];
                $rows[$i]['project'] = $scope[(int) $row['project_id']]['name'];
                $folders[$rows[$i]['path'] === '' ? '#'.$rows[$i]['project_id'] : $rows[$i]['path']] = true;  // with no folders, count the projects

                if ($asked === 'week') {
                    $ask[$row['kind']]++;
                }
            }

            $ask['total'] = count($rows);
            $ask['folders'] = count($folders);
            $ask['rows'] = array_slice($rows, 0, self::ROWS);
        }

        return $ask;
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
     * and week (tasks created plus tasks finished in the last 7 days).
     */
    public function counts(array $ids, $mine = null)
    {
        $counts = array('late' => 0, 'done' => 0, 'due' => 0, 'load' => 0, 'blocked' => 0, 'unowned' => 0, 'over' => 0, 'stuck' => 0, 'week' => 0);
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
                    SUM(CASE WHEN date_creation >= ? THEN 1 ELSE 0 END) + SUM(CASE WHEN is_active = 0 AND date_completed >= ? THEN 1 ELSE 0 END) AS n_week
                FROM tasks t WHERE t.project_id IN ('.$this->marks($ids).')'.$extra[0];
        $row = $this->db->execute($sql, array_merge(array($now, $this->monthStart(), $now, $now + self::DUE_DAYS * 86400, $now - self::WEEK_DAYS * 86400, $now - self::WEEK_DAYS * 86400), $ids, $extra[1]))->fetch(\PDO::FETCH_ASSOC);

        return array('late' => (int) $row['n_late'], 'done' => (int) $row['n_done'], 'due' => (int) $row['n_due'], 'load' => (int) $row['n_people'], 'blocked' => $this->blockedCount($ids, $mine), 'unowned' => (int) $row['n_unowned'], 'over' => (int) $row['n_over'], 'stuck' => (int) $row['n_stuck'], 'week' => (int) $row['n_week']);
    }

    /**
     * Tasks for the late, done, due, unowned, over, stuck and week questions, in order. Each row: id, title, project_id,
     * owner_id, owner_name, owner_username, stamp, date_due, time_spent, time_estimated, and for week also kind
     * ("created" or "finished").
     */
    public function tasks($question, array $ids, $mine = null)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (empty($ids)) {
            return array();
        }

        $now = time();

        if ($question === 'late') {
            $where = 't.is_active = 1 AND t.date_due > 0 AND t.date_due < ?';
            $params = array($now);
            $stamp = 't.date_due';
            $order = 't.date_due ASC';
        } elseif ($question === 'done') {
            $where = 't.is_active = 0 AND t.date_completed >= ?';
            $params = array($this->monthStart());
            $stamp = 't.date_completed';
            $order = 't.date_completed DESC';
        } elseif ($question === 'unowned') {
            $where = 't.is_active = 1 AND t.owner_id = 0';
            $params = array();
            $stamp = 't.date_creation';
            $order = 't.date_creation ASC';
        } elseif ($question === 'over') {
            $where = 't.is_active = 1 AND t.time_estimated > 0 AND t.time_spent > t.time_estimated';
            $params = array();
            $stamp = 't.date_creation';
            $order = '(t.time_spent - t.time_estimated) DESC';
        } elseif ($question === 'stuck') {
            $moved = '(CASE WHEN t.date_moved > 0 THEN t.date_moved ELSE t.date_creation END)';
            $where = 't.is_active = 1 AND '.$moved.' BETWEEN 1 AND '.((int) ($now - self::STUCK_DAYS * 86400));
            $params = array();
            $stamp = $moved;
            $order = $moved.' ASC';
        } elseif ($question === 'week') {
            return $this->week($ids, $mine);
        } else {
            $where = 't.is_active = 1 AND t.date_due >= ? AND t.date_due <= ?';
            $params = array($now, $now + self::DUE_DAYS * 86400);
            $stamp = 't.date_due';
            $order = 't.date_due ASC';
        }

        $extra = $this->mine($mine);
        $sql = 'SELECT t.id, t.title, t.project_id, t.owner_id, t.date_due, t.time_spent, t.time_estimated, '.$stamp.' AS stamp, u.name AS owner_name, u.username AS owner_username
                FROM tasks t LEFT JOIN users u ON u.id = t.owner_id
                WHERE '.$where.' AND t.project_id IN ('.$this->marks($ids).')'.$extra[0].' ORDER BY '.$order.', t.id ASC';

        return $this->db->execute($sql, array_merge($params, $ids, $extra[1]))->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Tasks created and tasks finished in the last 7 days, newest first. A task created and finished in the window shows twice.
     */
    private function week(array $ids, $mine = null)
    {
        $since = time() - self::WEEK_DAYS * 86400;
        $rows = array();
        $extra = $this->mine($mine);

        foreach (array('created' => array('t.date_creation >= ?', 't.date_creation'), 'finished' => array('t.is_active = 0 AND t.date_completed >= ?', 't.date_completed')) as $kind => $def) {
            $sql = 'SELECT t.id, t.title, t.project_id, t.owner_id, t.date_due, t.time_spent, t.time_estimated, '.$def[1].' AS stamp, \''.$kind.'\' AS kind, u.name AS owner_name, u.username AS owner_username
                    FROM tasks t LEFT JOIN users u ON u.id = t.owner_id
                    WHERE '.$def[0].' AND t.project_id IN ('.$this->marks($ids).')'.$extra[0];
            $rows = array_merge($rows, $this->db->execute($sql, array_merge(array($since), $ids, $extra[1]))->fetchAll(\PDO::FETCH_ASSOC));
        }

        usort($rows, function ($a, $b) {
            return array((int) $b['stamp'], (int) $a['id']) <=> array((int) $a['stamp'], (int) $b['id']);
        });

        return $rows;
    }

    /**
     * Open tasks that wait on an open task, one row for each link, oldest waiting task first. The link type is found by its
     * label ("is blocked by") because an admin can rename links. The waiting task is always in a visible project. The
     * blocker can be in any project, so the caller hides every blocker whose project is not in $ids.
     * Each row: id, title, project_id, owner_id, blocker_id, blocker_title, blocker_project_id, blocker_owner_id,
     * blocker_owner_name, blocker_owner_username.
     */
    public function blocked(array $ids, $mine = null)
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
                ORDER BY t.date_creation ASC, t.id ASC, b.id ASC';

        return $this->db->execute($sql, array_merge(array('is blocked by'), $ids, $extra[1]))->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function blockedCount(array $ids, $mine = null)
    {
        $extra = $this->mine($mine);
        $sql = 'SELECT COUNT(DISTINCT t.id) FROM task_has_links l
                JOIN links k ON k.id = l.link_id
                JOIN tasks t ON t.id = l.task_id
                JOIN tasks b ON b.id = l.opposite_task_id
                WHERE k.label = ? AND t.is_active = 1 AND b.is_active = 1 AND t.project_id IN ('.$this->marks($ids).')'.$extra[0];

        return (int) $this->db->execute($sql, array_merge(array('is blocked by'), $ids, $extra[1]))->fetchColumn();
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
