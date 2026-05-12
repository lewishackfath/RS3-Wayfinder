<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_login();

$user = current_user();
$active = active_profile();
$journeys = all_journeys(true);
$started = $active ? journeys_for_profile((int)$active['id']) : [];
$startedIds = [];
foreach ($started as $row) {
    $startedIds[(int)$row['id']] = $row;
}

page_header('Journeys');
?>
<div class="page-title-row">
    <div>
        <h1>Journeys</h1>
        <p class="muted">Choose a progression path for your active profile.</p>
    </div>
    <?php if ($active): ?>
        <a class="button secondary" href="/profiles/view.php?id=<?= (int)$active['id'] ?>">View active profile</a>
    <?php endif; ?>
</div>

<?php if (!$active): ?>
    <div class="card">
        <p class="muted">Add a RuneScape profile before starting a journey.</p>
        <a class="button" href="/profiles/new.php">Add profile</a>
    </div>
<?php elseif (!$journeys): ?>
    <div class="card">
        <p class="muted">No published journeys are available yet.</p>
    </div>
<?php else: ?>
    <div class="cards journey-cards">
        <?php foreach ($journeys as $journey): ?>
            <?php
                $progress = evaluate_journey_progress((int)$active['id'], (int)$journey['id']);
                $isStarted = isset($startedIds[(int)$journey['id']]);
            ?>
            <div class="card journey-card">
                <div class="journey-card-header">
                    <div class="journey-icon"><?= e($journey['icon'] ?: '🧭') ?></div>
                    <div>
                        <h2><?= e($journey['name']) ?></h2>
                        <p class="muted"><?= e($journey['description'] ?: 'No description yet.') ?></p>
                    </div>
                </div>
                <div class="progress-bar"><span style="width: <?= e((string)$progress['percent']) ?>%"></span></div>
                <p class="muted small"><?= (int)$progress['completed'] ?> / <?= (int)$progress['total'] ?> steps complete<?= $isStarted ? ' • Started' : '' ?></p>
                <div class="form-actions">
                    <a class="button" href="/journeys/view.php?id=<?= (int)$journey['id'] ?>"><?= $isStarted ? 'Continue' : 'View journey' ?></a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php page_footer(); ?>
