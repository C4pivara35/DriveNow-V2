<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    if (!headers_sent()) {
        $isHttps = (
            !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
        ) || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            session_set_cookie_params(0, '/; samesite=Lax', '', $isHttps, true);
        }
    }

    session_start();
}

require_once __DIR__ . '/../config/db.php';

/**
 * Verifica se uma coluna existe na base atual.
 */
function colunaExisteNoSchema($tabela, $coluna) {
    static $cache = [];

    $chave = strtolower((string)$tabela . '.' . (string)$coluna);
    if (array_key_exists($chave, $cache)) {
        return $cache[$chave];
    }

    global $pdo;

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$tabela, $coluna]);
        $cache[$chave] = ((int)$stmt->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cache[$chave] = false;
    }

    return $cache[$chave];
}

/**
 * Retorna true quando a conta do usuario esta ativa.
 * Se a coluna opcional `ativo` nao existir, o comportamento padrao e permitir acesso.
 */
function contaUsuarioAtiva($usuario = null) {
    if (!colunaExisteNoSchema('conta_usuario', 'ativo')) {
        return true;
    }

    if ($usuario === null) {
        $usuario = getUsuario();
    }

    if (!$usuario || !isset($usuario['id'])) {
        return false;
    }

    if (isset($usuario['ativo'])) {
        return (int)$usuario['ativo'] === 1;
    }

    global $pdo;

    try {
        $stmt = $pdo->prepare('SELECT ativo FROM conta_usuario WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$usuario['id']]);
        return (int)$stmt->fetchColumn() === 1;
    } catch (PDOException $e) {
        // Em caso de erro de leitura, manter comportamento permissivo para evitar bloqueio indevido.
        return true;
    }
}

/**
 * Resolve caminho relativo considerando se o script atual esta na raiz.
 */
function resolverCaminhoRelativo($caminhoRaiz, $caminhoSubpasta) {
    $scriptDir = dirname($_SERVER['PHP_SELF'] ?? '');
    $isRootLevel = ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.');
    return $isRootLevel ? $caminhoRaiz : $caminhoSubpasta;
}

/**
 * Redireciona com notificacao opcional em sessao.
 */
function redirecionarComNotificacao($destino, $mensagem = null, $tipo = 'error') {
    if ($mensagem !== null && $mensagem !== '') {
        $_SESSION['notification'] = [
            'type' => $tipo,
            'message' => $mensagem,
        ];
    }

    header('Location: ' . $destino);
    exit;
}

/**
 * Verifica se esta logado, caso contrario redireciona para login.php
 * */
function verificarAutenticacao() {
    if (!estaLogado()) {
        $loginPath = resolverCaminhoRelativo('login.php', '../login.php');

        $redirect = ltrim((string)($_SERVER['REQUEST_URI'] ?? ''), '/');
        if ($redirect !== '') {
            $loginPath .= '?redirect=' . urlencode($redirect);
        }

        header('Location: ' . $loginPath);
        exit();
    }

    if (!contaUsuarioAtiva()) {
        fazerLogout();
        $loginPath = resolverCaminhoRelativo('login.php', '../login.php');
        $separador = (strpos($loginPath, '?') !== false) ? '&' : '?';
        header('Location: ' . $loginPath . $separador . 'msg=conta_bloqueada');
        exit();
    }
}

/**
 * Middleware simples de controle de acesso por perfil.
 * Perfis suportados: publico, logado, proprietario, admin.
 */
function exigirPerfil($perfil, $opcoes = []) {
    $perfil = strtolower((string)$perfil);

    if ($perfil === 'publico') {
        return;
    }

    if ($perfil === 'logado') {
        verificarAutenticacao();
        return;
    }

    if ($perfil === 'admin') {
        verificarAutenticacao();
        if (!isAdmin()) {
            $destino = $opcoes['redirect'] ?? resolverCaminhoRelativo('index.php', '../index.php');
            $mensagem = $opcoes['message'] ?? 'Acesso restrito a administradores.';
            redirecionarComNotificacao($destino, $mensagem, 'error');
        }
        return;
    }

    if ($perfil === 'proprietario') {
        verificarAutenticacao();
        if (!usuarioEhProprietario()) {
            $destino = $opcoes['redirect'] ?? resolverCaminhoRelativo('vboard.php', '../vboard.php');
            $mensagem = $opcoes['message'] ?? 'Acesso restrito a proprietários.';
            redirecionarComNotificacao($destino, $mensagem, 'error');
        }
        return;
    }

    verificarAutenticacao();
}

function usuarioPodeReservar() {
    $usuario = getUsuario();

    if (!$usuario) {
        return false;
    }

    return ($usuario['cadastro_completo'] == 1 && $usuario['status_docs'] == 'aprovado');
}

/**
 * Retorna (ou cria) um token CSRF para o usuário autenticado.
 */
function obterCsrfToken() {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) < 64) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Valida token CSRF enviado em formularios.
 */
