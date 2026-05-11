<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/layout.php';

try {
    bootstrap_schema();
    page_header('Setup Check');
    echo '<div class="card"><h1>Setup complete</h1><p class="muted">Database tables and default roles/permissions are ready.</p><p><a class="button" href="/index.php">Go home</a></p></div>';
    page_footer();
} catch (Throwable $e) {
    if (is_debug()) {
        abort_page(500, $e->getMessage());
    }
    abort_page(500, 'Setup failed. Enable APP_DEBUG to see details.');
}
