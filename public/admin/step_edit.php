<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('journeys.manage');

$id = (int)($_GET['id'] ?? 0);
$step = $id ? step_by_id($id) : null;
$chapterId = $step ? (int)$step['chapter_id'] : (int)($_GET['chapter_id'] ?? 0);
$chapter = chapter_by_id($chapterId);
if (!$chapter || ($id && !$step)) {
    abort_page(404, 'Step or chapter not found.');
}
$journey = journey_by_id((int)$chapter['journey_id']);
if (!$journey || !journey_can_edit($journey)) {
    abort_page(403, 'You do not have permission to edit this journey.');
}
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    try {
        if ($id) {
            update_step(
                $id,
                (string)($_POST['title'] ?? ''),
                (string)($_POST['description'] ?? ''),
                (string)($_POST['completion_mode'] ?? ''),
                (string)($_POST['auto_rule_type'] ?? ''),
                (string)($_POST['rule_skill_name'] ?? ''),
                ($_POST['rule_level'] ?? '') === '' ? null : (int)$_POST['rule_level'],
                (string)($_POST['rule_quest_title'] ?? ''),
                (int)($_POST['sort_order'] ?? 0),
                !empty($_POST['is_optional']),
                ($_POST['requires_step_id'] ?? '') === '' ? null : (int)$_POST['requires_step_id'],
                ($_POST['content_item_id'] ?? '') === '' ? null : (int)$_POST['content_item_id']
            );
        } else {
            create_step(
                $chapterId,
                (string)($_POST['title'] ?? ''),
                (string)($_POST['description'] ?? ''),
                (string)($_POST['completion_mode'] ?? ''),
                (string)($_POST['auto_rule_type'] ?? ''),
                (string)($_POST['rule_skill_name'] ?? ''),
                ($_POST['rule_level'] ?? '') === '' ? null : (int)$_POST['rule_level'],
                (string)($_POST['rule_quest_title'] ?? ''),
                (int)($_POST['sort_order'] ?? 0),
                !empty($_POST['is_optional']),
                ($_POST['requires_step_id'] ?? '') === '' ? null : (int)$_POST['requires_step_id'],
                ($_POST['content_item_id'] ?? '') === '' ? null : (int)$_POST['content_item_id']
            );
        }
        redirect('/admin/journey_view.php?id=' . (int)$chapter['journey_id']);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$modes = journey_completion_modes();
$rules = journey_auto_rule_types();
$skills = runemetrics_skill_names();
$selectedMode = (string)($_POST['completion_mode'] ?? $step['completion_mode'] ?? 'manual_only');
$selectedRule = (string)($_POST['auto_rule_type'] ?? $step['auto_rule_type'] ?? '');
$selectedSkill = (string)($_POST['rule_skill_name'] ?? $step['rule_skill_name'] ?? '');
$template = (string)($_GET['template'] ?? '');
if (!$id && !$_POST && $template !== '') {
    $_POST = apply_step_template_values($template, [
        'title' => '',
        'completion_mode' => '',
        'auto_rule_type' => '',
        'is_optional' => 0,
    ]);
    $selectedMode = (string)($_POST['completion_mode'] ?? $selectedMode);
    $selectedRule = (string)($_POST['auto_rule_type'] ?? $selectedRule);
}
$prereqOptions = prerequisite_options_for_journey((int)$chapter['journey_id'], $id ?: null);
$contentOptions = content_items(['is_active' => 1]);
$selectedContentItemId = (int)($_POST['content_item_id'] ?? $step['content_item_id'] ?? 0);
$selectedRequiresStepId = (int)($_POST['requires_step_id'] ?? $step['requires_step_id'] ?? 0);
$isOptional = !empty($_POST['is_optional']) || (!$_POST && !empty($step['is_optional']));
page_header($id ? 'Edit Step' : 'Create Step');
?>
<div class="page-title-row">
    <div>
        <h1><?= $id ? 'Edit Step' : 'Create Step' ?></h1>
        <p class="muted"><?= e($chapter['journey_name']) ?> → <?= e($chapter['title']) ?></p>
    </div>
    <a class="button secondary" href="/admin/journey_view.php?id=<?= (int)$chapter['journey_id'] ?>">Back</a>
</div>

<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<?php if (!$id): ?>
    <div class="card step-template-toolbar">
        <h2>Start with a template</h2>
        <p class="muted">Templates pre-fill the completion behaviour for common step types.</p>
        <div class="form-actions">
            <a class="button secondary" href="/admin/step_edit.php?chapter_id=<?= (int)$chapterId ?>&template=skill_level">Skill level step</a>
            <a class="button secondary" href="/admin/step_edit.php?chapter_id=<?= (int)$chapterId ?>&template=quest_complete">Quest completion step</a>
            <a class="button secondary" href="/admin/step_edit.php?chapter_id=<?= (int)$chapterId ?>&template=manual_unlock">Manual unlock step</a>
            <a class="button secondary" href="/admin/step_edit.php?chapter_id=<?= (int)$chapterId ?>&template=optional_goal">Optional goal</a>
        </div>
    </div>
<?php endif; ?>

<form class="card form-card enhanced-form" method="post">
    <?= csrf_field() ?>

    <section class="form-section">
        <div class="form-section-intro">
            <h2>Step content</h2>
            <p class="muted">Describe a single action or milestone for the player.</p>
        </div>

        <label>Linked Content Library item
            <select name="content_item_id" id="content-item-select" class="searchable-select">
                <option value="">No linked content item</option>
                <?php foreach ($contentOptions as $contentOption): ?>
                    <option
                        value="<?= (int)$contentOption['id'] ?>"
                        data-type="<?= e($contentOption['type']) ?>"
                        data-name="<?= e($contentOption['name']) ?>"
                        <?= $selectedContentItemId === (int)$contentOption['id'] ? 'selected' : '' ?>
                    >
                        <?= e(content_types()[$contentOption['type']] ?? $contentOption['type']) ?> — <?= e($contentOption['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="field-help">Use this for quests, bosses, achievements, drops or unlocks already configured in the Content Library.</span>
        </label>

        <label>Step title
            <input name="title" value="<?= e($_POST['title'] ?? $step['title'] ?? '') ?>" placeholder="Reach 70 Herblore">
            <span class="field-help">Keep this short. It should be easy to scan in a checklist.</span>
        </label>

        <label>Description
            <textarea name="description" rows="5" placeholder="This unlocks access to stronger potions and helps prepare for later PvM goals."><?= e($_POST['description'] ?? $step['description'] ?? '') ?></textarea>
            <span class="field-help">Optional. Use this for context, tips or why the step matters.</span>
        </label>
    </section>

    <section class="form-section">
        <div class="form-section-intro">
            <h2>Completion behaviour</h2>
            <p class="muted">Choose whether Wayfinder checks this step automatically, manually, or both.</p>
        </div>

        <div class="choice-grid completion-choice-grid">
            <?php foreach ($modes as $value => $label): ?>
                <label class="choice-card">
                    <input type="radio" name="completion_mode" value="<?= e($value) ?>" <?= $selectedMode === $value ? 'checked' : '' ?>>
                    <span>
                        <strong><?= e($label) ?></strong>
                        <small>
                            <?php if ($value === 'auto_only'): ?>
                                Best for hard API data such as skill levels.
                            <?php elseif ($value === 'auto_or_manual'): ?>
                                Best where API data exists but manual fallback is helpful.
                            <?php else: ?>
                                Best for achievements, unlocks and learning goals.
                            <?php endif; ?>
                        </small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="form-section auto-rule-panel">
        <div class="form-section-intro">
            <h2>Automatic rule</h2>
            <p class="muted">Only required for automatic steps. Manual-only steps can leave this as “No automatic rule”.</p>
        </div>

        <label>Rule type
            <select name="auto_rule_type" id="auto-rule-type">
                <?php foreach ($rules as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $selectedRule === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="rule-fields rule-skill-level">
            <div class="form-grid">
                <label>Skill
                    <select name="rule_skill_name">
                        <option value="">Select skill</option>
                        <?php foreach ($skills as $skillName): ?>
                            <option value="<?= e($skillName) ?>" <?= strtolower($selectedSkill) === strtolower($skillName) ? 'selected' : '' ?>><?= e($skillName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>Target level
                    <input type="number" name="rule_level" min="1" max="150" value="<?= e($_POST['rule_level'] ?? $step['rule_level'] ?? '') ?>" placeholder="70">
                </label>
            </div>
            <p class="field-help">Example: Herblore + 70. This uses the profile’s RuneMetrics skill data, including virtual level handling.</p>
        </div>

        <div class="rule-fields rule-quest-complete">
            <label>Quest title
                <input name="rule_quest_title" value="<?= e($_POST['rule_quest_title'] ?? $step['rule_quest_title'] ?? '') ?>" placeholder="Temple at Senntisten">
                <span class="field-help">Use the quest name as it appears in RuneMetrics.</span>
            </label>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-intro">
            <h2>Journey logic</h2>
            <p class="muted">Use this to shape the player path without making everything mandatory.</p>
        </div>

        <label class="toggle-row">
            <input type="checkbox" name="is_optional" value="1" <?= $isOptional ? 'checked' : '' ?>>
            <span>
                <strong>Optional step</strong>
                <small>Optional steps can still be completed, but they do not count against the main journey completion percentage.</small>
            </span>
        </label>

        <label>Unlock after step
            <select name="requires_step_id">
                <option value="">Available immediately</option>
                <?php foreach ($prereqOptions as $optionStep): ?>
                    <option value="<?= (int)$optionStep['id'] ?>" <?= $selectedRequiresStepId === (int)$optionStep['id'] ? 'selected' : '' ?>>
                        <?= e($optionStep['chapter_title'] . ' → ' . $optionStep['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="field-help">Choose a prior milestone if this step should stay locked until another step is complete.</span>
        </label>
    </section>

    <section class="form-section">
        <div class="form-section-intro">
            <h2>Ordering</h2>
            <p class="muted">Control where this step appears inside the chapter.</p>
        </div>

        <label>Sort order
            <input type="number" name="sort_order" value="<?= e($_POST['sort_order'] ?? $step['sort_order'] ?? 0) ?>">
            <span class="field-help">Lower numbers appear first.</span>
        </label>
    </section>

    <div class="sticky-form-actions">
        <a class="button secondary" href="/admin/journey_view.php?id=<?= (int)$chapter['journey_id'] ?>">Cancel</a>
        <button class="button" type="submit">Save step</button>
    </div>
</form>

<script>
(function () {
    const ruleSelect = document.getElementById('auto-rule-type');
    const modeInputs = Array.from(document.querySelectorAll('input[name="completion_mode"]'));
    const skillFields = document.querySelector('.rule-skill-level');
    const questFields = document.querySelector('.rule-quest-complete');
    const autoPanel = document.querySelector('.auto-rule-panel');

    function selectedMode() {
        const checked = modeInputs.find(input => input.checked);
        return checked ? checked.value : 'manual_only';
    }

    function updateRuleVisibility() {
        const rule = ruleSelect ? ruleSelect.value : '';
        const mode = selectedMode();

        if (autoPanel) {
            autoPanel.classList.toggle('is-muted', mode === 'manual_only');
        }

        if (skillFields) {
            skillFields.hidden = rule !== 'skill_level';
        }

        if (questFields) {
            questFields.hidden = rule !== 'quest_complete';
        }

        if ((rule === 'skill_level' || rule === 'quest_complete') && mode !== 'auto_only') {
            const autoOnly = modeInputs.find(input => input.value === 'auto_only');
            if (autoOnly) {
                autoOnly.checked = true;
            }
        }

        if (selectedMode() === 'manual_only' && ruleSelect) {
            ruleSelect.value = '';
            if (skillFields) skillFields.hidden = true;
            if (questFields) questFields.hidden = true;
        }
    }

    if (ruleSelect) {
        ruleSelect.addEventListener('change', updateRuleVisibility);
    }
    modeInputs.forEach(input => input.addEventListener('change', updateRuleVisibility));
    updateRuleVisibility();
})();

const contentItemSelect = document.getElementById('content-item-select');
if (contentItemSelect) {
    contentItemSelect.addEventListener('change', function () {
        const selected = contentItemSelect.options[contentItemSelect.selectedIndex];
        if (!selected || !selected.value) return;

        const contentType = selected.dataset.type || '';
        const contentName = selected.dataset.name || '';
        const titleInput = document.querySelector('input[name="title"]');
        const modeAutoOnly = document.querySelector('input[name="completion_mode"][value="auto_only"]');
        const modeManual = document.querySelector('input[name="completion_mode"][value="manual_only"]');
        const ruleSelect = document.getElementById('auto-rule-type');
        const questInput = document.querySelector('input[name="rule_quest_title"]');

        if (titleInput && !titleInput.value.trim()) {
            titleInput.value = contentType === 'quest' ? 'Complete ' + contentName : contentName;
        }

        if (contentType === 'quest') {
            if (modeAutoOnly) modeAutoOnly.checked = true;
            if (ruleSelect) ruleSelect.value = 'quest_complete';
            if (questInput && !questInput.value.trim()) questInput.value = contentName;
        } else if (modeManual) {
            modeManual.checked = true;
            if (ruleSelect) ruleSelect.value = '';
        }

        if (typeof updateRuleVisibility === 'function') {
            updateRuleVisibility();
        } else if (ruleSelect) {
            ruleSelect.dispatchEvent(new Event('change'));
        }
    });
}
</script>
<?php page_footer(); ?>
