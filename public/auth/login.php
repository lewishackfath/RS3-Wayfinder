<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
redirect(discord_authorise_url());
