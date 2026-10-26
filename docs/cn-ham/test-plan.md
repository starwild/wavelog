# 验证方案 · Wavelog 中国 HAM 版

> 配套：`docs/cn-ham/requirements.md`、`docs/cn-ham/technical-design.md`
> 本文件的设计前提：**执行者是一个上下文被清空的子 agent**——它没有我们的对话记忆，只能看到这份文档 + 仓库 + 运行中的站点。所以每条用例都必须是「自包含、可判定、可复现」的。

---

## 0. 执行协议（写给执行测试的 agent，硬规则）

**你只能做这些：**
1. 读 `tests/`、`docs/cn-ham/` 下的文件；
2. 在 bash 里跑命令（curl / mariadb 客户端 / node / php -l）；
3. 通过 HTTP 响应、DOM、数据库查询、日志文件观察结果；
4. 往 `artifacts/` 写证据文件。

**你不许做这些：**
1. **不许读 `application/` 或 `assets/` 源码去找"应该是什么"**。黑盒。代码怎么实现不关你事，只看行为。
2. **不许改任何代码、迁移、配置、数据库结构**。发现 bug 就记录，不要顺手修。
3. **不许用"看起来正常"代替断言**。每条用例必须有明确期望值。
4. **不许跳过失败项继续当通过**。失败就标 FAIL，继续跑后面的，最后汇总。
5. **不许省略原始输出**。报告里每步贴真实命令 + 真实输出（过长可截断，必须标 `...`），不允许"复述结果"。
6. 重试失败的命令时，两次输出都要贴，并说明为什么重试。

**报告格式**（写到 `artifacts/test-<YYYYMMDD-HHMM>/REPORT.md`）：

```md
# 测试报告 <日期>
环境: BASE=... DB=...
## 结论
PASS xx / FAIL xx / SKIP xx
## 明细
### T1.3 写入路径 A
命令: <原样>
输出: <原样>
期望: COL_RIG_ANT = "GP-9"
实际: COL_RIG_ANT = ""
判定: FAIL
## 环境异常（如有）
## 未覆盖项（如有，说明原因）
```

**判定原则**：任何一条 FAIL 都必须在报告里能复现（给出命令）。不能复现的失败视为无效，需重跑。

---

## 1. 环境契约

| 项 | 值 |
|---|---|
| 工作区根 | `C:\Users\z\WorkBuddy\wavelog` |
| 仓库根 | `C:\Users\z\WorkBuddy\wavelog\wavelog`（注意是子目录） |
| BASE | `http://127.0.0.1:8080` |
| DB | `127.0.0.1:3306`，库 `wavelog`，用户 `wavelog` / `wavelog_pass` |
| DB 客户端 | `/c/local/mariadb/mariadb-11.4.8-winx64/bin/mariadb.exe`（bash 里用反斜杠路径同理） |
| PHP | `C:\local\php84\php.exe` |
| 账号 | `admin` / `Wavelog!2026` |
| 服务启动 | 双击 `start-db.cmd` + `start-web.cmd`，或 bash 里后台拉起（见下） |

服务没起时的兜底命令（bash 工具）：

```bash
# DB
cd /c/local/mariadb/mariadb-11.4.8-winx64 && ./bin/mariadbd.exe --defaults-file="C:/local/mariadb/data/my.ini" --port=3306 --console &
# Web
cd /c/Users/z/WorkBuddy/wavelog/wavelog && /c/local/php84/php.exe -S 127.0.0.1:8080 -t /c/Users/z/WorkBuddy/wavelog/wavelog &
```

### ⚠️ 环境前置：DXCC 数据必须存在

`dxcc_entities` 表若为空或缺少中国（`adif=318`），**ADIF 导出会静默返回空文件**（导出 SQL 对 `dxcc_entities` 做 INNER JOIN）。这不是代码 bug，是环境缺数据。

