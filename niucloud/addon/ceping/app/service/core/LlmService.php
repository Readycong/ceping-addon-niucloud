<?php

namespace addon\ceping\app\service\core;

use core\base\BaseCoreService;

/**
 * OpenAI兼容Chat Completions客户端
 */
class LlmService extends BaseCoreService
{
    public function chat(array $cfg, string $system, string $user, bool $json = true): string
    {
        $url = rtrim($cfg['ai_base_url'], '/') . '/chat/completions';
        $payload = [
            'model'       => $cfg['ai_model'],
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature' => 0.7,
            'timeout'     => (int)($cfg['ai_timeout'] ?? 30),
        ];
        if ($json) $payload['response_format'] = ['type' => 'json_object'];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => (int)($cfg['ai_timeout'] ?? 30) + 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $cfg['ai_api_key'],
            ],
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno) throw new \RuntimeException('LLM网络错误: ' . $err);
        $data = json_decode($body, true);
        if ($http >= 400) {
            throw new \RuntimeException('LLM接口错误(' . $http . '): ' . mb_substr($body, 0, 300));
        }
        return (string)($data['choices'][0]['message']['content'] ?? '');
    }
}
