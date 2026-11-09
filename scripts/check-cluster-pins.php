<?php

/**
 * Verify cluster packages from composer-index match expected SHAs and aliases.
 * Includes in-memory wrong-SHA demos for installed.php and installed.json.
 */

$expected = array(
    // No org packages are pinned for this repo; phpunit resolves from Packagist.
);

$root = dirname(__DIR__);
$installedPhp = $root . '/vendor/composer/installed.php';
$installedJson = $root . '/vendor/composer/installed.json';

function fail_stderr($message, $silent = false)
{
    if ($silent) {
        return;
    }
    fwrite(STDERR, $message);
    if (substr($message, -1) !== "\n") {
        fwrite(STDERR, "\n");
    }
}

function run_wrong_sha_demo_installed_php()
{
    $fixture = array(
        'versions' => array(
            'example/org' => array(
                'reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'aliases' => array('1.2.3'),
            ),
        ),
    );
    $wrong = $fixture;
    $wrong['versions']['example/org']['reference'] = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    return !assert_reference_match(
        $wrong,
        array('example/org' => array('reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'alias' => '1.2.3')),
        true
    );
}

function run_wrong_sha_demo_installed_json()
{
    $packages = array(
        array(
            'name' => 'example/org',
            'source' => array(
                'reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'url' => 'https://github.com/example/org.git',
            ),
        ),
    );
    $wrongPackages = $packages;
    $wrongPackages[0]['source']['reference'] = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    return !assert_json_packages($wrongPackages, array('example/org' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'), true);
}

/**
 * @param array<string, array{reference?: string, alias?: string}> $expectedMap
 */
function assert_reference_match(array $installedData, array $expectedMap, $silent = false)
{
    $versions = isset($installedData['versions']) ? $installedData['versions'] : array();
    $failed = false;
    foreach ($expectedMap as $name => $want) {
        if (!isset($versions[$name])) {
            fail_stderr(sprintf('check-cluster-pins: %s is not installed (installed.php).', $name), $silent);
            $failed = true;
            continue;
        }
        $pkg = $versions[$name];
        $aliases = isset($pkg['aliases']) ? $pkg['aliases'] : array();
        if (isset($want['alias']) && !in_array($want['alias'], $aliases, true)) {
            fail_stderr(sprintf(
                'check-cluster-pins: %s alias mismatch (expected %s in aliases %s).',
                $name,
                $want['alias'],
                json_encode($aliases)
            ), $silent);
            $failed = true;
        }
        $reference = isset($pkg['reference']) ? $pkg['reference'] : '';
        if (!is_string($reference) || strlen($reference) !== 40) {
            fail_stderr(sprintf('check-cluster-pins: %s has no 40-char reference in installed.php.', $name), $silent);
            $failed = true;
            continue;
        }
        if (strcasecmp($reference, $want['reference']) !== 0) {
            fail_stderr(sprintf(
                'check-cluster-pins: %s reference mismatch in installed.php (expected %s, got %s).',
                $name,
                strtolower($want['reference']),
                strtolower($reference)
            ), $silent);
            $failed = true;
        }
    }

    return !$failed;
}

/**
 * @param list<array<string, mixed>> $packages
 * @param array<string, string> $expectedRefs
 */
function assert_json_packages(array $packages, array $expectedRefs, $silent = false)
{
    $installed = array();
    foreach ($packages as $package) {
        if (!isset($package['name'], $package['source']['reference'])) {
            continue;
        }
        $installed[$package['name']] = strtolower((string) $package['source']['reference']);
    }
    $failed = false;
    foreach ($expectedRefs as $name => $wantSha) {
        if (!isset($installed[$name])) {
            fail_stderr(sprintf('check-cluster-pins: %s is not installed (installed.json).', $name), $silent);
            $failed = true;
            continue;
        }
        if ($installed[$name] !== strtolower($wantSha)) {
            fail_stderr(sprintf(
                'check-cluster-pins: %s reference mismatch in installed.json (expected %s, got %s).',
                $name,
                strtolower($wantSha),
                $installed[$name]
            ), $silent);
            $failed = true;
        }
    }

    return !$failed;
}

if (!run_wrong_sha_demo_installed_php()) {
    fail_stderr('check-cluster-pins: wrong-SHA demo did not fail on installed.php side.');
    exit(1);
}
if (!run_wrong_sha_demo_installed_json()) {
    fail_stderr('check-cluster-pins: wrong-SHA demo did not fail on installed.json side.');
    exit(1);
}

fwrite(STDOUT, "check-cluster-pins: wrong-SHA demo failed closed on installed.php and installed.json.\n");

if (!is_readable($installedPhp)) {
    fail_stderr('check-cluster-pins: vendor/composer/installed.php not found; run composer update first.');
    exit(1);
}
if (!is_readable($installedJson)) {
    fail_stderr('check-cluster-pins: vendor/composer/installed.json not found; run composer update first.');
    exit(1);
}

/** @var array{versions?: array<string, array<string, mixed>>} $installedPhpData */
$installedPhpData = require $installedPhp;
$jsonRaw = file_get_contents($installedJson);
if ($jsonRaw === false) {
    fail_stderr('check-cluster-pins: could not read installed.json.');
    exit(1);
}
/** @var array{packages?: list<array<string, mixed>>, 'packages-dev'?: list<array<string, mixed>>} $jsonData */
$jsonData = json_decode($jsonRaw, true);
if (!is_array($jsonData)) {
    fail_stderr('check-cluster-pins: invalid installed.json.');
    exit(1);
}
$mergedPackages = array_merge(
    isset($jsonData['packages']) ? $jsonData['packages'] : array(),
    isset($jsonData['packages-dev']) ? $jsonData['packages-dev'] : array()
);

$expectedForPhp = array();
$expectedForJson = array();
foreach ($expected as $name => $want) {
    $expectedForPhp[$name] = $want;
    $expectedForJson[$name] = $want['reference'];
}

$failed = false;
if (!assert_reference_match($installedPhpData, $expectedForPhp)) {
    $failed = true;
}
if (!assert_json_packages($mergedPackages, $expectedForJson)) {
    $failed = true;
}

// Packagist GAP guard for phpunit/phpunit (not Training-Datasmith).
$versions = isset($installedPhpData['versions']) ? $installedPhpData['versions'] : array();
if (!isset($versions['phpunit/phpunit'])) {
    fail_stderr('check-cluster-pins: phpunit/phpunit is not installed.');
    $failed = true;
} else {
    $phpunit = $versions['phpunit/phpunit'];
    $pretty = isset($phpunit['pretty_version']) ? $phpunit['pretty_version'] : '';
    if ($pretty === '' || version_compare($pretty, '8.5.52', '<')) {
        fail_stderr(sprintf('check-cluster-pins: phpunit/phpunit version %s is below patched 8.5.52.', $pretty));
        $failed = true;
    }
}

$byName = array();
foreach ($mergedPackages as $package) {
    if (isset($package['name'])) {
        $byName[$package['name']] = $package;
    }
}
if (isset($byName['phpunit/phpunit'])) {
    $sourceUrl = isset($byName['phpunit/phpunit']['source']['url']) ? $byName['phpunit/phpunit']['source']['url'] : '';
    if (stripos($sourceUrl, 'Training-Datasmith/phpunit') !== false) {
        fail_stderr('check-cluster-pins: phpunit/phpunit must not resolve from Training-Datasmith fork for this repo.');
        $failed = true;
    }
    if (stripos($sourceUrl, 'sebastianbergmann/phpunit') === false && stripos($sourceUrl, 'github.com') !== false) {
        fail_stderr('check-cluster-pins: phpunit/phpunit source.url is not sebastianbergmann/phpunit.');
        $failed = true;
    }
}

if ($failed) {
    exit(1);
}

fwrite(STDOUT, "check-cluster-pins: all cluster packages match expected SHAs and aliases.\n");
exit(0);
