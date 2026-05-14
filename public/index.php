<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/layout.php';

$user = current_user();

if (!$user) {
    page_header('Home');
    ?>
    <section class="hero public-landing-hero">
        <span class="journal-kicker">RS3 Wayfinder</span>
        <h1>Find your next RuneScape journey.</h1>
        <p class="muted">Build an adventurer’s journal for your RSN profiles, track journeys, boss logs, quests, skills and recommended next steps.</p>
        <p><a class="button" href="/auth/login.php">Login with Discord</a></p>
    </section>
    <?php
    page_footer();
    exit;
}

$roles = roles_for_user((int)$user['id']);
$profiles = profiles_for_user((int)$user['id']);
$active = active_profile();
$syncNotice = null;
$activeMetrics = null;
$myJourneys = [];
$myJourneyProgress = [];
$recommendations = [];
$profileAnalysis = null;
$recommendedJourneys = [];
$bossTotals = null;

if ($active) {
    try {
        $sync = runemetrics_sync_profile_if_due($active);
        $active = active_profile();
        $activeMetrics = runemetrics_profile_metrics((int)$active['id']);
        if (($sync['success'] ?? false) === true) {
            $syncNotice = 'RuneMetrics data refreshed for your active profile.';
        }
    } catch (Throwable $e) {
        $syncNotice = is_debug() ? $e->getMessage() : 'RuneMetrics sync failed. Cached data is shown where available.';
        $activeMetrics = runemetrics_profile_metrics((int)$active['id']);
    }
}

if ($active) {
    $profileId = (int)$active['id'];
    $myJourneys = journeys_for_profile($profileId);
    foreach ($myJourneys as $journey) {
        $myJourneyProgress[(int)$journey['id']] = evaluate_journey_progress($profileId, (int)$journey['id']);
    }
    $recommendations = wayfinder_recommendations_for_profile($profileId, 5);
    $profileAnalysis = wayfinder_profile_analysis($profileId);
    $recommendedJourneys = recommended_journeys_for_profile($profileId, 4);
    try { $bossTotals = boss_log_totals_for_profile($profileId); } catch (Throwable $e) { $bossTotals = null; }
}

page_header('Journal');
?>
<section class="journal-cover-panel">
    <div>
        <span class="journal-kicker">Adventurer Journal</span>
        <h1><?= $active ? e($active['rsn']) . '’s Wayfinder' : 'Begin your Wayfinder journal' ?></h1>
        <p class="muted">A living record of your quests, expeditions, boss hunts and discoveries.</p>
    </div>
    <?php if ($syncNotice): ?><div class="notice compact-notice"><?= e($syncNotice) ?></div><?php endif; ?>
</section>