function validarCsrfToken($token) {
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verifica se o usuario atual esta registrado como proprietario.
 */
function usuarioEhProprietario($usuario = null) {
    static $cache = [];

    if ($usuario === null) {
        $usuario = getUsuario();
    }

    if (!$usuario || !isset($usuario['id'])) {
        return false;
    }

    $usuarioId = (int)$usuario['id'];
    if ($usuarioId <= 0) {
        return false;
    }

    if (array_key_exists($usuarioId, $cache)) {
        return $cache[$usuarioId];
    }

    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT 1 FROM dono WHERE conta_usuario_id = ? LIMIT 1");
        $stmt->execute([$usuarioId]);
        $cache[$usuarioId] = (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $cache[$usuarioId] = false;
    }

    return $cache[$usuarioId];
}

/**
 * Verifica se o usuario atual possui ao menos um veiculo cadastrado.
 */
function usuarioTemVeiculos($usuario = null) {
    static $cache = [];

    if ($usuario === null) {
        $usuario = getUsuario();
    }

    if (!$usuario || !isset($usuario['id'])) {
        return false;
    }

    $usuarioId = (int)$usuario['id'];
    if ($usuarioId <= 0) {
        return false;
    }

    if (array_key_exists($usuarioId, $cache)) {
        return $cache[$usuarioId];
    }

    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT id FROM dono WHERE conta_usuario_id = ? LIMIT 1");
        $stmt->execute([$usuarioId]);
        $donoId = (int)$stmt->fetchColumn();

        if ($donoId <= 0) {
            $cache[$usuarioId] = false;
            return false;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM veiculo WHERE dono_id = ?");
        $stmt->execute([$donoId]);
        $cache[$usuarioId] = ((int)$stmt->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cache[$usuarioId] = false;
    }

    return $cache[$usuarioId];
}

/**
 * Verifica se o usuário atual é um administrador
 * 
 * Retorna true se o usuário for administrador, false caso contrário
 */
function isAdmin() {
    // Verificar se o usuário está logado
    if (!estaLogado()) {
        return false;
    }
    
    // Verificar se o usuário tem a flag de administrador
    $usuario = getUsuario();
    
    // Se o usuário tiver a flag is_admin = 1, então é administrador
    return isset($usuario['is_admin']) && $usuario['is_admin'] == 1;
}

/**
 * Verifica se o usuário é administrador e redireciona caso não seja
 */
function verificarAdmin() {
    if (!isAdmin()) {
        // Definir mensagem de erro
        // $_SESSION['notification'] = [
        //     'type' => 'error',
        //     'message' => 'Acesso negado! Você não tem permissões de administrador.'
        // ];
        
        // Redirecionar para a página inicial
        header('Location: ../index.php');
        exit;
    }
}

/**
 * Registra um novo usuário
 */
function registrarUsuario($primeiroNome, $segundoNome, $email, $senha) {
    global $pdo;
    
    // Verifica se o email já existe
    $stmt = $pdo->prepare("SELECT id FROM conta_usuario WHERE e_mail = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        return "Este e-mail já está cadastrado.";
    }
    
    // Hash da senha
    $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
    
    try {
        // Substitui a chamada da procedure por INSERT direto
        $stmt = $pdo->prepare("INSERT INTO conta_usuario (primeiro_nome, segundo_nome, e_mail, senha, data_de_entrada) VALUES (?, ?, ?, ?, CURDATE())");
        $stmt->execute([$primeiroNome, $segundoNome, $email, $senhaHash]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao registrar usuario: " . $e->getMessage());
        return "Nao foi possivel concluir seu cadastro agora. Tente novamente mais tarde.";
    }
}

// Força a puxar novamente as informacoes do banco de dados
function updateInfosUsuario() {
    global $pdo;
    
    if (!estaLogado()) {
        return false;
    }
    
    $usuario = getUsuario();
    
    // Busca os dados atualizados do usuário
    $stmt = $pdo->prepare("SELECT * FROM conta_usuario WHERE e_mail = ?");
    $stmt->execute([$usuario['e_mail']]);
    $usuarioAtualizado = $stmt->fetch();
    
    if ($usuarioAtualizado) {
        if (colunaExisteNoSchema('conta_usuario', 'ativo') && isset($usuarioAtualizado['ativo']) && (int)$usuarioAtualizado['ativo'] !== 1) {
            fazerLogout();
            return false;
        }

        // Remove a senha da sessão por segurança
        unset($usuarioAtualizado['senha']);
        
        // Atualiza a sessão com os novos dados
        $_SESSION['usuario'] = $usuarioAtualizado;
        return true;
    }
    
    return false;
}

/**
 * Faz login do usuário
 */
function fazerLogin($email, $senha) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM conta_usuario WHERE e_mail = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
    
    if ($usuario && password_verify($senha, $usuario['senha'])) {
        if (colunaExisteNoSchema('conta_usuario', 'ativo') && isset($usuario['ativo']) && (int)$usuario['ativo'] !== 1) {
            return 'Sua conta esta bloqueada. Entre em contato com o suporte.';
        }

        session_regenerate_id(true);

        // Remove a senha da sessão por segurança
        unset($usuario['senha']);
        
        $_SESSION['usuario'] = $usuario;
        return true;
    }
    
    return "E-mail ou senha incorretos.";
}

/**
 * Verifica se o usuário está logado
 */
function estaLogado() {
    return isset($_SESSION['usuario']);
}

/**
 * Retorna os dados do usuário logado
 */
function getUsuario() {
    return $_SESSION['usuario'] ?? null;
}

/**
 * Faz logout do usuário
 */
function fazerLogout() {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'] ?? '',
            $params['secure'] ?? false,
            $params['httponly'] ?? true
        );
    }

    session_destroy();
}
?>
