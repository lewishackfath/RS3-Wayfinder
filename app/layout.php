<?php
declare(strict_types=1);

function page_header(string $title): void
{
    $user = current_user();
    echo '<!doctype html><html lang="en-AU"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' - ' . e(env('APP_NAME', 'RS3 Wayfinder')) . '</title><link rel="stylesheet" href="/assets/app.css"><link rel="icon" type="image/png" href="/assets/branding/icon.png"></head><body>';
    echo '<header class="topbar"><a class="brand" href="/index.php"><img src="/assets/branding/icon.png" alt="Wayfinder" style="height:32px;width:32px;vertical-align:middle;margin-right:10px;border-radius:8px;">RS3 Wayfinder</a><nav>';
    if ($user) {
        echo '<a href="/dashboard.php">Dashboard</a>';
        if (current_user_can('admin.access')) echo '<a href="/admin/index.php">Admin</a>';
        echo '<a href="/auth/logout.php">Logout</a>';
    } else {
        echo '<a href="/auth/login.php">Login</a>';
    }
    echo '</nav></header><main class="container">';
}

function page_footer(): void
{
    echo '</main><footer class="footer">RS3 Wayfinder is an independent RuneScape journey tool and is not affiliated with Jagex or Discord.</footer></body></html>';
}