```bash
# 检查
mariadb ... -e "SELECT COUNT(*) FROM dxcc_entities; SELECT COUNT(*) FROM dxcc_entities WHERE adif=318;"
# 期望: >=300 且第二项 = 1
# 不满足则先修复（需要登录态）
curl -sS -b cookiejar "http://127.0.0.1:8080/index.php/update/dxcc"
```

**任何 ADIF 相关用例失败前，先确认这一条。**2026-10-27 的初始环境就踩了这个坑（表里只有 1 行 `adif=0`）。

---

## 2. 测试分层

| 层 | 覆盖 | 手段 | 可自动化 |
|---|---|---|---|
| **T0 冒烟** | 服务/DB/登录/建 QSO/导出 | `tests/smoke.sh` | ✅ 一条命令 |
| **T1 数据层** | 迁移、四写入路径、空值语义 | curl + SQL | ✅ |
| **T2 界面** | 表单字段存在、回显、折叠块、详情显示 | curl 抓 HTML + grep | ✅（弱） |
| **T3 ADIF** | 导出内容、导入回填、round-trip、0 值 | curl + 文件 diff | ✅ |
| **T4 解析器** | 中文口述/文本 → 字段 | `node tests/parser.test.js` | ✅ |
| **T5 回归** | 上游功能不被破坏 | curl 页面 + SQL | ✅（弱） |
| **T6 视觉** | 折叠块排版、语音浮层、OCR 确认面板 | 无头 Chrome 截图 + 人眼/视觉模型 | ❌ 半自动 |

T0–T3、T5 必须每次改动全跑；T4 只跟解析器相关；T6 在 UI 变更时跑。

---

## 3. 用例

### T0 冒烟（10 项）

```bash
cd /c/Users/z/WorkBuddy/wavelog/wavelog && bash tests/smoke.sh
```

期望：`PASS=10 FAIL=0`，退出码 0。脚本自带清理（删除测试 QSO）。

---

### T1 数据层

| ID | 名称 | 步骤 | 期望 |
|---|---|---|---|
| T1.1 | 迁移已执行 | `SELECT version FROM migrations ORDER BY version DESC LIMIT 1;` | `308` |
| T1.2 | 新列存在 | `SELECT column_name,data_type,is_nullable FROM information_schema.columns WHERE table_schema='wavelog' AND table_name='TABLE_HRD_CONTACTS_V01' AND column_name IN ('COL_RIG_ANT','COL_CN_DISTRICT','COL_WX','COL_WX_TEMP','COL_RPT','COL_CTCSS','COL_CARD_NO');` | 7 行；`COL_WX_TEMP` 为 `smallint` 且 `is_nullable=YES` |
| T1.3 | 写入路径 A（新建） | 见下方 curl | 响应含 `"message":"success"`；SQL 查该行 7 字段 = 提交值 |
| T1.4 | 写入路径 B（编辑） | `POST /index.php/qso/qso_save_ajax`（带 `id`） | 重新查该行，改动生效、未改字段不变 |
| T1.5 | 写入路径 C（ADIF 导入） | `curl -F userfile=@fixture.adi -F station_profile=1 ...` | 导入报告成功；SQL 查到 `APP_WAVELOG_*` 对应列有值 |
| T1.6 | 写入路径 D（API v2 PATCH） | 需 API token，**无 token 则 SKIP 并说明** | 响应 JSON 含新字段 |
| T1.7 | 空值语义 | 新建时**不传** `cn_temp` | `COL_WX_TEMP IS NULL`（不是 0、不是空串） |

**T1.3 参考命令**（已实测可用）：

