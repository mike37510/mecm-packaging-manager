<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Minimal SMTP client (AUTH LOGIN, STARTTLS / SSL) — no external dependency.
 */
final class MailService
{
    public function __construct(
        private readonly MailConfigService $config,
    ) {
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): array
    {
        $cfg = $this->config->get();
        if (!$this->config->isEnabled()) {
            return ['ok' => false, 'error' => __('flash.mail_disabled')];
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => __('flash.mail_recipient_invalid')];
        }

        try {
            $this->smtpSend($cfg, $to, $subject, $textBody, $htmlBody);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string, message?: string}
     */
    public function sendTest(string $to): array
    {
        $subject = __('mail.test_subject');
        $body = __('mail.test_body');
        $result = $this->send($to, $subject, $body);
        if (!empty($result['ok'])) {
            $result['message'] = __('flash.mail_test_ok', ['email' => $to]);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private function smtpSend(array $cfg, string $to, string $subject, string $textBody, ?string $htmlBody): void
    {
        $host = (string) $cfg['host'];
        $port = (int) $cfg['port'];
        $encryption = (string) $cfg['encryption'];
        $username = (string) $cfg['username'];
        $password = (string) $cfg['password'];
        $fromEmail = (string) $cfg['from_email'];
        $fromName = (string) $cfg['from_name'];

        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            20,
            STREAM_CLIENT_CONNECT,
            stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ])
        );
        if ($socket === false) {
            throw new \RuntimeException(__('flash.mail_connect_failed', [
                'error' => trim($errstr !== '' ? $errstr : ('#' . $errno)),
            ]));
        }
        stream_set_timeout($socket, 20);

        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO mecm-packaging-manager.local', [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException(__('flash.mail_tls_failed'));
                }
                $this->command($socket, 'EHLO mecm-packaging-manager.local', [250]);
            }

            if ($username !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($username), [334]);
                $this->command($socket, base64_encode($password), [235]);
            }

            $this->command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);

            $headers = [
                'Date: ' . date('r'),
                'From: ' . $this->formatAddress($fromName, $fromEmail),
                'To: ' . $to,
                'Subject: ' . $this->encodeHeader($subject),
                'MIME-Version: 1.0',
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@mecm-local>',
            ];

            if ($htmlBody !== null && $htmlBody !== '') {
                $boundary = 'b_' . bin2hex(random_bytes(8));
                $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
                $body = "--{$boundary}\r\n"
                    . "Content-Type: text/plain; charset=UTF-8\r\n"
                    . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                    . $this->normalizeBody($textBody) . "\r\n"
                    . "--{$boundary}\r\n"
                    . "Content-Type: text/html; charset=UTF-8\r\n"
                    . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                    . $this->normalizeBody($htmlBody) . "\r\n"
                    . "--{$boundary}--\r\n";
            } else {
                $headers[] = 'Content-Type: text/plain; charset=UTF-8';
                $headers[] = 'Content-Transfer-Encoding: 8bit';
                $body = $this->normalizeBody($textBody) . "\r\n";
            }

            $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body;
            $payload = preg_replace('/^\./m', '..', $payload) ?? $payload;
            fwrite($socket, $payload . "\r\n.\r\n");
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221], false);
        } finally {
            fclose($socket);
        }
    }

    /**
     * @param resource $socket
     * @param list<int> $codes
     */
    private function command($socket, string $line, array $codes, bool $required = true): void
    {
        fwrite($socket, $line . "\r\n");
        if ($required) {
            $this->expect($socket, $codes);
        } else {
            try {
                $this->expect($socket, $codes);
            } catch (\Throwable) {
                // QUIT reply optional
            }
        }
    }

    /**
     * @param resource $socket
     * @param list<int> $codes
     */
    private function expect($socket, array $codes): void
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
            if (!isset($line[3])) {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            $snippet = trim(preg_replace('/\s+/', ' ', $response) ?? $response);
            throw new \RuntimeException(__('flash.mail_smtp_error', [
                'code' => (string) $code,
                'message' => $snippet !== '' ? $snippet : 'empty',
            ]));
        }
    }

    private function formatAddress(string $name, string $email): string
    {
        $name = trim($name);
        if ($name === '') {
            return $email;
        }

        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function normalizeBody(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);

        return str_replace("\n", "\r\n", $body);
    }
}
