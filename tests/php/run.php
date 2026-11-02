<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/Fixtures/DisclosureFixtures.php';
foreach (glob(__DIR__ . '/*Test.php') as $testFile) {
    require $testFile;
}
$failures = 0;
$passed = 0;
$cases = count($GLOBALS['test_cases']);
if ($cases === 0) {
    fwrite(STDERR, "No PHP tests registered. Failing closed.\n");
    exit(1);
}
foreach ($GLOBALS['test_cases'] as $name => $case) {
    reset_fixtures();
    try {
        $case();
        ++$passed;
        echo "PASS {$name}\n";
    } catch (Throwable $e) {
        ++$failures;
        fwrite(STDERR, "FAIL {$name}: {$e->getMessage()}\n  {$e->getFile()}:{$e->getLine()}\n");
    }
}
printf("\nPHP cases: %d passed, %d failed (%d total)\n", $passed, $failures, $cases);
exit($failures ? 1 : 0);
