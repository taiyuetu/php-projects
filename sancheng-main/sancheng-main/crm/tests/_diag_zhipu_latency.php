<?php

/**
 * 临时诊断脚本：实测智谱 glm-5.3-flash 各参数组合的耗时（跑完可删）。
 * Key 只在进程内使用，绝不打印。
 */

$pdo = new PDO('sqlite:' . __DIR__ . '/../database/crm.sqlite');
$cfg = [];
foreach ($pdo->query("SELECT name, value FROM app_settings WHERE name LIKE 'ai_%'") as $r) {
    $cfg[$r['name']] = (string) $r['value'];
}
$key = $cfg['ai_api_key'] ?? '';
if ($key === '') {
    echo "没有配置 ai_api_key，无法实测\n";
    exit(1);
}

define('APP_PATH', dirname(__DIR__) . '/app');
define('BASE_PATH', dirname(APP_PATH));
require APP_PATH . '/core/autoloader.php';
require APP_PATH . '/config/config.php';
require APP_PATH . '/core/helpers.php';
require APP_PATH . '/core/Database.php';
require APP_PATH . '/core/Model.php';
require APP_PATH . '/core/Fields.php';
require APP_PATH . '/core/Schema.php';
require APP_PATH . '/core/AppMap.php';

$sys = Ai::systemPrompt();
echo 'system prompt: ', strlen($sys), " bytes\n";

$call = function (array $payload) use ($key): array {
    $t0 = microtime(true);
    $ch = curl_init('https://open.bigmodel.cn/api/paas/v4/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $raw = curl_exec($ch);
    $ms = (int) round((microtime(true) - $t0) * 1000);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode((string) $raw, true);
    $usage = $json['usage'] ?? [];
    $err = $json['error']['message'] ?? ($status >= 400 ? substr((string) $raw, 0, 200) : '');
    return [$ms, $status, $usage, $err];
};

$small = [['role' => 'user', 'content' => '回复一个 JSON：{"ok":true}，不要其他内容。']];
$big = array_merge([['role' => 'system', 'content' => $sys]], [['role' => 'user', 'content' => '现在有多少商品']]);

$cases = [
    'A 小提示词 + 无额外参数'        => ['model' => 'glm-5.3-flash', 'messages' => $small, 'max_tokens' => 2048],
    'B 小提示词 + reasoning_effort=low' => ['model' => 'glm-5.3-flash', 'messages' => $small, 'max_tokens' => 2048, 'reasoning_effort' => 'low'],
    'C 大提示词 + reasoning_effort=low + json' => ['model' => 'glm-5.3-flash', 'messages' => $big, 'max_tokens' => 2048, 'reasoning_effort' => 'low', 'response_format' => ['type' => 'json_object']],
    'D 大提示词 + 无额外参数（深度思考默认）' => ['model' => 'glm-5.3-flash', 'messages' => $big, 'max_tokens' => 2048],
    'E 大提示词 + thinking disabled（5.3 应报错）' => ['model' => 'glm-5.3-flash', 'messages' => $big, 'max_tokens' => 2048, 'thinking' => ['type' => 'disabled']],
];

foreach ($cases as $label => $payload) {
    [$ms, $status, $usage, $err] = $call($payload);
    printf("%-46s %6dms  HTTP %d  prompt=%s completion=%s reasoning=%s%s\n",
        $label, $ms, $status,
        $usage['prompt_tokens'] ?? '-',
        $usage['completion_tokens'] ?? '-',
        $usage['completion_tokens_details']['reasoning_tokens'] ?? ($usage['reasoning_tokens'] ?? '-'),
        $err !== '' ? '  ERR: ' . $err : ''
    );
}
