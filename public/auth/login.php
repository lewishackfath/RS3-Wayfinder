<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

if (current_user()) {
    redirect('/dashboard.php');
}

redirect(discord_authorise_url());
