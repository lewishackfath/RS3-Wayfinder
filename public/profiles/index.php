<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_login();
$user = current_user();
$profiles = profiles_for_user((int)$user['id']);
$active = active_profile();
page_header('My Profiles');
?>
<div class="page-title-row">
    <div>
        <h1>My RSN Profiles</h1>
        <p class="muted">Attach each RuneScape character you want Wayfinder to support. The active profile selector is now available in the top menu.</p>
    </div>
    <a class="button" href="/profiles/new.php">Add RSN</a>
</div>

<?php if (!$profiles): ?>
    <div class="card empty-state">
        <h2>No profiles yet</h2>
        <p>Add your first RSN to start preparing your Wayfinder dashboard.</p>
        <a class="button" href="/profiles/new.php">Add your first RSN</a>
    </div>
<?php else: ?>
    <div class="grid cards-grid">
        <?php foreach ($profiles as $profile): ?>
            <div class="card profile-card">
                <div class="profile-card-head">
                    <img class="profile-avatar" src="<?= e(runescape_avatar_url((string)$profile['rsn'])) ?>" alt="Avatar for <?= e($profile['rsn']) ?>" loading="lazy" referrerpolicy="no-referrer">
                    <div>
                        <div class="card-row">
                            <h2><?= e($profile['rsn']) ?></h2>
                            <?php if ((int)$profile['is_primary'] === 1): ?><span class="badge">Primary</span><?php endif; ?>
                            <?php if ($active && (int)$active['id'] === (int)$profile['id']): ?><span class="badge accent">Active</span><?php endif; ?>
                        </div>
                        <p class="muted small"><?= e(account_type_options()[$profile['account_type']] ?? $profile['account_type']) ?></p>
                    </div>
                </div>
                <p><strong>Visibility:</strong> <?= e(visibility_options()[$profile['visibility']] ?? $profile['visibility']) ?></p>
                <p class="muted">RuneMetrics sync: <?= e(format_sync_age($profile['last_sync_at'] ?? null)) ?></p>
                <div class="form-actions">
                    <form method="post" action="/profiles/select.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="profile_id" value="<?= (int)$profile['id'] ?>">
                        <button class="button secondary" type="submit">Use profile</button>
                    </form>
                    <a class="button secondary" href="/profiles/view.php?id=<?= (int)$profile['id'] ?>">View data</a>
                    <a class="button secondary" href="/profiles/edit.php?id=<?= (int)$profile['id'] ?>">Edit profile</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php page_footer(); ?>
