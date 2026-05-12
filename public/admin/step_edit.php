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
                (int)($_POST['sort_order'] ?? 0)
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
                (int)($_POST['sort_order'] ?? 0)
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

<form class="card form-card enhanced-form" method="post">
    <?= csrf_field() ?>

    <section class="form-section">
        <div class="form-section-intro">
            <h2>Step content</h2>
            <p class="muted">Describe a single action or milestone for the player.</p>
        </div>

        <label>Step title
            <input name="title" value="<?= e($_POST['title'] ?? $step['title'] ?? '') ?>" required placeholder="Reach 70 Herblore">
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

        if (mode === 'manual_only' && ruleSelect) {
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
</script>
<?php page_footer(); ?>
