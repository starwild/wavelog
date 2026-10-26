# 技术实现分析 · Wavelog 中国 HAM 版

> 配套：`docs/cn-ham/requirements.md`（要什么）｜`docs/cn-ham/test-plan.md`（怎么验）
> 代码根：`C:\Users\z\WorkBuddy\wavelog\wavelog`（工作区根 `...\wavelog`，仓库在子目录 `wavelog/`）

---

## 1. 总体判断

改动集中在 **一个 schema 变更 + 四个写入收口 + 两处表单 + 一套前端解析器**。不要被"7 个字段"骗了——真正的成本不在加列，在**展示面**：上游的详情页 / 日志列表 / QSL 卡搜索结果 / API 输出是**四份彼此独立的字段清单**，没有任何共用配置。漏一个就是"存进去了但看不见"。

因此实现策略是：**数据层先一次做全（可脚本验证），展示层按需增量（可视觉验证）**。

```
[迁移 308] → [写入四收口] → [ADIF 双向] → [编辑/新建表单] → [详情/列表/API/卡片] → [前端解析器]
   ①            ②              ③              ④                  ⑤                    ⑥
 可用 SQL 验   可用 ADIF 验    round-trip 验    可用 DOM 验        视觉 + API 验        可 node 单测
```

---

## 2. 字段落地矩阵

`●` = 必改 ｜ `○` = 可选（按是否上展示决定）｜ `-` = 不涉及

| 字段 | UI name | DB 列 | ①迁移 | ②create/edit/import | ③ADIF | ④表单 | ⑤详情/列表 | API v2 | 卡片模板 |
|---|---|---|---|---|---|---|---|---|---|
| 对方天线 | `rig_ant` | `COL_RIG_ANT` | ● | ● | `APP_WAVELOG_RIG_ANT` | ● | ● / ○ | ● | ● |
| 对方区县 | `cn_district` | `COL_CN_DISTRICT` | ● | ● | `APP_WAVELOG_CN_DISTRICT` | ● | ● / ○ | ● | ○ |
| 天气 | `cn_wx` | `COL_WX` | ● | ● | `APP_WAVELOG_WX` | ● | ● / ● | ● | ● |
| 温度 | `cn_temp` | `COL_WX_TEMP` | ● | ● | `APP_WAVELOG_WX_TEMP` | ● | ● / ○ | ●（numeric） | ● |
| 中继 | `cn_rpt` | `COL_RPT` | ● | ● | `APP_WAVELOG_RPT` | ● | ● / ○ | ● | ● |
| 亚音 | `cn_ctcss` | `COL_CTCSS` | ● | ● | `APP_WAVELOG_CTCSS` | ● | ● / ○ | ● | ○ |
| 卡片编号 | `cn_card_no` | `COL_CARD_NO` | ● | ● | `APP_WAVELOG_CARD_NO` | ● | ● / ● | ● | ● |
| 我方电台 | `my_rig` | `COL_MY_RIG` | - | ○（补默认值） | 已有 `MY_RIG` | ● | ● / - | ●（补） | ●（已有） |
| 我方天线 | `my_antenna` | `COL_MY_ANTENNA` | - | ○ | 已有 `MY_ANTENNA` | ● | ● / - | ●（补） | ●（已有） |
| 我方功率 | `transmit_power` | `COL_TX_PWR` | - | 已有 | 已有 `TX_PWR` | 已有 | 已有 | 已有 | 已有 |
| 对方电台 | `rig` | `COL_RIG` | - | 已有 | 已有 `RIG` | ● | ● / ○ | ●（补） | ●（已有） |
| 对方功率 | `rx_pwr` | `COL_RX_PWR` | - | ○ | 已有 `RX_PWR` | ● | ● / ○ | ●（补） | ○ |

> **我方电台/天线/对方电台/对方功率这 4 项上游 ADIF 已通，只是 UI 不给**。补 UI 后如果 `create_qso`/`edit` 白名单里没有对应 key，就会"填了存不上"——这是最容易踩的坑（静默丢弃，无报错）。

---

## 3. 分层实现方案

### 3.1 ① 迁移 308

