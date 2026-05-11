<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/layout.php';
page_header('Home');
?>
<section class="hero hero-home">
    <img class="hero-logo" src="/assets/branding/logo.png" alt="RS3 Wayfinder">
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
