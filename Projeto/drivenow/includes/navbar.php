<?php
if (!function_exists('estaLogado')) {
    require_once __DIR__ . '/auth.php';
}

$navBasePath = $navBasePath ?? '';
if ($navBasePath !== '' && substr($navBasePath, -1) !== '/') {
    $navBasePath .= '/';
}

$navCurrent = $navCurrent ?? '';
$navFixed = (bool)($navFixed ?? true);

$navUsuario = getUsuario();
$navLogado = estaLogado();
$navEhAdmin = $navLogado && isAdmin();
$navShowMarketplaceAnchors = (bool)($navShowMarketplaceAnchors ?? false);
$navShowDashboardLink = (bool)($navShowDashboardLink ?? $navLogado);

$linkClass = static function (string $key) use ($navCurrent): string {
    $base = 'nav-pill inline-flex items-center px-3 py-2 rounded-full text-sm';

    return $navCurrent === $key
        ? $base . ' nav-pill-active text-white bg-indigo-500/80 border border-indigo-400/50 shadow-md'
        : $base . ' nav-pill-idle text-white/85 hover:text-white hover:bg-white/10 border border-transparent';
};

$mobileLinkClass = static function (string $key) use ($navCurrent): string {
    return $navCurrent === $key
        ? 'px-3 py-2 rounded-xl bg-indigo-500/25 border border-indigo-400/40 text-white font-medium'
        : 'px-3 py-2 rounded-xl text-white/85 hover:bg-white/10 transition-colors';
};

$adminDesktopClass = $navCurrent === 'admin'
    ? 'px-3 py-2 rounded-xl bg-red-500 text-white text-sm font-medium transition-colors border border-red-300/40 shadow-md'
    : 'px-3 py-2 rounded-xl bg-red-500/90 hover:bg-red-600 text-white text-sm font-medium transition-colors';

$adminMobileClass = $navCurrent === 'admin'
    ? 'px-3 py-2 rounded-xl bg-red-500/30 border border-red-400/40 text-red-100 font-medium'
    : 'px-3 py-2 rounded-xl bg-red-500/20 border border-red-400/30 text-red-200 hover:bg-red-500/30 transition-colors';

$homeUrl = $navBasePath . 'index.php';
$homeBuscaUrl = $homeUrl . '#busca';
$dashboardUrl = $navBasePath . 'vboard.php';
$browseUrl = $navBasePath . 'reserva/catalogo.php';
$profileUrl = $navBasePath . 'perfil/editar.php';
$mensagensUrl = $navBasePath . 'mensagens/mensagens.php';
$adminUrl = $navBasePath . 'admin/dadmin.php';
$loginUrl = $navBasePath . 'login.php';
$cadastroUrl = $navBasePath . 'cadastro.php';
$logoutUrl = $navBasePath . 'logout.php';

$wrapperClasses = $navFixed
    ? 'fixed top-0 left-0 right-0 z-50 backdrop-blur-md bg-slate-900/72 border-b subtle-border dn-nav-shell'
    : 'backdrop-blur-md bg-white/5 border subtle-border rounded-2xl mb-8 shadow-lg overflow-hidden dn-nav-shell';

$containerClasses = $navFixed ? 'max-w-7xl mx-auto px-4 sm:px-6' : 'container mx-auto px-4';
$rowHeightClasses = $navFixed ? 'h-20' : 'h-16';

$navPrimeiroNome = trim((string)($navUsuario['primeiro_nome'] ?? ''));
$navSegundoNome = trim((string)($navUsuario['segundo_nome'] ?? ''));
$navNomeExibicao = $navPrimeiroNome !== '' ? $navPrimeiroNome : 'Perfil';

