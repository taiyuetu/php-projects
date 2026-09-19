<?php

/** 临时诊断：走应用自己的 AiClient::chat（PHP streams + 真实参数 + 真实消息）复现耗时。 */

define('APP_PATH', dirname(__DIR__) . '/app');
define('BASE_PATH', dirname(APP_PATH));
date_default_timezone_set('Asia/Shanghai');
$_SERVER['REQUEST_METHOD'] = 'GET';

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

$cfg = AiClient::config();
echo 'provider=', $cfg['provider'], ' model=', $cfg['model'], ' fast_mode=', var_export($cfg['fast_mode'], true),
    ' fast_params=', json_encode($cfg['fast_params']), ' max_tokens=', $cfg['max_tokens'],
    ' timeout=', $cfg['timeout'], ' json_mode=', var_export($cfg['json_mode'], true), PHP_EOL;

$messages = Ai::messages('现在有多少商品', 1);
echo 'user message: ', strlen($messages[1]['content']), " bytes\n";

$t0 = microtime(true);
$res = AiClient::chat($messages);
$ms = (int) round((microtime(true) - $t0) * 1000);
echo 'wall: ', $ms, "ms  client-latency: ", $res['latency_ms'], "ms  ok=", var_export($res['ok'], true), PHP_EOL;
echo 'error/notice: ', ($res['error'] ?? '') . ' ' . ($res['notice'] ?? ''), PHP_EOL;
echo 'usage: ', json_encode($res['usage'] ?? []), PHP_EOL;
echo 'reply head: ', mb_substr((string) ($res['content'] ?? ''), 0, 120), PHP_EOL;
