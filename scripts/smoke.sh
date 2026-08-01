#!/usr/bin/env bash
# Smoke test — LawFirm ERP API (v1.0 RC). قابل للتشغيل على أي بيئة (محليّة أو Render).
# الاستخدام:
#   scripts/smoke.sh <BASE_URL> <ADMIN_EMAIL> <ADMIN_PASS> [USER_EMAIL] [USER_PASS]
# مثال Render:
#   scripts/smoke.sh https://law-api-xxx.onrender.com owner@firm.com 'PASS' emp@firm.com 'PASS'
# يطبع PASS/FAIL لكل نقطة، ويُنهي برمز غير صفري عند أي فشل.
set -u

BASE="${1:?BASE_URL required}"; BASE="${BASE%/}"
ADMIN_EMAIL="${2:?admin email required}"; ADMIN_PASS="${3:?admin password required}"
USER_EMAIL="${4:-}"; USER_PASS="${5:-}"

pass=0; fail=0
ok(){ printf '  \033[32mPASS\033[0m  %s\n' "$1"; pass=$((pass+1)); }
no(){ printf '  \033[31mFAIL\033[0m  %s\n' "$1"; fail=$((fail+1)); }
jqget(){ python3 -c "import sys,json;d=json.load(sys.stdin);print(eval('d'+sys.argv[1]))" "$1" 2>/dev/null; }

# status_of METHOD PATH [TOKEN] [BODY]
status_of(){ local m="$1" p="$2" t="${3:-}" b="${4:-}"; local h=(-s -o /dev/null -w '%{http_code}' -X "$m" "$BASE$p" -H 'Accept: application/json'); [ -n "$t" ]&&h+=(-H "Authorization: Bearer $t"); [ -n "$b" ]&&h+=(-H 'Content-Type: application/json' -d "$b"); curl "${h[@]}"; }
body_of(){ local m="$1" p="$2" t="${3:-}" b="${4:-}"; local h=(-s -X "$m" "$BASE$p" -H 'Accept: application/json'); [ -n "$t" ]&&h+=(-H "Authorization: Bearer $t"); [ -n "$b" ]&&h+=(-H 'Content-Type: application/json' -d "$b"); curl "${h[@]}"; }

echo "== Smoke test against $BASE =="

# 1) Health check
c=$(status_of GET /up); [ "$c" = 200 ]&&ok "health /up = 200" || no "health /up = $c"

# 2) Authentication — login
login=$(body_of POST /api/auth/login "" "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASS\"}")
ATOK=$(printf '%s' "$login" | jqget "['data']['tokens']['access_token']")
RTOK=$(printf '%s' "$login" | jqget "['data']['tokens']['refresh_token']")
[ -n "${ATOK:-}" ]&&[ "$ATOK" != None ]&&ok "admin login → access token" || { no "admin login failed"; ATOK=""; }

# 3) DB connection (implied) — /auth/me returns the user from DB
if [ -n "$ATOK" ]; then
  me=$(body_of GET /api/auth/me "$ATOK"); email=$(printf '%s' "$me" | jqget "['data']['user']['email']")
  [ "$email" = "$ADMIN_EMAIL" ]&&ok "DB/auth: /auth/me returns user" || no "/auth/me mismatch ($email)"
fi

# 4) Refresh token — rotation: server revokes the old pair and issues a new one.
# A real client adopts the rotated access token; do the same so later checks use a valid token.
if [ -n "${RTOK:-}" ] && [ "$RTOK" != None ]; then
  ref=$(body_of POST /api/auth/refresh "" "{\"refresh_token\":\"$RTOK\"}")
  n=$(printf '%s' "$ref" | jqget "['data']['tokens']['access_token']")
  if [ -n "${n:-}" ] && [ "$n" != None ]; then ok "refresh token → new access token (rotation)"; ATOK="$n"; else no "refresh token failed"; fi
fi

# 5) Authorization (positive) — admin reaches roles.manage endpoint
[ -n "$ATOK" ] && { c=$(status_of GET /api/roles "$ATOK"); [ "$c" = 200 ]&&ok "authz: admin GET /roles = 200" || no "admin GET /roles = $c"; }

# 6) Protected route WITHOUT token → 401
c=$(status_of GET /api/users); [ "$c" = 401 ]&&ok "protected /users without token = 401" || no "protected /users = $c (expected 401)"

# 7) Error envelope — 404 returns {errors:{code}} not bare message / no leak
if [ -n "$ATOK" ]; then
  nf=$(body_of GET /api/users/99999999 "$ATOK"); code=$(printf '%s' "$nf" | jqget "['errors']['code']")
  [ -n "${code:-}" ]&&[ "$code" != None ]&&ok "error envelope on 404 (code=$code)" || no "404 not enveloped"
  printf '%s' "$nf" | grep -qiE '"trace"|vendor/laravel|#[0-9]+ /' && no "SECURITY: stack trace leaked (APP_DEBUG?)" || ok "no stack-trace leak on error"
fi

# 8) CORS header present
co=$(curl -s -o /dev/null -D - -X GET "$BASE/api/health" -H 'Origin: https://example.test' 2>/dev/null | grep -i 'access-control-allow-origin' || true)
[ -n "$co" ]&&ok "CORS header present ($(echo "$co"|tr -d '\r'))" || no "no CORS header"

# 9) Security — non-admin cannot reach admin APIs (needs a non-admin credential)
if [ -n "$USER_EMAIL" ]; then
  ul=$(body_of POST /api/auth/login "" "{\"email\":\"$USER_EMAIL\",\"password\":\"$USER_PASS\"}")
  UTOK=$(printf '%s' "$ul" | jqget "['data']['tokens']['access_token']")
  if [ -n "${UTOK:-}" ]&&[ "$UTOK" != None ]; then
    ok "non-admin login ok"
    c=$(status_of GET /api/admin/summary "$UTOK"); [ "$c" = 403 ]&&ok "authz: non-admin GET /admin/summary = 403" || no "non-admin /admin/summary = $c (expected 403)"
    c=$(status_of GET /api/roles "$UTOK");        [ "$c" = 403 ]&&ok "authz: non-admin GET /roles = 403" || no "non-admin /roles = $c (expected 403)"
    c=$(status_of GET /api/users "$UTOK");         [ "$c" = 403 ]&&ok "authz: non-admin GET /users = 403" || no "non-admin /users = $c (expected 403)"
  else no "non-admin login failed"; fi
else
  printf '  \033[33mSKIP\033[0m  non-admin authz checks (no USER_EMAIL given)\n'
fi

# 10) Performance — API latency of /auth/me
if [ -n "$ATOK" ]; then
  t=$(curl -s -o /dev/null -w '%{time_total}' -X GET "$BASE/api/auth/me" -H "Authorization: Bearer $ATOK" -H 'Accept: application/json')
  ms=$(python3 -c "print(round(float('$t')*1000))"); echo "  INFO  /auth/me latency: ${ms} ms"
fi

echo "== $pass passed, $fail failed =="
[ "$fail" -eq 0 ]
