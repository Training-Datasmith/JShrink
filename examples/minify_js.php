<?php

declare(strict_types=1);

/**
 * Example: Minify JavaScript using JShrink.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use J_Shrink\Minifier;

$js = <<<'JS'
// Application bootstrap
(function() {
    "use strict";

    /**
     * Greet a user.
     * @param {string} name
     */
    function greet(name) {
        var message = "Hello, " + name + "!";
        console.log(message);
    }

    greet("World");
}());
JS;

// Basic minification — licence/flagged comments (/*! … */) are preserved.
$minified = Minifier::minify($js);
echo "Minified (" . strlen($minified) . " bytes):" . PHP_EOL;
echo $minified . PHP_EOL . PHP_EOL;

// Strip ALL comments, including flagged/licence blocks.
$stripped = Minifier::minify($js, ['flaggedComments' => false]);
echo "Fully stripped (" . strlen($stripped) . " bytes):" . PHP_EOL;
echo $stripped . PHP_EOL;