- 6 个新列（`COL_RIG_ANT` 等）+ 可选 2 个 station 列（D3 若采纳）。
- 范式照 `application/migrations/258_adif_3_1_6.php:55-75`：动态取表名 + `SHOW COLUMNS ... LIKE` 幂等 + 裸 `ALTER TABLE ... ADD COLUMN ... AFTER`，包 try/catch。
- `AFTER` 定位：`COL_RIG_ANT` 跟在 `COL_RIG_INTL` 后；`COL_WX*`/`COL_RPT`/`COL_CTCSS`/`COL_CN_DISTRICT`/`COL_CARD_NO` 跟在 `COL_NOTES_INTL` 后（不追求物理顺序，但对齐语义便于人工排查）。
- **同步 `application/config/migration.php:25` → 308**。`install/assets/install.sql` 不动（其中 migrations 版本写 170，安装器装完会 `latest()` 补齐）。
- `down()`：`DROP COLUMN`，逐列判存在。

### 3.2 ② 写入四收口

| 位置 | 改法 |
|---|---|
| `Logbook_model::create_qso()` 357-421 | `$data` 数组字面量加 6 个 `COL_*`，值来自 `$qso_data['cn_*']` |
| `Logbook_model::edit()` 1928-2012 | 加 6 个 `COL_* => $this->input->post('...')`（不传则保持原值 → 用 `null` 判定，别用 `''` 覆盖） |
| `Logbook_model::import()` 5824-5996 | 加 6 条 `$record['app_wavelog_*']` 映射（key 全小写，解析器 `Adif_parser.php:245` 归一） |
| `Qso::saveqso()` 275-335 | `$qso_data` 白名单补 6 个 `cn_*` + `rig_ant` |

**特殊处理**
- 温度：空值必须存 `NULL`（不是 0），否则 `getAdifFieldLine()` 会吞掉 0℃ 的真值。
- `edit()` 的"不传即不改"：表单永远提交全部字段，所以直接用 `post()` 值覆盖即可；但 API 的 PATCH 走 `update_qso_columns()`，只写出现的 key，不冲突。

### 3.3 ③ ADIF 双向

**导出**（`AdifHelper.php`）
- 6 个自定义字段**不进 `$normalFields`**（那个数组是 `COL_{tag}` 硬绑定，`AdifHelper.php:149`），在 `249-253` 之后逐行显式输出：
  ```php
  $line .= $this->getAdifFieldLine('APP_WAVELOG_RIG_ANT', $qso->COL_RIG_ANT ?? '');
  ```
- 文件头**已带** `PROGRAMID=Wavelog`（实测导出：`<PROGRAMID:7>Wavelog`）。自定义字段前缀写 `APP_WAVELOG_*`，与头部仅大小写差异——ADIF 字段名大小写不敏感，可接受；若要绝对稳妥，可把 `APP_` 前缀改成 `APP_WAVELOG_`（保持现状）并在导入侧同时容忍大小写。
- 温度改用显式判 `!== null`，绕开 helper 的 0 值吞掉行为。

**导入**（`Adif_parser` → `import()`）
- 解析器**不需要改**：不白名单过滤，`APP_*` 原样保留、key 转小写。
- v1 API 写入侧只接整串 ADIF，因此自定义字段是否能写入**取决于 `import()` 映射是否到位**，与 v1 无关。

**v1 读出**（`Api.php`）：`_build_qso_array()` 的 `$normalFields`（751）与 GET 过滤白名单（593）都是硬编码，新增字段两边都要加。

### 3.4 ④ 表单（两处，字段名必须一致）

| 页面 | 文件 | 插入锚点 |
|---|---|---|
| 新建 | `views/qso/index.php` | QSO 面板 `394` 后（comment 之后）；卡片要素放 General 面板 |
| 编辑 | `views/qso/edit_ajax.php` | QSO 面板 `186` 后（comment 之后）；或 Awards 面板 `389` 后 |

