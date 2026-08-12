<?php

namespace App\Services;

use App\Models\PushSubscription;

final class WebPushService
{
    private array $config;
    private PushSubscription $subscriptions;

    public function __construct(?PushSubscription $subscriptions = null)
    {
        $this->config = is_file(BASE_PATH . '/config/push.php') ? require BASE_PATH . '/config/push.php' : [];
        $this->subscriptions = $subscriptions ?? new PushSubscription();
    }

    public function publicKey(): string
    {
        return trim((string)($this->config['public_key'] ?? ''));
    }

    public function enabled(): bool
    {
        return $this->publicKey() !== '' && trim((string)($this->config['private_key_pem'] ?? '')) !== '';
    }

    public function sendToUser(int $userId): array
    {
        if (!$this->enabled()) {
            return ['sent' => 0, 'failed' => 0, 'disabled' => true];
        }
        $sent = 0;
        $failed = 0;
        foreach ($this->subscriptions->activeForUser($userId) as $subscription) {
            $status = $this->send((string)$subscription['endpoint']);
            if ($status >= 200 && $status < 300) {
                $sent++;
                $this->subscriptions->touchPushed((int)$subscription['id']);
            } else {
                $failed++;
                if (in_array($status, [404, 410], true)) {
                    $this->subscriptions->deactivate((int)$subscription['id']);
                }
            }
        }
        return ['sent' => $sent, 'failed' => $failed, 'disabled' => false];
    }

    private function send(string $endpoint): int
    {
        $headers = [
            'TTL: 60',
            'Content-Length: 0',
            'Authorization: vapid t=' . $this->jwt($endpoint) . ', k=' . $this->publicKey(),
            'Crypto-Key: p256ecdsa=' . $this->publicKey(),
        ];
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => '',
                'ignore_errors' => true,
                'timeout' => 8,
            ],
        ]);
        @file_get_contents($endpoint, false, $context);
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $match)) {
                return (int)$match[1];
            }
        }
        return 0;
    }

    private function jwt(string $endpoint): string
    {
        $audience = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
        $port = parse_url($endpoint, PHP_URL_PORT);
        if ($port) $audience .= ':' . $port;
        $header = $this->base64Url(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_UNESCAPED_SLASHES));
        $claims = $this->base64Url(json_encode([
            'aud' => $audience,
            'exp' => time() + 3600,
            'sub' => (string)($this->config['subject'] ?? 'mailto:admin@hongphongnb.com'),
        ], JSON_UNESCAPED_SLASHES));
        $input = $header . '.' . $claims;
        $privateKey = openssl_pkey_get_private((string)$this->config['private_key_pem']);
        if (!$privateKey || !openssl_sign($input, $der, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('KhÃ´ng kÃ½ Ä‘Æ°á»£c Web Push VAPID');
        }
        return $input . '.' . $this->base64Url($this->derToJose($der, 64));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function derToJose(string $der, int $partLength): string
    {
        $offset = 0;
        if (ord($der[$offset++]) !== 0x30) throw new \RuntimeException('Chá»¯ kÃ½ VAPID khÃ´ng há»£p lá»‡');
        $this->readLength($der, $offset);
        if (ord($der[$offset++]) !== 0x02) throw new \RuntimeException('Chá»¯ kÃ½ VAPID khÃ´ng há»£p lá»‡');
        $rLength = $this->readLength($der, $offset);
        $r = substr($der, $offset, $rLength);
        $offset += $rLength;
        if (ord($der[$offset++]) !== 0x02) throw new \RuntimeException('Chá»¯ kÃ½ VAPID khÃ´ng há»£p lá»‡');
        $sLength = $this->readLength($der, $offset);
        $s = substr($der, $offset, $sLength);
        return str_pad(ltrim($r, "\x00"), intdiv($partLength, 2), "\x00", STR_PAD_LEFT)
            . str_pad(ltrim($s, "\x00"), intdiv($partLength, 2), "\x00", STR_PAD_LEFT);
    }

    private function readLength(string $der, int &$offset): int
    {
        $length = ord($der[$offset++]);
        if ($length < 0x80) return $length;
        $bytes = $length & 0x7f;
        $length = 0;
        for ($i = 0; $i < $bytes; $i++) {
            $length = ($length << 8) | ord($der[$offset++]);
        }
        return $length;
    }
}
