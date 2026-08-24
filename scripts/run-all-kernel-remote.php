<?php
$root = '/var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com';
chdir($root);

$lines = file($root . '/app/Console/Kernel.php');
$buf = '';
foreach ($lines as $line) {
    $s = ltrim($line);
    if (str_starts_with($s, '//') || str_starts_with($s, '*') || str_starts_with($s, '/*')) {
        continue;
    }
    $buf .= $line;
}

preg_match_all("/->command\(\s*'([^']+)'/", $buf, $m);
$cmds = array_values(array_unique($m[1]));
$skip = ['users:auto-logout'];
$log = $root . '/storage/logs/kernel-run-all-' . date('Ymd-His') . '.log';

$toRun = array_values(array_filter($cmds, static fn ($c) => !in_array($c, $skip, true)));
$total = count($toRun);

echo "log={$log} count={$total}" . PHP_EOL;
file_put_contents($log, 'start ' . date('c') . ' count=' . $total . PHP_EOL);

foreach ($toRun as $i => $cmd) {
    $n = $i + 1;
    echo "[{$n}/{$total}] {$cmd}" . PHP_EOL;
    file_put_contents($log, "===== [{$n}/{$total}] START {$cmd} " . date('c') . ' =====' . PHP_EOL, FILE_APPEND);
    passthru('/usr/bin/php artisan ' . $cmd . ' >> ' . escapeshellarg($log) . ' 2>&1', $ec);
    file_put_contents($log, "===== [{$n}/{$total}] END {$cmd} exit={$ec} " . date('c') . ' =====' . PHP_EOL, FILE_APPEND);
}

echo 'DONE ' . date('c') . PHP_EOL;
file_put_contents($log, 'DONE ' . date('c') . PHP_EOL, FILE_APPEND);