```bash
CJ=/tmp/wl_cj.txt; rm -f $CJ
curl -sS -c $CJ -b $CJ -o /dev/null http://127.0.0.1:8080/index.php/user/login
curl -sS -c $CJ -b $CJ -o /dev/null -d "user_name=admin&user_password=Wavelog!2026&id=" \
  http://127.0.0.1:8080/index.php/user/login
curl -sS -b $CJ -H "X-Requested-With: XMLHttpRequest" -X POST \
  "http://127.0.0.1:8080/index.php/qso?manual=1" \
  --data-urlencode "start_date=27/10/26" --data-urlencode "start_time=09:30" \
  --data-urlencode "callsign=BG1TEST" --data-urlencode "band=20m" --data-urlencode "mode=SSB" \
  --data-urlencode "freq_display=14200000" \
  --data-urlencode "rst_sent=59" --data-urlencode "rst_rcvd=59" \
  --data-urlencode "station_profile=1" \
  --data-urlencode "rig_ant=GP-9" --data-urlencode "cn_wx=SUNNY" --data-urlencode "cn_temp=25" \
  --data-urlencode "cn_rpt=439.050 -5" --data-urlencode "cn_ctcss=T88.5" \
  --data-urlencode "cn_card_no=C-0001" --data-urlencode "cn_district=朝阳区"
```

**三个必知的接口事实**（写命令时最容易错）：
- 日期格式是 **`d/m/y`**（两位年，来自 `config/wavelog.php` 的 `qso_date_format`），传 `27/10/2026` 会解析失败并落成 1970 年。
- `freq_display` 单位是 **Hz**，14.200 MHz 要传 `14200000`。
- 必须带 `X-Requested-With: XMLHttpRequest`，否则 403。
- 建 QSO 有**重复检查**：同呼号+波段+模式已存在时返回既有记录、不新增。测试前先 `DELETE FROM TABLE_HRD_CONTACTS_V01 WHERE COL_CALL='BG1TEST';`

---

### T2 界面

| ID | 名称 | 步骤 | 期望 |
|---|---|---|---|
| T2.1 | 新建页有字段 | `curl -b $CJ $BASE/index.php/qso?manual=1 \| grep -c 'name="rig_ant"'` 等 7 项 | 每项 ≥ 1 |
| T2.2 | 编辑弹窗同名字段 | `curl -b $CJ "$BASE/index.php/qso/edit_ajax/<id>"` 同上 | 每项 ≥ 1，且 name 与新建页**完全一致** |
| T2.3 | 详情页显示 | 先造带值数据，`curl -b $CJ "$BASE/index.php/logbook/view/<id>"` | HTML 含 `GP-9`、`晴`（或 `SUNNY`）、`25`、`439.050` |
| T2.4 | 折叠块默认收起 | 抓 HTML，定位容器 class | 含 `collapse` 且不含 `collapse show` |
| T2.5 | 中文不乱码 | 抓含中文的页面 | 响应头 `charset=utf-8`，中文原样出现 |

T2 只能验「字段存在 + 后端渲染」，交互行为（打字、切换、回填）**必须走 T6**，用无头 Chrome 截图看。

---

### T3 ADIF round-trip

| ID | 名称 | 步骤 | 期望 |
|---|---|---|---|
| T3.1 | 导出含自定义字段 | 造数据 → `GET /index.php/adif/exportall` → grep | 含 `<APP_WAVELOG_RIG_ANT:` 等 7 个 tag，值正确 |
| T3.2 | 文件头 | 同上 | 含 `<PROGRAMID:7>Wavelog`（上游已有，大小写与 `APP_WAVELOG_*` 仅差大小写，ADIF 字段名不区分大小写） |
| T3.3 | 导出可再导入 | 把导出文件通过 `/index.php/adif/import` 导入（先删该 QSO 避免重复） | 导入成功，7 字段值与原值逐一相等 |
| T3.4 | **0℃ 陷阱** | 造 `cn_temp=0` → 导出 | 导出**必须包含** `<APP_WAVELOG_WX_TEMP:1>0`（不得被吞） |
| T3.5 | 中文值往返 | `cn_rpt=439.050 -5 T88.5`（ASCII）/ `cn_card_no=C-0001` | 往返一致；若测试中文字段，确认 UTF-8 不乱码 |

导入命令参考：

```bash
curl -sS -b $CJ -F "userfile=@/path/to/test.adi" -F "station_profile=1" \
  -F "fhash=$(printf '%s' 'BG1ABC' | sha256sum | cut -d' ' -f1)" \
  http://127.0.0.1:8080/index.php/adif/import
```

