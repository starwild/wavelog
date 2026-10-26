# AGENTS.md

本文件是本仓库的「协作契约」：任何 AI/人类贡献者动手前先读，改完功能后回来更新。

---

## 1. 这个仓库是什么

- 上游：`https://github.com/wavelog/wavelog`（Wavelog，Web 版业余无线电日志，PHP + CodeIgniter 3.2.0-dev）
- **本仓库定位：Wavelog 中国 HAM 版（china-ham 分支）**。在上游基础上做本地化增强，核心目标：
  1. 记录 QSO **双方设备**（我方/对方电台、天线、功率）——上游只记我方，对方天线完全无处可放；
  2. 覆盖**中国大陆 QSO 卡片常见要素**（天气、温度、中继频率、卡片编号、省/市、设备型号等）；
  3. **口述录入 + 卡片/截图自动识别**，解析成结构化字段后自动填 QSO 表单。
- 分支策略：`master` 只做上游同步（`git pull` 快进），**所有开发在 `china-ham` 分支**；特性分支从 `china-ham` 切出，命名 `feat/<短描述>`。

> 上游要求 PR 打向 `dev` 分支，但那与本仓库无关；本仓库只维护 `master`（上游镜像）+ `china-ham`（我们的产品线）。

---

## 2. 本地环境（已就绪，别重复搭建）

| 项 | 值 |
|---|---|
| 站点 | http://127.0.0.1:8080/ （账号 `admin` / `Wavelog!2026`） |
| 代码 | `C:\Users\z\WorkBuddy\wavelog`（即仓库根） |
| PHP | `C:\local\php84\php.exe`（8.4.27 NTS x64，php.ini 已配好扩展） |
| DB | MariaDB 11.4.8 便携版 `C:\local\mariadb`，`127.0.0.1:3306`，库 `wavelog` / `wavelog` / `wavelog_pass` |
| 启动 | 双击 `start-db.cmd` + `start-web.cmd`（本质是 `mariadbd` 和 `php -S 127.0.0.1:8080`） |
| 日志 | `application/logs/log-YYYY-MM-DD.php`、`install/includes/...` 安装器日志 `application/logs/installer_debug.log` |

Windows 沙箱注意：PowerShell 工具无法拉起子进程，用 bash 工具起服务；删文件用 bash `rm`；`.ps1` 必须纯 ASCII（本仓库用不到）。

浏览器验证：`chrome.exe --headless --screenshot=out.png --window-size=1440,1000 <url>`（本机 Chrome 在 `C:\Program Files\Google\Chrome\Application`）。

---

## 3. 架构地图（改动前必看）

```
application/
  controllers/     88 个控制器，class Foo extends CI_Controller，__construct() 里自己 authorize()
  models/          94 个模型，属性名转小写：$this->logbook_model
  views/           纯 PHP 视图，顺序 load header → 业务视图 → footer
  views/interface_assets/{header,footer}.php   全站布局壳；JS 注入点在 footer.php:3361+
  libraries/       业务服务层（Callbook、OptionsLib、api_v2/*）
  config/          config.php / database.php 是安装器生成且 git 忽略，基线看 *.sample.php
  migrations/      NNN_snake_case.php，最大序号 307
  locale/          gettext，zh_CN 已存在
src/               Wavelog\ 命名空间（Dxcc、Label、QSLManager、StaticMap、predict、jwt）
system/            vendored CI3 —— **被上游打过补丁（Hooks 强制开启），改动前先确认**
assets/js/sections/ 100 个页面级脚本，按控制器命名（qso.js 是录入页主脚本）
install/           独立安装器（不走 CI 路由）+ 我们的 _cli_install.php
```

### QSO 写入的唯一收口（改 schema 必须同时改这三处）

