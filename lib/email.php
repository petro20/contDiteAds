<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

/**
 * Cliente SMTP minimalista para uso com Hostinger Email.
 * Suporta SSL (porta 465) com AUTH LOGIN. Sem dependências externas.
 *
 * USO:
 *   email_enviar('destinatario@x.com', 'Assunto', '<p>Olá</p>');
 *
 * Retorna true em sucesso ou string com erro.
 */
function email_enviar(string $para, string $assunto, string $corpoHtml, ?string $corpoTexto = null): bool|string {
    if (SMTP_USER === '' || SMTP_PASS === '') {
        return 'SMTP não configurado (.env)';
    }

    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $secure = strtolower(SMTP_SECURE);

    $address = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $errno = 0; $errstr = '';
    $sock = @stream_socket_client($address, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$sock) {
        return "Conexão SMTP falhou: $errstr ($errno)";
    }
    stream_set_timeout($sock, 15);

    $read = function() use ($sock): string {
        $resp = '';
        while (($line = fgets($sock, 1024)) !== false) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $resp;
    };
    $send = function(string $cmd) use ($sock, $read): string {
        fwrite($sock, $cmd . "\r\n");
        return $read();
    };

    $banner = $read();
    if (!str_starts_with($banner, '220')) { fclose($sock); return "SMTP banner: $banner"; }

    $r = $send('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    if (!str_starts_with($r, '250')) { fclose($sock); return "EHLO: $r"; }

    if ($secure === 'tls') {
        $r = $send('STARTTLS');
        if (!str_starts_with($r, '220')) { fclose($sock); return "STARTTLS: $r"; }
        stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
        $r = $send('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if (!str_starts_with($r, '250')) { fclose($sock); return "EHLO pós-TLS: $r"; }
    }

    $r = $send('AUTH LOGIN');
    if (!str_starts_with($r, '334')) { fclose($sock); return "AUTH: $r"; }
    $r = $send(base64_encode(SMTP_USER));
    if (!str_starts_with($r, '334')) { fclose($sock); return "USER: $r"; }
    $r = $send(base64_encode(SMTP_PASS));
    if (!str_starts_with($r, '235')) { fclose($sock); return "PASS: $r"; }

    $r = $send('MAIL FROM:<' . SMTP_FROM_EMAIL . '>');
    if (!str_starts_with($r, '250')) { fclose($sock); return "MAIL FROM: $r"; }

    $r = $send('RCPT TO:<' . $para . '>');
    if (!str_starts_with($r, '250')) { fclose($sock); return "RCPT TO: $r"; }

    $r = $send('DATA');
    if (!str_starts_with($r, '354')) { fclose($sock); return "DATA: $r"; }

    $boundary = bin2hex(random_bytes(8));
    $headers = [
        'From: ' . sprintf('%s <%s>', SMTP_FROM_NAME, SMTP_FROM_EMAIL),
        'To: ' . $para,
        'Subject: =?UTF-8?B?' . base64_encode($assunto) . '?=',
        'MIME-Version: 1.0',
        'Date: ' . date('r'),
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    $body = implode("\r\n", $headers) . "\r\n\r\n";

    $texto = $corpoTexto ?? strip_tags($corpoHtml);
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($texto)) . "\r\n";

    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($corpoHtml)) . "\r\n";

    $body .= "--$boundary--\r\n.";

    $r = $send($body);
    if (!str_starts_with($r, '250')) { fclose($sock); return "Envio: $r"; }

    fwrite($sock, "QUIT\r\n");
    fclose($sock);
    return true;
}

/**
 * Renderiza um template (de templates_mensagem) com variáveis.
 * Não toca no banco — recebe o array já lido.
 */
function email_renderizar_template(string $corpo, array $vars): string {
    foreach ($vars as $k => $v) {
        $corpo = str_replace('{' . $k . '}', (string)$v, $corpo);
    }
    return $corpo;
}
