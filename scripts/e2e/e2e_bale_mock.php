<?php

/**
 * Minimal Bale API mock for sandbox E2E testing.
 *
 * Accepts any POST, replies ok:true and appends the request body to
 * /tmp/ravand-e2e/bale.log so the E2E script can extract OTP codes.
 * Run: php e2e_bale_mock.php 8899 /tmp/ravand-e2e/bale.log
 */

$port = (int) ($argv[1] ?? 8899);
$logFile = $argv[2] ?? '/tmp/ravand-e2e/bale.log';

$server = stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
if ($server === false) {
    fwrite(STDERR, "mock: failed to bind: {$errstr}\n");
    exit(1);
}

fwrite(STDERR, "mock: listening on 127.0.0.1:{$port}\n");

while (true) {
    $client = @stream_socket_accept($server, 60);
    if ($client === false) {
        continue;
    }

    // Read request until the end of headers.
    $raw = '';
    while (($line = fgets($client)) !== false && $line !== "\r\n") {
        $raw .= $line;
    }

    $contentLength = 0;
    if (preg_match('/^Content-Length:\s*(\d+)/im', $raw, $m)) {
        $contentLength = (int) $m[1];
    }

    $body = $contentLength > 0 ? fread($client, $contentLength) : '';

    // Append the raw body (one line per request) to the log.
    file_put_contents($logFile, $body."\n", FILE_APPEND | LOCK_EX);

    $body = '{"ok":true,"result":[]}';

    fwrite(
        $client,
        "HTTP/1.1 200 OK\r\n".
        "Content-Type: application/json\r\n".
        'Content-Length: '.strlen($body)."\r\n".
        "Connection: close\r\n\r\n".
        $body,
    );
    fclose($client);
}