| 路径 | 入口 |
|---|---|
| 单条新建 | `Qso::saveqso()` → `Logbook_model::create_qso()` → `add_qso()`（`db->insert`） |
| 单条编辑 | `Qso::qso_save_ajax()` → `Logbook_model::edit()`（白名单式 `$data` 数组，不认的字段直接丢） |
| 批量/导入/API/竞赛 | `Logbook_model::import_bulk()`（字段映射表在 ~5824-6001 行） |

**新增一个 QSO 字段，四处都要动，缺一就会出现「能存不能导出」或「能导入不能存」：**
1. `application/migrations/NNN_*.php` 加列 + `application/config/migration.php:25` 的 `migration_version` 同补；
2. `create_qso()` / `edit()` 写入；
3. `import_bulk()` 字段映射（ADIF 导入）；
4. `application/libraries/AdifHelper.php` 的 `$normalFields`（导出，规则是 `COL_<TAG>` 硬绑定）。

### 已知坑（踩过的，别再踩）

- **主表名不要硬编码**，一律 `$this->config->item('table_name')`（默认 `TABLE_HRD_CONTACTS_V01`）。
- 功率没有 `COL_MY_POWER`：我方 = `COL_TX_PWR`，对方 = `COL_RX_PWR`（ADIF 语义如此）。
- `COL_MY_ANTENNA` 是「半死字段」：只有 ADIF 导入写、界面不可编辑、导出会带。改造时正好补全。
- 录入页字段名 ≠ 直觉：网格是 `locator`（不是 gridsquare）；真实频率是 `#frequency`（Hz，name=`freq_display`），`#freq_calculated` 只是显示值。
- `#callsign` 有输入清洗器（`assets/js/sections/qso.js:274`）：会去空格、把 `.` 改成 `/P`——程序化填值要走 `.trigger('input')` 后再校正。
- LIVE 模式日期/时间字段有**重复 id**（可视域禁用 + 隐藏域），脚本选择器用 `input[name=start_time]:not([type=hidden])`。
- `application/config/config.php`、`database.php`、`install/.lock`、`updates/*`、`userdata/*` 被 git 忽略——提交前确认 `git status` 里没有本地配置。
- `csrf_protection = FALSE`；AJAX 端点靠 `X-Requested-With` 判断（`Qso.php:264`），新增 AJAX 接口照抄这个模式即可。

---

## 4. 代码约定

- 缩进 **Tab**（`.editorconfig`：`indent 4`、UTF-8、LF、去行尾空格）；PHP/JS 末尾留空行。
- 每个 PHP 文件头：`<?php if (!defined('BASEPATH')) exit('No direct script access allowed');`
- 不是 PSR；`src/` 里才用命名空间。方法 snake_case，AJAX 方法以 `_ajax` 结尾。
- 字符串一律 `__("...")`，不进 PHP 数组；翻译文件在 `application/locale/<code>/LC_MESSAGES/messages.po`。**不要提交 .po/.mo**（由 `po_gen.sh` 与 CI 生成）。中文文案直接写中文再包 `__()`，让 Weblate 流程处理。
- 权限：控制器构造函数里 `if (!$this->user_model->authorize(2)) { ... redirect('dashboard'); }`；`99`=管理员、`3`=操作员、`2`=已登录。
- 新页面 = `controllers/X.php` + `views/x/index.php` + `assets/js/sections/x.js`（通过 `$footerData['scripts'][]` 注入）。CI 默认路由，不用改 `routes.php`。
- 新 REST 资源 = 在 `application/libraries/api_v2/` 加 `<Name>_resource.php`，分发器自动接管，不必改路由。
- 没有单测、没有 phpcs、没有构建工具（无 composer.json / package.json）。质量靠：`php -l` 语法检查 + 本地跑起来点一遍 + 看日志。

---

## 5. 中国 HAM 版改造范围（当前阶段）

