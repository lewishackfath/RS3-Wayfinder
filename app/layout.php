<?php
declare(strict_types=1);

function page_header(string $title): void
{
    $user = current_user();
    echo '<!doctype html><html lang="en-AU"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' - ' . e(env('APP_NAME', 'RS3 Wayfinder')) . '</title><link rel="icon" type="image/png" href="/assets/branding/icon.png"><link rel="apple-touch-icon" href="/assets/branding/icon.png"><link rel="stylesheet" href="/assets/app.css"></head><body>';
    echo '<header class="topbar"><a class="brand" href="/index.php"><img class="brand-icon" src="/assets/branding/icon.png" alt=""><span>' . e(env('APP_NAME', 'RS3 Wayfinder')) . '</span></a><nav>';
    if ($user) {
        echo '<a href="/dashboard.php">Dashboard</a>';
        echo '<a href="/profiles/index.php">Profiles</a>';
        render_profile_selector((int)$user['id']);
        if (current_user_can('admin.access')) echo '<a href="/admin/index.php">Admin</a>';
        echo '<a href="/auth/logout.php">Logout</a>';
    } else {
        echo '<a href="/auth/login.php">Login</a>';
    }
    echo '</nav></header><main class="container">';
}

function render_profile_selector(int $userId): void
{
    $profiles = profiles_for_user($userId);
    if (!$profiles) {
        echo '<a class="profile-select-link" href="/profiles/new.php">Add RSN</a>';
        return;
    }

    $active = active_profile();
    echo '<form class="profile-select-form" method="post" action="/profiles/select.php">';
    echo csrf_field();
    echo '<label class="sr-only" for="active_profile_id">Active profile</label>';
    echo '<span class="profile-select-avatar-wrap">';
    if ($active) {
        echo '<img class="profile-select-avatar" src="' . e(runescape_avatar_url((string)$active['rsn'])) . '" alt="" loading="lazy" referrerpolicy="no-referrer">';
    }
    echo '</span>';
    echo '<select id="active_profile_id" name="profile_id" onchange="this.form.submit()">';
    foreach ($profiles as $profile) {
        $selected = $active && (int)$profile['id'] === (int)$active['id'] ? ' selected' : '';
        $label = (string)$profile['rsn'];
        if ((int)$profile['is_primary'] === 1) {
            $label .= ' ★';
        }
        echo '<option value="' . (int)$profile['id'] . '"' . $selected . '>' . e($label) . '</option>';
    }
    echo '</select></form>';
}

function page_footer(): void
{
    echo '</main><footer class="footer">RS3 Wayfinder is an independent RuneScape journey tool and is not affiliated with Jagex or Discord.</footer></body></html>';
}
