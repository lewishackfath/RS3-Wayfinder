<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('profiles.view');

$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_permission('profiles.delete');
    require_csrf();

    $profileId = (int)($_POST['profile_id'] ?? 0);
    if ($profileId > 0) {
        $ownerStmt = $pdo->prepare('SELECT user_id FROM player_profiles WHERE id = ? LIMIT 1');
        $ownerStmt->execute([$profileId]);
        $ownerId = (int)($ownerStmt->fetchColumn() ?: 0);

        $pdo->prepare('DELETE FROM player_profiles WHERE id = ?')->execute([$profileId]);

        if ($ownerId > 0) {
            ensure_user_has_primary_profile($ownerId);
        }
    }

    redirect('/admin/profiles.php?' . http_build_query(array_filter([
        'q' => $_GET['q'] ?? null,
        'account_type' => $_GET['account_type'] ?? null,
        'visibility' => $_GET['visibility'] ?? null,
        'sync' => $_GET['sync'] ?? null,
    ], static fn($value) => $value !== null && $value !== '')));
}

$q = trim((string)($_GET['q'] ?? ''));
$accountType = (string)($_GET['account_type'] ?? '');
$visibility = (string)($_GET['visibility'] ?? '');
$sync = (string)($_GET['sync'] ?? '');

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(pp.rsn LIKE ? OR pp.rsn_normalised LIKE ? OR u.username LIKE ? OR u.global_name LIKE ? OR u.discord_id LIKE ? OR u.email LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}

if ($accountType !== '' && isset(account_type_options()[$accountType])) {
    $where[] = 'pp.account_type = ?';
    $params[] = $accountType;
}

if ($visibility !== '' && isset(visibility_options()[$visibility])) {
    $where[] = 'pp.visibility = ?';
    $params[] = $visibility;
}

