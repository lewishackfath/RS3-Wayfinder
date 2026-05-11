<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_login();
$user = current_user();
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $profileId = create_profile((int)$user['id'], (string)($_POST['rsn'] ?? ''), (string)($_POST['account_type'] ?? 'main'), (string)($_POST['visibility'] ?? 'private'));
        set_active_profile((int)$profileId, (int)$user['id']);
        redirect('/profiles/index.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
page_header('Add Profile');
?>
<div class="card narrow">
    <h1>Add RSN Profile</h1>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-stack">
        <?= csrf_field() ?>
        <label>RuneScape Name
            <input type="text" name="rsn" maxlength="12" required placeholder="Your RSN" value="<?= e($_POST['rsn'] ?? '') ?>">
        </label>
        <label>Account type
            <select name="account_type">
                <?php foreach (account_type_options() as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= (($_POST['account_type'] ?? 'main') === $value) ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Profile visibility
            <select name="visibility">
                <?php foreach (visibility_options() as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= (($_POST['visibility'] ?? 'private') === $value) ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="form-actions">
            <button class="button" type="submit">Save profile</button>
            <a class="button secondary" href="/profiles/index.php">Cancel</a>
        </div>
    </form>
</div>
<?php page_footer(); ?>
