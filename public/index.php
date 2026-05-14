<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_login();
$user = current_user();
page_header('Codex');
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
    $myJourneys = journeys_for_profile((int)$active['id']);
    foreach ($myJourneys as $journey) {
        $myJourneyProgress[(int)$journey['id']] = evaluate_journey_progress((int)$active['id'], (int)$journey['id']);
    }
    $recommendations = wayfinder_recommendations_for_profile((int)$active['id'], 5);
    $profileAnalysis = wayfinder_profile_analysis((int)$active['id']);
    $recommendedJourneys = recommended_journeys_for_profile((int)$active['id'], 4);
}
?>
<div class="card dashboard-hero-card">
    <div class="dashboard-logo-wrap">
        <img class="dashboard-logo" src="/assets/branding/logo.png" alt="RS3 Wayfinder">
    </div>
    <h1>Welcome, <?= e($user['global_name'] ?: $user['username']) ?></h1>
    <p class="muted">Your account is active. Add RSN profiles and Wayfinder will collect RuneMetrics profile and quest data when each profile is viewed.</p>
    <?php if ($syncNotice): ?><div class="notice"><?= e($syncNotice) ?></div><?php endif; ?>
    <h2>Your roles</h2>
    <p><?php foreach ($roles as $role): ?><span class="badge"><?= e($role['name']) ?></span><?php endforeach; ?></p>
    <h2>Active profile</h2>
    <?php if ($active): ?>
        <div class="active-profile-panel">
            <img class="profile-avatar large" src="<?= e(runescape_avatar_url((string)$active['rsn'])) ?>" alt="Avatar for <?= e($active['rsn']) ?>" loading="lazy" referrerpolicy="no-referrer">
            <div>
                <h3><?= e($active['rsn']) ?></h3>
                <p class="muted"><?= e(account_type_options()[$active['account_type']] ?? $active['account_type']) ?> • <?= e(visibility_options()[$active['visibility']] ?? $active['visibility']) ?></p>
                <?php if ($activeMetrics): ?>
                    <p class="muted">Total level <?= e(format_number_short($activeMetrics['total_level'] ?? null)) ?> • Combat <?= e(format_number_short($activeMetrics['combat_level'] ?? null)) ?> • Last sync <?= e(format_sync_age($active['last_sync_at'] ?? null)) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <p><a class="button" href="/profiles/view.php?id=<?= (int)$active['id'] ?>">View profile data</a> <a class="button secondary" href="/journeys/index.php">Browse journeys</a> <a class="button secondary" href="/profiles/index.php">Manage profiles</a></p>

        <section class="dashboard-section recommendation-section">
            <div class="page-title-row compact">
                <div>
                    <h2>Recommended next steps</h2>
                    <p class="muted">Suggestions based on enabled journeys for <?= e($active['rsn']) ?>.</p>
                </div>
                <?php if ($profileAnalysis): ?>
                    <span class="badge success"><?= e((string)$profileAnalysis['overall_percent']) ?>% overall</span>
                <?php endif; ?>
            </div>

            <?php if (!$myJourneys): ?>
                <div class="empty-panel">
                    <p class="muted">Enable a journey to receive account-specific recommendations.</p>
                    <a class="button" href="/journeys/index.php">Browse journeys</a>
                </div>
            <?php elseif (!$recommendations): ?>
                <div class="empty-panel">
                    <p class="muted">No recommendations right now. You may have completed all currently available required steps.</p>
                    <a class="button secondary" href="/journeys/index.php">Review journeys</a>
                </div>
            <?php else: ?>
                <div class="recommendation-grid">
                    <?php foreach ($recommendations as $rec): ?>
                        <article class="recommendation-card">
                            <div class="recommendation-icon"><?= e($rec['journey_icon'] ?? '🧭') ?></div>
                            <div class="recommendation-main">
                                <span class="muted small"><?= e($rec['journey_name'] ?? 'Journey') ?> • <?= e(str_replace('_', ' ', (string)($rec['type'] ?? 'recommendation'))) ?></span>
                                <h3><?= e($rec['title']) ?></h3>
                                <p class="muted"><?= e($rec['summary']) ?></p>
                                <p class="small"><?= e($rec['detail']) ?></p>
                                <a class="button secondary" href="<?= e($rec['cta_url']) ?>"><?= e($rec['cta_label'] ?? 'View') ?></a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($profileAnalysis): ?>
                <div class="recommendation-analysis-row">
                    <div><strong><?= e($profileAnalysis['enabled_journeys']) ?></strong><span>Enabled journeys</span></div>
                    <div><strong><?= e($profileAnalysis['available_steps']) ?></strong><span>Available steps</span></div>
                    <div><strong><?= e($profileAnalysis['locked_steps']) ?></strong><span>Locked steps</span></div>
                    <div><strong><?= e($profileAnalysis['optional_steps']) ?></strong><span>Optional steps</span></div>
                </div>
            <?php endif; ?>
        </section>

        <section class="dashboard-section recommended-journeys-section">
            <div class="page-title-row compact">
                <div>
                    <h2>Recommended journeys</h2>
                    <p class="muted">Suggested paths based on <?= e($active['rsn']) ?> and your selected interests.</p>
                </div>
                <a class="button secondary" href="/journeys/index.php">Browse all journeys</a>
            </div>

            <?php if (!$recommendedJourneys): ?>
                <div class="empty-panel">
                    <p class="muted">No journey recommendations yet. Add interests to this profile or create more published journeys with tags.</p>
                    <a class="button secondary" href="/profiles/edit.php?id=<?= (int)$active['id'] ?>">Edit interests</a>
                </div>
            <?php else: ?>
                <div class="recommended-journey-grid">
                    <?php foreach ($recommendedJourneys as $item): ?>
                        <?php $journey = $item['journey']; ?>
                        <article class="recommended-journey-card">
                            <div class="journey-list-icon small"><?= e($journey['icon'] ?: '🧭') ?></div>
                            <div>
                                <h3><?= e($journey['name']) ?></h3>
                                <p class="muted"><?= e($journey['description'] ?: 'No description yet.') ?></p>
                                <?php if (!empty($item['tags'])): ?>
                                    <p><?php foreach ($item['tags'] as $tagName): ?><span class="badge"><?= e($tagName) ?></span><?php endforeach; ?></p>
                                <?php endif; ?>
                                <ul class="recommendation-reasons">
                                    <?php foreach (array_slice($item['reasons'], 0, 2) as $reason): ?><li><?= e($reason) ?></li><?php endforeach; ?>
                                </ul>
                                <a class="button secondary" href="/journeys/view.php?id=<?= (int)$journey['id'] ?>">View journey</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="dashboard-section">
            <div class="page-title-row compact">
                <div>
                    <h2>My journeys</h2>
                    <p class="muted">Journeys currently enabled for <?= e($active['rsn']) ?>.</p>
                </div>
                <a class="button secondary" href="/journeys/index.php">Browse journeys</a>
            </div>

            <?php if (!$myJourneys): ?>
                <div class="empty-panel">
                    <p class="muted">No journeys are enabled yet.</p>
                    <a class="button" href="/journeys/index.php">Choose a journey</a>
                </div>
            <?php else: ?>
                <div class="dashboard-journey-list">
                    <?php foreach ($myJourneys as $journey): ?>
                        <?php $progress = $myJourneyProgress[(int)$journey['id']] ?? ['percent' => 0, 'completed' => 0, 'total' => 0]; ?>
                        <article class="dashboard-journey-item">
                            <div class="journey-list-icon small"><?= e($journey['icon'] ?: '🧭') ?></div>
                            <div class="dashboard-journey-main">
                                <div class="journey-list-heading">
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
    <?php elseif ($profiles): ?>
        <p><?php foreach ($profiles as $profile): ?><span class="badge"><?= e($profile['rsn']) ?><?= ((int)$profile['is_primary'] === 1) ? ' • Primary' : '' ?></span><?php endforeach; ?></p>
        <p><a class="button secondary" href="/profiles/index.php">Manage profiles</a></p>
    <?php else: ?>
        <p class="muted">No RSNs are attached yet.</p>
        <p><a class="button" href="/profiles/new.php">Add your first RSN</a></p>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
