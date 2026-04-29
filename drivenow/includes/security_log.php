<?php

function registrarEventoSeguranca(string $evento, array $contexto = []): void
{
    try {
        $diretorioLogs = __DIR__ . '/../logs';

        if (!is_dir($diretorioLogs)) {
            mkdir($diretorioLogs, 0750, true);
        }

        $usuarioId = null;
        if (function_exists('getUsuario')) {
            $usuario = getUsuario();
            if (is_array($usuario) && isset($usuario['id'])) {
                $usuarioId = (int)$usuario['id'];
            }
        }

        $registro = [
            'timestamp' => date('c'),
            'evento' => preg_replace('/[^a-zA-Z0-9_.-]/', '_', $evento),
            'usuario_id' => $usuarioId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'contexto' => filtrarContextoSeguranca($contexto),
        ];

        $linha = json_encode($registro, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($linha === false) {
            $linha = json_encode([
                'timestamp' => date('c'),
                'evento' => 'security_log_encode_failed',
            ]);
        }

        error_log($linha . PHP_EOL, 3, $diretorioLogs . '/security.log');
    } catch (Throwable $erro) {
        error_log('Falha ao registrar evento de seguranca: ' . $erro->getMessage());
    }
}

function filtrarContextoSeguranca(array $contexto): array
{
    $bloqueados = ['token', 'senha', 'password', 'cvv', 'cartao', 'card', 'cnh', 'documento'];
    $filtrado = [];

    foreach ($contexto as $chave => $valor) {
        $chaveTexto = strtolower((string)$chave);
        $sensivel = false;

        foreach ($bloqueados as $termo) {
            if (str_contains($chaveTexto, $termo)) {
                $sensivel = true;
                break;
            }
        }

        if ($sensivel) {
            $filtrado[$chave] = '[redacted]';
            continue;
        }

        if (is_array($valor)) {
            $filtrado[$chave] = filtrarContextoSeguranca($valor);
            continue;
        }

        if (is_scalar($valor) || $valor === null) {
            $filtrado[$chave] = is_string($valor) ? substr($valor, 0, 500) : $valor;
        }
    }

    return $filtrado;
}
