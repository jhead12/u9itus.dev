#!/bin/bash
# Quick API Testing Script for U9itus
# Usage: ./test-api.sh [local|production]

ENV=${1:-local}

if [ "$ENV" = "production" ]; then
    BASE_URL="https://your-domain.com"
    echo "🚀 Testing PRODUCTION API: $BASE_URL"
else
    BASE_URL="http://localhost:8000"
    echo "🧪 Testing LOCAL API: $BASE_URL"
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local description=$4
    
    echo -e "${YELLOW}Testing:${NC} $description"
    echo "  → $method $endpoint"
    
    if [ -z "$data" ]; then
        response=$(curl -s -w "\n%{http_code}" -X $method "$BASE_URL$endpoint" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json")
    else
        response=$(curl -s -w "\n%{http_code}" -X $method "$BASE_URL$endpoint" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -d "$data")
    fi
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
        echo -e "  ${GREEN}✓ Success${NC} (HTTP $http_code)"
        echo "$body" | jq '.' 2>/dev/null || echo "$body" | head -c 200
    else
        echo -e "  ${RED}✗ Failed${NC} (HTTP $http_code)"
        echo "$body" | jq '.' 2>/dev/null || echo "$body"
    fi
    echo ""
}

echo "═══════════════════════════════════════════"
echo "  1. HEALTH CHECKS"
echo "═══════════════════════════════════════════"
echo ""

test_endpoint "GET" "/api/health" "" "API Health Check"

echo "═══════════════════════════════════════════"
echo "  2. POLITICIAN ENDPOINTS"
echo "═══════════════════════════════════════════"
echo ""

POLITICIAN_DATA='{
    "wix_member_id": "test-member-'$(date +%s)'",
    "full_name": "Senator Jane Doe",
    "political_office": "State Senator",
    "governance_level": "state",
    "state": "CA",
    "city": "Los Angeles",
    "email": "jane.doe'$(date +%s)'@example.com",
    "bio": "Experienced state senator focused on education reform."
}'

test_endpoint "POST" "/api/politicians" "$POLITICIAN_DATA" "Create Politician Profile"

echo "═══════════════════════════════════════════"
echo "  3. VOTER ENDPOINTS"
echo "═══════════════════════════════════════════"
echo ""

VOTER_DATA='{
    "full_name": "John Smith",
    "email": "john.smith'$(date +%s)'@example.com",
    "state": "CA",
    "city": "San Francisco",
    "zip_code": "94102",
    "payment_method": "paypal",
    "paypal_email": "john.paypal@example.com"
}'

test_endpoint "POST" "/api/voters" "$VOTER_DATA" "Register Voter"

echo "═══════════════════════════════════════════"
echo "  4. CAMPAIGN ENDPOINTS"
echo "═══════════════════════════════════════════"
echo ""

# Note: This will fail without valid politician UUID
# Just testing the endpoint is reachable
test_endpoint "GET" "/api/campaigns?status=active&limit=5" "" "List Active Campaigns"

echo "═══════════════════════════════════════════"
echo "  5. WIX OAUTH ENDPOINTS"
echo "═══════════════════════════════════════════"
echo ""

test_endpoint "GET" "/wix/oauth/install?token=test-token" "" "Wix OAuth Install Redirect (should redirect)"

echo "═══════════════════════════════════════════"
echo "  6. WIX WEBHOOK ENDPOINTS"
echo "═══════════════════════════════════════════"
echo ""

WEBHOOK_DATA='{
    "eventType": "app.installed",
    "instanceId": "test-instance-'$(date +%s)'",
    "data": {
        "siteUrl": "https://test-site.wixsite.com/mysite"
    }
}'

test_endpoint "POST" "/api/wix/webhooks" "$WEBHOOK_DATA" "Wix Webhook Handler (will fail signature check)"

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "✅ API Testing Complete!"
echo ""
echo "📝 Notes:"
echo "  - Some endpoints require authentication (will show 401)"
echo "  - UUID-based endpoints need valid UUIDs from database"
echo "  - Wix webhook signature verification will fail in test mode"
echo ""
echo "🔍 Next Steps:"
echo "  1. Check Laravel logs: tail -f storage/logs/laravel.log"
echo "  2. Use Postman collection for authenticated requests"
echo "  3. Test Wix integration on actual Wix test site"
echo ""
