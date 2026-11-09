<?php

$root = dirname(__DIR__);
$paths = array(
    $root . '/src/J_Shrink/Minifier.php',
    $root . '/tests/J_Shrink/Test/J_Shrink_Test.php',
    $root . '/tests/bootstrap.php',
    $root . '/composer.json',
    $root . '/phpunit.xml.dist',
);

$forbiddenTokens = array(
    'namespace JShrink',
    '\\JShrink\\',
    'class JShrinkTest',
    'JShrink\\\\Test',
    'minifyDirectToOutput',
    'getReal',
    'saveString',
    'isAlphaNumeric',
    'saveRegex',
    'getChar',
    'processOneLineComments',
    'processMultiLineComments',
    'getNext',
    '$defaultOptions',
    '$startIndex',
    '$endPoint',
    '$thirdCommentString',
    '$stringType',
    'testUnclosedCommentException',
    'testUnclosedStringException',
    'testUnclosedRegexException',
    'testJShrink',
    'testUglify',
    'testRequests',
    'testDevelopment',
    'getTestFiles',
    'uglifyProvider',
    'JShrinkProvider',
    'requestProvider',
    'developmentProvider',
);

$scanDirs = array($root . '/src', $root . '/tests', $root . '/examples');
$failed = false;

if (is_dir($root . '/src/JShrink')) {
    fwrite(STDERR, "check-rename-complete: legacy directory src/JShrink still exists.\n");
    $failed = true;
}
if (is_dir($root . '/tests/JShrink')) {
    fwrite(STDERR, "check-rename-complete: legacy directory tests/JShrink still exists.\n");
    $failed = true;
}

foreach ($paths as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "check-rename-complete: required file missing: {$path}\n");
        $failed = true;
    }
}

foreach ($scanDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        if ($contents === false) {
            continue;
        }
        foreach ($forbiddenTokens as $token) {
            if (strpos($contents, $token) !== false) {
                fwrite(STDERR, sprintf(
                    "check-rename-complete: forbidden token %s in %s\n",
                    $token,
                    $file->getPathname()
                ));
                $failed = true;
            }
        }
    }
}

$testFile = $root . '/tests/J_Shrink/Test/J_Shrink_Test.php';
if (is_file($testFile)) {
    $testContents = file_get_contents($testFile);
    if ($testContents === false || strpos($testContents, 'PHPUnit\Framework\TestCase') === false) {
        fwrite(STDERR, "check-rename-complete: test file must extend PHPUnit\\Framework\\TestCase.\n");
        $failed = true;
    }
    if ($testContents === false || strpos($testContents, 'assertEquals') === false) {
        fwrite(STDERR, "check-rename-complete: test file must use assertEquals.\n");
        $failed = true;
    }
}

if ($failed) {
    exit(1);
}

fwrite(STDOUT, "check-rename-complete: no old repo-owned names remain.\n");
exit(0);
