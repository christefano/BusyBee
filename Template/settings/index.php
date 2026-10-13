<div class="page-header">
    <h2>BusyBee</h2>
</div>

<form class="busybee-settings" method="post" action="<?= $this->url->href('SettingsController', 'save', array('plugin' => 'BusyBee')) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>

    <fieldset>
        <legend><?= $this->busyBee->t('Display questions') ?></legend>
        <p class="form-help"><?= $this->busyBee->t('The buttons above the Overview, My projects, My tasks, and My subtasks pages. Clear a box to hide that question, or clear every box to hide the bar.') ?></p>
        <?php foreach ($questions as $key => $text): ?>
            <label>
                <input type="checkbox" name="ask_<?= $key ?>" value="1" <?= isset($question_on[$key]) ? 'checked' : '' ?>>
                <?= $this->text->e($text[0]) ?>
            </label>
            <p class="form-help"><?= $this->text->e($text[1]) ?></p>
            <p class="form-help"><?= $this->busyBee->t('Shown on') ?> <?= $this->text->e($this->busyBee->shownOn($key)) ?>.</p>
        <?php endforeach ?>
    </fieldset>

    <fieldset>
        <legend><?= $this->busyBee->t('Icons') ?></legend>
        <label>
            <input type="checkbox" name="icons" value="1" <?= $icons_on ? 'checked' : '' ?>>
            <?= $this->busyBee->t('Show BusyBee icons') ?>
        </label>
        <p class="form-help"><?= $this->busyBee->t('Show or hide icons displayed in front of the questions and in front of an answer.') ?></p>
        <fieldset>
            <legend><?= $this->busyBee->t('Question icon') ?></legend>
            <div class="busybee-choices">
                <?php foreach (\Kanboard\Plugin\BusyBee\Helper\BusyBeeHelper::questionIcons() as $key => $label): ?>
                    <label>
                        <input type="radio" name="question_icon" value="<?= $key ?>" <?= $question_icon === $key ? 'checked' : '' ?>>
                        <?= $this->busyBee->icon('question', $key) ?>
                        <?= $this->busyBee->t($label) ?>
                    </label>
                <?php endforeach ?>
            </div>
        </fieldset>
        <fieldset>
            <legend><?= $this->busyBee->t('Answer icon') ?></legend>
            <div class="busybee-choices">
                <?php foreach (\Kanboard\Plugin\BusyBee\Helper\BusyBeeHelper::answerIcons() as $key => $label): ?>
                    <label>
                        <input type="radio" name="answer_icon" value="<?= $key ?>" <?= $answer_icon === $key ? 'checked' : '' ?>>
                        <?= $this->busyBee->icon('answer', $key) ?>
                        <?= $this->busyBee->t($label) ?>
                    </label>
                <?php endforeach ?>
            </div>
        </fieldset>
    </fieldset>

    <button type="submit" class="btn btn-blue"><?= t('Save') ?></button>
</form>
