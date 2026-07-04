<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$fecha = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
if (str_contains($ip, ',')) {
    $ip = trim(explode(',', $ip)[0]);
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'DESCONOCIDO';
$rawBody = file_get_contents('php://input') ?: '';
$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? null;

$bodyParsed = null;
if ($rawBody !== '') {
    $json = json_decode($rawBody, true);
    $bodyParsed = json_last_error() === JSON_ERROR_NONE ? $json : $rawBody;
}

$request = [
    'metodo'       => $metodo,
    'uri'          => $_SERVER['REQUEST_URI'] ?? '/',
    'query'        => $_GET,
    'post'         => $_POST,
    'content_type' => $contentType,
    'raw_body'     => $rawBody,
    'body'         => $bodyParsed,
    'headers'      => obtenerHeaders(),
    'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? null,
];

$detalle = $rawBody !== ''
    ? 'Petición recibida con body raw'
    : 'Petición recibida en index.php';

$payload = [
    'concepto'       => 'solicitud_http',
    'detalle'        => $detalle,
    'ip'             => $ip,
    'ubicacion'      => $_SERVER['REQUEST_URI'] ?? '/',
    'tipo_log_id'    => 1,
    'transaccion_id' => uniqid('req_', true),
    'fecha'          => $fecha,
    'request'        => $request,
];

$logLine = json_encode(
    $payload,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
);

if ($logLine === false) {
    $logLine = 'solicitud_http | error json_encode: ' . json_last_error_msg() . ' | raw_body=' . $rawBody;
}

error_log($logLine);

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