- 两处**没有共用 partial**，必须各写一遍；`name` 不一致 = `edit()` 取不到值。
- 折叠块照抄 `index.php:774-790` 的 `card + card-header[data-bs-toggle=collapse]` 结构，Bootstrap 5 原生，无额外 JS。
- 我方默认值：station_profile 的 `station_power` 已有读取范式（`Qso.php:785` `get_station_power`）；D3 采纳后同样加 `station_rig` / `station_antenna` 并在此端点一并返回。

### 3.5 ⑤ 展示面（四份独立清单，逐个点）

| 面 | 文件 | 说明 |
|---|---|---|
| 详情弹窗 | `views/view_log/qso.php` | 手写 `<table>`，每字段一组 `<tr>`；Comment（225-231）后插入卡片要素组，天线区（261）后插对方天线。数据来自 `SELECT table_name.*`，新列自动可读 |
| 日志列表 | `views/view_log/partial/log_ajax.php` | 表头 `3-24` + 单元格 `26-64` 两个 switch；用户可选列是 `users.user_column1..5` |
| 历史小表 | `controllers/Logbook.php:1504/1535` | 同名 switch 的控制器副本，需同步 |
| QSL 卡搜索 | `views/qslcard/searchresult.php:11-90 / 112-180` | 第三份 switch，唯一必须单独维护的重复 |
| QSL 卡设计器 | `models/Qslpostcard_model.php:622 resolve_field()` + `views/qslpostcard/designer.php:4-12` | `data-field` 字符串必须与 `resolve_field()` 判断串**完全一致**；对方天线新开 `qso.rig_ant`，不要复用已有的 `qso.antenna`（那是我方天线） |

天气是枚举 → 中文映射写一个 helper（`application/helpers/cn_ham_helper.php`），视图和导出共用；不要在每个视图里重复 switch。

### 3.6 ⑥ 前端解析器（核心资产）

**结构**：`assets/js/sections/cn_qso_parser.js`
- IIFE 挂 `window.CnQsoParser`，同时 `if (typeof module !== 'undefined') module.exports = ...` → **node 可直接单测**，这是本方案能自动化验证的前提。
- 纯函数：`parse(text) → {fields:{}, confidence:{}, unknown:[]}`，不碰 DOM、不发请求。
- 词表外置为同文件内的常量对象（波段表、模式表、省份表、天气表、中文数字表）。

**接入**：`assets/js/sections/voice_entry.js`
- 只负责 UI（输入框、麦克风按钮、结果确认面板）与回填。
- 回填走事件链：`#callsign` `.trigger('input')` + `blur`；`#frequency` `.trigger('change')`；`#band` / `.mode` `.trigger('change')`。
- 加载方式：`$footerData['scripts'][]`（`footer.php:3361` 循环，路径不带前导 `/`，`?v=filemtime` 自动破缓存）。

**语音**：能力探测 `('webkitSpeechRecognition' in window)` + 一次实义测试；失败则隐藏麦克风按钮，提示"用输入法语音键把文字打进来"。**不作为依赖**。

**OCR**：Tesseract.js 资产放 `userdata/vendor/tesseract/`（git 忽略、`git pull` 不覆盖、静态直出可访问）。worker/wasm/lang 全用同源相对路径。`.wasm` 的 MIME 在内置 PHP 服务器下是 `application/octet-stream`，`instantiateStreaming` 会失败并自动 fallback 到 `arrayBuffer`（功能可用）；上 Apache/nginx 时补 `application/wasm`。

### 3.7 开关（D5 等）

`user_options` 是纯 KV 表，加选项**零 DDL**。完整改动清单（照 `oqrs/oqrs_grouped_search` 范例）：
1. `models/User_model.php`：种子 `326-349`、批写 `415-438`、session 镜像 `446-458`、登录装载 `620-685`
2. `controllers/User.php`：存 `1130-1147`、回填 `940-1040`
3. `views/user/edit.php`：控件照 `941-950`（hidden `off` + checkbox `on`）
4. 生效点：`Logbook_model` 三个写入函数，**优先读 session**（每条 QSO 都跑，别查库）

---

## 4. 关键决策与理由

