<?php
$norm = fn($s)=>strtoupper(trim((string)$s));
$sc = app(App\Http\Controllers\MarketPlace\SheinController::class);
$sresp = $sc->getSheinPricingData(Illuminate\Http\Request::create("/shein-pricing-data","GET"));
$sd = json_decode($sresp->getContent(), true);
$srows = $sd["data"] ?? [];
$sheinMissing = [];
foreach ($srows as $r) {
    if (!empty($r["is_parent"])) continue;
    $inv = (float)($r["inv"] ?? 0);
    $miss = !empty($r["is_missing_shein"]);
    $price = (float)($r["special_offer"] ?? 0);
    if ($inv > 0 && ($miss || $price <= 0)) { $sheinMissing[$norm($r["sku"])] = true; }
}
echo "shein-pricing Missing L = ".count($sheinMissing).PHP_EOL;

$mc = app(App\Http\Controllers\MapIssuesController::class);
$mresp = $mc->data(Illuminate\Http\Request::create("/map-issues/data","GET"));
$md = json_decode($mresp->getContent(), true);
echo "map-issues SH NL = ".($md["shein_missing_listing_count"] ?? "n/a").PHP_EOL;
$miMissing = [];
foreach (($md["data"] ?? []) as $r) {
    if (!empty($r["pm_missing"])) continue;
    if (!empty($r["shein_missing_listing"])) { $miMissing[$norm($r["(Child) sku"])] = true; }
}
echo "map-issues recount = ".count($miMissing).PHP_EOL;

$onlyShein = array_diff_key($sheinMissing, $miMissing);
$onlyMi = array_diff_key($miMissing, $sheinMissing);
echo "only on shein-pricing (".count($onlyShein)."): ".implode(", ", array_slice(array_keys($onlyShein),0,20)).PHP_EOL;
echo "only on map-issues (".count($onlyMi)."): ".implode(", ", array_slice(array_keys($onlyMi),0,20)).PHP_EOL;
