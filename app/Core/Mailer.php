<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal raw-SMTP client — talks the protocol over a socket directly, so
 * sending mail needs no Composer dependency (this app has none). Handles
 * AUTH LOGIN + STARTTLS/implicit TLS, which covers typical providers
 * (Gmail, Mailgun, SES SMTP, ...). No CC/BCC — add if ever needed.
 *
 * Config comes from .env: MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD,
 * MAIL_ENCRYPTION ("tls"|"ssl"|"none"), MAIL_FROM, MAIL_FROM_NAME.
 */
final class Mailer
{
    /**
     * @param list<array{filename:string,content:string,mimeType:string}> $attachments
     * @param ?string $replyTo set when the technical sender (MAIL_FROM, this
     *   app's own verified account) isn't who should receive replies — e.g.
     *   InvoiceController::sendEmail() sets the organization's own address,
     *   since MAIL_FROM is a single shared relay every tenant sends through
     *   and can't itself be swapped per-tenant (most providers reject/flag
     *   a From they didn't authenticate as).
     */
    public static function send(
        string $to,
        string $subject,
        string $html,
        array $attachments = [],
        ?string $replyTo = null,
    ): bool {
        $host = (string) env('MAIL_HOST', '');
        if ($host === '') {
            return false;
        }

        $port       = (int) env('MAIL_PORT', 587);
        $user       = (string) env('MAIL_USERNAME', '');
        $pass       = (string) env('MAIL_PASSWORD', '');
        $encryption = strtolower((string) env('MAIL_ENCRYPTION', 'tls'));
        $fromEmail  = (string) env('MAIL_FROM', $user);
        $fromName   = (string) env('MAIL_FROM_NAME', app_name());

        $transport = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $socket    = @stream_socket_client("$transport$host:$port", $errno, $errstr, 10);
        if ($socket === false) {
            return false;
        }

        $helo = $_SERVER['SERVER_NAME'] ?? 'localhost';

        try {
            self::expect($socket, 220);
            self::command($socket, "EHLO $helo", 250);

            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    return false;
                }
                self::command($socket, "EHLO $helo", 250);
            }

            if ($user !== '') {
                self::command($socket, 'AUTH LOGIN', 334);
                self::command($socket, base64_encode($user), 334);
                self::command($socket, base64_encode($pass), 235);
            }

            self::command($socket, "MAIL FROM:<$fromEmail>", 250);
            self::command($socket, "RCPT TO:<$to>", 250);
            self::command($socket, 'DATA', 354);

            $headers = "From: $fromName <$fromEmail>\r\n"
                . "To: <$to>\r\n"
                . ($replyTo !== null ? "Reply-To: <$replyTo>\r\n" : '')
                . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
                . "MIME-Version: 1.0\r\n";

            $message = $attachments === []
                ? $headers . "Content-Type: text/html; charset=UTF-8\r\n\r\n" . $html
                : self::multipart($headers, $html, $attachments);

            $body = preg_replace('/^\./m', '..', $message); // dot-stuffing, RFC 5321 §4.5.2
            self::command($socket, $body . "\r\n.", 250);
            self::command($socket, 'QUIT', 221);

            return true;
        } catch (\RuntimeException) {
            return false;
        } finally {
            fclose($socket);
        }
    }

    /** @param list<array{filename:string,content:string,mimeType:string}> $attachments */
    private static function multipart(string $headers, string $html, array $attachments): string
    {
        $boundary = 'b' . bin2hex(random_bytes(16));

        $parts = "--$boundary\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html));

        foreach ($attachments as $file) {
            $parts .= "--$boundary\r\n"
                . "Content-Type: {$file['mimeType']}; name=\"{$file['filename']}\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . "Content-Disposition: attachment; filename=\"{$file['filename']}\"\r\n\r\n"
                . chunk_split(base64_encode($file['content']));
        }

        $parts .= "--$boundary--";

        return $headers . "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n" . $parts;
    }

    /** @param resource $socket */
    private static function command($socket, string $line, int $expect): void
    {
        fwrite($socket, $line . "\r\n");
        self::expect($socket, $expect);
    }

    /** @param resource $socket */
    private static function expect($socket, int $code): void
    {
        do {
            $line = fgets($socket, 512);
            if ($line === false) {
                throw new \RuntimeException('SMTP connection closed unexpectedly');
            }
        } while (isset($line[3]) && $line[3] === '-'); // multi-line reply, keep reading

        if ((int) substr($line, 0, 3) !== $code) {
            throw new \RuntimeException("Unexpected SMTP response: $line");
        }
    }
}
