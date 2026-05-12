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

<form class="card form-card" method="post">
    <?= csrf_field() ?>
    <label>Title
        <input name="title" value="<?= e($_POST['title'] ?? $step['title'] ?? '') ?>" required>
    </label>
    <label>Description
        <textarea name="description" rows="5"><?= e($_POST['description'] ?? $step['description'] ?? '') ?></textarea>
    </label>
    <label>Completion mode
        <select name="completion_mode">
            <?php $selectedMode = (string)($_POST['completion_mode'] ?? $step['completion_mode'] ?? 'manual_only'); ?>
            <?php foreach ($modes as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $selectedMode === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Automatic rule
        <select name="auto_rule_type">
            <?php $selectedRule = (string)($_POST['auto_rule_type'] ?? $step['auto_rule_type'] ?? ''); ?>
            <?php foreach ($rules as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $selectedRule === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="grid two-col-grid">
        <label>Skill name
            <select name="rule_skill_name">
                <option value="">Select skill</option>
                <?php $selectedSkill = (string)($_POST['rule_skill_name'] ?? $step['rule_skill_name'] ?? ''); ?>
                <?php foreach ($skills as $skillName): ?>
                    <option value="<?= e($skillName) ?>" <?= strtolower($selectedSkill) === strtolower($skillName) ? 'selected' : '' ?>><?= e($skillName) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Target level
            <input type="number" name="rule_level" min="1" max="150" value="<?= e($_POST['rule_level'] ?? $step['rule_level'] ?? '') ?>">
        </label>
    </div>
    <label>Quest title
        <input name="rule_quest_title" value="<?= e($_POST['rule_quest_title'] ?? $step['rule_quest_title'] ?? '') ?>" placeholder="Temple at Senntisten">
    </label>
    <label>Sort order
        <input type="number" name="sort_order" value="<?= e($_POST['sort_order'] ?? $step['sort_order'] ?? 0) ?>">
    </label>

    <div class="notice">
        <strong>Completion mode guide:</strong><br>
        Automatic only is best for skill/XP checks. Manual only is best for achievements, unlocks and subjective learning steps. Automatic or manual is useful for quests and anything the API may miss.
    </div>

    <div class="form-actions">
        <button class="button" type="submit">Save step</button>
    </div>
</form>
<?php page_footer(); ?>
