#!/usr/bin/env bash
# Wavelog 基线冒烟测试（T0）
# 用法: bash tests/smoke.sh
# 依赖: curl / netstat / MariaDB 客户端（路径写死，见 DB_CLI）
# 不改任何代码，只做只读探测 + 临时 QSO 写入后清理。

set -u

BASE="${BASE:-http://127.0.0.1:8080}"
USER_NAME="${WL_USER:-admin}"
USER_PASS="${WL_PASS:-Wavelog!2026}"
DB_CLI="/c/local/mariadb/mariadb-11.4.8-winx64/bin/mariadb.exe"
DB_ARGS=(-u wavelog -pwavelog_pass -h 127.0.0.1 wavelog -N -B)
CJ="/tmp/wl_cj.txt"
rm -f "$CJ"
PASS=0; FAIL=0

ok()   { PASS=$((PASS+1)); echo "PASS  $1"; }
bad()  { FAIL=$((FAIL+1)); echo "FAIL  $1  -> $2"; }

echo "== T0 Wavelog baseline smoke =="
echo "BASE=$BASE"

# 1. 服务端口
for p in 8080 3306; do
  if netstat -ano 2>/dev/null | grep -q ":$p .*LISTENING"; then ok "port $p listening"; else bad "port $p listening" "not listening"; fi
done

# 2. 登录页
code=$(curl -sS -m 20 -c "$CJ" -b "$CJ" -o /tmp/wl_login.html -w '%{http_code}' "$BASE/index.php/user/login")
if [ "$code" = "200" ] && grep -qi "Login" /tmp/wl_login.html; then ok "login page 200"; else bad "login page 200" "http=$code"; fi

# 3. 登录
code=$(curl -sS -m 20 -c "$CJ" -b "$CJ" -o /dev/null -w '%{http_code}' \
  -d "user_name=$USER_NAME&user_password=$USER_PASS&id=" "$BASE/index.php/user/login")
if [ "$code" = "303" ]; then ok "login POST 303"; else bad "login POST 303" "http=$code"; fi

# 4. Dashboard
code=$(curl -sS -m 30 -b "$CJ" -o /tmp/wl_dash.html -w '%{http_code}' "$BASE/index.php/dashboard")
if [ "$code" = "200" ] && grep -qi "Dashboard" /tmp/wl_dash.html; then ok "dashboard 200"; else bad "dashboard 200" "http=$code"; fi

# 5. 数据库连通 + 表数量
tables=$("$DB_CLI" "${DB_ARGS[@]}" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='wavelog';" 2>/dev/null | tr -d '\r')
if [ "${tables:-0}" -ge 30 ]; then ok "db tables=$tables"; else bad "db tables>=30" "got=${tables:-none}"; fi

# 6. 建一条 QSO（日期 d/m/y，频率单位 Hz）
# 先清掉同名残留，否则 dupe_check 会命中已有记录、不新增行
"$DB_CLI" "${DB_ARGS[@]}" -e "DELETE FROM TABLE_HRD_CONTACTS_V01 WHERE COL_CALL='BG1TEST';" 2>/dev/null
before=$("$DB_CLI" "${DB_ARGS[@]}" -e "SELECT COUNT(*) FROM TABLE_HRD_CONTACTS_V01;" 2>/dev/null | tr -d '\r')
resp=$(curl -sS -m 30 -b "$CJ" -H "X-Requested-With: XMLHttpRequest" -X POST "$BASE/index.php/qso?manual=1" \
  --data-urlencode "start_date=26/10/26" --data-urlencode "start_time=12:00" \
  --data-urlencode "callsign=BG1TEST" --data-urlencode "band=20m" --data-urlencode "mode=SSB" \
  --data-urlencode "freq_display=14200000" --data-urlencode "rst_sent=59" --data-urlencode "rst_rcvd=59" \
  --data-urlencode "station_profile=1")
after=$("$DB_CLI" "${DB_ARGS[@]}" -e "SELECT COUNT(*) FROM TABLE_HRD_CONTACTS_V01;" 2>/dev/null | tr -d '\r')
if echo "$resp" | grep -q '"message":"success"' && [ "${after:-0}" -gt "${before:-0}" ]; then
  ok "qso create (rows $before -> $after)"
else
  bad "qso create" "resp=$(echo "$resp" | head -c 160)"
fi

# 7. QSO 落库字段正确
row=$("$DB_CLI" "${DB_ARGS[@]}" -e "SELECT COL_CALL,COL_BAND,COL_MODE,COL_FREQ,COL_RST_SENT FROM TABLE_HRD_CONTACTS_V01 WHERE COL_CALL='BG1TEST' ORDER BY COL_PRIMARY_KEY DESC LIMIT 1;" 2>/dev/null | tr -d '\r')
case "$row" in
  "BG1TEST	20m	SSB	14200000	59") ok "qso row fields" ;;
  *) bad "qso row fields" "got=[$row]" ;;
esac

# 8. ADIF 导出可用 + 有文件头
code=$(curl -sS -m 60 -b "$CJ" -o /tmp/wl_exp.adi -w '%{http_code}' "$BASE/index.php/adif/exportall")
if [ "$code" = "200" ] && grep -q "PROGRAMID" /tmp/wl_exp.adi && grep -q "<EOH>" /tmp/wl_exp.adi; then
  ok "adif export header"
else
  bad "adif export header" "http=$code"
fi

# 9. 导出的 ADIF 含刚建的 QSO
if grep -q "BG1TEST" /tmp/wl_exp.adi; then ok "adif export contains qso"; else bad "adif export contains qso" "not found"; fi

# 清理
"$DB_CLI" "${DB_ARGS[@]}" -e "DELETE FROM TABLE_HRD_CONTACTS_V01 WHERE COL_CALL='BG1TEST';" 2>/dev/null
rm -f "$CJ"

echo "== result: PASS=$PASS FAIL=$FAIL =="
[ "$FAIL" -eq 0 ] || exit 1
