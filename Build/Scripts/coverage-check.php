<?php

declare(strict_types=1);

/*
 * Fails when the line coverage in a Clover report is below a minimum.
 * PHPUnit has no threshold of its own.
 *
 *   php coverage-check.php <clover.xml> <minimum percent>
 */

if ($argc !== 3 || !is_numeric($argv[2])) {
    fwrite(STDERR, "Usage: php coverage-check.php <clover.xml> <minimum percent>\n");
    exit(2);
}

[, $file, $minimum] = $argv;
$xml = is_file($file) ? simplexml_load_file($file) : false;
if ($xml === false) {
    fwrite(STDERR, "Cannot read the Clover report $file\n");
    exit(2);
}

$metrics = $xml->xpath('/coverage/project/metrics')[0] ?? null;
$statements = (int)($metrics['statements'] ?? 0);
if ($statements === 0) {
    fwrite(STDERR, "The Clover report $file has no statements; was a coverage driver loaded?\n");
    exit(2);
}

$percent = 100 * (int)$metrics['coveredstatements'] / $statements;
printf("Line coverage: %.2f%% (%d of %d lines), minimum %.2f%%\n", $percent, (int)$metrics['coveredstatements'], $statements, $minimum);

if ($percent < (float)$minimum) {
    fwrite(STDERR, "Line coverage is below the minimum.\n");
    exit(1);
}
