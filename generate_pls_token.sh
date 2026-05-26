#!/bin/bash
set -euo pipefail

# Load .env from project root when run from repo
ROOT="$(cd "$(dirname "$0")" && pwd)"
if [ -f "$ROOT/.env" ]; then
  set -a
  # shellcheck disable=SC1091
  source "$ROOT/.env"
  set +a
fi

SHOPIFY_SHOP="${PROLIGHTSOUNDS_SHOPIFY_DOMAIN:-5core-wholesale.myshopify.com}"
SHOPIFY_SHOP="${SHOPIFY_SHOP#https://}"
SHOPIFY_SHOP="${SHOPIFY_SHOP#http://}"
SHOPIFY_SHOP="${SHOPIFY_SHOP%%/*}"
SHOPIFY_CLIENT_ID="${PROLIGHTSOUNDS_SHOPIFY_CLIENT_ID:-${PROLIGHTSOUNDS_SHOPIFY_API_KEY:-}}"
SHOPIFY_CLIENT_SECRET="${PROLIGHTSOUNDS_SHOPIFY_CLIENT_SECRET:-}"

if [ -z "$SHOPIFY_CLIENT_ID" ] || [ -z "$SHOPIFY_CLIENT_SECRET" ]; then
  echo "Missing PROLIGHTSOUNDS_SHOPIFY_CLIENT_ID / PROLIGHTSOUNDS_SHOPIFY_CLIENT_SECRET in .env"
  exit 1
fi

echo "Requesting access token from ${SHOPIFY_SHOP}..."

TOKEN_RESPONSE=$(curl -s -X POST "https://${SHOPIFY_SHOP}/admin/oauth/access_token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=client_credentials" \
  -d "client_id=${SHOPIFY_CLIENT_ID}" \
  -d "client_secret=${SHOPIFY_CLIENT_SECRET}")

ACCESS_TOKEN=$(echo "$TOKEN_RESPONSE" | php -r 'echo json_decode(stream_get_contents(STDIN), true)["access_token"] ?? "";')

if [ -z "$ACCESS_TOKEN" ]; then
  echo "Failed to get token: $TOKEN_RESPONSE"
  exit 1
fi

echo ""
echo "Success! Access token generated (expires in ~24h):"
echo "$ACCESS_TOKEN"
echo ""
echo "Optional: update .env (auto-refresh is preferred):"
echo "PROLIGHTSOUNDS_SHOPIFY_PASSWORD=$ACCESS_TOKEN"
echo "PROLIGHTSOUNDS_SHOPIFY_ACCESS_TOKEN=$ACCESS_TOKEN"