> `fhash` = `sha256(session.user_callsign)`，值为 `BG1ABC`。若报错，改用浏览器 UI 导入并说明。

---

### T4 解析器单测

前提：`assets/js/sections/cn_qso_parser.js` 已存在且 `module.exports` 可用。

```bash
node tests/parser.test.js
```

- 样本：`tests/fixtures/spoken-samples.tsv`（`输入<TAB>期望字段JSON`）
- 判据：**字段级准确率 ≥ 90%**，且呼号识别 100%（20/20）
- 输出：逐条 PASS/FAIL + 汇总准确率

若 `parser.test.js` 不存在 → 判 SKIP 并在报告里写明「P1 未实现」。

---

### T5 回归（上游功能不能被我们改坏）

| ID | 页面 | 期望 |
|---|---|---|
| T5.1 | `/index.php/dashboard` | 200，含 `Dashboard` |
| T5.2 | `/index.php/logbook` | 200 |
| T5.3 | `/index.php/qso?manual=1` | 200 |
| T5.4 | `/index.php/adif` | 200 |
| T5.5 | `/index.php/statistics` | 200 或 302（视权限），不得 500 |
| T5.6 | 旧字段编辑保存 | 改 `comment`/`qsl_sent` 后回读一致 |
| T5.7 | API v2 GET | `GET /index.php/api/v2/qso/<id>`（带 token）200 且含 `call`；无 token 则 SKIP |
| T5.8 | 应用日志 | `application/logs/log-<today>.php` 无**新增** `ERROR`（POTA/SOTA/WWFF 下载失败、APCu 未加载属已知噪声，允许） |

---

### T6 视觉（半自动）

```bash
"/c/Program Files/Google/Chrome/Application/chrome.exe" --headless --disable-gpu \
  --window-size=1440,1400 --screenshot=artifacts/test-<ts>/qso-form.png \
  "http://127.0.0.1:8080/index.php/qso?manual=1"
```

判据：折叠块标题可见、展开后字段两列对齐、无横向溢出、无遮挡。**截图必须存进 artifacts**。

---

## 4. 夹具

| 文件 | 用途 |
|---|---|
| `tests/smoke.sh` | T0 |
| `tests/fixtures/test-qso.adi` | T1.5 / T3 导入样本（P0 时创建） |
| `tests/fixtures/spoken-samples.tsv` | T4 口述样本（P1 时创建） |
| `tests/parser.test.js` | T4 runner（P1 时创建） |

**测试数据约定**：
- 呼号固定用 `BG1TEST`（不存在于真实呼号库，便于清理）。
- 每条用例结束删掉自己造的数据；报告里声明清理动作。
- 不许删 `admin` 用户、不许改 `station_profile` 主数据。

---

## 5. 回归矩阵

| 改动类型 | 必跑 |
|---|---|
| 迁移 / model 写入 | T0 T1 T3 T5 |
| 视图 / 前端 | T0 T2 T5 T6 |
| 解析器 | T4 T0 |
| ADIF 相关 | T0 T3 T5.4 T5.8 |
| 发版前 | 全跑 + T6 全截图 |

---

## 6. 为什么必须用「空上下文」的 agent 测

这是刻意设计的，不是洁癖：

1. **实现者会无意识地按自己的实现去测**——"我知道这里传的是 Hz"，于是不测单位；空上下文的人只会看到文档说 Hz，然后自己撞。
2. **本方案的核心风险是「静默失败」**：字段没进白名单 → 保存变空但不报错；DXCC 缺数据 → 导出空文件但不报错；`0` 值被 helper 吞掉 → 不报错。只有照着"输入 → 期望 → 实际"黑盒比对才抓得到。
3. **有记忆的人会跳过"看起来显然"的步骤**，比如先确认 DXCC 数据。这次的坑就是那么埋的。

因此：**执行测试的 agent 不得复用实现会话，也不得读实现代码。** 需要写的命令、期望值全部沉淀在本文件里；本文件写不清楚的地方，就是测试方案本身的缺陷，回头补文档而不是给 agent 口头解释。
