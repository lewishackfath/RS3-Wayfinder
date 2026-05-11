<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_login();
$user = current_user();
$profileId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$profile = profile_for_user($profileId, (int)$user['id']);
if (!$profile) abort_page(404, 'Profile not found.');
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        if (isset($_POST['delete_profile'])) {
            delete_profile($profileId, (int)$user['id']);
            redirect('/profiles/index.php');
        }
        update_profile($profileId, (int)$user['id'], (string)($_POST['rsn'] ?? ''), (string)($_POST['account_type'] ?? 'main'), (string)($_POST['visibility'] ?? 'private'), !empty($_POST['is_primary']));
        redirect('/profiles/index.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $profile = array_merge($profile, $_POST);
    }
}
page_header('Edit Profile');
?>
<div class="card narrow">
    <h1>Edit RSN Profile</h1>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-stack">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$profileId ?>">
        <label>RuneScape Name
            <input type="text" name="rsn" maxlength="12" required value="<?= e($profile['rsn']) ?>">
        </label>
        <label>Account type
            <select name="account_type">
                <?php foreach (account_type_options() as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($profile['account_type'] === $value) ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Profile visibility
            <select name="visibility">
                <?php foreach (visibility_options() as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($profile['visibility'] === $value) ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="checkbox-label"><input type="checkbox" name="is_primary" value="1" <?= ((int)$profile['is_primary'] === 1) ? 'checked' : '' ?>> Make this my primary profile</label>
        <div class="form-actions">
            <button class="button" type="submit">Save changes</button>
            <a class="button secondary" href="/profiles/index.php">Cancel</a>
        </div>
    </form>
    <form method="post" onsubmit="return confirm('Delete this profile?');" class="danger-zone">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$profileId ?>">
        <button class="button danger" type="submit" name="delete_profile" value="1">Delete profile</button>
    </form>
</div>
<?php page_footer(); ?>
