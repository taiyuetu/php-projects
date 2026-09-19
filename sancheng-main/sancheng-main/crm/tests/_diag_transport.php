<?php

/** 临时诊断：对比 curl 与 PHP streams 各种选项组合的耗时（跑完可删）。 */

$pdo = new PDO('sqlite:' . __DIR__ . '/../database/crm.sqlite');
$cfg = [];
foreach ($pdo->query('SELECT name, value FROM app_settings WHERE name LIKE \'ai_%\'') as $r) {
    $cfg[$r['name']] = (string) $r['value'];
}
$key = $cfg['ai_api_key'] ?? '';

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
require APP_PATH . '/core/Router.php';
require APP_PATH . '/core/Controller.php';

$messages = Ai::messages('现在有多少商品', 1);
$payload = [
    'model' => 'glm-5.3-flash',
    'messages' => $messages,
    'temperature' => 0.2,
    'stream' => false,
    'max_tokens' => 2048,
    'reasoning_effort' => 'low',
    'response_format' => ['type' => 'json_object'],
];
$content = json_encode($payload, JSON_UNESCAPED_UNICODE);

$curlOnce = function () use ($key, $content) {
    $t0 = microtime(true);
    $ch = curl_init('https://open.bigmodel.cn/api/paas/v4/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
        CURLOPT_POSTFIELDS => $content, CURLOPT_TIMEOUT => 150, CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [(int) round((microtime(true) - $t0) * 1000), $status];
};

$streamOnce = function (array $opts) use ($key, $content) {
    $t0 = microtime(true);
    $headers = ['Content-Type: application/json', 'Accept: application/json',
                'Authorization: Bearer ' . $key, 'Content-Length: ' . strlen($content)];
    if (!empty($opts['connection'])) {
        $headers[] = 'Connection: ' . $opts['connection'];
    }
    if (!empty($opts['ua'])) {
        $headers[] = 'User-Agent: ' . $opts['ua'];
    }
    $http = [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 150.0,
        'connect_timeout' => 8.0,
    ];
    if (isset($opts['protocol_version'])) {
        $http['protocol_version'] = $opts['protocol_version'];
    }
    $http['content'] = $content;
    $ctx = stream_context_create(['http' => $http]);
    $raw = @file_get_contents('https://open.bigmodel.cn/api/paas/v4/chat/completions', false, $ctx);
    $status = 0;
    foreach (($http_response_header ?? []) as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $line, $m)) {
            $status = (int) $m[1];
        }
    }
    return [(int) round((microtime(true) - $t0) * 1000), $status, strlen((string) $raw)];
};

echo "curl 基线: ", implode('ms ', $curlOnce()), PHP_EOL;
$variants = [
    'streams 1.1 + Connection: close（当前应用配置）' => ['protocol_version' => 1.1, 'connection' => 'close', 'ua' => APP_NAME . '/' . APP_VERSION . ' (AI assistant)'],
    'streams 1.1 + 不带 Connection 头'               => ['protocol_version' => 1.1],
    'streams 1.0'                                    => ['protocol_version' => 1.0, 'connection' => 'close'],
    'streams 1.1 + close + 无 UA'                     => ['protocol_version' => 1.1, 'connection' => 'close'],
];
foreach ($variants as $label => $opts) {
    [$ms, $status, $len] = $streamOnce($opts);
    echo $label, ": {$ms}ms HTTP {$status} ({$len} bytes)\n";
}
