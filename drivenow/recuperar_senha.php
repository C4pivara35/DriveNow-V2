<?php
require_once 'includes/auth.php';
require_once 'includes/security_log.php';

$erro = '';
$sucesso = '';
$linkRedefinicaoLocal = '';
$mostrarFormRedefinicao = false;
$tokenRedefinicao = '';
$csrfToken = obterCsrfToken();

function ambienteLocalReset(): bool
{
    return in_array(strtolower((string)envValor('APP_ENV', 'production')), ['local', 'dev', 'development'], true);
}

function linkResetVisivelLocalmente(): bool
{
    return ambienteLocalReset() && envBooleano('PASSWORD_RESET_SHOW_LINK', false);
}

function tokenResetValido(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return null;
    }

    global $pdo;

    try {
        $stmt = $pdo->prepare(
            "SELECT pr.id AS reset_id, pr.conta_usuario_id, cu.e_mail
             FROM password_resets pr
             INNER JOIN conta_usuario cu ON cu.id = pr.conta_usuario_id
             WHERE pr.token_hash = ?
               AND pr.used_at IS NULL
               AND pr.expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute([hash('sha256', $token)]);
        $reset = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Erro ao validar token de redefinicao de senha: ' . $e->getMessage());
        return null;
    }

    return $reset ?: null;
}

function criarLinkRedefinicao(string $token): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = strtok((string)($_SERVER['REQUEST_URI'] ?? '/recuperar_senha.php'), '?');

    return $scheme . '://' . $host . $path . '?token=' . urlencode($token);
}

function enviarLinkRedefinicao(string $email, string $link): bool
{
    $assunto = 'Redefinicao de senha - DriveNow';
    $mensagem = "Recebemos uma solicitacao para redefinir sua senha no DriveNow.\n\n"
        . "Use o link abaixo em ate 30 minutos:\n"
        . $link . "\n\n"
        . "Se voce nao solicitou esta alteracao, ignore esta mensagem.";

    return @mail($email, $assunto, $mensagem, "From: no-reply@drivenow.local\r\n");
}