### P0 双方设备
- 新增列（迁移 308）：对方天线 `COL_RIG_ANT`、我方设备补充 `COL_MY_RADIO_MODEL`（若决定不依赖 CAT）、对方功率复用 `COL_RX_PWR`。
- 录入/编辑表单加「双方设备」折叠块：我方 电台/天线/功率（默认取 station_profile）+ 对方 电台/天线/功率。
- ADIF 双向：标准 `RIG` / `MY_ANTENNA` / `TX_PWR` / `RX_PWR` 保持原映射；对方天线走 `APP_WAVELOG_RIG_ANT`。

### P0 中国卡片字段
`COL_WX`（天气）、`COL_TEMP`（温度℃）、`COL_RPT`（中继/直频）、`COL_CARD_NO`（卡片编号）、`COL_QTH_CN`? 优先复用 `COL_STATE` 存省/直辖市，`COL_CITY` 存市。全部同步 ADIF `APP_WAVELOG_*` 扩展 + 导入导出。

### P0 口述 / 自动识别录入
- `assets/js/sections/cn_qso_parser.js`：中文口述/自由文本 → 结构化字段（呼号、波段、模式、RST、QTH、网格、设备、天线、功率、中继、天气、温度）。规则优先，正则 + 单位归一（"五九"→59、"十四兆"→14MHz→20m、"单边带"→SSB、"一百瓦"→100W）。
- `assets/js/sections/voice_entry.js`：Web Speech API（`webkitSpeechRecognition`, `lang='zh-CN'`）→ 中间结果实时解析 → 回填表单。
- 回填**必须走既有事件链**（不是直接 `.val()`）：`#callsign` `.trigger('input')`+`blur` 触发呼号库查询，`#frequency` `.trigger('change')` 联动波段/CAT，`#band`/`.mode` `.trigger('change')`。若 `user_dxwaterfall_enable` 开着可复用 `DX_WATERFALL_UTILS.qsoForm.populateFromSpot()`。
- 卡片照片识别：客户端 Tesseract.js（离线放 `assets/js/tesseract/`，需自带 wasm + chi_sim traineddata，约 10-15MB，不引 CDN）→ 文本 → 同一个 parser。识别结果**一律先进预览面板让用户确认**，禁止直接落库。
- 服务端兜底（可选、配置开关）：`controllers/Autofill.php` + `libraries/` 做 LLM/ASR 代理，密钥走 config（不进仓库）。

### 明确不做
- 不改动上游 `system/` 框架代码（除非上游本身有补丁且无法绕开）。
- 不引入前端构建链（webpack/vite/package.json）；新库一律放 `assets/` 用 `<script>` 引入。
- 不提交数据库凭据、API key、`.po/.mo`。

---

## 6. 完成定义（DoD）

一个改动算完成，必须全部满足：

1. `php -l` 通过本次改动的所有 PHP 文件；
2. 本地起服务点一遍：录入 → 保存 → 列表 → 编辑 → 重新保存 → ADIF 导出 → ADIF 导入（round-trip 字段不丢）；
3. `application/logs/log-YYYY-MM-DD.php` 无新增 ERROR（POTA/SOTA/WWFF 拉取失败属已知环境问题，可忽略）；
4. 若动了 schema：`/index.php/migrate` 在干净库与已装库上都能跑通，`down()` 可回滚；
5. 中文文案走 `__()`；新增字段在 `README-LOCAL.md` 的改动清单里登记；
6. 提交信息：`<type>(<scope>): <中文摘要>`，`type ∈ feat|fix|refactor|docs|chore`，例：`feat(qso): 新增双方设备录入块`。

---

## 7. 给 Agent 的工作纪律

- **先读再改**：QSO 相关改动先确认走的是 `create_qso`/`edit`/`import_bulk` 哪条路径，别在视图层加字段就算完。
- **小步提交**：一个提交只做一件事，方便上游同步时挑拣。
- **别动本地配置**：`application/config/{config,database}.php` 是环境产物，改了会被忽略，也不该提交。
- **改完回来更新本文件**：新增迁移、新增目录、新增 JS 入口、新的坑，都往第 3、5 节补一行。
- 分析类任务派子 agent 并行跑，别在主会话里翻 300 个文件。
