<?php

declare(strict_types=1);

$tipo = $_GET['tipo'] ?? null;

if ($tipo === null) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Provocar errores - pruebas Cloud Logging</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; max-width: 760px; margin: 40px auto; padding: 0 16px; color: #1a1a1a; }
        h1 { font-size: 1.5rem; }
        p.intro { color: #555; }
        ul { list-style: none; padding: 0; }
        li { margin: 12px 0; padding: 16px; border: 1px solid #e2e2e2; border-radius: 10px; }
        a.btn { display: inline-block; font-weight: 600; text-decoration: none; color: #fff; background: #2563eb; padding: 8px 14px; border-radius: 8px; }
        a.btn.warn { background: #d97706; }
        a.btn.danger { background: #dc2626; }
        .desc { color: #555; margin-top: 8px; font-size: 0.92rem; }
        code { background: #f3f4f6; padding: 1px 5px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Provocar errores intencionalmente</h1>
    <p class="intro">Haz clic en cada botón para generar el error y luego revísalo en Cloud Logging.</p>
    <ul>
        <li>
            <a class="btn" href="?tipo=fatal">Fatal error (500)</a>
            <div class="desc">Llama a una función inexistente. Log: <code>PHP Fatal error: Uncaught Error: Call to undefined function</code></div>
        </li>
        <li>
            <a class="btn" href="?tipo=exception">Excepción no capturada (500)</a>
            <div class="desc">Lanza una excepción sin try/catch. Log: <code>Uncaught RuntimeException</code></div>
        </li>
        <li>
            <a class="btn" href="?tipo=error500">HTTP 500 forzado</a>
            <div class="desc">Devuelve status 500 sin fatal de PHP. No genera log de error PHP.</div>
        </li>
        <li>
            <a class="btn warn" href="?tipo=timeout">Timeout de ejecución</a>
            <div class="desc">Bucle que supera <code>max_execution_time</code>. Consume CPU unos segundos.</div>
        </li>
        <li>
            <a class="btn warn" href="?tipo=memory">Memory limit de PHP</a>
            <div class="desc">Agota el <code>memory_limit</code> de PHP. Log: <code>Allowed memory size ... exhausted</code></div>
        </li>
        <li>
            <a class="btn danger" href="?tipo=oom" onclick="return confirm('Esto puede matar el pod (OOMKilled) y reiniciar la app. ¿Continuar?');">OOMKilled (mata el pod)</a>
            <div class="desc">Supera el límite de memoria del contenedor. NO genera log PHP; el pod se reinicia. Se ve en <code>kubectl describe pod</code>.</div>
        </li>
    </ul>
</body>
</html>
    <?php
    return;
}

header('Content-Type: text/plain; charset=utf-8');

switch ($tipo) {
    case 'fatal':
        funcionQueNoExiste();
        break;

    case 'exception':
        throw new RuntimeException('Excepción de prueba lanzada a propósito');

    case 'error500':
        http_response_code(500);
        echo 'Error 500 forzado';
        break;

    case 'timeout':
        set_time_limit(3);
        $x = 0.0;
        while (true) {
            for ($i = 0; $i < 1000000; $i++) {
                $x += sqrt($i);
            }
        }
        break;

    case 'memory':
        ini_set('memory_limit', '64M');
        $data = [];
        while (true) {
            $data[] = str_repeat('x', 1024 * 1024);
        }
        break;

    case 'oom':
        ini_set('memory_limit', '-1');
        $data = [];
        while (true) {
            $data[] = str_repeat('x', 10 * 1024 * 1024);
        }
        break;

    default:
        http_response_code(400);
        echo 'Tipo de error desconocido: ' . $tipo;
}