<?php if ($active): ?>
    <section class="journal-section journal-summary-strip">
        <article>
            <span>Journeys</span>
            <strong><?= e((string)count($myJourneys)) ?></strong>
            <small>enabled paths</small>
        </article>
        <article>
            <span>Overall</span>
            <strong><?= $profileAnalysis ? e((string)$profileAnalysis['overall_percent']) . '%' : '—' ?></strong>
            <small>journey completion</small>
        </article>
        <article>
            <span>Boss Log</span>
            <strong><?= $bossTotals ? e((string)$bossTotals['completion_pct']) . '%' : '—' ?></strong>
            <small><?= $bossTotals ? e((string)$bossTotals['obtained_count']) . ' / ' . e((string)$bossTotals['drop_count']) . ' drops' : 'not catalogued' ?></small>
        </article>
        <article>
            <span>Last Sync</span>
            <strong><?= e(format_sync_age($active['last_sync_at'] ?? null)) ?></strong>
            <small>RuneMetrics</small>
        </article>
    </section>

    <section class="journal-section next-steps-section">
        <div class="journal-section-heading">
            <div>
                <span class="journal-kicker">✦ Next discoveries</span>
                <h2>Recommended next steps</h2>
            </div>
            <a class="button secondary" href="/journeys/index.php">Browse journeys</a>
        </div>

        <?php if (!$myJourneys): ?>
            <div class="journal-empty-note">
                <p class="muted">Enable a journey to start receiving account-specific recommendations.</p>
                <a class="button" href="/journeys/index.php">Choose a journey</a>
            </div>
        <?php elseif (!$recommendations): ?>
            <div class="journal-empty-note">
                <p class="muted">No recommendations right now. You may have completed all currently available required steps.</p>
                <a class="button secondary" href="/journeys/index.php">Review journeys</a>
            </div>
        <?php else: ?>
            <div class="journal-recommendation-list">
                <?php foreach ($recommendations as $rec): ?>
                    <article class="journal-entry-card">
                        <div class="journal-entry-symbol"><?= e($rec['journey_icon'] ?? '🧭') ?></div>
                        <div>
                            <span class="muted small"><?= e($rec['journey_name'] ?? 'Journey') ?> • <?= e(str_replace('_', ' ', (string)($rec['type'] ?? 'recommendation'))) ?></span>
                            <h3><?= e($rec['title']) ?></h3>
                            <p class="muted"><?= e($rec['summary']) ?></p>
                            <p class="small"><?= e($rec['detail']) ?></p>
                        </div>
                        <a class="button secondary" href="<?= e($rec['cta_url']) ?>"><?= e($rec['cta_label'] ?? 'View') ?></a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="journal-section journey-ledger-section">
        <div class="journal-section-heading">
            <div>
                <span class="journal-kicker">☉ Active paths</span>
                <h2>Journey ledger</h2>
            </div>
            <a class="button secondary" href="/journeys/index.php">Manage journeys</a>
        </div>

        <?php if (!$myJourneys): ?>
            <div class="journal-empty-note">
                <p class="muted">No journeys are enabled yet.</p>
                <a class="button" href="/journeys/index.php">Choose a journey</a>
            </div>
        <?php else: ?>
            <div class="journal-journey-list">
                <?php foreach ($myJourneys as $index => $journey): ?>
                    <?php $progress = $myJourneyProgress[(int)$journey['id']] ?? ['percent' => 0, 'completed' => 0, 'total' => 0]; ?>
                    <article class="journal-journey-entry <?= $index % 2 ? 'tilt-right' : 'tilt-left' ?>">
                        <div class="journal-entry-symbol small-symbol"><?= e($journey['icon'] ?: '🧭') ?></div>
                        <div class="journal-journey-main">
                            <div class="journal-list-heading">
                                <h3><?= e($journey['name']) ?></h3>
                                <span class="muted small"><?= (int)($progress['required_completed'] ?? $progress['completed']) ?> / <?= (int)($progress['required_total'] ?? $progress['total']) ?> required</span>
                            </div>
                            <div class="progress-bar"><span style="width: <?= e((string)$progress['percent']) ?>%"></span></div>
                            <?php if (!empty($progress['recommended'][0])): ?>
                                <p class="muted small">Next: <?= e($progress['recommended'][0]['title']) ?></p>
                            <?php endif; ?>
                        </div>
                        <a class="button secondary" href="/journeys/view.php?id=<?= (int)$journey['id'] ?>">Continue</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="journal-section discovery-grid-section">
        <div class="journal-section-heading">
            <div>
                <span class="journal-kicker">❖ Suggested expeditions</span>
                <h2>Recommended journeys</h2>
            </div>
            <a class="button secondary" href="/profiles/edit.php?id=<?= (int)$active['id'] ?>">Edit interests</a>
        </div>

        <?php if (!$recommendedJourneys): ?>
            <div class="journal-empty-note">
                <p class="muted">No journey recommendations yet. Add interests to this profile or create more published journeys with tags.</p>
            </div>
        <?php else: ?>
            <div class="journal-discovery-grid">
                <?php foreach ($recommendedJourneys as $item): ?>
                    <?php $journey = $item['journey']; ?>
                    <article class="journal-discovery-card">
                        <div class="journal-entry-symbol small-symbol"><?= e($journey['icon'] ?: '🧭') ?></div>
                        <h3><?= e($journey['name']) ?></h3>
                        <p class="muted"><?= e($journey['description'] ?: 'No description yet.') ?></p>
                        <?php if (!empty($item['tags'])): ?>
                            <p><?php foreach ($item['tags'] as $tagName): ?><span class="badge"><?= e($tagName) ?></span><?php endforeach; ?></p>
                        <?php endif; ?>
                        <a class="button secondary" href="/journeys/view.php?id=<?= (int)$journey['id'] ?>">View journey</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($profiles): ?>
    <section class="journal-section">
        <span class="journal-kicker">Profiles</span>
        <h2>Select an adventurer</h2>
        <p class="muted">Choose an active profile from the selector above to open their journal.</p>
        <p><?php foreach ($profiles as $profile): ?><span class="badge"><?= e($profile['rsn']) ?><?= ((int)$profile['is_primary'] === 1) ? ' • Primary' : '' ?></span><?php endforeach; ?></p>
        <p><a class="button secondary" href="/profiles/index.php">Manage profiles</a></p>
    </section>
<?php else: ?>
    <section class="journal-section">
        <span class="journal-kicker">New journal</span>
        <h2>Add your first RSN</h2>
        <p class="muted">No RSNs are attached yet. Add a profile to begin tracking skills, quests, boss drops and journeys.</p>
        <p><a class="button" href="/profiles/new.php">Add your first RSN</a></p>
    </section>
<?php endif; ?>
<?php page_footer(); ?>
