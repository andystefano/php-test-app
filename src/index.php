<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$fecha = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
if (str_contains($ip, ',')) {
    $ip = trim(explode(',', $ip)[0]);
}

$rawBody = file_get_contents('php://input') ?: '';
$parsedBody = null;

if ($rawBody !== '') {
    $json = json_decode($rawBody, true);
    $parsedBody = json_last_error() === JSON_ERROR_NONE ? $json : $rawBody;
}

$request = [
    'metodo'   => $_SERVER['REQUEST_METHOD'] ?? 'DESCONOCIDO',
    'uri'      => $_SERVER['REQUEST_URI'] ?? '/',
    'query'    => $_GET,
    'post'     => $_POST,
    'body'     => $parsedBody,
    'headers'  => obtenerHeaders(),
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
];

error_log(json_encode([
    'concepto'       => 'solicitud_http',
    'detalle'        => 'Petición recibida en index.php',
    'ip'             => $ip,
    'ubicacion'      => $_SERVER['REQUEST_URI'] ?? '/',
    'tipo_log_id'    => 1,
    'transaccion_id' => uniqid('req_', true),
    'fecha'          => $fecha,
    'request'        => $request,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo 'solicitud recibida';

function obtenerHeaders(): array
{
    $headers = [];

    foreach ($_SERVER as $clave => $valor) {
        if (str_starts_with($clave, 'HTTP_')) {
            $nombre = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($clave, 5)))));
            $headers[$nombre] = $valor;
        }
    }

    if (isset($_SERVER['CONTENT_TYPE'])) {
        $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
    }

    if (isset($_SERVER['CONTENT_LENGTH'])) {
        $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
    }

    return $headers;
}
