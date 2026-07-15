<?php
$s = file_get_contents(__DIR__.'/ssh-start-sku-sync-check.php');
$marker = "\$worker = <<<'PHP'\n";
$a = strpos($s, $marker);
if ($a === false) {
    $marker = "\$worker = <<<'PHP'\r\n";
    $a = strpos($s, $marker);
}
if ($a === false) {
    fwrite(STDERR, "marker missing\n");
    exit(1);
}
$a += strlen($marker);
$b = strpos($s, "\nPHP;", $a);
if ($b === false) {
    $b = strpos($s, "\r\nPHP;", $a);
}
$w = substr($s, $a, $b - $a);
$w = str_replace("\r\n", "\n", $w);
file_put_contents(__DIR__.'/mm-run-sku-check.worker.php', $w);
echo "wrote ".strlen($w)." bytes\n";
