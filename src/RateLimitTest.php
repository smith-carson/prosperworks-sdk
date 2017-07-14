<?php
require "RateLimit.php";
use ProsperWorks\RateLimit;

echo "---=== Starting Rate Limit class test ===---\n";

$requests = $argv[1] ?? 500;
$limits = array_map(function ($a) { return explode('/', $a); }, explode(',', $argv[2] ?? ''));
$limits = array_column($limits, 1, 0)?: RateLimit::DEFAULT_LIMITS;
echo "       total requests: {$requests} reqs\n";
foreach ($limits as $frame => $reqs) echo "       allowed: $reqs requests / $frame secs\n";
echo "      (args: <total: int> <limits: '100/6,250/36'>)\n\n";

$limit = RateLimit::do($limits);
$frames = array_keys(RateLimit::do()->getLimits());

$simulateRequest = function() use ($limit) {
    //if we get the maximum all the times, it will hit exactly 100 requests / 6 secs
    $usec = rand(100, 6 * 1000000 / 100);
    usleep($usec);
    return $usec;
};

$start = time();
for ($i = 1; $i <= $requests; $i++) {
    if ($limit->willBeRateLimited()) {
        echo "ERROR: Rate limited!\n";
    }

    $slept = str_pad($simulateRequest(), 5, 0, STR_PAD_LEFT);
    $limit->pushRequest();

    $took = time() - $start;
    echo "'Request' took {$slept}μs || limits: ";
    foreach ($frames as $frame) {
        echo "{$limit->totalRequests($frame)} in {$frame}s; ";
    }
    echo "(of $i in {$took}s)\n";
}

echo "\n---=== TEST FINISHED ===---\n";