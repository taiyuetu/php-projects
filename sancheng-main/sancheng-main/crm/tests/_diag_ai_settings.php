<?php

/** 临时诊断脚本：读 AI 设置（Key 只显示是否已配置，不回显）。 */

$pdo = new PDO('sqlite:' . __DIR__ . '/../database/crm.sqlite');
foreach ($pdo->query("SELECT name, CASE WHEN name LIKE '%key%' THEN '[set:' || (value <> '') || ']' ELSE substr(value, 1, 80) END AS v FROM app_settings WHERE name LIKE 'ai_%'") as $r) {
    echo $r['key'], ' = ', $r['v'], PHP_EOL;
}
