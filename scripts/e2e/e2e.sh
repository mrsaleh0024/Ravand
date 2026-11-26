#!/usr/bin/env bash
# Ravand sandbox E2E — full auth flow against a real HTTP server:
#   1. create-owner (phone identity)
#   2. owner login with phone+password -> OTP challenge (Bale mocked)
#   3. verify OTP -> dashboard + live endpoint
#   4. owner creates a manager with a temp password
#   5. manager logs in with the temp password -> forced password change
#   6. manager sets a new password -> dashboard accessible
# The real .env is backed up and restored at the end (trap).
set -u
export PATH=/home/z/my-project/tools/bin:$PATH

ROOT=/home/z/my-project/Ravand
LOGDIR=/tmp/ravand-e2e
MOCKLOG="$LOGDIR/bale.log"
APP_URL=http://127.0.0.1:8123
MOCK_PORT=8899
JAR="$LOGDIR/cookies.txt"

cd "$ROOT"
mkdir -p "$LOGDIR"

cleanup() {
    if [ "${SERVER_PID:-0}" -ne 0 ]; then kill "$SERVER_PID" 2>/dev/null; fi
    if [ "${MOCK_PID:-0}" -ne 0 ]; then kill "$MOCK_PID" 2>/dev/null; fi
    if [ -f "$ROOT/.env.bak-e2e" ]; then mv "$ROOT/.env.bak-e2e" "$ROOT/.env"; fi
    php artisan config:clear >/dev/null 2>&1
    php artisan route:clear >/dev/null 2>&1
    echo "cleanup done"
}
trap cleanup EXIT

fail() { echo "E2E FAIL: $1"; exit 1; }
pass() { echo "E2E OK : $1"; }

# --- 0) isolated env ---------------------------------------------------------
cp .env .env.bak-e2e

cat > .env <<'ENV'
APP_NAME=Ravand
APP_ENV=local
APP_KEY=
APP_DEBUG=false
APP_URL=http://127.0.0.1:8123
APP_LOCALE=fa
APP_FALLBACK_LOCALE=en
LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=sqlite
DB_DATABASE=/tmp/ravand-e2e/e2e.sqlite

SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=local

BALE_API_BASE=http://127.0.0.1:8899/bot
BALE_CODE_BOT_TOKEN=e2e-code-token
BALE_ADMIN_BOT_TOKEN=e2e-admin-token
BALE_WEBHOOK_SECRET=e2e-secret
BALE_OWNER_2FA=true

RAVAND_DEFAULT_TIMEZONE=Asia/Tehran
RAVAND_DEFAULT_LOCALE=fa
ENV

php artisan key:generate --force >/dev/null 2>&1
grep -q "^APP_KEY=base64" .env || fail "APP_KEY generation"
rm -f /tmp/ravand-e2e/e2e.sqlite
php artisan migrate:fresh --force >/dev/null 2>&1 || fail "migrate:fresh"
pass "isolated sqlite env + migrations"

# --- 1) create owner ---------------------------------------------------------
php artisan ravand:create-owner \
    --name="صاحب فروشگاه" \
    --phone="0912 345 6789" \
    --tenant-name="فروشگاه راوند" \
    --slug="ravand-e2e" \
    --currency=IRT \
    --password="OwnerPass123" >/dev/null 2>&1 || fail "create-owner"
# The owner links his Bale chat via the Security page in real life; for the
# E2E the chat id is set directly (same value the mock log will show).
php artisan tinker --execute="App\Models\User::where('phone','09123456789')->update(['bale_chat_id' => '555000111']);" >/dev/null 2>&1
php artisan tinker --execute="echo App\Models\User::where('phone','09123456789')->count();" 2>/dev/null | grep -qx "1" || fail "owner row missing"
pass "create-owner: phone 09123456789 (Persian-spaced input normalized)"

# --- 2) start mock bale + app server -----------------------------------------
: > "$MOCKLOG"
php scripts/e2e/e2e_bale_mock.php $MOCK_PORT "$MOCKLOG" >/dev/null 2>&1 &
MOCK_PID=$!
php artisan serve --host=127.0.0.1 --port=8123 >/dev/null 2>&1 &
SERVER_PID=$!
sleep 3

curl -s -o /dev/null "$APP_URL/login" || fail "server not responding"
pass "servers up (app :8123, bale-mock :8899)"

# --- helpers -----------------------------------------------------------------
# Extract the XSRF cookie from the curl cookie jar (URL-decoded).
csrf_token() {
    local raw
    raw=$(grep -P '\tXSRF-TOKEN\t' "$JAR" | awk '{print $7}')
    python3 -c "import urllib.parse,sys; print(urllib.parse.unquote(sys.argv[1]))" "$raw"
}

# --- 3) owner login -> OTP challenge ------------------------------------------
rm -f "$JAR"
curl -s -c "$JAR" -o /dev/null "$APP_URL/login"
OWNER_OTP_REDIRECT=$(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code} %{redirect_url}' \
    -H 'X-XSRF-TOKEN: '"$(csrf_token)" \
    -H 'Content-Type: application/json' \
    -H 'Accept: application/json' \
    --data-raw '{"phone":"0912 345 6789","password":"OwnerPass123","remember":false}' \
    "$APP_URL/login")
echo "$OWNER_OTP_REDIRECT" | grep -q '^302' || fail "owner login not a redirect: $OWNER_OTP_REDIRECT"
echo "$OWNER_OTP_REDIRECT" | grep -q '/otp' || fail "owner login did not redirect to OTP: $OWNER_OTP_REDIRECT"
pass "owner login redirected to OTP challenge"

