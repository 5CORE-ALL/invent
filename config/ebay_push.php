<?php

return [
    /*
     | eBay 1/2/3 page-driven price pushes (push-ebay-price-tabulator & co.) are accepted only when the
     | price equals the server's Std-vs-Dil / Sprc Dil rule price. Set EBAY_RULE_ONLY_PUSH=false to disable.
     */
    'rule_only' => env('EBAY_RULE_ONLY_PUSH', true),
];
