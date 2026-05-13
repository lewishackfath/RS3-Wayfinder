<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('content.manage');

$result = null;
$error = null;
$defaultRsn = '';
$active = active_profile();
if ($active) {
    $defaultRsn = (string)$active['rsn'];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    try {
        $rsn = (string)($_POST['rsn'] ?? '');
        $result = import_quests_from_runemetrics($rsn);
        $defaultRsn = $rsn;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $defaultRsn = (string)($_POST['rsn'] ?? $defaultRsn);
    }
}

page_header('Import Quests');
?>
<div class="page-title-row">
    <div>
        <h1>Import Quests</h1>
        <p class="muted">Pull quest names from RuneMetrics and create/update quest content records.</p>
    </div>
    <a class="button secondary" href="/admin/content.php?type=quest">Back to quests</a>
</div>

<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
    <h2>RuneMetrics Quest Import</h2>
    <p class="muted">Use a public RuneMetrics profile to fetch the quest list. Existing admin content, requirements and descriptions are preserved.</p>

    <form class="enhanced-form" method="post">
        <?= csrf_field() ?>
        <section class="form-section">
            <label>RuneScape Name
                <input name="rsn" value="<?= e($defaultRsn) ?>" maxlength="12" required placeholder="Player RSN">
                <span class="field-help">This only uses the public quest list returned by RuneMetrics. The player’s completion status is stored on their profile sync, not in the global content library.</span>
            </label>
        </section>

        <div class="form-actions">
            <button class="button" type="submit">Import quest list</button>
        </div>
    </form>
</div>

<?php if ($result): ?>
    <div class="card">
        <h2>Import Result</h2>
        <div class="admin-summary-grid">
            <div class="stat-card"><span>Total received</span><strong><?= e($result['total_received']) ?></strong></div>
            <div class="stat-card"><span>Created</span><strong><?= e($result['created']) ?></strong></div>
            <div class="stat-card"><span>Updated</span><strong><?= e($result['updated']) ?></strong></div>
            <div class="stat-card"><span>Skipped</span><strong><?= e($result['skipped']) ?></strong></div>
        </div>

        <?php if (!empty($result['errors'])): ?>
            <h3>Errors</h3>
            <ul>
                <?php foreach ($result['errors'] as $importError): ?><li><?= e($importError) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <p><a class="button secondary" href="/admin/content.php?type=quest">View quest content</a></p>
    </div>
<?php endif; ?>

<div class="card">
    <h2>What this import does</h2>
    <ul class="muted">
        <li>Creates missing quest content records.</li>
        <li>Refreshes import metadata for existing quest records.</li>
        <li>Does not overwrite admin descriptions, requirements or configured relationships.</li>
        <li>Does not mark global quests as complete; player completion still comes from profile RuneMetrics data.</li>
    </ul>
</div>
<?php page_footer(); ?>