if ($sync === 'public') {
    $where[] = 'pp.runemetrics_public = 1';
} elseif ($sync === 'private') {
    $where[] = 'pp.runemetrics_public = 0';
} elseif ($sync === 'never') {
    $where[] = 'pp.last_sync_at IS NULL';
} elseif ($sync === 'synced') {
    $where[] = 'pp.last_sync_at IS NOT NULL';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("\n    SELECT\n        pp.*,\n        u.username AS owner_username,\n        u.global_name AS owner_global_name,\n        u.discord_id AS owner_discord_id,\n        u.avatar_hash AS owner_avatar_hash,\n        u.email AS owner_email,\n        ppm.display_name AS rm_display_name,\n        ppm.total_level,\n        ppm.total_xp,\n        ppm.combat_level,\n        ppm.quests_complete,\n        ppm.quests_started,\n        ppm.quests_not_started,\n        ppm.last_successful_sync_at,\n        ppm.last_sync_status,\n        ppm.last_sync_error,\n        COALESCE(pj_stats.journey_count, 0) AS journey_count,\n        COALESCE(pj_stats.active_journey_count, 0) AS active_journey_count\n    FROM player_profiles pp\n    JOIN users u ON u.id = pp.user_id\n    LEFT JOIN player_profile_metrics ppm ON ppm.profile_id = pp.id\n    LEFT JOIN (\n        SELECT profile_id, COUNT(*) AS journey_count, SUM(CASE WHEN completed_at IS NULL THEN 1 ELSE 0 END) AS active_journey_count\n        FROM player_journeys\n        GROUP BY profile_id\n    ) pj_stats ON pj_stats.profile_id = pp.id\n    {$whereSql}\n    ORDER BY pp.created_at DESC, pp.rsn ASC\n");
$stmt->execute($params);
$profiles = $stmt->fetchAll();

$totalProfiles = (int)$pdo->query('SELECT COUNT(*) FROM player_profiles')->fetchColumn();
$matchingProfiles = count($profiles);

function admin_profile_owner_name(array $row): string
{
    $name = trim((string)($row['owner_global_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    $username = trim((string)($row['owner_username'] ?? ''));
    return $username !== '' ? $username : 'User #' . (int)$row['user_id'];
}

function admin_profile_number($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    return number_format((int)$value);
}

function admin_profile_sync_label(array $profile): string
{
    if (empty($profile['last_sync_at'])) {
        return 'Never synced';
    }

    if ($profile['runemetrics_public'] === null || $profile['runemetrics_public'] === '') {
        return 'Synced';
    }

    return ((int)$profile['runemetrics_public'] === 1) ? 'RuneMetrics public' : 'RuneMetrics private';
}

function admin_profile_discord_avatar(array $row): string
{
    $discordId = trim((string)($row['owner_discord_id'] ?? ''));
    $avatarHash = trim((string)($row['owner_avatar_hash'] ?? ''));

    if ($discordId !== '' && $avatarHash !== '') {
        $extension = str_starts_with($avatarHash, 'a_') ? 'gif' : 'png';
        return 'https://cdn.discordapp.com/avatars/' . rawurlencode($discordId) . '/' . rawurlencode($avatarHash) . '.' . $extension . '?size=64';
    }

    return '/assets/default-avatar.svg';
}

page_header('Manage Profiles');
?>
<style>
/* Admin profiles rebuild: fully scoped so the fantasy theme cannot break the page flow. */
.wf-admin-profiles {
    width: min(1380px, calc(100vw - 2rem));
    margin: 0 auto;
    display: grid;
    gap: 1rem;
}
.wf-admin-profiles,
.wf-admin-profiles * {
    box-sizing: border-box;
}
.wf-admin-profiles h1,
.wf-admin-profiles h2,
.wf-admin-profiles h3,
.wf-admin-profiles p {
    margin-left: 0 !important;
}
.wf-admin-profiles__header,
.wf-admin-profiles__filters,
.wf-admin-profiles__list,
.wf-admin-profile-card {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(231, 197, 109, .28);
    border-radius: 18px;
    background:
        linear-gradient(135deg, rgba(231, 197, 109, .095), rgba(96, 216, 214, .035) 34%, rgba(18, 15, 11, .94)),
        linear-gradient(180deg, rgba(45, 32, 20, .94), rgba(18, 14, 10, .96));
    box-shadow: 0 18px 44px rgba(0,0,0,.34), inset 0 1px 0 rgba(255,235,179,.11);
}
.wf-admin-profiles__header::before,
.wf-admin-profiles__filters::before,
.wf-admin-profile-card::before {
    content: "";
    position: absolute;
    inset: 7px;
    pointer-events: none;
    border: 1px solid rgba(231, 197, 109, .11);
    border-radius: 13px;
    background:
        linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px),
        linear-gradient(180deg, rgba(255,255,255,.018) 1px, transparent 1px);
    background-size: 24px 24px;
    opacity: .52;
}
.wf-admin-profiles__header {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: end;
    gap: 1rem;
    padding: 1.35rem;
}
.wf-admin-profiles__eyebrow {
    display: inline-flex;
    width: fit-content;
    margin-bottom: .45rem;
    font-size: .72rem;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: rgba(96, 216, 214, .86);
    font-weight: 800;
}
.wf-admin-profiles__header h1 {
    margin: 0 0 .3rem !important;
    line-height: 1.08;
    font-family: Georgia, "Times New Roman", serif;
    color: #f2d78b;
    text-shadow: 0 2px 0 rgba(0,0,0,.42), 0 0 18px rgba(231,197,109,.12);
}
.wf-admin-profiles__header p {
    margin: 0 !important;
    color: rgba(226, 207, 164, .76);
    max-width: 760px;
}
.wf-admin-profiles__counts {
    display: grid;
    grid-template-columns: repeat(2, minmax(120px, 1fr));
    gap: .75rem;
    min-width: 280px;
}
.wf-admin-profiles__count {
    padding: .85rem 1rem;
    border: 1px solid rgba(231,197,109,.22);
    border-radius: 14px;
    background: rgba(5, 5, 4, .34);
}
.wf-admin-profiles__count span {
    display: block;
    font-size: .7rem;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: rgba(226, 207, 164, .68);
    font-weight: 800;
}
.wf-admin-profiles__count strong {
    display: block;
    margin-top: .15rem;
    color: #f2d78b;
    font-size: 1.55rem;
    line-height: 1;
}
.wf-admin-profiles__filters {
    padding: 1rem;
}
.wf-admin-profiles__filter-grid {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: minmax(220px, 1.4fr) repeat(3, minmax(170px, 1fr)) auto;
    gap: .85rem;
    align-items: end;
}
.wf-admin-profiles__filters label {
    display: grid;
    gap: .4rem;
    margin: 0;
    font-weight: 800;
    color: rgba(226, 207, 164, .78);
    font-size: .76rem;
    letter-spacing: .08em;
    text-transform: uppercase;
}
.wf-admin-profiles__filters input,
.wf-admin-profiles__filters select {
    width: 100%;
    min-height: 42px;
    border-radius: 12px;
    border: 1px solid rgba(231,197,109,.28);
    background: rgba(7, 6, 4, .72);
    color: #f7edd1;
    padding: .72rem .85rem;
    box-shadow: inset 0 1px 7px rgba(0,0,0,.35);
}
.wf-admin-profiles__filter-actions {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
}
.wf-admin-profiles__list {
    padding: 1rem;
    display: grid;
    gap: .9rem;
    background:
        radial-gradient(circle at 15% 0%, rgba(96,216,214,.06), transparent 30%),
        linear-gradient(180deg, rgba(20,17,13,.78), rgba(10,9,8,.88));
}
.wf-admin-profile-card {
    z-index: 1;
    display: grid;
    grid-template-columns: minmax(260px, 1.35fr) minmax(220px, .9fr) minmax(320px, 1.35fr) minmax(190px, .8fr) minmax(130px, auto);
    gap: 1rem;
    align-items: stretch;
    padding: 1rem;
}
.wf-admin-profile-card > * {
    position: relative;
    z-index: 1;
    min-width: 0;
}
.wf-admin-profile-card__identity {
    display: flex;
    gap: .85rem;
    align-items: flex-start;
}
.wf-admin-profile-card__avatar {
    width: 58px;
    height: 58px;
    min-width: 58px;
    border-radius: 16px;
    object-fit: cover;
    border: 1px solid rgba(231,197,109,.38);
    background: rgba(0,0,0,.32);
    box-shadow: 0 10px 22px rgba(0,0,0,.3), 0 0 18px rgba(96,216,214,.10);
}
.wf-admin-profile-card__title {
    font-size: 1.05rem;
    font-weight: 900;
    color: #f7df9f;
    overflow-wrap: anywhere;
}
.wf-admin-profile-card__sub {
    color: rgba(226, 207, 164, .68);
    font-size: .85rem;
    margin-top: .18rem;
    overflow-wrap: anywhere;
}
.wf-admin-profile-card__badges {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    margin-top: .55rem;
}
.wf-admin-pill {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    border-radius: 999px;
    border: 1px solid rgba(231,197,109,.24);
    background: rgba(255,255,255,.055);
    color: rgba(247,237,209,.9);
    padding: .25rem .55rem;
    font-size: .74rem;
    line-height: 1.2;
    font-weight: 800;
}
.wf-admin-pill--accent { border-color: rgba(96,216,214,.34); color: #9df2ed; }
.wf-admin-pill--success { border-color: rgba(128,215,126,.34); color: #b8f3b4; }
.wf-admin-profile-card__owner {
    display: flex;
    gap: .65rem;
    align-items: flex-start;
}
.wf-admin-profile-card__owner-avatar {
    width: 38px;
    height: 38px;
    min-width: 38px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid rgba(231,197,109,.24);
    background: rgba(0,0,0,.35);
}
.wf-admin-profile-card__section-title {
    display: block;
    margin-bottom: .45rem;
    color: rgba(226, 207, 164, .68);
    text-transform: uppercase;
    letter-spacing: .1em;
    font-size: .69rem;
    font-weight: 900;
}
.wf-admin-profile-card__metrics {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .45rem;
}
.wf-admin-profile-card__metric {
    border: 1px solid rgba(231,197,109,.12);
    border-radius: 11px;
    background: rgba(0,0,0,.18);
    padding: .55rem .65rem;
}
.wf-admin-profile-card__metric span {
    display: block;
    color: rgba(226, 207, 164, .62);
    font-size: .72rem;
}
.wf-admin-profile-card__metric strong {
    display: block;
    margin-top: .1rem;
    color: #f7edd1;
    font-size: .96rem;
}
.wf-admin-profile-card__sync,
.wf-admin-profile-card__journeys {
    display: grid;
    gap: .45rem;
    align-content: start;
}
.wf-admin-profile-card__error {
    padding: .5rem .6rem;
    border-radius: 10px;
    border: 1px solid rgba(255,120,104,.32);
    background: rgba(120,25,20,.20);
    color: #ffd5cc;
    font-size: .82rem;
    overflow-wrap: anywhere;
}
.wf-admin-profile-card__actions {
    display: grid;
    gap: .5rem;
    align-content: start;
}
.wf-admin-profile-card__actions .button,
.wf-admin-profile-card__actions button {
    width: 100%;
    white-space: nowrap;
}
.wf-admin-profile-card__actions form {
    margin: 0;
}
.wf-admin-profiles__empty {
    position: relative;
    z-index: 1;
    text-align: center;
    padding: 2rem;
    color: rgba(226, 207, 164, .78);
}
@media (max-width: 1200px) {
    .wf-admin-profile-card {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .wf-admin-profile-card__actions {
        grid-column: 1 / -1;
        grid-template-columns: repeat(2, minmax(140px, max-content));
        justify-content: start;
    }
}
@media (max-width: 980px) {
    .wf-admin-profiles__header {
        grid-template-columns: 1fr;
    }
    .wf-admin-profiles__counts {
        min-width: 0;
    }
    .wf-admin-profiles__filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .wf-admin-profiles__filter-actions {
        grid-column: 1 / -1;
    }
}
@media (max-width: 700px) {
    .wf-admin-profiles {
        width: min(100%, calc(100vw - 1rem));
    }
    .wf-admin-profiles__counts,
    .wf-admin-profiles__filter-grid,
    .wf-admin-profile-card,
    .wf-admin-profile-card__metrics {
        grid-template-columns: 1fr;
    }
    .wf-admin-profile-card__actions {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="wf-admin-profiles">
    <header class="wf-admin-profiles__header">
        <div>
            <span class="wf-admin-profiles__eyebrow">Admin archive</span>
            <h1>Player Profiles</h1>
            <p>Review linked RuneScape profiles, owners, profile visibility and RuneMetrics sync state from a dedicated admin ledger.</p>
        </div>
        <div class="wf-admin-profiles__counts" aria-label="Profile counts">
            <div class="wf-admin-profiles__count"><span>Total profiles</span><strong><?= e($totalProfiles) ?></strong></div>
            <div class="wf-admin-profiles__count"><span>Showing</span><strong><?= e($matchingProfiles) ?></strong></div>
        </div>
    </header>

    <section class="wf-admin-profiles__filters" aria-label="Profile filters">
        <form method="get" class="wf-admin-profiles__filter-grid">
            <label>
                <span>Search</span>
                <input type="search" name="q" value="<?= e($q) ?>" placeholder="RSN, owner, Discord ID or email">
            </label>
            <label>
                <span>Account type</span>
                <select name="account_type">
                    <option value="">All account types</option>
                    <?php foreach (account_type_options() as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $accountType === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Visibility</span>
                <select name="visibility">
                    <option value="">All visibility</option>
                    <?php foreach (visibility_options() as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $visibility === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Sync state</span>
                <select name="sync">
                    <option value="">All sync states</option>
                    <option value="synced" <?= $sync === 'synced' ? 'selected' : '' ?>>Synced</option>
                    <option value="never" <?= $sync === 'never' ? 'selected' : '' ?>>Never synced</option>
                    <option value="public" <?= $sync === 'public' ? 'selected' : '' ?>>RuneMetrics public</option>
                    <option value="private" <?= $sync === 'private' ? 'selected' : '' ?>>RuneMetrics private</option>
                </select>
            </label>
            <div class="wf-admin-profiles__filter-actions">
                <button class="button">Filter</button>
                <a class="button secondary" href="/admin/profiles.php">Reset</a>
            </div>
        </form>
    </section>

    <section class="wf-admin-profiles__list" aria-label="Profile results">
        <?php if (!$profiles): ?>
            <div class="wf-admin-profiles__empty">
                <h2>No profiles found</h2>
                <p>Try clearing or changing your filters.</p>
            </div>
        <?php else: ?>
            <?php foreach ($profiles as $profile): ?>
                <article class="wf-admin-profile-card">
                    <div>
                        <span class="wf-admin-profile-card__section-title">Profile</span>
                        <div class="wf-admin-profile-card__identity">
                            <img class="wf-admin-profile-card__avatar" src="<?= e(runescape_avatar_url((string)$profile['rsn'])) ?>" alt="Avatar for <?= e($profile['rsn']) ?>" loading="lazy" referrerpolicy="no-referrer">
                            <div>
                                <div class="wf-admin-profile-card__title"><?= e($profile['rm_display_name'] ?: $profile['rsn']) ?></div>
                                <div class="wf-admin-profile-card__sub">RSN: <?= e($profile['rsn']) ?></div>
                                <div class="wf-admin-profile-card__badges">
                                    <span class="wf-admin-pill wf-admin-pill--accent"><?= e(account_type_options()[$profile['account_type']] ?? $profile['account_type']) ?></span>
                                    <span class="wf-admin-pill"><?= e(visibility_options()[$profile['visibility']] ?? $profile['visibility']) ?></span>
                                    <?php if ((int)$profile['is_primary'] === 1): ?><span class="wf-admin-pill wf-admin-pill--success">Primary</span><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <span class="wf-admin-profile-card__section-title">Owner</span>
                        <div class="wf-admin-profile-card__owner">
                            <img class="wf-admin-profile-card__owner-avatar" src="<?= e(admin_profile_discord_avatar($profile)) ?>" alt="Owner avatar" loading="lazy" referrerpolicy="no-referrer">
                            <div>
                                <div class="wf-admin-profile-card__title"><?= e(admin_profile_owner_name($profile)) ?></div>
                                <?php if (!empty($profile['owner_username'])): ?><div class="wf-admin-profile-card__sub">@<?= e($profile['owner_username']) ?></div><?php endif; ?>
                                <?php if (!empty($profile['owner_email'])): ?><div class="wf-admin-profile-card__sub"><?= e($profile['owner_email']) ?></div><?php endif; ?>
                                <div class="wf-admin-profile-card__sub">User #<?= e((int)$profile['user_id']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <span class="wf-admin-profile-card__section-title">Progress snapshot</span>
                        <div class="wf-admin-profile-card__metrics">
                            <div class="wf-admin-profile-card__metric"><span>Total level</span><strong><?= e(admin_profile_number($profile['total_level'])) ?></strong></div>
                            <div class="wf-admin-profile-card__metric"><span>Combat</span><strong><?= e(admin_profile_number($profile['combat_level'])) ?></strong></div>
                            <div class="wf-admin-profile-card__metric"><span>Total XP</span><strong><?= e(admin_profile_number($profile['total_xp'])) ?></strong></div>
                            <div class="wf-admin-profile-card__metric"><span>Quests complete</span><strong><?= e(admin_profile_number($profile['quests_complete'])) ?></strong></div>
                        </div>
                    </div>

                    <div>
                        <span class="wf-admin-profile-card__section-title">Sync & journeys</span>
                        <div class="wf-admin-profile-card__sync">
                            <span class="wf-admin-pill"><?= e(admin_profile_sync_label($profile)) ?></span>
                            <div class="wf-admin-profile-card__sub">Last sync: <?= e($profile['last_sync_at'] ?: 'Never') ?></div>
                            <?php if (!empty($profile['last_sync_status'])): ?><div class="wf-admin-profile-card__sub">Status: <?= e($profile['last_sync_status']) ?></div><?php endif; ?>
                            <div class="wf-admin-profile-card__sub"><strong><?= e((int)$profile['active_journey_count']) ?></strong> active journey<?= (int)$profile['active_journey_count'] === 1 ? '' : 's' ?></div>
                            <div class="wf-admin-profile-card__sub"><?= e((int)$profile['journey_count']) ?> total journey<?= (int)$profile['journey_count'] === 1 ? '' : 's' ?></div>
                            <?php if (!empty($profile['last_sync_error'])): ?><div class="wf-admin-profile-card__error"><?= e($profile['last_sync_error']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <span class="wf-admin-profile-card__section-title">Actions</span>
                        <div class="wf-admin-profile-card__actions">
                            <a class="button secondary" href="/profiles/view.php?id=<?= (int)$profile['id'] ?>&admin=1">View</a>
                            <?php if (current_user_can('profiles.delete')): ?>
                                <form method="post" onsubmit="return confirm('Delete this profile? This cannot be undone.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="profile_id" value="<?= (int)$profile['id'] ?>">
                                    <button class="button danger">Delete</button>
                                </form>
                            <?php endif; ?>
                            <div class="wf-admin-profile-card__sub">Created <?= e($profile['created_at']) ?></div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</section>
<?php page_footer(); ?>
