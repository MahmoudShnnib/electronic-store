<?php

declare(strict_types=1);

/*
 * READ-ONLY invariant check against a real database. Prints PASS/FAIL per rule in
 * audit/INVARIANTS.md with the records that break it; exits 1 when anything fails.
 *
 *   php audit/check_invariants.php                                    # DB from .env (local dev)
 *   DB_DATABASE=electronic_store_test php audit/check_invariants.php  # the test DB
 *
 * Everything runs inside one Postgres READ ONLY transaction that is rolled back at the
 * end, so even a bug in the checker cannot write. (A session-level
 * SET SESSION CHARACTERISTICS was tried first and did NOT block writes here.)
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require __DIR__.'/invariants.php';

if (app()->environment('production')) {
    fwrite(STDERR, "Refusing to run with APP_ENV=production.\n");
    exit(2);
}

DB::beginTransaction();
DB::statement('SET TRANSACTION READ ONLY');
echo 'Database: '.DB::connection()->getDatabaseName()."\n\n";
$results = auditInvariants();
DB::rollBack();

$failed = 0;
foreach ($results as $rule => $violations) {
    $skipped = count($violations) === 1 && str_starts_with($violations[0], 'SKIPPED');
    $status = $violations === [] ? 'PASS' : ($skipped ? 'SKIP' : 'FAIL');
    echo sprintf("%s  %-3s %s\n", $status, $rule, AUDIT_RULES[$rule]);
    foreach (array_slice($violations, 0, 50) as $line) {
        echo "        - $line\n";
    }
    if (count($violations) > 50) {
        echo '        … and '.(count($violations) - 50)." more\n";
    }
    $failed += $violations === [] || $skipped ? 0 : 1;
}

echo "\n".($failed === 0 ? 'ALL RULES PASS' : "$failed rule(s) FAIL")."\n";
exit($failed === 0 ? 0 : 1);
