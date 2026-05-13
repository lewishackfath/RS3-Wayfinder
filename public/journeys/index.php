<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_login();

$user = current_user();
$active = active_profile();
$journeys = all_journeys(true);
$allTags = all_journey_tags();
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

$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? 'all');
$tagFilter = (int)($_GET['tag_id'] ?? 0);
$sort = (string)($_GET['sort'] ?? 'default');
$allowedStatuses = ['all', 'enabled', 'available'];
$allowedSorts = ['default', 'name', 'progress_desc', 'steps_desc'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'default';
}

$journeyCards = [];
foreach ($journeys as $journey) {
    $journeyId = (int)$journey['id'];
    $isStarted = isset($startedIds[$journeyId]);
    $tags = journey_tags_for_journey($journeyId);
    $tagIds = array_map(static fn(array $tag): int => (int)$tag['id'], $tags);
    $steps = steps_for_journey($journeyId);
    $progress = $active && $isStarted
        ? evaluate_journey_progress((int)$active['id'], $journeyId)
        : ['percent' => 0, 'completed' => 0, 'total' => count($steps), 'required_completed' => 0, 'required_total' => count(array_filter($steps, static fn(array $step): bool => (int)$step['is_optional'] !== 1))];

    $searchText = strtolower(trim((string)$journey['name'] . ' ' . (string)($journey['description'] ?? '') . ' ' . implode(' ', array_column($tags, 'name'))));

    if ($q !== '' && strpos($searchText, strtolower($q)) === false) {
        continue;
    }
    if ($statusFilter === 'enabled' && !$isStarted) {
        continue;
    }
    if ($statusFilter === 'available' && $isStarted) {
        continue;
    }
    if ($tagFilter > 0 && !in_array($tagFilter, $tagIds, true)) {
        continue;
    }

    $journeyCards[] = [
        'journey' => $journey,
        'is_started' => $isStarted,
        'tags' => $tags,
        'progress' => $progress,
        'step_count' => count($steps),
    ];
}

usort($journeyCards, static function (array $a, array $b) use ($sort): int {
    if ($sort === 'name') {
        return strcasecmp((string)$a['journey']['name'], (string)$b['journey']['name']);
    }
    if ($sort === 'progress_desc') {
        return ((int)$b['progress']['percent'] <=> (int)$a['progress']['percent'])
            ?: strcasecmp((string)$a['journey']['name'], (string)$b['journey']['name']);
    }
    if ($sort === 'steps_desc') {
        return ((int)$b['step_count'] <=> (int)$a['step_count'])
            ?: strcasecmp((string)$a['journey']['name'], (string)$b['journey']['name']);
    }

    return ((int)$a['journey']['sort_order'] <=> (int)$b['journey']['sort_order'])
        ?: strcasecmp((string)$a['journey']['name'], (string)$b['journey']['name']);
});

$enabledCount = count($startedIds);
$totalCount = count($journeys);

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
    <div class="card journey-filter-card">
        <div class="page-title-row compact">
            <div>
                <h2>Find a journey</h2>
                <p class="muted"><?= (int)$enabledCount ?> enabled • <?= (int)$totalCount ?> published journeys available</p>
            </div>
        </div>

        <form class="filter-form journey-filter-form" method="get">
            <label>
                Search
                <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, description or tag">
            </label>
            <label>
                Status
                <select name="status">
                    <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>All journeys</option>
                    <option value="enabled"<?= $statusFilter === 'enabled' ? ' selected' : '' ?>>Enabled only</option>
                    <option value="available"<?= $statusFilter === 'available' ? ' selected' : '' ?>>Not enabled yet</option>
                </select>
            </label>
            <label>
                Tag
                <select name="tag_id">
                    <option value="0">All tags</option>
                    <?php foreach ($allTags as $tag): ?>
                        <option value="<?= (int)$tag['id'] ?>"<?= $tagFilter === (int)$tag['id'] ? ' selected' : '' ?>><?= e($tag['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Sort
                <select name="sort">
                    <option value="default"<?= $sort === 'default' ? ' selected' : '' ?>>Default order</option>
                    <option value="name"<?= $sort === 'name' ? ' selected' : '' ?>>Name A-Z</option>
                    <option value="progress_desc"<?= $sort === 'progress_desc' ? ' selected' : '' ?>>Progress highest first</option>
                    <option value="steps_desc"<?= $sort === 'steps_desc' ? ' selected' : '' ?>>Most steps first</option>
                </select>
            </label>
            <div class="filter-actions">
                <button class="button" type="submit">Apply filters</button>
                <a class="button secondary" href="/journeys/">Reset</a>
            </div>
        </form>
    </div>

    <?php if (!$journeyCards): ?>
        <div class="card">
            <p class="muted">No journeys matched those filters.</p>
            <a class="button secondary" href="/journeys/">Clear filters</a>
        </div>
    <?php else: ?>
        <div class="journey-list">
            <?php foreach ($journeyCards as $card): ?>
                <?php
                    $journey = $card['journey'];
                    $journeyId = (int)$journey['id'];
                    $isStarted = (bool)$card['is_started'];
                    $progress = $card['progress'];
                    $tags = $card['tags'];
                ?>
                <article class="card journey-list-item<?= $isStarted ? ' is-enabled' : '' ?>">
                    <div class="journey-list-icon"><?= e($journey['icon'] ?: '🧭') ?></div>

                    <div class="journey-list-main">
                        <div class="journey-list-heading">
                            <h2><?= e($journey['name']) ?></h2>
                            <?php if ($isStarted): ?><span class="badge success">Enabled</span><?php endif; ?>
                        </div>
                        <p class="muted"><?= e($journey['description'] ?: 'No description yet.') ?></p>
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
<?php endif; ?>
<?php page_footer(); ?>
