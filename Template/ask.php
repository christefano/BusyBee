<?php
$askController = isset($ask_controller) ? $ask_controller : 'FolderController';
$askAction = isset($ask_action) ? $ask_action : 'show';
$questions = array();

foreach ($this->busyBee->questionTexts() as $key => $text) {
    if (in_array($key, $ask['questions'], true)) {
        $questions[$key] = $text[0];
    }
}
$counts = $ask['counts'];
$asked = $ask['asked'];
$now = time();
$plural = function ($n, $one, $many) {
    return $n === 1 ? $this->busyBee->t($one, 1) : $this->busyBee->t($many, $n);
};
$taskText = function ($n) use ($plural) {
    return $plural($n, '%d task', '%d tasks');
};
$icons = $this->busyBee->iconsEnabled();
$bee = $icons ? $this->busyBee->icon('question') : '';
$answerIcon = $icons ? $this->busyBee->icon('answer') : '';
?>
<div class="busybee">
    <div class="busybee-bar" role="group" aria-label="<?= $this->busyBee->t('Questions') ?>">
        <?= $bee ?>
        <div class="busybee-items">
        <?php foreach ($questions as $key => $label): ?>
            <?php $params = array_merge($url_params, array('ask' => $asked === $key ? null : $key)) ?>
            <span class="busybee-item">
                <a class="busybee-q<?= $asked === $key ? ' busybee-on' : '' ?>" href="<?= $this->url->href($askController, $askAction, $params) ?>" <?= $asked === $key ? ' aria-current="true"' : '' ?>>
                    <?= $this->text->e($label) ?>
                    <span class="busybee-count<?= $key === 'late' && $counts['late'] > 0 ? ' busybee-hot' : '' ?>"><?= (int) $counts[$key] ?></span>
                </a>
            </span>
        <?php endforeach ?>
        </div>
    </div>

    <?php if ($asked !== ''): ?>
        <div class="busybee-panel">
            <?php if ($asked === 'load'): ?>
                <?php $people = $ask['people'] ?>
                <?php $top = null ?>
                <?php foreach ($people as $p) { if ((int) $p['owner_id'] > 0) { $top = $p; break; } } ?>
                <?php if ($top === null): ?>
                    <p class="busybee-answer"><?= $answerIcon ?><span><?= $this->busyBee->t('No one has an open task.') ?></span></p>
                <?php else: ?>
                    <p class="busybee-answer"><?= $answerIcon ?><span>
                        <?= $this->text->e($top['owner_name'] ?: $top['owner_username']) ?> <?= (int) $top['n_overdue'] > 0
                            ? ((int) $top['n_overdue'] === 1 ? t('has %s and 1 is overdue.', $plural((int) $top['n_open'], '%d open task', '%d open tasks')) : t('has %s and %d are overdue.', $plural((int) $top['n_open'], '%d open task', '%d open tasks'), (int) $top['n_overdue']))
                            : t('has %s.', $plural((int) $top['n_open'], '%d open task', '%d open tasks')) ?>
                    </span></p>
                <?php endif ?>
                <?php $max = 1; foreach ($people as $p) { $max = max($max, (int) $p['n_open']); } ?>
                <div class="busybee-scroll">
                    <table class="busybee-table">
                        <thead><tr><th><?= t('User') ?></th><th><?= t('Open tasks') ?></th><th><?= t('Overdue') ?></th><th><?= t('Projects') ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($people as $p): ?>
                            <tr>
                                <td><?= (int) $p['owner_id'] > 0 ? $this->busyBee->userLink($p['owner_id'], $p['owner_name'] ?: $p['owner_username']) : t('Unassigned') ?></td>
                                <td>
                                    <span class="busybee-track" role="img" aria-label="<?= t('%d open, %d overdue', (int) $p['n_open'], (int) $p['n_overdue']) ?>"><i style="width:<?= round(100 * ((int) $p['n_open'] - (int) $p['n_overdue']) / $max) ?>%"></i><i class="busybee-late" style="width:<?= round(100 * (int) $p['n_overdue'] / $max) ?>%"></i></span>
                                    <?= (int) $p['n_open'] ?>
                                </td>
                                <td><?= (int) $p['n_overdue'] ?></td>
                                <td><?= (int) $p['n_projects'] ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($asked === 'blocked'): ?>
                <?php $n = (int) $ask['total'] ?>
                <p class="busybee-answer"><?= $answerIcon ?><span>
                    <?= $n === 0 ? t('Nothing is blocked.') : ($n === 1 ? t('1 task is blocked.') : t('%d tasks are blocked.', $n)) ?>
                </span></p>
                <?php if ($n > 0): ?>
                    <div class="busybee-scroll">
                        <table class="busybee-table">
                            <thead><tr><th><?= t('Waiting task') ?></th><th><?= t('Blocked by') ?></th><th><?= t('Owner of the blocker') ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($ask['rows'] as $row): ?>
                                <tr>
                                    <td>
                                        <?= $this->url->link($this->text->e($row['title']), 'TaskViewController', 'show', array('task_id' => $row['id'], 'project_id' => $row['project_id'])) ?>
                                        <small class="busybee-path"><?= $row['path'] === '' ? '' : $this->text->e($row['path']).' &rsaquo; ' ?><?= $this->text->e($row['project']) ?></small>
                                    </td>
                                    <?php if ($row['blocker_visible']): ?>
                                        <td>
                                            <?= $this->url->link($this->text->e($row['blocker_title']), 'TaskViewController', 'show', array('task_id' => $row['blocker_id'], 'project_id' => $row['blocker_project_id'])) ?>
                                            <small class="busybee-path"><?= $row['blocker_path'] === '' ? '' : $this->text->e($row['blocker_path']).' &rsaquo; ' ?><?= $this->text->e($row['blocker_project']) ?><?= empty($row['blocker_closed']) ? '' : ' '.$this->text->e(t('(closed project)')) ?></small>
                                        </td>
                                        <td><?= (int) $row['blocker_owner_id'] > 0 ? $this->busyBee->userLink($row['blocker_owner_id'], $row['blocker_owner_name'] ?: $row['blocker_owner_username']) : t('Unassigned') ?></td>
                                    <?php else: ?>
                                        <td><?= t('A task in a project you cannot see') ?></td>
                                        <td></td>
                                    <?php endif ?>
                                </tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                    <?= $this->busyBee->pager($ask, $url_params, $askController, $askAction) ?>
                <?php endif ?>
            <?php else: ?>
                <?php $n = (int) $ask['total'] ?>
                <p class="busybee-answer"><?= $answerIcon ?><span>
                    <?php if ($asked === 'week'): ?>
                        <?php if ($n === 0): ?>
                            <?= t('Nothing was created or finished in the last 7 days.') ?>
                        <?php else: ?>
                            <?= $ask['created'] === 0 ? t('No task was created') : ($ask['created'] === 1 ? t('1 task was created') : t('%d tasks were created', $ask['created'])) ?> <?= t('and') ?> <?= t('%d finished in the last 7 days.', $ask['finished']) ?>
                        <?php endif ?>
                    <?php elseif ($n === 0): ?>
                        <?= array(
                            'late' => t('Nothing is late.'),
                            'done' => t('No tasks finished this month.'),
                            'due' => t('Nothing is due within 30 days.'),
                            'unowned' => t('Every open task has an owner.'),
                            'over' => t('No task is over its estimate.'),
                            'stuck' => t('No task has been stuck for 30 days.'),
                            'active' => t('No task has changed in the last 30 days.'),
                        )[$asked] ?>
                    <?php elseif ($asked === 'late'): ?>
                        <?= $n === 1 ? $this->busyBee->t('1 task in 1 folder is late.') : t('%s in %s are late.', $taskText($n), $plural((int) $ask['folders'], '%d folder', '%d folders')) ?>
                    <?php elseif ($asked === 'done'): ?>
                        <?= t('%s finished since %s.', $taskText($n), $this->dt->date($ask['since'])) ?>
                    <?php elseif ($asked === 'unowned'): ?>
                        <?= $n === 1 ? t('1 task has no owner.') : t('%d tasks have no owner.', $n) ?>
                    <?php elseif ($asked === 'over'): ?>
                        <?= $n === 1 ? t('1 task is over its estimate.') : t('%d tasks are over their estimate.', $n) ?>
                    <?php elseif ($asked === 'active'): ?>
                        <?= $n === 1 ? t('1 task changed in the last 30 days.') : t('%d tasks changed in the last 30 days.', $n) ?>
                    <?php elseif ($asked === 'stuck'): ?>
                        <?= $n === 1 ? t('1 task has not moved in 30 days.') : t('%d tasks have not moved in 30 days.', $n) ?>
                    <?php else: ?>
                        <?= $n === 1 ? t('1 task is due within 30 days.') : t('%d tasks are due within 30 days.', $n) ?>
                    <?php endif ?>
                </span></p>
                <?php if ($n > 0): ?>
                    <?php
                    $heads = array(
                        'late' => array(t('Owner'), t('Late by')),
                        'done' => array(t('Owner'), t('Closed')),
                        'due' => array(t('Owner'), t('Due in')),
                        'unowned' => array(t('Due'), t('Open for')),
                        'over' => array(t('Owner'), t('Hours')),
                        'stuck' => array(t('Owner'), t('Not moved for')),
                        'week' => array(t('Owner'), t('Change')),
                        'active' => array(t('Owner'), t('Change'), t('Last activity')),
                    );
                    $dayText = function ($days) {
                        return $days === 1 ? t('1 day') : t('%d days', $days);
                    };
                    ?>
                    <div class="busybee-scroll">
                        <table class="busybee-table">
                            <thead><tr><th><?= t('Task') ?></th><?php foreach ($heads[$asked] as $head): ?><th><?= $head ?></th><?php endforeach ?></tr></thead>
                            <tbody>
                            <?php foreach ($ask['rows'] as $row): ?>
                                <tr>
                                    <td>
                                        <?= $this->url->link($this->text->e($row['title']), 'TaskViewController', 'show', array('task_id' => $row['id'], 'project_id' => $row['project_id'])) ?>
                                        <small class="busybee-path"><?= $row['path'] === '' ? '' : $this->text->e($row['path']).' &rsaquo; ' ?><?= $this->text->e($row['project']) ?></small>
                                    </td>
                                    <?php if ($asked === 'active'): ?>
                                        <?php $ago = max(0, (int) floor(($now - (int) $row['stamp']) / 86400)) ?>
                                        <td><?= (int) $row['owner_id'] > 0 ? $this->busyBee->userLink($row['owner_id'], $row['owner_name'] ?: $row['owner_username']) : t('Unassigned') ?></td>
                                        <td><?= $this->text->e($this->busyBee->changeText($row['change'])) ?></td>
                                        <td><?= $ago === 0 ? t('Today') : t('%s ago', $dayText($ago)) ?></td>
                                    <?php else: ?>
                                    <td>
                                        <?php if ($asked === 'unowned'): ?>
                                            <?= (int) $row['date_due'] > 0 ? $this->dt->date((int) $row['date_due']) : t('No due date') ?>
                                        <?php else: ?>
                                            <?= (int) $row['owner_id'] > 0 ? $this->busyBee->userLink($row['owner_id'], $row['owner_name'] ?: $row['owner_username']) : t('Unassigned') ?>
                                        <?php endif ?>
                                    </td>
                                    <td>
                                        <?php if ($asked === 'late'): ?>
                                            <?= $dayText(max(1, (int) floor(($now - (int) $row['stamp']) / 86400))) ?>
                                        <?php elseif ($asked === 'done'): ?>
                                            <?= $this->dt->date((int) $row['stamp']) ?>
                                        <?php elseif ($asked === 'unowned' || $asked === 'stuck'): ?>
                                            <?= $dayText(max(0, (int) floor(($now - (int) $row['stamp']) / 86400))) ?>
                                        <?php elseif ($asked === 'over'): ?>
                                            <?= $this->busyBee->hoursText(array('hours' => $row['time_spent'], 'estimated' => $row['time_estimated'])) ?>
                                        <?php elseif ($asked === 'week'): ?>
                                            <?= $row['kind'] === 'created' ? t('Created') : t('Finished') ?> <?= $this->dt->date((int) $row['stamp']) ?>
                                        <?php else: ?>
                                            <?= $dayText(max(0, (int) ceil(((int) $row['stamp'] - $now) / 86400))) ?>
                                        <?php endif ?>
                                    </td>
                                    <?php endif ?>
                                </tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                    <?= $this->busyBee->pager($ask, $url_params, $askController, $askAction) ?>
                <?php endif ?>
            <?php endif ?>
        </div>
    <?php endif ?>
</div>