| 决策 | 选择 | 理由 |
|---|---|---|
| 自定义字段协议 | 只用 `APP_WAVELOG_*`，不塞 `COMMENT` | 标准、可逆、他方软件至少能看到原串；冗余进 COMMENT 做成开关（D5） |
| 天气存储 | 枚举码（`SUNNY`…），不是中文 | 与 UI 语言解耦，导出 ASCII 安全，翻译走 gettext |
| 温度 | `SMALLINT NULL` | 0℃ 是合法值；NULL = 未填 |
| 省 | 复用 `COL_STATE` + ADIF 中国两字母码 | 上游迁移 173 已内置枚举，别造新轮子 |
| 解析器 | 纯前端 + node 可测 | 可回归；不依赖网络/密钥 |
| 语音 | 浏览器 API 仅增强 | 国内 Chrome 依赖 Google 服务，不可靠 |
| OCR | 本地 Tesseract 基线，云端可选 | 免密钥可离线；精度问题用"仅印刷体"限定预期 |
| 资产位置 | OCR 放 `userdata/vendor/` | 不进 git、升级不覆盖 |

---

## 5. 改动清单（含量级）

| # | 文件 | 动作 | 量级 |
|---|---|---|---|
| 1 | `application/migrations/308_cn_ham_fields.php` | 新建 | S |
| 2 | `application/config/migration.php:25` | 307→308 | XS |
| 3 | `application/models/Logbook_model.php` | create_qso / edit / import 三处 | M |
| 4 | `application/controllers/Qso.php:275-335` | 白名单 | S |
| 5 | `application/libraries/AdifHelper.php:253+` | 6 行显式输出 + PROGRAMID | S |
| 6 | `application/views/qso/index.php` | 两个折叠块 | M |
| 7 | `application/views/qso/edit_ajax.php` | 同上 | M |
| 8 | `application/views/view_log/qso.php` | 详情字段组 | S |
| 9 | `application/views/view_log/partial/log_ajax.php` + `Logbook.php:1504/1535` | 列表列 | S |
| 10 | `application/views/qslcard/searchresult.php` | 第三份 switch | S |
| 11 | `application/models/Qslpostcard_model.php` + `designer.php` | 卡片字段 | S |
| 12 | `application/libraries/api_v2/Qso_resource.php:786/906` | v2 白名单 + numeric | XS |
| 13 | `application/controllers/Api.php:593/751` | v1 读出 | XS |
| 14 | `application/helpers/cn_ham_helper.php` | 新建，天气/省份枚举映射 | S |
| 15 | `assets/js/sections/cn_qso_parser.js` | 新建，纯函数 | L |
| 16 | `assets/js/sections/voice_entry.js` | 新建，UI + 回填 | M |
| 17 | 各控制器 `$footerData['scripts'][]` | 注入（注意 `Logbook.php:63` 当前没传 footerData） | XS |
| 18 | 用户选项 4 处 + 视图 + 生效点 | 开关 | S |

**总量估计：P0 约 900–1200 行，其中解析器占一半。**

---

## 6. 与上游同步策略

- 改上游文件的**总行数控制在 300 行以内**（视图除外），集中在 4 个 model 函数 + 2 个视图。
- 新增文件（迁移、helper、parser、voice_entry）**零冲突**。
- 每次同步：`git rebase master`（master 只 fast-forward 上游），冲突集中在 `Logbook_model.php` 与两个表单视图。
- 提交粒度按文件拆，便于 cherry-pick。

---

## 7. 技术债与已知限制（写死在文档里，别当 bug 追）

1. **列表列没有配置化**：四份 switch 是上游结构，本次不重构（重构会炸掉上游同步）。接受 4 处重复。
2. **ADR 字段的 0 值陷阱**：`getAdifFieldLine()` 吞 `0`，只在温度上绕开，其它字段不适用（功率 0 无意义）。
3. **v1 API 只读不写自定义字段**（写入走 ADIF 串），已知限制。
4. **手写卡片 OCR 无解**，UI 必须写死"仅印刷体"。
5. **中继无结构化**：P0 只存文本，P3 再评估建表。
6. 天气枚举**不可扩展**（写死 7 种），要加就走 helper 一处改。
