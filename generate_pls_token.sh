#!/bin/bash

SHOPIFY_SHOP="5core-wholesale"
SHOPIFY_CLIENT_ID="beb835d5966561d9952e9a12b72b7633"
SHOPIFY_CLIENT_SECRET="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"

echo "Requesting access token from ${SHOPIFY_SHOP}.myshopify.com..."

TOKEN_RESPONSE=$(curl -s -X POST "https://${SHOPIFY_SHOP}.myshopify.com/admin/oauth/access_token" -H "Content-Type: application/x-www-form-urlencoded" -d "grant_type=client_credentials" -d "client_id=${SHOPIFY_CLIENT_ID}" -d "client_secret=${SHOPIFY_CLIENT_SECRET}")

ACCESS_TOKEN=$(echo "$TOKEN_RESPONSE" | jq -r '.access_token')

if [ "$ACCESS_TOKEN" = "null" ] || [ -z "$ACCESS_TOKEN" ]; then
  echo "Failed to get token: $TOKEN_RESPONSE"
  exit 1
fi

echo ""
echo "Success! Access token generated:"
echo "$ACCESS_TOKEN"
echo ""
echo "Update your .env file line 198 with:"
echo "PROLIGHTSOUNDS_SHOPIFY_ACCESS_TOKEN=$ACCESS_TOKEN"
