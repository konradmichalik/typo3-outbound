<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_outbound" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Measures the Petstore plugin in a TYPO3 instance in three cache states and aggregates the
 * profiles of konradmichalik/typo3-request-profiler. Invoked by `ddev benchmark`.
 *
 * Usage: php benchmark.php <baseUrl> <count> <profilesDir> <typo3Bin>
 */

if ($argc < 5) {
    fwrite(\STDERR, "Usage: php benchmark.php <baseUrl> <count> <profilesDir> <typo3Bin>\n");
    exit(1);
}

$baseUrl = rtrim($argv[1], '/');
$count = max(1, (int) $argv[2]);
$profilesDir = $argv[3];
$typo3Bin = $argv[4];
$warmup = 5;

/*
 * Run one after another, not interleaved: flushing the response cache for the cold case also
 * flushes the pages tagged with it, which would turn every page cache hit into a miss.
 */
$scenarios = [
    'page cache hit' => ['path' => '/', 'flush' => false, 'expectHit' => true],
    'response cache hit' => ['path' => '/?type=1794', 'flush' => false, 'expectHit' => false],
    'cold (no response cache)' => ['path' => '/?type=1794', 'flush' => true, 'expectHit' => false],
];

function fetch(string $url): void
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        \CURLOPT_RETURNTRANSFER => true,
        \CURLOPT_SSL_VERIFYPEER => false,
        \CURLOPT_SSL_VERIFYHOST => 0,
        \CURLOPT_TIMEOUT => 30,
        \CURLOPT_HTTPHEADER => ['X-Outbound-Petstore-Mock: 1'],
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, \CURLINFO_RESPONSE_CODE);

    if (!is_string($body) || 200 !== $status || !str_contains($body, 'outbound-petstore') || str_contains($body, 'outbound-error')) {
        throw new RuntimeException(sprintf('%s answered HTTP %d without the rendered Petstore plugin. Is the instance installed with `ddev install`?', $url, $status));
    }
}

function flushResponseCache(string $typo3Bin): void
{
    exec(escapeshellarg(\PHP_BINARY).' '.escapeshellarg($typo3Bin).' outbound:cache:flush petstore 2>&1', $output, $exitCode);
    if (0 !== $exitCode) {
        throw new RuntimeException('outbound:cache:flush petstore failed: '.implode(' ', $output));
    }
}

/**
 * Reads and deletes the profiles written so far. Collecting after every request keeps the
 * profiler's retention limit (50 files by default) from discarding samples.
 *
 * @return list<array<string, mixed>>
 */
function collectProfiles(string $profilesDir): array
{
    $profiles = [];
    foreach (glob($profilesDir.'/*.json') ?: [] as $file) {
        $profile = json_decode((string) file_get_contents($file), true);
        unlink($file);
        if (is_array($profile) && isset($profile['timing']['total_ms'])) {
            $profiles[] = $profile;
        }
    }

    return $profiles;
}

/**
 * @param list<float> $values
 */
function median(array $values): float
{
    sort($values);
    $middle = intdiv(count($values), 2);

    return 0 === count($values) % 2 ? ($values[$middle - 1] + $values[$middle]) / 2 : $values[$middle];
}

/**
 * @param list<float> $values
 */
function p95(array $values): float
{
    sort($values);

    return $values[(int) ceil(0.95 * count($values)) - 1];
}

echo "typo3-outbound performance benchmark\n";
echo "  base URL : {$baseUrl} (Petstore plugin, fixture responses)\n";
echo "  requests : {$count} per scenario after {$warmup} warmup requests\n\n";

$rows = [];
$exitCode = 0;

try {
    foreach ($scenarios as $label => $scenario) {
        $url = $baseUrl.$scenario['path'];
        for ($i = 0; $i < $warmup; ++$i) {
            if ($scenario['flush']) {
                flushResponseCache($typo3Bin);
            }
            fetch($url);
        }
        collectProfiles($profilesDir);

        echo str_pad($label, 26);
        $profiles = [];
        for ($i = 0; $i < $count; ++$i) {
            if ($scenario['flush']) {
                flushResponseCache($typo3Bin);
            }
            fetch($url);
            $profiles = [...$profiles, ...collectProfiles($profilesDir)];
            if (0 === $i % 10) {
                echo '.';
            }
        }
        echo "\n";

        if (count($profiles) !== $count) {
            throw new RuntimeException(sprintf('%s: %d profiles for %d requests. Does the instance run in the Development context?', $label, count($profiles), $count));
        }
        $hits = count(array_filter($profiles, static fn (array $profile): bool => true === ($profile['cache']['hit'] ?? null)));
        if ($hits !== ($scenario['expectHit'] ? $count : 0)) {
            throw new RuntimeException(sprintf('%s: %d of %d requests were page cache hits, the scenario does not measure what it claims.', $label, $hits, $count));
        }
        $timings = array_map(static fn (array $profile): float => (float) $profile['timing']['total_ms'], $profiles);
        $rows[$label] = [
            'median' => median($timings),
            'p95' => p95($timings),
            'queries' => median(array_map(static fn (array $profile): float => (float) ($profile['queries']['count'] ?? 0), $profiles)),
        ];
    }
} catch (RuntimeException $exception) {
    fwrite(\STDERR, "\n".$exception->getMessage()."\n");
    $exitCode = 1;
} finally {
    // The requests were answered from fixtures; keep them out of the cache of the live demo.
    flushResponseCache($typo3Bin);
}

if (0 !== $exitCode) {
    exit($exitCode);
}

printf("\n%-26s %5s %11s %11s %9s\n", 'Scenario', 'n', 'median ms', 'p95 ms', 'queries');
echo str_repeat('-', 66)."\n";
foreach ($rows as $label => $row) {
    printf("%-26s %5d %11.2f %11.2f %9.0f\n", $label, $count, $row['median'], $row['p95'], $row['queries']);
}
echo "\nMedian query count and timing.total_ms from typo3-request-profiler. The timing starts at the profiler\n";
echo "middleware, so bootstrap and middlewares before it are not included; a page cache hit is mostly outside it.\n";
