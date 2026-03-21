# AliExpress IOP `/rest` API — Signature & Working Examples

## Security

Never commit or paste **app_secret** or **session/access_token** in tickets or chat. If exposed, rotate credentials in the AliExpress developer console.

## Why `IncompleteSignature` often happens

### 1. Wrong HTTP layout (very common)

The official **IOP PHP SDK** does **not** send all parameters as `application/x-www-form-urlencoded` in the POST body.

It does:

1. Puts **system parameters + `sign`** on the **URL query string**:  
   `https://api-sg.aliexpress.com/rest?app_key=...&format=json&method=...&partner_id=...&session=...&sign_method=sha256&simplify=true&timestamp=...&sign=...`
2. Sends **API/business parameters only** (e.g. `product_list_get_request`) as **`multipart/form-data`** in the POST body.

If you POST everything as urlencoded in the body, the gateway may still validate the signature against a different canonicalization → **`IncompleteSignature`**.

This project defaults to **`ALIEXPRESS_TRANSPORT=iop`** (query + multipart). Use **`ALIEXPRESS_TRANSPORT=form`** only if your doc explicitly requires a single form body.

### 2. Signature string (method names without `/`)

The official **IOP PHP SDK** (`IopClient::generateSign`) works like this:

1. Merge **all** parameters that will be sent (system + business), **except** `sign`.
2. `ksort($params)` — sort by **key** (ASCII).
3. Build string:  
   - **Only if** the API “name” passed to the signer **contains `/`**, prepend that path (e.g. `/auth/token/create`).  
   - For normal method names like `aliexpress.solution.product.list.get` there is **no `/`**, so the prefix is **empty** — **do not** prepend `/rest`.
4. Concatenate: `key1` + `value1` + `key2` + `value2` + … (no `&`, no `=`, no URL encoding in the string).
5. `sign = strtoupper(hash_hmac('sha256', $stringToSign, $app_secret))`.

So: **`/rest` is the HTTP path only, not part of the signature string** for solution methods.

## Is `session` / token included in the signature?

**Yes.** Everything you send that is merged **before** `sign` is appended participates in the HMAC string (except the `sign` key itself). That includes **`session`** (or `access_token` if you use that key instead).

## Required system parameters (IOP-style)

The SDK’s `execute()` adds (all participate in signing):

| Parameter       | Typical value |
|----------------|---------------|
| `app_key`      | Your app key |
| `method`       | e.g. `aliexpress.solution.product.list.get` |
| `timestamp`    | Milliseconds (integer) |
| `sign_method`  | `sha256` |
| `format`       | `json` |
| `simplify`     | `true` (string in form body) |
| `partner_id`   | SDK id, e.g. `iop-sdk-php` |
| `session`      | **Seller OAuth token** (IOP uses this name, not `access_token`) |

Then add business params, e.g. `product_list_get_request` = JSON string.

**Token name:** Official IOP uses **`session`**. If your integration expects `access_token`, set `ALIEXPRESS_TOKEN_PARAM=access_token` in `.env` (see `config/services.php`).

## IP whitelist vs signature

| Situation | Typical response |
|-----------|------------------|
| **IP not allowed** | Often a distinct business/code (e.g. access denied / IP / gateway rejection). Wording varies by product; it is **not** reliably the same JSON as signature errors. |
| **Bad signature** | `"code":"IncompleteSignature"` or `"type":"ISV"` + message about signature / platform standards. |

**Confirm outbound IP:** from the server run `curl -s https://api.ipify.org` (or check hosting panel). Add that IP in **AliExpress Open Platform → app → IP whitelist** if your app uses whitelisting.

If you see **`IncompleteSignature` on the server** after fixing transport, it is **almost always** signing input mismatch (param names, missing `format`/`simplify`/`partner_id`, wrong token key, or JSON body not byte-identical to what was signed).

## Timestamp: IOP `msectime()` vs real milliseconds

The reference SDK uses `time() . '000'` (not full millisecond precision). Set `ALIEXPRESS_TIMESTAMP_STYLE=iop` (default) to match. If the API rejects it, try `ALIEXPRESS_TIMESTAMP_STYLE=ms`.

## Working PHP (copy-paste)

```php
<?php

$appKey = 'YOUR_APP_KEY';
$appSecret = 'YOUR_APP_SECRET';
$session = 'YOUR_OAUTH_TOKEN'; // same value you might store as "access_token"

$method = 'aliexpress.solution.product.list.get';

$productListGetRequest = json_encode([
    'current_page' => 1,
    'page_size' => 5,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$params = [
    'app_key' => $appKey,
    'format' => 'json',
    'method' => $method,
    'partner_id' => 'iop-sdk-php',
    'product_list_get_request' => $productListGetRequest,
    'session' => $session,
    'sign_method' => 'sha256',
    'simplify' => 'true',
    'timestamp' => (int) round(microtime(true) * 1000),
];

ksort($params);
$stringToSign = '';
foreach ($params as $k => $v) {
    $stringToSign .= $k . $v;
}
$params['sign'] = strtoupper(hash_hmac('sha256', $stringToSign, $appSecret));

$ch = curl_init('https://api-sg.aliexpress.com/rest');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($params),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);
$body = curl_exec($ch);
curl_close($ch);

echo $body;
```

## Working cURL (after computing `SIGN` in PHP)

Replace placeholders; **do not** hand-edit the sign — always compute in code.

```bash
curl -sS -X POST 'https://api-sg.aliexpress.com/rest' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode "app_key=YOUR_APP_KEY" \
  --data-urlencode "format=json" \
  --data-urlencode "method=aliexpress.solution.product.list.get" \
  --data-urlencode "partner_id=iop-sdk-php" \
  --data-urlencode 'product_list_get_request={"current_page":1,"page_size":5}' \
  --data-urlencode "session=YOUR_SESSION_TOKEN" \
  --data-urlencode "sign_method=sha256" \
  --data-urlencode "simplify=true" \
  --data-urlencode "timestamp=1730000000000" \
  --data-urlencode "sign=COMPUTED_UPPERCASE_HEX_HMAC_SHA256"
```

## `aliexpress.solution.product.edit`

Same pattern; only `method` and business body change:

```php
$method = 'aliexpress.solution.product.edit';
$editProductRequest = json_encode([
    'product_id' => '1000005237852',
    'multi_language_subject_list' => [
        ['subject' => 'New title', 'language' => 'en'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$params = [
    'app_key' => $appKey,
    'edit_product_request' => $editProductRequest,
    'format' => 'json',
    'method' => $method,
    'partner_id' => 'iop-sdk-php',
    'session' => $session,
    'sign_method' => 'sha256',
    'simplify' => 'true',
    'timestamp' => (int) round(microtime(true) * 1000),
];
// ksort + concat + sign as above
```

## References

- IOP SDK: `generateSign($apiName, $params)` — prefix only when `$apiName` contains `/`.
- Project implementation: `App\Services\AliExpressApiService` (uses `config('services.aliexpress.*')`).
