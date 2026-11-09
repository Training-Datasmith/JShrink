<?php

/**
 * @param string $outputPath
 */
function check_phpunit_baseline_file($outputPath)
{
    $baseline = require dirname(__FILE__) . '/baseline-counts.php';
    $wantTests = (int) $baseline['tests'];
    $wantAssertions = (int) $baseline['assertions'];

    $raw = file_get_contents($outputPath);
    if ($raw === false) {
        fwrite(STDERR, "check-phpunit-baseline: cannot read {$outputPath}\n");

        return 1;
    }
    $stripped = preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $raw);
    if ($stripped === null) {
        $stripped = $raw;
    }
    if (preg_match('/^(PHP[[:space:]]+)?Warning:/m', $stripped)
        || preg_match('/^(PHP[[:space:]]+)?Deprecated:/m', $stripped)
        || preg_match('/^(PHP[[:space:]]+)?Notice:/m', $stripped)) {
        fwrite(STDERR, "check-phpunit-baseline: output contains Warning, Deprecated, or Notice.\n");

        return 1;
    }
    $pattern = '/^OK \(' . $wantTests . ' tests, ' . $wantAssertions . ' assertions\)$/m';
    if (!preg_match($pattern, $stripped)) {
        fwrite(STDERR, sprintf(
            "check-phpunit-baseline: missing OK (%d tests, %d assertions).\n",
            $wantTests,
            $wantAssertions
        ));

        return 1;
    }
    if (preg_match('/\b(skipped|incomplete)\b/i', $stripped)) {
        fwrite(STDERR, "check-phpunit-baseline: output mentions skipped or incomplete tests.\n");

        return 1;
    }
    fwrite(STDOUT, sprintf("check-phpunit-baseline: OK (%d tests, %d assertions).\n", $wantTests, $wantAssertions));

    return 0;
}

if (PHP_SAPI === 'cli' && isset($argv[1])) {
    exit(check_phpunit_baseline_file($argv[1]));
}