$tokenGet = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
if ($tokenGet !== '') {
    if (tokenResetValido($tokenGet)) {
        $mostrarFormRedefinicao = true;
        $tokenRedefinicao = $tokenGet;
    } else {
        $erro = 'Link de redefinicao invalido ou expirado.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfToken($_POST['csrf_token'] ?? '')) {
        $erro = 'Nao foi possivel validar sua sessao. Tente novamente.';
    } elseif (isset($_POST['email'])) {
        $email = trim((string)$_POST['email']);

        if ($email === '') {
            $erro = 'O e-mail e obrigatorio.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'E-mail invalido.';
        } else {
            $sucesso = 'Se o e-mail estiver cadastrado, voce recebera um link para redefinir sua senha.';

            try {
                $stmt = $pdo->prepare('SELECT id, e_mail FROM conta_usuario WHERE e_mail = ? LIMIT 1');
                $stmt->execute([$email]);
                $usuarioReset = $stmt->fetch();

                if ($usuarioReset) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                    $expiresAt = (new DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s');

                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        'UPDATE password_resets
                         SET used_at = NOW()
                         WHERE conta_usuario_id = ? AND used_at IS NULL'
                    );
                    $stmt->execute([(int)$usuarioReset['id']]);

                    $stmt = $pdo->prepare(
                        'INSERT INTO password_resets
                            (conta_usuario_id, token_hash, expires_at, requester_ip, user_agent)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        (int)$usuarioReset['id'],
                        $tokenHash,
                        $expiresAt,
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                    ]);

                    $pdo->commit();

                    $linkRedefinicao = criarLinkRedefinicao($token);

                    registrarEventoSeguranca('password_reset_requested', [
                        'conta_usuario_id' => (int)$usuarioReset['id'],
                    ]);

                    if (linkResetVisivelLocalmente()) {
                        $linkRedefinicaoLocal = $linkRedefinicao;
                    } elseif (!enviarLinkRedefinicao((string)$usuarioReset['e_mail'], $linkRedefinicao)) {
                        error_log('Falha ao enviar e-mail de redefinicao de senha para usuario_id=' . (int)$usuarioReset['id']);
                        registrarEventoSeguranca('password_reset_delivery_failed', [
                            'conta_usuario_id' => (int)$usuarioReset['id'],
                        ]);
                    }
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Erro na solicitacao de redefinicao de senha: ' . $e->getMessage());
            }
        }
    } elseif (isset($_POST['nova_senha'])) {
        $tokenPost = trim((string)($_POST['reset_token'] ?? ''));
        $novaSenha = (string)($_POST['nova_senha'] ?? '');
        $confirmarSenha = (string)($_POST['confirmar_senha'] ?? '');
        $reset = tokenResetValido($tokenPost);

        if (!$reset) {
            $erro = 'Link de redefinicao invalido ou expirado.';
        } elseif ($novaSenha === '') {
            $erro = 'A nova senha e obrigatoria.';
            $mostrarFormRedefinicao = true;
            $tokenRedefinicao = $tokenPost;
        } elseif ($novaSenha !== $confirmarSenha) {
            $erro = 'As senhas nao coincidem.';
            $mostrarFormRedefinicao = true;
            $tokenRedefinicao = $tokenPost;
        } elseif (mb_strlen($novaSenha) < 5) {
            $erro = 'A senha deve ter pelo menos 5 caracteres.';
            $mostrarFormRedefinicao = true;
            $tokenRedefinicao = $tokenPost;
        } else {
            try {
                $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);

                $pdo->beginTransaction();

                $stmt = $pdo->prepare('UPDATE conta_usuario SET senha = ? WHERE id = ?');
                $stmt->execute([$senhaHash, (int)$reset['conta_usuario_id']]);

                $stmt = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
                $stmt->execute([(int)$reset['reset_id']]);

                $pdo->commit();

                registrarEventoSeguranca('password_reset_completed', [
                    'conta_usuario_id' => (int)$reset['conta_usuario_id'],
                ]);

                $sucesso = 'Senha redefinida com sucesso! Voce ja pode fazer login com sua nova senha.';
                $mostrarFormRedefinicao = false;
                header('Refresh: 3; url=login.php');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Erro na redefinicao de senha: ' . $e->getMessage());
                $erro = 'Ocorreu um erro ao processar sua solicitacao. Por favor, tente novamente mais tarde.';
                $mostrarFormRedefinicao = true;
                $tokenRedefinicao = $tokenPost;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - DriveNow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/ui-modern.css">
    <style>
        .animate-pulse-15s { animation-duration: 15s; }
        .animate-pulse-20s { animation-duration: 20s; }
        .animate-pulse-25s { animation-duration: 25s; }
        .subtle-border {
            border-color: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="drivenow-modern min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-purple-950 text-white p-4 md:p-8 overflow-x-hidden flex items-center justify-center">

    <div class="fixed top-0 right-0 w-96 h-96 rounded-full bg-indigo-700 opacity-10 blur-3xl -z-10 animate-pulse animate-pulse-15s"></div>
    <div class="fixed bottom-0 left-0 w-80 h-80 rounded-full bg-purple-700 opacity-10 blur-3xl -z-10 animate-pulse animate-pulse-20s"></div>
    <div class="fixed top-1/3 left-1/4 w-64 h-64 rounded-full bg-slate-700 opacity-5 blur-3xl -z-10 animate-pulse animate-pulse-25s"></div>

    <div class="absolute top-4 left-4 md:top-8 md:left-8">
        <a href="login.php" class="flex items-center group text-white/70 hover:text-white transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 group-hover:-translate-x-1 transition-transform">
                <path d="m12 19-7-7 7-7"></path>
                <path d="M19 12H5"></path>
            </svg>
            <span>Voltar ao Login</span>
        </a>
    </div>

    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">DriveNow</h1>
            <p class="text-white/70">Recuperacao de senha</p>
        </div>

        <div class="section-shell backdrop-blur-lg bg-white/5 border subtle-border rounded-3xl p-8 shadow-xl">
            <?php if ($erro): ?>
                <div class="mb-6 bg-red-500/20 border border-red-400/30 text-white px-4 py-3 rounded-xl">
                    <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($sucesso): ?>
                <div class="mb-6 bg-green-500/20 border border-green-400/30 text-white px-4 py-3 rounded-xl">
                    <?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($linkRedefinicaoLocal): ?>
                <div class="mb-6 bg-blue-500/20 border border-blue-400/30 text-white px-4 py-3 rounded-xl break-words">
                    Link local de teste:
                    <a class="text-blue-200 underline" href="<?= htmlspecialchars($linkRedefinicaoLocal, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($linkRedefinicaoLocal, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </div>
            <?php endif; ?>

            <?php if (!$mostrarFormRedefinicao): ?>
                <p class="text-white/70 mb-6">Digite seu e-mail cadastrado para receber um link de recuperacao.</p>

                <form method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="relative">
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer"
                            placeholder=" "
                            required
                            value="<?= isset($_POST['email']) ? htmlspecialchars((string)$_POST['email'], ENT_QUOTES, 'UTF-8') : '' ?>"
                        >
                        <label
                            for="email"
                            class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                        >
                            Email
                        </label>
                    </div>

                    <button type="submit" class="btn-dn-primary w-full bg-indigo-500 hover:bg-indigo-600 text-white font-medium rounded-xl transition-colors border border-indigo-400/30 px-4 py-3 shadow-md hover:shadow-lg">
                        Enviar Link de Recuperacao
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="reset_token" value="<?= htmlspecialchars($tokenRedefinicao, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="relative">
                        <input
                            type="password"
                            id="nova_senha"
                            name="nova_senha"
                            class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer"
                            placeholder=" "
                            required
                        >
                        <label
                            for="nova_senha"
                            class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                        >
                            Nova Senha
                        </label>
                        <button
                            type="button"
                            class="absolute right-3 top-3 text-white/50 hover:text-white/80 transition-colors"
                            onclick="togglePasswordVisibility('nova_senha')"
                        >
                            Mostrar
                        </button>
                    </div>

                    <div class="relative">
                        <input
                            type="password"
                            id="confirmar_senha"
                            name="confirmar_senha"
                            class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer"
                            placeholder=" "
                            required
                        >
                        <label
                            for="confirmar_senha"
                            class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                        >
                            Confirmar Nova Senha
                        </label>
                        <button
                            type="button"
                            class="absolute right-3 top-3 text-white/50 hover:text-white/80 transition-colors"
                            onclick="togglePasswordVisibility('confirmar_senha')"
                        >
                            Mostrar
                        </button>
                    </div>

                    <button type="submit" class="btn-dn-primary w-full bg-indigo-500 hover:bg-indigo-600 text-white font-medium rounded-xl transition-colors border border-indigo-400/30 px-4 py-3 shadow-md hover:shadow-lg">
                        Redefinir Senha
                    </button>
                </form>
            <?php endif; ?>

            <div class="text-center text-white/70 pt-4 border-t border-white/10 mt-6">
                <p>Lembrou sua senha? <a href="./login.php" class="text-indigo-300 hover:text-indigo-200">Faca login</a></p>
            </div>
        </div>

        <div class="mt-8 text-center text-white/60 text-sm">
            <p>&copy; <script>document.write(new Date().getFullYear())</script> DriveNow. Todos os direitos reservados.</p>
        </div>
    </div>

    <script>
        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            input.type = input.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>
