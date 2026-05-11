<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/layout.php';
page_header('Home');
?>
<section class="hero">
    <h1>Find your next RuneScape journey.</h1>
    <p class="muted">RS3 Wayfinder will help players follow progression paths based on their account, goals and playstyle.</p>
    <p>
        <?php if (current_user()): ?>
            <a class="button" href="/dashboard.php">Open Dashboard</a>
        <?php else: ?>
            <a class="button" href="/auth/login.php">Login with Discord</a>
        <?php endif; ?>
    </p>
</section>
<?php page_footer(); ?>
