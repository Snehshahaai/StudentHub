#!/usr/bin/env bash
# StudentHub - integration checks that need no database.
#   bash scripts/verify.sh
# 1. every PHP / JS file parses   2. every local link, script, stylesheet and image exists
# 3. helper files and private folders are blocked   4. protected pages and the API refuse anonymous users
cd "$(dirname "$0")/.." || exit 1
fail=0
ok()  { printf '  \033[32mPASS\033[0m %s\n' "$1"; }
bad() { printf '  \033[31mFAIL\033[0m %s\n' "$1"; fail=1; }

echo "1. Syntax"
for f in $(git ls-files -co --exclude-standard '*.php'); do
    php -l "$f" >/dev/null 2>&1 || bad "$f does not parse"
done
if command -v node >/dev/null; then
    for f in js/*.js; do node --check "$f" 2>/dev/null || bad "$f does not parse"; done
fi
[ $fail -eq 0 ] && ok "all PHP and JS files parse"

echo "2. Local links and assets"
broken=0
for f in index.html pages/*.html pages/*.php php/views/*.php; do
    dir=$(dirname "$f")
    for ref in $(grep -oE '(href|src|action)="[^"#?]+' "$f" | sed -E 's/^[a-z]+="//' | grep -vE '^(https?:|mailto:|tel:|data:|javascript:)|<|\$'); do
        case "$ref" in *'<?'*|*'$'*) continue;; esac
        # pages/ files are relative to pages/; layout partials are used from pages/
        base="$dir"; [ "$dir" = "php/views" ] && base="pages"
        [ -e "$base/$ref" ] || { bad "$f -> $ref not found"; broken=1; }
    done
done
[ $broken -eq 0 ] && ok "every local href / src / action exists"

echo "3 + 4. Server rules (PHP built-in server)"
php -S 127.0.0.1:8799 router.php >/dev/null 2>&1 & pid=$!
sleep 1
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
expect() { [ "$2" = "$3" ] && ok "$1 -> $3" || bad "$1 -> $2 (expected $3)"; }
B=http://127.0.0.1:8799
for p in php/lib.php php/db.php php/events.php php/dashboard.php database/schema.sql php/views/admin-layout.php uploads/events/x.php; do
    expect "$p blocked" "$(code $B/$p)" 403
done
for p in pages/admin-dashboard.php pages/admin-students.php pages/admin-events.php pages/admin-events-live.php pages/student-dashboard.php pages/profile.php pages/faculty-dashboard.php; do
    expect "$p anonymous redirects to login" "$(code $B/$p)" 303
done
for m in GET POST PUT DELETE; do
    expect "events API $m anonymous" "$(code -X $m $B/php/api/events-api.php)" 401
done
expect "me.php anonymous" "$(code $B/php/me.php)" 401
expect "home page" "$(code $B/index.html)" 200
kill $pid 2>/dev/null

echo
[ $fail -eq 0 ] && echo "All checks passed." || echo "Some checks FAILED."
exit $fail