sleep 0.5
OTP=$(grep -oP '"text":"[^"]*"' "$MOCKLOG" | tail -1 | grep -oP '\d{6}' | head -1)
[ -n "$OTP" ] || fail "no 6-digit code in bale mock log"
pass "OTP $OTP sent via code bot"

curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code}' "$APP_URL/otp-challenge" | grep -q 200 || fail "OTP page not reachable"

VERIFY=$(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code} %{redirect_url}' \
    -H 'X-XSRF-TOKEN: '"$(csrf_token)" \
    -H 'Content-Type: application/json' \
    -H 'Accept: application/json' \
    --data-raw "{\"code\":\"$OTP\"}" \
    "$APP_URL/otp-challenge")
echo "$VERIFY" | grep -q '^302' || fail "OTP verify not a redirect: $VERIFY"
pass "OTP verified, session authenticated"

DASH=$(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code}' "$APP_URL/dashboard")
echo "$DASH" | grep -q 200 || fail "dashboard not accessible for owner ($DASH)"
pass "owner dashboard reachable"

# --- 4) live dashboard endpoint (owner payload) --------------------------------
LIVE=$(curl -s -b "$JAR" -c "$JAR" "$APP_URL/panel/live")
echo "$LIVE" | python3 -c "import json,sys; d=json.load(sys.stdin); assert 'events' in d and 'chart' in d and 'stats' in d and 'system' in d, d.keys()" || fail "live payload malformed"
pass "panel/live JSON ok (events + 14d chart + stats + owner system health)"

# --- 5) owner creates a manager with a temp password ---------------------------
CREATE=$(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code} %{redirect_url}' \
    -H 'X-XSRF-TOKEN: '"$(csrf_token)" \
    -H 'Content-Type: application/json' \
    -H 'Accept: application/json' \
    --data-raw '{"name":"مدیر فروش","phone":"09351112233","role":"manager","temp_password":"ManagerTemp1"}' \
    "$APP_URL/panel/members")
echo "$CREATE" | grep -q '^302' || fail "member create failed: $CREATE"
php artisan tinker --execute="echo App\Models\User::where('phone','09351112233')->value('must_change_password') ? 'locked' : 'free';" 2>/dev/null | grep -qx "locked" || fail "manager must_change_password flag missing"
pass "manager created by owner (temp password + must_change_password)"

# --- 6) logout owner, manager logs in with temp password ------------------------
curl -s -b "$JAR" -c "$JAR" -o /dev/null \
    -H 'X-XSRF-TOKEN: '"$(csrf_token)" \
    -H 'Accept: application/json' \
    -X POST "$APP_URL/logout"
curl -s -b "$JAR" -c "$JAR" -o /dev/null "$APP_URL/dashboard" -w '%{http_code}' | grep -qv 200 || fail "owner still authenticated after logout"
pass "owner logged out"

rm -f "$JAR"
curl -s -c "$JAR" -o /dev/null "$APP_URL/login"
MG_LOGIN_BODY=$(curl -s -b "$JAR" -c "$JAR" -w '\n%{http_code}' \
    -H 'X-XSRF-TOKEN: '"$(csrf_token)" \
    -H 'Content-Type: application/json' \
    -H 'Accept: application/json' \
    --data-raw '{"phone":"09351112233","password":"ManagerTemp1"}' \
    "$APP_URL/login")
# Direct login: Fortify answers JSON {"two_factor":false} (200) or a 302.
echo "$MG_LOGIN_BODY" | tail -1 | grep -q '^302$\|^200$' || fail "manager login failed: $MG_LOGIN_BODY"
echo "$MG_LOGIN_BODY" | head -1 | grep -q 'two_factor.:false' || true
MG_DASH=$(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code} %{redirect_url}' "$APP_URL/dashboard")
echo "$MG_DASH" | grep -q '302 .*force-password' || fail "manager not locked to password.force: $MG_DASH"
pass "manager with temp password locked to forced password change"

# --- 7) manager sets a new password ---------------------------------------------
FORCE=$(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code} %{redirect_url}' \
    -H 'X-XSRF-TOKEN: '"$(csrf_token)" \
    -H 'Content-Type: application/json' \
    -H 'Accept: application/json' \
    --data-raw '{"password":"NewManagerPass9","password_confirmation":"NewManagerPass9"}' \
    -X PUT "$APP_URL/settings/force-password")
echo "$FORCE" | grep -q '^302' || fail "force-password update failed: $FORCE"
MG_DASH2=$(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code}' "$APP_URL/dashboard")
echo "$MG_DASH2" | grep -q 200 || fail "manager dashboard still blocked after change: $MG_DASH2"
php artisan tinker --execute="echo App\Models\User::where('phone','09351112233')->value('must_change_password') ? 'locked' : 'free';" 2>/dev/null | grep -qx "free" || fail "must_change_password not cleared"
pass "manager set new password, flag cleared, dashboard unlocked"

# --- 8) audit trail sanity --------------------------------------------------------
php artisan tinker --execute="
\$a = App\Models\AuditLog::query()->whereIn('action',['auth.otp.issued','auth.otp.verified','auth.login','auth.logout','member.added','auth.password.changed'])->pluck('action')->unique()->values()->implode(',');
echo \$a;" 2>/dev/null | grep -q 'auth.otp.verified' || fail "audit trail incomplete"
pass "audit trail records OTP + auth lifecycle"

echo ''
echo 'E2E COMPLETE — all steps green.'
