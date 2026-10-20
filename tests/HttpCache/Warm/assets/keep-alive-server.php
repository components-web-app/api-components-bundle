<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$log = $argv[1];
$server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
if (false === $server) {
    fwrite(\STDERR, $errorMessage);
    exit(1);
}
fwrite(\STDOUT, parse_url('tcp://' . stream_socket_get_name($server, false), \PHP_URL_PORT) . "\n");
fflush(\STDOUT);

$clients = [];
$buffers = [];
$connections = 0;
while (true) {
    $read = [$server, ...$clients];
    $write = $except = null;
    if (false === stream_select($read, $write, $except, null)) {
        exit(1);
    }
    foreach ($read as $stream) {
        if ($stream === $server) {
            $client = stream_socket_accept($server);
            if (false !== $client) {
                $id = ++$connections;
                $clients[$id] = $client;
                $buffers[$id] = '';
            }
            continue;
        }
        $id = array_search($stream, $clients, true);
        $data = fread($stream, 8192);
        if (false === $data || ('' === $data && feof($stream))) {
            fclose($stream);
            unset($clients[$id], $buffers[$id]);
            continue;
        }
        $buffers[$id] .= $data;
        while (false !== $end = strpos($buffers[$id], "\r\n\r\n")) {
            $head = substr($buffers[$id], 0, $end);
            $buffers[$id] = substr($buffers[$id], $end + 4);
            $close = 1 === preg_match('/^connection:\s*close\s*$/mi', $head);
            file_put_contents($log, $id . "\n", \FILE_APPEND);
            $body = '{"redirectPath":"/elsewhere"}';
            fwrite($stream, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n" . ($close ? "Connection: close\r\n" : '') . "\r\n" . $body);
            if ($close) {
                fclose($stream);
                unset($clients[$id], $buffers[$id]);
                break;
            }
        }
    }
}
