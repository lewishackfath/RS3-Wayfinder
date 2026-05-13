<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$limit = (int)($_SERVER['argv'][1] ?? env('ACCOUNT_DELETE_PURGE_LIMIT', 25));
$graceMinutes = (int)($_SERVER['argv'][2] ?? env('ACCOUNT_DELETE_GRACE_MINUTES', 60));

try {
    $deleted = purge_queued_deleted_accounts($limit, $graceMinutes);
    echo '[' . gmdate('c') . "] Purged {$deleted} queued deleted account(s).
";
} catch (Throwable $e) {
    fwrite(STDERR, '[' . gmdate('c') . '] Account purge failed: ' . $e->getMessage() . "
");
    exit(1);
}
