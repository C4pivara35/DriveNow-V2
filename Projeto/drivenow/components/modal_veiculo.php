<?php
require_once __DIR__ . '/../includes/VehicleImages.php';

// Verificar se estamos editando um veículo
$editando = false;
$veiculoEditando = null;
$imagensVeiculoEditando = [];

if (isset($_GET['editando']) && is_numeric($_GET['editando']) && isset($_SESSION['editando_veiculo'])) {
    $editando = true;
    $veiculoEditando = $_SESSION['editando_veiculo'];
    $veiculoId = $_GET['editando'];
    
    // Limpar da sessão após obter os dados
    unset($_SESSION['editando_veiculo']);

    $imagensVeiculoEditando = buscarImagensVeiculo($pdo, (int)$veiculoId);
}

// Buscar todos os estados
$stmt = $pdo->query("SELECT id, estado_nome, sigla FROM estado ORDER BY estado_nome");
$estados = $stmt->fetchAll();

// Buscar categorias se não estiverem definidas
if (!isset($categorias)) {
    $stmt = $pdo->query("SELECT id, categoria FROM categoria_veiculo ORDER BY categoria");
    $categorias = $stmt->fetchAll();
}

// Definir caminhos para uso no HTML
$basePath = '';
$scriptName = $_SERVER['SCRIPT_NAME'];

// Se estamos na pasta 'veiculo', ajustar o caminho base
if (strpos($scriptName, '/veiculo/') !== false) {
    $basePath = '../';
}

// Definir caminhos para uso no HTML
// Alterar o action do formulário com base em se estamos editando ou não
$formAction = $editando 
    ? $basePath . 'veiculo/atualizar_veiculo.php' 
    : $basePath . 'veiculo/adicionar_veiculo.php';
$verificarPlacaUrl = $basePath . 'veiculo/verificar_placa.php';
?>

