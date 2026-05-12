<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_login();

$user = current_user();
$active = active_profile();
$journeys = all_journeys(true);
$notice = null;

if ($active && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')) {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');
    $journeyId = (int)($_POST['journey_id'] ?? 0);
    $journey = journey_by_id($journeyId);

    try {
        if (!$journey || (int)$journey['is_published'] !== 1) {
            throw new InvalidArgumentException('Journey not found.');
        }

        if ($action === 'enable') {
            start_journey_for_profile((int)$active['id'], $journeyId);
            $notice = 'Journey enabled for ' . $active['rsn'] . '.';
        } elseif ($action === 'disable') {
            stop_journey_for_profile((int)$active['id'], $journeyId);
            $notice = 'Journey disabled for ' . $active['rsn'] . '.';
        }
    } catch (Throwable $e) {
        $notice = $e->getMessage();
    }
}

$started = $active ? journeys_for_profile((int)$active['id']) : [];
$startedIds = [];
foreach ($started as $row) {
    $startedIds[(int)$row['id']] = $row;
}
$recommendedJourneys = $active ? recommended_journeys_for_profile((int)$active['id'], 6) : [];

page_header('Journeys');
?>
<div class="page-title-row">
    <div>
        <h1>Journeys</h1>
        <p class="muted">Choose which paths Wayfinder should track for your active profile.</p>
    </div>
    <?php if ($active): ?>
        <a class="button secondary" href="/profiles/view.php?id=<?= (int)$active['id'] ?>">View active profile</a>
    <?php endif; ?>
</div>

<?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>

<?php if ($active && $recommendedJourneys): ?>
    <div class="card journey-recommendation-strip">
        <div class="page-title-row compact">
            <div>
                <h2>Recommended for <?= e($active['rsn']) ?></h2>
                <p class="muted">These suggestions use your profile interests and current account progression.</p>
            </div>
            <a class="button secondary" href="/profiles/edit.php?id=<?= (int)$active['id'] ?>">Edit interests</a>
        </div>
        <div class="recommended-journey-grid compact">
            <?php foreach ($recommendedJourneys as $item): ?>
                <?php $recJourney = $item['journey']; ?>
                <article class="recommended-journey-card compact">
                    <div class="journey-list-icon small"><?= e($recJourney['icon'] ?: '🧭') ?></div>
                    <div>
                        <h3><?= e($recJourney['name']) ?></h3>
                        <p class="muted small"><?= e($item['reasons'][0] ?? 'Recommended journey') ?></p>
                        <a class="button secondary" href="/journeys/view.php?id=<?= (int)$recJourney['id'] ?>">View</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (!$active): ?>
    <div class="card">
        <p class="muted">Add a RuneScape profile before enabling a journey.</p>
        <a class="button" href="/profiles/new.php">Add profile</a>
    </div>
<?php elseif (!$journeys): ?>
    <div class="card">
        <p class="muted">No published journeys are available yet.</p>
    </div>
<?php else: ?>
    <div class="journey-list">
        <?php foreach ($journeys as $journey): ?>
            <?php
                $journeyId = (int)$journey['id'];
                $isStarted = isset($startedIds[$journeyId]);
                $progress = $isStarted ? evaluate_journey_progress((int)$active['id'], $journeyId) : ['percent' => 0, 'completed' => 0, 'total' => count(steps_for_journey($journeyId))];
            ?>
            <article class="card journey-list-item<?= $isStarted ? ' is-enabled' : '' ?>">
                <div class="journey-list-icon"><?= e($journey['icon'] ?: '🧭') ?></div>

                <div class="journey-list-main">
                    <div class="journey-list-heading">
                        <h2><?= e($journey['name']) ?></h2>
                        <?php if ($isStarted): ?><span class="badge success">Enabled</span><?php endif; ?>
                    </div>
                    <p class="muted"><?= e($journey['description'] ?: 'No description yet.') ?></p>
                    <?php $tags = journey_tags_for_journey($journeyId); ?>
                    <?php if ($tags): ?><p class="journey-tags-row"><?php foreach ($tags as $tag): ?><span class="badge"><?= e($tag['name']) ?></span><?php endforeach; ?></p><?php endif; ?>

                    <div class="journey-list-progress">
                        <div class="progress-bar"><span style="width: <?= e((string)$progress['percent']) ?>%"></span></div>
                        <p class="muted small">
                            <?php if ($isStarted): ?>
                                <?= (int)($progress['required_completed'] ?? $progress['completed']) ?> / <?= (int)($progress['required_total'] ?? $progress['total']) ?> required steps complete
                            <?php else: ?>
                                <?= (int)$progress['total'] ?> steps available • not tracking yet
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="journey-list-actions">
                    <a class="button secondary" href="/journeys/view.php?id=<?= $journeyId ?>"><?= $isStarted ? 'Continue' : 'View' ?></a>
                    <?php if ($isStarted): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="disable">
                            <input type="hidden" name="journey_id" value="<?= $journeyId ?>">
                            <button class="button secondary" type="submit">Disable</button>
                        </form>
                    <?php else: ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="enable">
                            <input type="hidden" name="journey_id" value="<?= $journeyId ?>">
                            <button class="button" type="submit">Enable journey</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php page_footer(); ?>
