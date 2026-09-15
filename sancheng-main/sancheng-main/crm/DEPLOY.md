<!--
叁程 CRM (Triphase CRM) — 部署与运维手册
Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
-->

# 叁程 CRM (Triphase CRM) — 生产部署与运维手册

> 适用版本：**1.11.0**（`app/config/config.php` 中的 `APP_VERSION`）
> 适用场景：私有化部署给单一公司 / 团队使用（自托管单租户）。
> 若要做多租户 SaaS / 大并发集群，本文档的容量章节不适用，需要先做架构改造（见第 11 节）。

本文档只讲“上线生产要做的每一件事”，并且每条命令/参数都对着代码核实过。部署前请通读一遍。

---

## 目录

1. [总体架构与安全边界](#1-总体架构与安全边界)
2. [环境要求](#2-环境要求)
3. [配置：.env 全部键位](#3-配置env-全部键位)
4. [全新生产安装（逐步）](#4-全新生产安装逐步)
5. [Web 服务器配置（Apache / Nginx）](#5-web-服务器配置apache--nginx)
6. [上线安全清单](#6-上线安全清单)
7. [备份与恢复](#7-备份与恢复)
8. [升级 / 迁移](#8-升级--迁移)
9. [日常运维与排障](#9-日常运维与排障)
10. [AI 助手生产注意事项](#10-ai-助手生产注意事项)
11. [容量边界与诚实说明](#11-容量边界与诚实说明)
12. [数据合规速览](#12-数据合规速览)
13. [上线检查清单](#13-上线检查清单)

---

## 1. 总体架构与安全边界

```
crm/
├── public/            # ⚠️ Web 根目录 —— 站点只能暴露这一个目录
│   ├── index.php      # 唯一入口（前端控制器）
│   ├── .htaccess      # Apache 重写到 index.php；拒绝点文件
│   └── uploads/       # 附件存储（其 .htaccess 已禁止 PHP 执行）
├── app/               # 应用代码：config / core(框架) / controllers / models / views
├── database/          # ⚠️ crm.sqlite 数据库文件 + schema.sql + migrations/
├── tests/             # 零依赖回归测试
├── .env               # ⚠️ 部署配置与密钥（不入库、不进 git）
├── .env.example       # .env 模板
└── .htaccess          # 仅当 Web 根无法指向 public/ 时的兜底重写
```

**三条必须守住的边界：**

1. **Web 根目录必须指向 `public/`**。`app/`、`database/`（内含整个数据库文件）、`.env`、`.git/` 都要位于 Web 根**之外**。代码结构本身（`public/` 之外的 `.htaccess`）不承担保护它们的主要职责——那只是不能改 docroot 时的兜底。
2. **所有 SQL 都是 PDO 绑定参数**、所有输出走 `e()` 转义、全站表单有 CSRF 校验、会话 Cookie 为 `HttpOnly + SameSite=Lax`（HTTPS 下自动加 `Secure`）。这些是代码保证，部署时只需确保跑在 HTTPS 下。
3. **登录节流、记住登录 token 轮换、上传目录禁执行、AI 工具白名单+审计** 均为内建能力，无需额外中间件；你只需要按第 6 节清单把“默认值”逐个改成“生产值”。

### 请求如何流动（排查问题时的地图）

```
public/index.php → app/bootstrap.php（常量/autoloader/会话/记住登录）
                → app/routes.php（显式路由表）
                → Router::dispatch() → Controller → Model → Database(PDO/SQLite)
视图渲染由 Controller::view() 完成（先缓冲视图再套 layouts/main）
```

---

## 2. 环境要求

| 项目 | 最低 | 推荐 |
|---|---|---|
| PHP | 8.0+（代码用到 `match`/`str_contains`/箭头函数等 8.x 语法） | **8.1 或 8.2（LTS）** |
| PHP 扩展 | `pdo_sqlite`（必需） | + `openssl`（AI 走 https 必需）<br>+ `fileinfo`（附件按内容识别 MIME，无则降级按扩展名） |
| Web 服务器 | Apache 2.4 + `mod_rewrite` | Apache 或 Nginx（见第 5 节配置） |
| 数据库 | 无（内嵌 SQLite） | 无 |
| 磁盘/内存 | 常规 VPS/共享主机即可 | 生产建议独立虚机或容器 |

**无需**：Composer、Node、MySQL、memcached、mbstring（测试明确断言不依赖）。

PHP CLI 版本核验（部署机执行）：

```bash
php -v                       # 版本 ≥ 8.0
php -m | grep -i -E "pdo_sqlite|openssl|fileinfo"
```

---

## 3. 配置：`.env` 全部键位

优先级（从高到低）：**真实环境变量 > `.env` 文件 > 代码内默认值**。
`.env` 放在项目根（Web 根之外），建库脚本 `migrate.php` 与运行时读取逻辑一致。

| 键 | 默认 | 生产建议 | 说明 |
|---|---|---|---|
| `DB_PATH` | `database/crm.sqlite` | 显式写绝对路径更稳 | 相对路径基于项目根解析；`migrate.php` 与 Web 进程都读它 |
| `APP_ENV` | `development` | **`production`** | `development` 时 `display_errors=1` 且开放注册；`production` 时错误不外露 |
| `ALLOW_REGISTRATION` | 跟随环境 | **不设**（保持关闭） | `production` 下默认禁止公开注册；要开放自注册才设 `1` |
| `CRM_DEMO_DATA` | 灌演示数据 | **`0`** | 控制建库时是否插入样例商品/客户/线索/商机/订单 |
| `AI_ENABLED` | `0`（库内设置） | `0` 或按需 | AI 总开关；`1` 时 /ai 可用 |
| `AI_PROVIDER` | `mock` | 按采购 | `mock \| ollama \| openai \| deepseek \| moonshot \| dashscope \| zhipu \| mimo \| siliconflow \| custom` |
| `AI_MODEL` | 空（用服务商默认） | 按采购 | 例：`deepseek-v4-flash`、`gpt-4o-mini` |
| `AI_BASE_URL` | 空（用服务商官方地址） | 代理/中转时填 | 例：`https://api.openai.com/v1` |
| `AI_API_KEY` | — | **强烈建议放这里** | 放 .env 就不会落库；设置页填的会进 `app_settings` 表（不回显） |
| `AI_MODE` | `preview` | `preview` | `preview`=先预览后确认；`auto`=自动执行（删除类操作仍强制人工确认） |
| `AI_FAST_MODE` | `1` | 保持 | 服务商 thinking 开关 |
| `AI_ALLOW_DELETE` | 未设则取库内设置（默认开） | 按需设 `0` 关闭 AI 删除能力 | 环境变量优先于设置页 |
| `UPLOAD_PATH` | `public/uploads/attachments` | 不改（附件只有一个家） | 附件落盘目录；`/backup` 打包与恢复都读它（相对路径基于项目根） |
| `BACKUP_PATH` | `database/backups` | 换到大盘/异盘挂载点时改这里 | 后台“生成快照”的目录，也是导入时唯一能选服务器本地包的位置；已在 Web 根之外，另带 `.htaccess` 双保险 |

> 代码中还有 `URL_ROOT_OVERRIDE`，但它在 `app/config/config.php` 里（非 .env 键），通常保持空、由系统按 `SCRIPT_NAME` 自动探测即可。

**会话参数**（代码内固定，无需配置）：

- Session 名：`sancheng_crm_session`
- 记住登录 Cookie：`crm_remember_token`，有效期 30 天，每次使用即轮换（一次性凭据）
- 时区：**固定 Asia/Shanghai (UTC+8)**，与服务器时区无关（`bootstrap.php` 强制设置）

---

## 4. 全新生产安装（逐步）

以下命令全部在项目根目录执行，并把 `crm.example.com` / 路径替换成你的实际值。

### 4.1 准备目录与权限

```bash
# 假设代码已放到 /var/www/crm（用 git clone 或打包解压）
cd /var/www/crm

# 需要写的目录：数据库目录（SQLite + WAL）与附件上传目录
chown -R www-data:www-data database public/uploads      # 以你的 Web 用户为准
chmod -R u+rwX,go-w database public/uploads
# .env 只允许属主读，防止同机其他用户窥探密钥
touch .env && chmod 600 .env
```

### 4.2 写 .env

```bash
cp .env.example .env
```

编辑为：

```dotenv
DB_PATH=/var/www/crm/database/crm.sqlite
APP_ENV=production
CRM_DEMO_DATA=0
# ALLOW_REGISTRATION 不要设：production 下默认关闭公开注册

# AI（可选，采购了再填）
#AI_ENABLED=0
#AI_PROVIDER=deepseek
#AI_MODEL=deepseek-v4-flash
#AI_API_KEY=sk-xxxx        # 放这里，密钥不进数据库
#AI_MODE=preview
```

### 4.3 建库（干净库，不带演示数据）

```bash
php database/migrate.php --no-demo
php database/migrate.php --status      # 确认业务表齐全、增量迁移 [applied]
```

预期输出形如 `All 11 expected tables present. OK`（种子账号与系统设置始终会建；`--status` 会把全部业务表列出来核对）。

### 4.4 首次登录与“清场”

1. 用种子账号登录：`admin@example.com` / `password`（**种子密码是公开的，上线第一步就改**）。
2. 设置 → 个人资料：改密码、姓名。
3. 设置 → 应用信息：改成贵司公司名 / 版权主体 / 货币符号。
4. 给同事逐个创建账号（production 注册默认关闭，由管理员建号）。
5. 确认数据库文件不再包含任何演示业务数据：
   ```bash
   php -r 'define("BASE_PATH", getcwd()); define("APP_PATH", BASE_PATH."/app"); require APP_PATH."/core/autoloader.php"; $c=Schema::columns("products"); echo "products 列数: ".count($c).PHP_EOL;'
   # 更直接：登录后在 商品/客户/线索 各列表确认无样例行
   ```

### 4.5 指向 public/ 并冒烟

- Web 根指向 `public/`（Apache/Nginx 写法见第 5 节），访问 `https://crm.example.com/login`。
- 完整回归（可选但推荐在正式切换前跑一次；测试自带隔离数据库，不会碰生产库）：
  ```bash
  php tests/run.php
  ```

---

## 5. Web 服务器配置（Apache / Nginx）

### 5.1 推荐：docroot 指向 public/（Apache）

```apache
<VirtualHost *:443>
    ServerName crm.example.com
    DocumentRoot /var/www/crm/public

    SSLEngine on
    SSLCertificateFile      /etc/letsencrypt/live/crm.example.com/fullchain.pem
    SSLCertificateKeyFile   /etc/letsencrypt/live/crm.example.com/privkey.pem

    <Directory /var/www/crm/public>
        AllowOverride All            # 需要读 public/.htaccess（重写 + 禁点文件 + 禁上传目录执行）
        Require all granted
    </Directory>

    # 附加上传大小：应用允许 20MB 附件，PHP 默认 upload_max_filesize=2M 会先于应用报错
    php_value upload_max_filesize 24M
    php_value post_max_size        26M

    # 安全响应头（可选加固）
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</VirtualHost>
```

> Apache 自带的两个 `.htaccess`（项目根兜底版 + `public/` 版）已包含：静态文件直出、其余走 `index.php`、拒绝点文件、上传目录禁止执行脚本。`public/uploads/.htaccess` 用 `FilesMatch` 封死 php/phtml/cgi 等。

### 5.2 Nginx（`.htaccess` 不生效，需等价配置）

```nginx
server {
    listen 443 ssl http2;
    server_name crm.example.com;
    root /var/www/crm/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/crm.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/crm.example.com/privkey.pem;

    client_max_body_size 24m;          # 应用上限 20MB

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;   # 按实际 PHP-FPM 版本/路径改
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # 防御纵深：附件目录里即使出现 .php 也绝不执行
    location ~* ^/uploads/.*\.(php|phtml|php[0-9]|cgi|pl|sh)$ { deny all; }

    # 安全响应头
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options SAMEORIGIN always;
    add_header Strict-Transport-Security "max-age=31536000" always;
}
```

> Nginx 方案下请求会走 `try_files → index.php`，等价于 Apache 的 `RewriteRule`。因为 docroot 是 `public/`，`app/`、`database/`、`.env` 天然不在站点范围内，无需额外 location 屏蔽。

### 5.3 兜底拓扑：无法改 docroot 的共享主机

项目根的 `.htaccess` 会把一切请求重写到 `public/`。此拓扑下 `database/`、`.env` 的“安全”依赖 mod_rewrite 正常工作，**脆弱**。若只能用这种方式，请务必在虚拟主机配置或项目根追加显式拒绝：

```apache
# 项目根 .htaccess 追加（Apache 2.4）
RewriteRule ^(\.env|app|database|tests)(/|$) - [F,L]
<FilesMatch "^(\.env|\.git)">
    Require all denied
</FilesMatch>
```

**仍然强烈建议：能用 docroot 就用 docroot。**

---

## 6. 上线安全清单

对照逐项勾选（每一项在代码里都有对应实现，这里只是“生产化的开关”）：

| # | 事项 | 操作 |
|---|---|---|
| 1 | HTTPS | 全站强制 HTTPS；会话 Cookie 在 HTTPS 下会自动带 `Secure`（含反代 `X-Forwarded-Proto` 场景） |
| 2 | `APP_ENV=production` | 关闭 `display_errors`（避免路径/参数泄露）；见 4.2 |
| 3 | 公开注册 | 保持关闭（production 默认即关闭）；只由管理员建号 |
| 4 | 种子管理员 | `admin@example.com` 密码立即更换；若只用自建账号，可在用户表停用/删除种子行 |
| 5 | 演示数据 | `CRM_DEMO_DATA=0` / `--no-demo` 建库，并核验无样例行 |
| 6 | 密钥 | AI key 走 `.env`（`AI_API_KEY`），不填进设置页数据库 |
| 7 | 附件与上传 | 目录属主为 Web 用户；`upload_max_filesize/post_max_size` 调到 ≥ 24M，让应用 20MB 上限生效 |
| 8 | 数据库文件权限 | `database/` 属主为 Web 用户、目录不可被 Web 读取路径访问（docroot 之外即可） |
| 9 | 错误日志 | 见第 9 节——**生产下应用不写错误文件**，必须配置 PHP `error_log`，否则故障静默 |
| 10 | 回归 | 每次升级前后跑 `php tests/run.php`（自带隔离库） |
| 11 | 定时备份 | 见第 7 节，上线当天就挂 cron |

---

## 7. 备份与恢复

### 7.1 要备份什么

1. **数据库文件**（`database/crm.sqlite`，含 `-wal`/`-shm` 伴随文件）
2. **上传目录**（`public/uploads/attachments/`）
3. （可选）`.env`——保证能重建一致环境

> 这三样后台已经替你做好了：登录 → **备份与恢复**（`/backup`，仅管理员）一键导出一个 zip，
> 里面就是 `manifest.json` + `database/crm.sqlite` + `uploads/attachments/*`（+ 可选 `.env`）。
> 日常拿包、换机器、回滚都走 7.6；下面的命令行是“没人登录也能备”的那一层兵（cron）。

### 7.2 数据库在线备份（推荐：SQLite 官方 .backup，天然一致）

> SQLite 开启了 WAL（代码内 `PRAGMA journal_mode = WAL`），**不要直接 `cp` 正被使用的 .sqlite 文件**（会漏掉 WAL 里的提交）。用官方备份 API：

```bash
# 方式 A：服务器有 sqlite3 CLI
sqlite3 /var/www/crm/database/crm.sqlite ".backup '/backup/crm-$(date +%F-%H%M).sqlite'"

# 方式 B：没有 CLI 时，用 PHP 的 SQLite3::backup（也是安全快照）
php -r '
$src = new SQLite3("/var/www/crm/database/crm.sqlite");
$dst = new SQLite3("/backup/crm-" . date("Ymd-His") . ".sqlite");
$src->backup($dst, "main", "main");
echo "backup ok\n";
'
```

### 7.3 附件与整包

```bash
rsync -a --delete /var/www/crm/public/uploads/attachments/ /backup/attachments/
```

### 7.4 定时任务（cron）

```cron
# 每天 03:10 备份数据库，保留 14 份；03:12 同步附件
10 3 * * * root bash -lc 'd=/backup/crm/sqlite/$(date +%F); mkdir -p $d; sqlite3 /var/www/crm/database/crm.sqlite ".backup \"$d/db.sqlite\""; find /backup/crm/sqlite -mindepth 1 -maxdepth 1 -type d -mtime +14 -exec rm -rf {} +'
12 3 * * * root rsync -a --delete /var/www/crm/public/uploads/attachments/ /backup/crm/attachments/
```

> 备份文件放**另一台机器/另一块盘**才算备份。加密敏感：备份含客户个人数据，落盘加密或异地私有存储。

### 7.5 恢复演练（务必真做一次）

```bash
# 1) 停写或接受短暂不一致窗口
# 2) 用备份覆盖数据库文件（先停 Web 或先 cp 走当前文件）
cp /backup/crm/sqlite/2026-09-08/db.sqlite /var/www/crm/database/crm.sqlite
chown www-data:www-data /var/www/crm/database/crm.sqlite
# 3) 如备份与代码版本存在迁移差，执行升级而不是直接删：
php database/migrate.php --status   # 正常应显示已应用的增量迁移都在
# 4) 附件目录 rsync 回来
# 5) 冒烟：登录、看一条客户、看一个附件
```

> 数据库文件可以“低版本备份 + 新代码”直接起：`migrate.php` 的基线幂等自愈 + 增量迁移台账就是为此设计的（缺列自动补、已应用不重跑）。**恢复后数据库里的 `_migrations` 台账决定增量是否重放，别删那行表。**

### 7.6 后台一键导出 / 导入（日常用这个）

入口：侧边栏 **备份与恢复**（或 `/backup`），仅管理员可见 —— 因为备份包 = 整库明文 + 全部附件，
而导入能改写全站数据，这两个能力都比“改应用设置”更危险。

| 按钮 | 做什么 | 落在哪 |
| --- | --- | --- |
| 导出备份包 | 一致性快照 + 附件（+ 可选 `.env`）打成一个 zip 下载到本机 | 服务器不留副本（临时包发完即删） |
| 生成快照 | 同内容落一份到 `database/backups/`，可按“保留最近 N 份”自动清理旧的 | `database/backups/crm-backup-*.zip` |
| 仅检查这个包 | 只校验不写：报出包里的表数/行数/附件数/导出时间与版本差 | 不动任何数据 |
| 导入并覆盖 | 先生成回滚点，再覆写库 + 放回附件 + 自动补结构 | `database/backups/crm-pre-restore-*.sqlite` |

#### 「导出」和「生成快照」到底差在哪

两个按钮调的是**同一个** `Backup::createArchive()`：同一个 manifest、同一套校验、包内容一字不差。
区别只在包落在哪 —— 而落在哪，决定了它能干什么：

| | 导出备份包 | 生成快照 |
| ---|---|---|
| 包在哪 | 你的电脑 / 网盘（服务器发完即删） | 服务器的 `database/backups/` |
| 能直接拿来恢复吗 | 能，但要走上传 → **受 `upload_max_filesize` / `post_max_size` 限制** | 能，**不上传也不走网络**，多大都能导 |
| 占服务器磁盘 | 不占 | 占（所以有“保留最近 N 份”） |
| 防的是什么事故 | 防**这台机器没了**（磁盘、机房、整机丢失） | 防**写错了**（刚才导了一个不对的包） |
| 典型用途 | 换机器、交人带走、归档到异地 | 本机回滚、给 rsync/cron 同步到异地 |

> 一句话：**快照不是另一种备份，它就是备份留在服务器上的那一份。**
> 而“在本地”与“在服务器上”的区别，恰好决定了你能不能一键把它导回去。
> 两个都做才完整：只有快照 = 机器挂了一起没；只有导出 = 想回滚时得先把包再传上去。

几个刻意的设计，出问题时先回想它们：

- **不 cp 库文件。** 页面上的“导出”走 `SQLite3::backup`（没有 ext-sqlite3 时退到 `VACUUM INTO`），
  所以 `-wal` 里刚提交的内容一定在包里。看到“备份里没有刚才录的那条”就是有人在绕这个过程手工拷贝。
- **恢复是“原地覆写”，不是换文件。** Windows 上被 PDO 打开着的 sqlite 删不掉也改名不了，
  所以代码反向用 backup 把备份写进正在使用的库。你不必先停 Web。
- **恢复前自动留一个 `crm-pre-restore-*.sqlite`**，页面上直接给下载与“选作来源”。
  这类文件**永不被保留策略清理**；反悔就用它再导一次。
- **恢复是整体替换。** 备份之后新增的行会消失，备份里没有的表也会消失（测试里用一张临时表钉住了这一点）。
  想“两边合并”别点这个按钮。
- **附件默认只新增 / 同名覆盖**；“整目录替换”要显式勾选，且只删包里没有的文件。
- **`.env` 永不就地覆盖**：即使包里带了，也只另存为 `.env.restored-时间戳` 给你比对。
- **恢复后会清空 `remember_tokens`** 并踢掉当前设备的记住登录凭据（旧凭据不能走进恢复后的数据）。
  因此“新机行数比源机多 1/少 1”先查这里。
- **操作记录写在 `database/backups/history.jsonl`**（库外）——写在库里会被下一次恢复一起换掉。

### 7.7 换机器（本机 → 新服务器）完整步骤

```bash
# 旧机器：后台「生成快照」→ 得到 database/backups/crm-backup-YYYYmmdd-HHiiss-xxxxxx.zip
#（比“导出到本机”更适合转移：不受浏览器与上传限制影响，并且当场就能拿来当演练回滚点）

# 1) 新机器：先放代码（不要拷 .env / database/crm.sqlite / public/uploads）
cd /var/www && git clone <repo> crm && cd crm

# 2) 把包拷到新机器的备份目录（文件名保持原样，页面靠一条正则认它）
rsync -a user@old:/var/www/crm/database/backups/crm-backup-20260909-*.zip /var/www/crm/database/backups/

# 3) 新机器：配好 .env（至少 APP_ENV=production；CRM_DEMO_DATA=0 让建库不灌演示数据）
cp .env.example .env && vi .env
php database/migrate.php            # 先把空库建出来（后台导入需要一个已存在的库文件）

# 4) 面权：给 web 用户写权限（库目录要能写 -wal/-shm，备份目录要能写包）
chown -R www-data:www-data database public/uploads && chmod -R g+w database public/uploads

# 5) 浏览器登录新机器 → 备份与恢复 → 来源选「从服务器快照恢复」
#    → 只勾“数据库 + 附件”→ 仅检查这个包（看清楚行数对不对）
#    → 输“覆盖导入”→ 导入并覆盖
#
#    包比 post_max_size 大时不要走上传，上面这条路不经过 PHP 上传。

# 6) 冒烟：登录 → 客户列表数一数 → 开一个带附件的商机看图能不能显示 → /settings?tab=ai 确认 Key 还在
php database/migrate.php --status   # 自动补完结构后确认台账

# 7) 旧机器下线前再做一次（只为了带走最后几小时的数据）：重跑 5–6 步
```

回滚：导入页报错时，库**已经**被覆写的情况下用页面上点名的 `crm-pre-restore-*.sqlite` 再导一次；
没报错但发现“不对，我要的是上一版”，同样从快照列表里选那一份。（命令行恢复见 7.5。）

> 页面只能恢复**它自己生成的**文件名（`crm-backup-*.zip` 与 `crm-pre-restore-*.sqlite` 两种形状）。
> 手工拷进 `database/backups/` 的 `my-backup.zip` 不会出现
> 在列表里 —— 这不是 bug，是为了让“下载/删除/恢复”三个动作的参数永远不可能读到目录外的文件。
> 改名成 `crm-backup-Ymd-HHiiss-<6个十六进制>.zip` 就能用。

---

## 8. 升级 / 迁移

### 8.1 版本发布惯例

- 升级信息看仓库根 `CHANGELOG.md`（含数据库变更说明）。
- 结构变更只有两种落点，二者都由 `migrate.php` 统一处理：
  - **整张新表 / 索引 / 触发器** → 直接进 `database/schema.sql`（幂等，旧库自动补建）
  - **已有表加列等 ALTER** → 新增 `database/migrations/NNN_*.sql`，并同步更新 `schema.sql`（全新库由基线建齐，旧库由增量补齐）
- 增量文件执行一次后记录在数据库 `_migrations` 表，**改名已登记的文件等于让它重跑**——不要随意改名（2026-09 曾因 `017_xxx.sql.sql` 双后缀踩过，教训见 CHANGELOG/测试）。

### 8.2 升级步骤

```bash
cd /var/www/crm
# 1) 备份（第 7 节）—— 任何升级前必做
# 2) 拉新代码（排除 .env / database/*.sqlite* / public/uploads）
#    若用 git：git fetch && git checkout <版本tag>；再手工确认 .env 未被动
# 3) 看迁移状态
php database/migrate.php --status
# 4) 执行迁移（幂等，可重复跑）
php database/migrate.php
# 5) 回归
php tests/run.php
# 6) 冒烟关键路径：登录 → 客户列表 → 商机看板 → 新建商品 → /ai（若启用）
```

### 8.3 回滚

- 结构变更整体是**增量式**（只加不改不删），正常不需要回滚代码即可继续服务。
- 若新版本有严重缺陷要回代码：恢复 8.2 第 1 步的备份（代码 + 数据库一起回到上一个发布点），这是唯一干净的路径。**不要**只回代码文件而数据库已跑到新结构——结构向后兼容旧代码一般没问题（旧代码不认识新列），但若新代码改写了数据格式则必须整体回滚。

---

## 9. 日常运维与排障

### 9.1 先配好错误日志（重要）

`APP_ENV=production` 时 `display_errors=0`，**应用本身不写错误日志文件**。上线第一件事是让 PHP 把错误写到磁盘：

```ini
; php.ini（或 php-fpm pool）
log_errors = On
error_log = /var/log/php-crm-error.log
display_errors = Off
```

否则出现“500 Server Error / 白屏”时你将没有任何线索。

### 9.2 常见问题速查

| 症状 | 原因与处理 |
|---|---|
| `no such table: xxx` | 数据库没建/没升级：`php database/migrate.php`（幂等自愈，会补缺表缺列） |
| `duplicate column name` | 某增量文件本应在旧库补列，但列已在 → 通常是文件名/台账错位。查 `php database/migrate.php --status` 与 `database/migrations/` 是否一一对应 |
| 上传报“超过了服务器限制（最大2MB）” | PHP `upload_max_filesize` 只有 2M，而应用上限 20MB → 按第 5 节调到 ≥ 24M |
| `database is locked` / 偶发写入失败 | SQLite 单写者；WAL 已开。检查是否有长事务/慢查询，避免多进程同时高频写。见第 11 节容量边界 |
| 登录失败次数多了被锁 60 秒 | 内建节流，正常现象；成功登录即清除 |
| 500 但页面无任何信息 | `display_errors=0` 且没配 error_log → 按 9.1 配置后看日志 |
| 修改设置不生效 | 通过设置页修改会即时生效；若你手工改了数据库里 `app_settings` 行，重启 php-fpm（进程级缓存）再验证 |
| 时间显示不对 | 代码固定 Asia/Shanghai（UTC+8），与服务器时区无关；这是设计，不是 bug |

### 9.3 常规健康检查

```bash
php database/migrate.php --status          # 迁移台账与表齐全
curl -I https://crm.example.com/login      # 200/302 且带安全头
php tests/run.php                          # 全量回归（隔离库）
du -sh database/crm.sqlite public/uploads/attachments   # 关注增长速度
```

---

## 10. AI 助手生产注意事项

- **默认关闭**：`ai_enabled=0` 时 /ai 页与接口不可用、不发任何外部请求。要启用才开。
- **密钥路径**：优先 `AI_API_KEY`（.env），避免密钥落库；设置页填的 key 也不会回显到浏览器。
- **出口限制**：AI 端点强制 https（本机 localhost/Ollama 除外），这是代码层约束，无需额外配。
- **权限模型**：AI 侧强制 owner-or-admin 归属（界面“负责人”只是标签，不隔离；AI 才隔离）。给同事开账号前想清楚这层语义。
- **删除能力**：`ai_allow_delete`（默认开）。要对公网多账号开放时建议设 `0` 关掉 AI 删除，人工在界面删。
- **成本**：AI 是外购按量计费，key 是谁的就是谁付费。托管给客户时务必写进合同，否则变成你的成本。
- **审计**：所有 AI 对话/操作落在 `ai_actions` 表（含删除行快照）——这就是你的“操作留痕”，向客户承诺可审计时指给它看。

---

## 11. 容量边界与诚实说明

| 项 | 边界 | 建议 |
|---|---|---|
| 并发写入 | SQLite 单写者；WAL 缓解读写互斥 | 同办公室几十人日常使用无压力；**高并发重写**场景（多分支机构同时导入/批量改）会撞锁 |
| 数据量 | 单文件，百万行级仍可接受，但备份/写入会变慢 | 定期归档历史；关注 `database/crm.sqlite` 体积增长 |
| 多租户 | **不支持**——一套部署 = 一家公司，账号只有 admin/sales 两级 | 多客户托管需改造（tenant 隔离 + ACL），不在本文档范围 |
| 界面权限 | “登录即协作”：任意登录账号可见/可改全部业务数据（负责人是标签不是隔离） | 这是产品语义，不是 bug；向客户讲清楚，需要数据隔离先谈改造 |
| 附件 | 单文件 ≤ 20MB，类型白名单（jpg/png/gif/webp/pdf/xls/xlsx/ods/csv/zip/rar） | 大文件走网盘/对象存储不在本产品范围 |

如果客户画像超出以上任一行的“建议”列，请在报价前把它列为二期改造项，而不是承诺现状能满足。

---

## 12. 数据合规速览

- 系统存有客户个人数据（姓名、电话、邮箱、地址等），部署地区适用个保法/GDPR 时，需有：隐私说明、数据导出能力（CSV 导出内建）、删除流程（删除客户会级联删线索/商机/订单）。
- `ai_actions` 审计表会**保留被删记录的快照**（这是“留痕”设计，也是合规双刃剑）——对外提供服务时要在隐私政策里写明保留期与删除口径。
- 备份文件含个人数据：加密、限权、定销毁周期（第 7.4 节）。

---

## 13. 上线检查清单

```text
[ ] PHP ≥ 8.0 + pdo_sqlite/openssl/fileinfo 已装（php -m 核验）
[ ] .env：APP_ENV=production、CRM_DEMO_DATA=0、密钥走 .env
[ ] php database/migrate.php --no-demo && --status 正常
[ ] 种子 admin@example.com 密码已改（或账号已停用）
[ ] 应用信息（公司名/版权/货币）已改
[ ] Web 根 = public/；app/、database/、.env 在 Web 根外
[ ] HTTPS 强制 + 安全响应头已加
[ ] PHP upload_max_filesize/post_max_size ≥ 24M
[ ] PHP error_log 已配置且可写
[ ] 定时备份 cron 已挂，并完成一次恢复演练
[ ] 后台 /backup 能打开，“导出备份包”能下载成功（未挂 cron 前这就是当日备份）
[ ] 已真做一次“导出 → 另一台机器导入”演练（没跑过的备份不算备份）
[ ] php tests/run.php 全绿
[ ] 冒烟：登录/客户/商机看板/新建商品/附件上传/（AI 如启用）
[ ] CHANGELOG 对应版本已核对，无未完成迁移
```

---

*叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.*