<!-- MODAL DE ADICIONAR/EDITAR VEÍCULO -->
<div id="veiculoModal" class="dn-vehicle-modal fixed inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center hidden overflow-y-auto">
    <div class="dn-vehicle-modal-panel w-full max-w-3xl backdrop-blur-lg bg-white/10 border subtle-border rounded-3xl p-6 shadow-xl transform transition-all my-8">
        <div class="dn-vehicle-modal-header flex items-center gap-4 mb-6">
            <div class="p-3 rounded-2xl bg-indigo-500/30 text-white border border-indigo-400/30">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                    <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>
                    <path d="M7 17h10"/>
                    <circle cx="7" cy="17" r="2"/>
                    <path d="M17 17h2"/>
                    <circle cx="17" cy="17" r="2"/>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-white">
                <?= $editando ? 'Editar Veículo' : 'Cadastrar Novo Veículo' ?>
            </h3>
            <button type="button" onclick="closeVeiculoModal()" class="ml-auto text-white/70 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        
        <div id="modalVeiculoError" class="mb-6 bg-red-500/20 border border-red-400/30 text-white px-4 py-3 rounded-xl hidden"></div>
        <div id="modalVeiculoSuccess" class="mb-6 bg-green-500/20 border border-green-400/30 text-white px-4 py-3 rounded-xl hidden"></div>
        
        <form id="formAdicionarVeiculo" method="POST" action="<?= $formAction ?>" enctype="multipart/form-data" class="dn-vehicle-form space-y-5">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(obterCsrfToken()) ?>">
            <?php if ($editando): ?>
            <input type="hidden" name="veiculo_id" value="<?= $veiculoId ?>">
            <input type="hidden" name="acao" value="editar">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="relative">
                    <select 
                        id="veiculo_marca" 
                        name="veiculo_marca" 
                        class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer" 
                        required
                    >
                        <option value="">Selecione uma marca</option>
                        <option value="Chevrolet" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'Chevrolet') ? 'selected' : '' ?>>Chevrolet</option>
                        <option value="Fiat" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'Fiat') ? 'selected' : '' ?>>Fiat</option>
                        <option value="Ford" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'Ford') ? 'selected' : '' ?>>Ford</option>
                        <option value="Volkswagen" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'Volkswagen') ? 'selected' : '' ?>>Volkswagen</option>
                        <option value="Toyota" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'Toyota') ? 'selected' : '' ?>>Toyota</option>
                        <option value="Hyundai" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'Hyundai') ? 'selected' : '' ?>>Hyundai</option>
                        <option value="Honda" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'Honda') ? 'selected' : '' ?>>Honda</option>
                        <option value="Renault" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'Renault') ? 'selected' : '' ?>>Renault</option>
                        <option value="Nissan" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'Nissan') ? 'selected' : '' ?>>Nissan</option>
                        <option value="Peugeot" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'Peugeot') ? 'selected' : '' ?>>Peugeot</option>
                        <option value="Citroën" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'Citroën') ? 'selected' : '' ?>>Citroën</option>
                        <option value="Jeep" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'Jeep') ? 'selected' : '' ?>>Jeep</option>
                        <option value="Mitsubishi" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'Mitsubishi') ? 'selected' : '' ?>>Mitsubishi</option>
                        <option value="Kia" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'Kia') ? 'selected' : '' ?>>Kia</option>
                        <option value="Mercedes-Benz" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'Mercedes-Benz') ? 'selected' : '' ?>>Mercedes-Benz</option>
                        <option value="BMW" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'BMW') ? 'selected' : '' ?>>BMW</option>
                        <option value="Audi" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'Audi') ? 'selected' : '' ?>>Audi</option>
                        <option value="BYD" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'BYD') ? 'selected' : '' ?>>BYD</option>
                        <option value="Chery" <?= ($editando && $veiculoEditando['veiculo_marca'] === 'Chery') ? 'selected' : '' ?>>Chery</option>
                    </select>
                    <label 
                        for="veiculo_marca" 
                        class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                    >
                        Marca
                    </label>
                </div>

                <div class="relative">
                    <select 
                        id="veiculo_modelo" 
                        name="veiculo_modelo" 
                        class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer" 
                        required
                        data-selected="<?= $editando ? $veiculoEditando['veiculo_modelo'] : '' ?>"
                    >
                        <option value="">Selecione o modelo</option>
                    </select>
                    <label 
                        for="veiculo_modelo" 
                        class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                    >
                        Modelo
                    </label>
                </div>

                <div class="relative">
                    <input 
                        type="number" 
                        id="veiculo_ano" 
                        name="veiculo_ano" 
                        class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer" 
                        placeholder=" " 
                        min="1900" 
                        max="2026" 
                        required
                        value="<?= $editando ? $veiculoEditando['veiculo_ano'] : '' ?>"
                    >
                    <label 
                        for="veiculo_ano" 
                        class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                    >
                        Ano
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                <div class="relative mb-6"> <!-- Adicionado mb-6 para espaço para mensagem de erro -->
                    <input 
                        type="text" 
                        id="veiculo_placa" 
                        name="veiculo_placa" 
                        class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer transition-colors" 
                        placeholder=" " 
                        required 
                        maxlength="8"
                        oninput="this.value = this.value.toUpperCase();"
                        value="<?= $editando ? $veiculoEditando['veiculo_placa'] : '' ?>"
                        <?= $editando ? 'readonly' : '' ?>
                    >
                    <label 
                        for="veiculo_placa" 
                        class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                    >
                        Placa
                    </label>
                    <div id="placaError" class="absolute -bottom-9 left-0 text-xs text-red-400 opacity-0 transition-opacity duration-200">
                        Digite uma placa válida (Ex: ABC-1234 ou ABC1D23)
                    </div>
                </div>

                <div class="relative">
                    <input 
                        type="number" 
                        id="veiculo_km" 
                        name="veiculo_km" 
                        class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer" 
                        placeholder=" " 
                        min="0" 
                        required
                        value="<?= $editando ? $veiculoEditando['veiculo_km'] : '' ?>"
                    >
                    <label 
                        for="veiculo_km" 
                        class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                    >
                        Quilometragem
                    </label>
                </div>

                <div class="relative">
                    <select 
                        id="veiculo_cambio" 
                        name="veiculo_cambio" 
                        class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer" 
                        required
                    >
                        <option value="Automático" <?= ($editando && $veiculoEditando['veiculo_cambio'] === 'Automático') ? 'selected' : '' ?>>Automático</option>
                        <option value="Manual" <?= ($editando && $veiculoEditando['veiculo_cambio'] === 'Manual') ? 'selected' : '' ?>>Manual</option>
                        <option value="CVT" <?= ($editando && $veiculoEditando['veiculo_cambio'] === 'CVT') ? 'selected' : '' ?>>CVT</option>
                        <option value="Semi-automático" <?= ($editando && $veiculoEditando['veiculo_cambio'] === 'Semi-automático') ? 'selected' : '' ?>>Semi-automático</option>
                    </select>
                    <label 
                        for="veiculo_cambio" 
                        class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                    >
                        Câmbio
                    </label>
                </div>

                <div class="relative">
                    <select 
                        id="veiculo_combustivel" 
                        name="veiculo_combustivel" 
                        class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer" 
                        required
                    >
                        <option value="Gasolina" <?= ($editando && $veiculoEditando['veiculo_combustivel'] === 'Gasolina') ? 'selected' : '' ?>>Gasolina</option>
                        <option value="Álcool" <?= ($editando && $veiculoEditando['veiculo_combustivel'] === 'Álcool') ? 'selected' : '' ?>>Álcool</option>
                        <option value="Diesel" <?= ($editando && $veiculoEditando['veiculo_combustivel'] === 'Diesel') ? 'selected' : '' ?>>Diesel</option>
                        <option value="Flex" <?= ($editando && $veiculoEditando['veiculo_combustivel'] === 'Flex') ? 'selected' : '' ?>>Flex</option>
                        <option value="Elétrico" <?= ($editando && $veiculoEditando['veiculo_combustivel'] === 'Elétrico') ? 'selected' : '' ?>>Elétrico</option>
                        <option value="Híbrido" <?= ($editando && $veiculoEditando['veiculo_combustivel'] === 'Híbrido') ? 'selected' : '' ?>>Híbrido</option>
                    </select>
                    <label 
                        for="veiculo_combustivel" 
                        class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                    >
                        Combustível
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                <div class="relative">
                    <input 
                        type="number" 
                        id="veiculo_portas" 
                        name="veiculo_portas" 
                        class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer" 
                        placeholder=" " 
                        min="2" 
                        max="6" 
                        required
                        value="<?= $editando ? $veiculoEditando['veiculo_portas'] : '' ?>"
                    >
                    <label 
                        for="veiculo_portas" 
                        class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                    >
                        Número de Portas
                    </label>
                </div>

                <div class="relative">
                    <input 
                        type="number" 
                        id="veiculo_acentos" 
                        name="veiculo_acentos" 
                        class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer" 
                        placeholder=" " 
                        min="2" 
                        max="9" 
                        required
                        value="<?= $editando ? $veiculoEditando['veiculo_acentos'] : '' ?>"
                    >
                    <label 
                        for="veiculo_acentos" 
                        class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                    >
                        Número de Assentos
                    </label>
                </div>

                <div class="relative">
                    <select 
                        id="veiculo_tracao" 
                        name="veiculo_tracao" 
                        class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer" 
                        required
                    >
                        <option value="Dianteira" <?= ($editando && $veiculoEditando['veiculo_tracao'] === 'Dianteira') ? 'selected' : '' ?>>Dianteira</option>
                        <option value="Traseira" <?= ($editando && $veiculoEditando['veiculo_tracao'] === 'Traseira') ? 'selected' : '' ?>>Traseira</option>
                        <option value="4x4" <?= ($editando && $veiculoEditando['veiculo_tracao'] === '4x4') ? 'selected' : '' ?>>4x4</option>
                        <option value="AWD" <?= ($editando && $veiculoEditando['veiculo_tracao'] === 'AWD') ? 'selected' : '' ?>>AWD (Integral)</option>
                    </select>
                    <label 
                        for="veiculo_tracao" 
                        class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                    >
                        Tração
                    </label>
                </div>

                <div class="relative">
                    <input 
                        type="number" 
                        id="preco_diaria" 
                        name="preco_diaria" 
                        class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer" 
                        placeholder=" " 
                        min="50" 
                        step="0.01" 
                        required
                        value="<?= $editando ? $veiculoEditando['preco_diaria'] : '' ?>"
                    >
                    <label 
                        for="preco_diaria" 
                        class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                    >
                        Preço Diário (R$)
                    </label>
                </div>
            </div>

            <!-- NOVA SEÇÃO: Localização com seleção hierárquica -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <!-- Seleção de Estado -->
                <div class="relative">
                    <select 
                        id="estado_id" 
                        name="estado_id" 
                        class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer" 
                        required
                    >
                        <option value="">Selecione o estado</option>
                        <?php foreach ($estados as $estado): ?>
                            <option value="<?= $estado['id'] ?>" <?= ($editando && $veiculoEditando['estado_id'] == $estado['id']) ? 'selected' : '' ?>><?= htmlspecialchars($estado['estado_nome']) ?> (<?= $estado['sigla'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <label 
                        for="estado_id" 
                        class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                    >
                        Estado
                    </label>
                </div>

                <!-- Seleção de Cidade (será preenchido via JavaScript) -->
                <div class="relative">
                    <select 
                        id="cidade_id" 
                        name="cidade_id" 
                        class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer" 
                        required
                        disabled
                        data-selected="<?= $editando ? $veiculoEditando['cidade_id'] : '' ?>"
                    >
                        <option value="">Selecione primeiro o estado</option>
                    </select>
                    <label 
                        for="cidade_id" 
                        class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                    >
                        Cidade
                    </label>
                </div>

                <!-- Seleção de Local (será preenchido via JavaScript) -->
                <div class="relative">
                    <select 
                        id="local_id" 
                        name="local_id" 
                        class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer" 
                        required
                        disabled
                        data-selected="<?= $editando ? $veiculoEditando['local_id'] : '' ?>"
                    >
                        <option value="">Selecione primeiro a cidade</option>
                    </select>
                    <label 
                        for="local_id" 
                        class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                    >
                        Local
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-1 gap-5">
                <div class="relative">
                    <select 
                        id="categoria_veiculo_id" 
                        name="categoria_veiculo_id" 
                        class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer" 
                        required
                    >
                        <option value="">Selecione a categoria...</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?= $categoria['id'] ?>" <?= ($editando && $veiculoEditando['categoria_veiculo_id'] == $categoria['id']) ? 'selected' : '' ?>><?= htmlspecialchars($categoria['categoria']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label 
                        for="categoria_veiculo_id" 
                        class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                    >
                        Categoria
                    </label>
                </div>
            </div>

            <div class="relative">
                <textarea 
                    id="descricao" 
                    name="descricao" 
                    class="block w-full px-4 py-3 text-white bg-white/5 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 peer" 
                    placeholder=" " 
                    rows="4"
                ><?= $editando ? $veiculoEditando['descricao'] : '' ?></textarea>
                <label 
                    for="descricao" 
                    class="absolute left-3 top-3 text-white/70 transition-all duration-200 -translate-y-0 peer-focus:-translate-y-6 peer-focus:scale-75 peer-focus:text-indigo-300 peer-[:not(:placeholder-shown)]:-translate-y-6 peer-[:not(:placeholder-shown)]:scale-75 peer-[:not(:placeholder-shown)]:text-indigo-300"
                >
                    Descrição do Veículo
                </label>
                <small class="text-white/50 mt-1 block">Esta descrição será exibida para os clientes interessados no veículo.</small>
            </div>

            <div class="rounded-2xl bg-white/5 border subtle-border p-4 space-y-4">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                    <div>
                        <h4 class="text-white font-semibold">Imagens do veiculo</h4>
                        <p class="text-white/60 text-sm">Adicione de 1 a 5 fotos em JPG ou PNG.</p>
                    </div>
                    <span id="vehicleImageCounter" class="text-sm text-indigo-100 bg-indigo-500/20 border border-indigo-400/30 rounded-xl px-3 py-1">0/5</span>
                </div>

                <?php if ($editando && !empty($imagensVeiculoEditando)): ?>
                    <div id="existingVehicleImages" class="grid grid-cols-2 md:grid-cols-5 gap-3">
                        <?php foreach ($imagensVeiculoEditando as $imagemVeiculo): ?>
                            <label class="vehicle-existing-image relative block overflow-hidden rounded-xl border border-white/10 bg-slate-950/30" data-image-id="<?= (int)$imagemVeiculo['id'] ?>">
                                <img src="<?= htmlspecialchars($basePath . ltrim((string)$imagemVeiculo['imagem_url'], '/')) ?>" alt="Imagem do veiculo" class="h-28 w-full object-cover">
                                <span class="absolute inset-x-0 bottom-0 flex items-center gap-2 bg-black/70 px-2 py-2 text-xs text-white">
                                    <input type="checkbox" name="remover_imagens[]" value="<?= (int)$imagemVeiculo['id'] ?>" class="vehicle-remove-image h-4 w-4 rounded border-white/30 bg-white/10">
                                    Remover
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div>
                    <input
                        type="file"
                        id="veiculo_imagens"
                        name="veiculo_imagens[]"
                        accept="image/jpeg,image/png"
                        multiple
                        <?= $editando ? '' : 'required' ?>
                        class="block w-full text-sm text-white file:mr-4 file:rounded-xl file:border-0 file:bg-indigo-500 file:px-4 file:py-2 file:text-white hover:file:bg-indigo-600"
                    >
                    <div id="vehicleImagePreview" class="mt-3 grid grid-cols-2 md:grid-cols-5 gap-3"></div>
                </div>
            </div>

            <div class="dn-vehicle-modal-actions flex gap-3 pt-4">
                <button type="submit" id="btnSubmitVeiculo" class="flex-1 bg-indigo-500 hover:bg-indigo-600 text-white font-medium rounded-xl transition-colors border border-indigo-400/30 px-4 py-2 shadow-md hover:shadow-lg flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 mr-2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    <span><?= $editando ? 'Salvar Alterações' : 'Cadastrar Veículo' ?></span>
                </button>
                <button type="button" onclick="closeVeiculoModal()" class="flex-1 border border-white/20 text-white hover:bg-white/20 rounded-xl px-4 py-2 font-medium backdrop-blur-sm bg-white/5 hover:bg-white/10 shadow-md hover:shadow-lg flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 mr-2">
                        <path d="m12 19-7-7 7-7"></path>
                        <path d="M19 12H5"></path>
                    </svg>
                    <span>Cancelar</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($editando): ?>
    <script>
        // Script para pré-selecionar o modelo quando estiver editando
        document.addEventListener('DOMContentLoaded', function() {
            // Disparar o evento de mudança para carregar os modelos
            const marcaSelect = document.getElementById('veiculo_marca');
            if (marcaSelect) {
                setTimeout(function() {
                    const event = new Event('change');
                    marcaSelect.dispatchEvent(event);
                    
                    // Depois de carregar os modelos, selecionar o modelo adequado
                    setTimeout(function() {
                        const modeloSelect = document.getElementById('veiculo_modelo');
                        const modeloSelecionado = modeloSelect.getAttribute('data-selected');
                        if (modeloSelect && modeloSelecionado) {
                            // Procurar a opção com o texto igual ao modelo selecionado
                            for (let i = 0; i < modeloSelect.options.length; i++) {
                                if (modeloSelect.options[i].text === modeloSelecionado) {
                                    modeloSelect.selectedIndex = i;
                                    break;
                                }
                            }
                        }
                    }, 300);
                }, 100);
            }
            
            // Se estiverem usando campos de localização hierárquica, fazer o mesmo para eles
            // Exemplo para estado, cidade e local
            const estadoSelect = document.getElementById('estado_id');
            if (estadoSelect) {
                // Pré-selecionar o estado
                estadoSelect.value = '<?= $veiculoEditando['estado_id'] ?? '' ?>';
                
                // Disparar evento para carregar cidades
                setTimeout(function() {
                    const event = new Event('change');
                    estadoSelect.dispatchEvent(event);
                    
                    // Depois selecionar a cidade
                    setTimeout(function() {
                        const cidadeSelect = document.getElementById('cidade_id');
                        if (cidadeSelect) {
                            cidadeSelect.value = '<?= $veiculoEditando['cidade_id'] ?? '' ?>';
                            
                            // Disparar evento para carregar locais
                            const event = new Event('change');
                            cidadeSelect.dispatchEvent(event);
                            
                            // Selecionar o local
                            setTimeout(function() {
                                const localSelect = document.getElementById('local_id');
                                if (localSelect) {
                                    localSelect.value = '<?= $veiculoEditando['local_id'] ?? '' ?>';
                                }
                            }, 300);
                        }
                    }, 300);
                }, 100);
            }
        });
    </script>
<?php endif; ?>
