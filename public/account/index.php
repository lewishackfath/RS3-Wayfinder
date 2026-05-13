<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_login();
$user = current_user();
$userId = (int)$user['id'];
$error = null;
$success = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_account') {
            update_account_nickname($userId, (string)($_POST['nickname'] ?? ''));
            $success = 'Account details updated.';
            $user = account_user_by_id($userId) ?: $user;
        } elseif ($action === 'delete_profile') {
            $profileId = (int)($_POST['profile_id'] ?? 0);
            delete_profile($profileId, $userId);
            if (active_profile_id() === $profileId) {
                unset($_SESSION['active_profile_id']);
            }
            $success = 'Profile deleted.';
        } elseif ($action === 'delete_account') {
            $confirm = trim((string)($_POST['confirm_delete'] ?? ''));
            if ($confirm !== 'DELETE') {
                throw new InvalidArgumentException('Type DELETE to confirm account deletion.');
            }
            request_current_user_account_deletion($userId);
            logout_user();
            redirect('/index.php');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$profiles = profiles_for_user($userId);
$active = active_profile();
$profileInterestMap = [];
foreach ($profiles as $profile) {
    $profileInterestMap[(int)$profile['id']] = profile_interest_tags((int)$profile['id']);
}

page_header('Account');
?>
<div class="page-title-row">
    <div>
        <h1>Account</h1>
        <p class="muted">Manage your Wayfinder account, linked RuneScape profiles and profile interests.</p>
    </div>
    <a class="button" href="/profiles/new.php">Add RSN profile</a>
</div>

<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>

<div class="grid account-grid">
    <section class="card account-summary-card">
        <div class="profile-card-head">
            <img class="profile-avatar" src="<?= e(discord_avatar_url_for_user($user)) ?>" alt="Discord avatar" loading="lazy" referrerpolicy="no-referrer">
            <div>
                <h2><?= e(user_display_name($user)) ?></h2>
                <p class="muted small">Discord: <?= e($user['global_name'] ?: $user['username']) ?></p>
                <?php if (!empty($user['email'])): ?><p class="muted small"><?= e($user['email']) ?></p><?php endif; ?>
            </div>
        </div>
        <form method="post" class="form-stack compact-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_account">
            <label>Wayfinder nickname
                <input type="text" name="nickname" maxlength="100" placeholder="How Wayfinder should display your name" value="<?= e($user['nickname'] ?? '') ?>">
            </label>
            <div class="form-actions">
                <button class="button" type="submit">Save account</button>
            </div>
        </form>
    </section>

    <section class="card danger-zone account-danger-card">
        <h2>Delete account</h2>
        <p class="muted">This queues your Wayfinder account for deletion and immediately disables access. A cleanup cron removes linked profiles, journey progress and profile data later. This does not delete your Discord account.</p>
        <form method="post" class="form-stack" onsubmit="return confirm('Queue your Wayfinder account for deletion? You will be logged out immediately.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_account">
            <label>Type DELETE to confirm
                <input type="text" name="confirm_delete" autocomplete="off" placeholder="DELETE">
            </label>
            <button class="button danger" type="submit">Queue account deletion</button>
        </form>
    </section>
</div>

<section class="card">
    <div class="page-title-row inline-title-row">
        <div>
            <h2>RuneScape profiles</h2>
            <p class="muted">Interests are stored per profile so each character can receive different recommendations.</p>
        </div>
        <a class="button secondary" href="/profiles/new.php">Add profile</a>
    </div>

    <?php if (!$profiles): ?>
        <div class="empty-state compact-empty">
            <h3>No profiles yet</h3>
            <p>Add your first RSN to start tracking progression.</p>
            <a class="button" href="/profiles/new.php">Add your first RSN</a>
        </div>
    <?php else: ?>
        <div class="grid cards-grid account-profile-grid">
            <?php foreach ($profiles as $profile): ?>
                <?php $interestTags = $profileInterestMap[(int)$profile['id']] ?? []; ?>
                <article class="card profile-card nested-card">
                    <div class="profile-card-head">
                        <img class="profile-avatar" src="<?= e(runescape_avatar_url((string)$profile['rsn'])) ?>" alt="Avatar for <?= e($profile['rsn']) ?>" loading="lazy" referrerpolicy="no-referrer">
                        <div>
                            <div class="card-row">
                                <h3><?= e($profile['rsn']) ?></h3>
                                <?php if ((int)$profile['is_primary'] === 1): ?><span class="badge">Primary</span><?php endif; ?>
                                <?php if ($active && (int)$active['id'] === (int)$profile['id']): ?><span class="badge accent">Active</span><?php endif; ?>
                            </div>
                            <p class="muted small"><?= e(account_type_options()[$profile['account_type']] ?? $profile['account_type']) ?> · <?= e(visibility_options()[$profile['visibility']] ?? $profile['visibility']) ?></p>
                        </div>
                    </div>
                    <p class="muted small">RuneMetrics sync: <?= e(format_sync_age($profile['last_sync_at'] ?? null)) ?></p>
                    <div class="tag-list profile-interest-list">
                        <?php if ($interestTags): ?>
                            <?php foreach ($interestTags as $tag): ?><span class="badge"><?= e($tag['name']) ?></span><?php endforeach; ?>
                        <?php else: ?>
                            <span class="muted small">No interests selected yet.</span>
                        <?php endif; ?>
                    </div>
                    <div class="form-actions wrap-actions">
                        <form method="post" action="/profiles/select.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="profile_id" value="<?= (int)$profile['id'] ?>">
                            <button class="button secondary" type="submit">Use profile</button>
                        </form>
                        <a class="button secondary" href="/profiles/view.php?id=<?= (int)$profile['id'] ?>">View</a>
                        <a class="button secondary" href="/profiles/edit.php?id=<?= (int)$profile['id'] ?>">Edit & interests</a>
                        <form method="post" onsubmit="return confirm('Delete <?= e($profile['rsn']) ?> from your account?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_profile">
                            <input type="hidden" name="profile_id" value="<?= (int)$profile['id'] ?>">
                            <button class="button danger subtle-danger" type="submit">Delete</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