$navIniciais = '';
if ($navPrimeiroNome !== '') {
    $navIniciais .= strtoupper(substr($navPrimeiroNome, 0, 1));
}
if ($navSegundoNome !== '') {
    $navIniciais .= strtoupper(substr($navSegundoNome, 0, 1));
}
if ($navIniciais === '') {
    $navIniciais = 'DN';
}
?>
<header class="<?= $wrapperClasses ?>">
    <div class="<?= $containerClasses ?>">
        <div class="<?= $rowHeightClasses ?> relative flex items-center justify-between gap-4">
            <a href="<?= htmlspecialchars($homeUrl) ?>" class="flex items-center text-xl font-bold text-white">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -15.43 122.88 122.88" fill="currentColor" class="h-6 w-6 mr-2 text-indigo-300">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M10.17,34.23c-10.98-5.58-9.72-11.8,1.31-11.15l2.47,4.63l5.09-15.83C21.04,5.65,24.37,0,30.9,0H96c6.53,0,10.29,5.54,11.87,11.87l3.82,15.35l2.2-4.14c11.34-0.66,12.35,5.93,0.35,11.62l1.95,2.99c7.89,8.11,7.15,22.45,5.92,42.48v8.14c0,2.04-1.67,3.71-3.71,3.71h-15.83c-2.04,0-3.71-1.67-3.71-3.71v-4.54H24.04v4.54c0,2.04-1.67,3.71-3.71,3.71H4.5c-2.04,0-3.71-1.67-3.71-3.71V78.2c0-0.2,0.02-0.39,0.04-0.58C-0.37,62.25-2.06,42.15,10.17,34.23zM30.38,58.7l-14.06-1.77c-3.32-0.37-4.21,1.03-3.08,3.89l1.52,3.69c0.49,0.95,1.14,1.64,1.9,2.12c0.89,0.55,1.96,0.82,3.15,0.87l12.54,0.1c3.03-0.01,4.34-1.22,3.39-4C34.96,60.99,33.18,59.35,30.38,58.7zM54.38,52.79h14.4c0.85,0,1.55,0.7,1.55,1.55s-0.7,1.55-1.55,1.55h-14.4c-0.85,0-1.55-0.7-1.55-1.55S53.52,52.79,54.38,52.79zM89.96,73.15h14.4c0.85,0,1.55,0.7,1.55,1.55s-0.7,1.55-1.55,1.55h-14.4c-0.85,0-1.55-0.7-1.55-1.55S89.1,73.15,89.96,73.15zM92.5,58.7l14.06-1.77c3.32-0.37,4.21,1.03,3.08,3.89l-1.52,3.69c-0.49,0.95-1.14,1.64-1.9,2.12c-0.89,0.55-1.96,0.82-3.15,0.87l-12.54,0.1c-3.03-0.01-4.34-1.22-3.39-4C87.92,60.99,89.7,59.35,92.5,58.7zM18.41,73.15h14.4c0.85,0,1.55,0.7,1.55,1.55s-0.7,1.55-1.55,1.55h-14.4c-0.85,0-1.55-0.7-1.55-1.55S17.56,73.15,18.41,73.15zM19.23,31.2h86.82l-3.83-15.92c-1.05-4.85-4.07-9.05-9.05-9.05H33.06c-4.97,0-7.52,4.31-9.05,9.05L19.23,31.2z"/>
                </svg>
                DriveNow
            </a>

            <nav class="hidden lg:flex absolute left-1/2 -translate-x-1/2 items-center gap-2 text-sm text-white/80">
                <a href="<?= htmlspecialchars($homeUrl) ?>" class="<?= $linkClass('home') ?>">Home</a>
                <a href="<?= htmlspecialchars($browseUrl) ?>" class="<?= $linkClass('veiculos') ?>">Veiculos</a>

                <?php if ($navShowMarketplaceAnchors): ?>
                    <a href="<?= htmlspecialchars($homeBuscaUrl) ?>" class="nav-pill nav-pill-idle inline-flex items-center px-3 py-2 rounded-full text-sm text-white/85 hover:text-white hover:bg-white/10 border border-transparent">Busca</a>
                <?php endif; ?>

                <?php if ($navLogado): ?>
                    <?php if (!empty($navShowDashboardLink)): ?>
                        <a href="<?= htmlspecialchars($dashboardUrl) ?>" class="<?= $linkClass('painel') ?>">Meu painel</a>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($mensagensUrl) ?>" class="<?= $linkClass('mensagens') ?>">Mensagens</a>
                <?php endif; ?>
            </nav>

            <div class="hidden md:flex items-center gap-2">
                <?php if (!$navLogado): ?>
                    <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn-dn-ghost px-4 py-2 rounded-xl border subtle-border text-white/90 hover:bg-white/10 transition-colors">Login</a>
                    <a href="<?= htmlspecialchars($cadastroUrl) ?>" class="btn-dn-primary px-4 py-2 rounded-xl bg-indigo-500 hover:bg-indigo-600 text-white font-medium transition-colors">Cadastro</a>
                <?php else: ?>
                    <?php if ($navEhAdmin): ?>
                        <a href="<?= htmlspecialchars($adminUrl) ?>" class="<?= $adminDesktopClass ?>">Admin</a>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($profileUrl) ?>" class="user-chip inline-flex items-center gap-2 px-2 py-1.5 text-white/95 transition-colors hover:bg-white/10 text-sm border subtle-border rounded-full bg-white/5">
                        <span class="user-avatar inline-flex h-8 w-8 items-center justify-center rounded-full bg-indigo-500 text-white text-xs font-semibold"><?= htmlspecialchars($navIniciais) ?></span>
                        <span class="pr-1 font-medium"><?= htmlspecialchars($navNomeExibicao) ?></span>
                    </a>
                    <a href="<?= htmlspecialchars($logoutUrl) ?>" class="btn-dn-ghost px-3 py-2 rounded-xl bg-slate-700/80 hover:bg-slate-600 text-white text-sm font-medium transition-colors border subtle-border">Sair</a>
                <?php endif; ?>
            </div>

            <button
                type="button"
                class="md:hidden inline-flex items-center justify-center p-2 rounded-lg border subtle-border text-white/90 hover:bg-white/10"
                data-global-navbar-toggle
                aria-expanded="false"
                aria-controls="globalNavbarMenu"
            >
                <span class="sr-only">Abrir menu</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        <div id="globalNavbarMenu" class="md:hidden hidden border-t subtle-border pb-4">
            <div class="pt-3 flex flex-col gap-1 text-sm">
                <a href="<?= htmlspecialchars($homeUrl) ?>" class="<?= $mobileLinkClass('home') ?>">Home</a>
                <a href="<?= htmlspecialchars($browseUrl) ?>" class="<?= $mobileLinkClass('veiculos') ?>">Veiculos</a>

                <?php if ($navShowMarketplaceAnchors): ?>
                    <a href="<?= htmlspecialchars($homeBuscaUrl) ?>" class="<?= $mobileLinkClass('home') ?>">Busca</a>
                <?php endif; ?>

                <?php if ($navLogado): ?>
                    <?php if (!empty($navShowDashboardLink)): ?>
                        <a href="<?= htmlspecialchars($dashboardUrl) ?>" class="<?= $mobileLinkClass('painel') ?>">Meu painel</a>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($mensagensUrl) ?>" class="<?= $mobileLinkClass('mensagens') ?>">Mensagens</a>
                    <a href="<?= htmlspecialchars($profileUrl) ?>" class="<?= $mobileLinkClass('perfil') ?>">Minha conta</a>
                    <?php if ($navEhAdmin): ?>
                        <a href="<?= htmlspecialchars($adminUrl) ?>" class="<?= $adminMobileClass ?>">Admin</a>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($logoutUrl) ?>" class="mt-2 px-3 py-2 rounded-xl bg-slate-700/80 hover:bg-slate-600 text-white text-center font-medium border subtle-border">Sair</a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($loginUrl) ?>" class="mt-2 px-3 py-2 rounded-xl border subtle-border text-white/90 hover:bg-white/10 text-center">Login</a>
                    <a href="<?= htmlspecialchars($cadastroUrl) ?>" class="px-3 py-2 rounded-xl bg-indigo-500 hover:bg-indigo-600 text-white text-center font-medium border border-indigo-400/30">Cadastro</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<script>
(function () {
    const toggleButton = document.querySelector('[data-global-navbar-toggle]');
    const menu = document.getElementById('globalNavbarMenu');

    if (!toggleButton || !menu) {
        return;
    }

    toggleButton.addEventListener('click', function () {
        const isOpen = !menu.classList.contains('hidden');
        menu.classList.toggle('hidden', isOpen);
        toggleButton.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        if (!menu.classList.contains('hidden')) {
            menu.classList.add('hidden');
            toggleButton.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>
