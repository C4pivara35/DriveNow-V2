-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 26/04/2026 às 19:23
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `drivenow`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `administrador`
--

CREATE TABLE `administrador` (
  `id` int(11) NOT NULL,
  `conta_usuario_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `atributo`
--

CREATE TABLE `atributo` (
  `id` int(11) NOT NULL,
  `nome_atributo` varchar(100) DEFAULT NULL,
  `descricao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `atributos_veiculos`
--

CREATE TABLE `atributos_veiculos` (
  `veiculo_id` int(11) NOT NULL,
  `atributo_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `avaliacao_locatario`
--

CREATE TABLE `avaliacao_locatario` (
  `id` int(11) NOT NULL,
  `reserva_id` int(11) NOT NULL,
  `proprietario_id` int(11) NOT NULL COMMENT 'ID do proprietário que faz a avaliação',
  `locatario_id` int(11) NOT NULL COMMENT 'ID do locatário que está sendo avaliado',
  `nota` int(1) NOT NULL CHECK (`nota` between 1 and 5),
  `comentario` text DEFAULT NULL,
  `data_avaliacao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `avaliacao_proprietario`
--

CREATE TABLE `avaliacao_proprietario` (
  `id` int(11) NOT NULL,
  `reserva_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'ID do locatário que faz a avaliação',
  `proprietario_id` int(11) NOT NULL COMMENT 'ID do proprietário que está sendo avaliado',
  `nota` int(1) NOT NULL CHECK (`nota` between 1 and 5),
  `comentario` text DEFAULT NULL,
  `data_avaliacao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estrutura para tabela `avaliacao_veiculo`
--

CREATE TABLE `avaliacao_veiculo` (
  `id` int(11) NOT NULL,
  `reserva_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `veiculo_id` int(11) NOT NULL,
  `nota` int(1) NOT NULL CHECK (`nota` between 1 and 5),
  `comentario` text DEFAULT NULL,
  `data_avaliacao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estrutura para tabela `categoria_veiculo`
--

CREATE TABLE `categoria_veiculo` (
  `id` int(11) NOT NULL,
  `categoria` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estrutura para tabela `cidade`
--

CREATE TABLE `cidade` (
  `id` int(11) NOT NULL,
  `estado_id` int(11) NOT NULL,
  `cidade_nome` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estrutura para tabela `conta_usuario`
--

CREATE TABLE `conta_usuario` (
  `id` int(11) NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `primeiro_nome` varchar(50) DEFAULT NULL,
  `segundo_nome` varchar(50) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `e_mail` varchar(100) DEFAULT NULL,
  `senha` varchar(255) DEFAULT NULL,
  `data_de_entrada` date DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `cpf` varchar(14) DEFAULT NULL COMMENT 'CPF do usuário',
  `foto_cnh_frente` varchar(255) DEFAULT NULL COMMENT 'Caminho para foto da CNH (frente)',
  `foto_cnh_verso` varchar(255) DEFAULT NULL COMMENT 'Caminho para foto da CNH (verso)',
  `status_docs` enum('pendente','verificando','aprovado','rejeitado') NOT NULL DEFAULT 'pendente' COMMENT 'Status da verificação dos documentos',
  `data_verificacao` datetime DEFAULT NULL COMMENT 'Data da última verificação',
  `admin_verificacao` int(11) DEFAULT NULL COMMENT 'ID do admin que verificou os documentos',
  `tem_cnh` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Se o usuário possui CNH',
  `cadastro_completo` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Se o usuário completou o cadastro com documentos',
  `observacoes_docs` text DEFAULT NULL COMMENT 'Observações da verificação',
  `mensagens_nao_lidas` int(11) NOT NULL DEFAULT 0,
  `media_avaliacao_proprietario` decimal(3,1) DEFAULT NULL,
  `total_avaliacoes_proprietario` int(11) DEFAULT 0,
  `media_avaliacao_locatario` decimal(3,1) DEFAULT NULL,
  `total_avaliacoes_locatario` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dados iniciais publicos para ambiente demo
-- Senha das contas demo: password
--

INSERT INTO `conta_usuario` (`id`, `is_admin`, `ativo`, `primeiro_nome`, `segundo_nome`, `telefone`, `e_mail`, `senha`, `data_de_entrada`, `foto_perfil`, `cpf`, `foto_cnh_frente`, `foto_cnh_verso`, `status_docs`, `data_verificacao`, `admin_verificacao`, `tem_cnh`, `cadastro_completo`, `observacoes_docs`, `mensagens_nao_lidas`, `media_avaliacao_proprietario`, `total_avaliacoes_proprietario`, `media_avaliacao_locatario`, `total_avaliacoes_locatario`) VALUES
(1, 1, 1, 'Admin', 'Demo', NULL, 'admin@drivenow.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', CURDATE(), NULL, NULL, NULL, NULL, 'aprovado', NULL, NULL, 0, 1, NULL, 0, NULL, 0, NULL, 0),
(2, 0, 1, 'Usuario', 'Demo', NULL, 'usuario@drivenow.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', CURDATE(), NULL, NULL, NULL, NULL, 'aprovado', NULL, NULL, 1, 1, NULL, 0, NULL, 0, NULL, 0);

INSERT INTO `administrador` (`id`, `conta_usuario_id`) VALUES
(1, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `dono`
--

CREATE TABLE `dono` (
  `id` int(11) NOT NULL,
  `conta_usuario_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estrutura para tabela `estado`
--

CREATE TABLE `estado` (
  `id` int(11) NOT NULL,
  `estado_nome` varchar(100) NOT NULL,
  `sigla` char(2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estrutura para tabela `favoritos`
--

CREATE TABLE `favoritos` (
  `veiculo_id` int(11) NOT NULL,
  `conta_usuario_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico_pagamento`
--

CREATE TABLE `historico_pagamento` (
  `id` int(11) NOT NULL,
  `pagamento_id` int(11) NOT NULL,
  `status_anterior` varchar(20) DEFAULT NULL,
  `novo_status` varchar(20) NOT NULL,
  `observacao` text DEFAULT NULL,
  `data_alteracao` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estrutura para tabela `imagem`
--

CREATE TABLE `imagem` (
  `id` int(11) NOT NULL,
  `veiculo_id` int(11) DEFAULT NULL,
  `imagem_url` varchar(255) DEFAULT NULL,
  `imagem_ordem` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estrutura para tabela `indisponibilidade_veiculo`
--

CREATE TABLE `indisponibilidade_veiculo` (
  `id` int(11) NOT NULL,
  `veiculo_id` int(11) NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `motivo` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `local`
--

CREATE TABLE `local` (
  `id` int(11) NOT NULL,
  `cidade_id` int(11) NOT NULL,
  `nome_local` varchar(100) NOT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estrutura para tabela `log_verificacao_docs`
--

CREATE TABLE `log_verificacao_docs` (
  `id` int(11) NOT NULL,
  `conta_usuario_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `status_anterior` enum('pendente','verificando','aprovado','rejeitado') DEFAULT NULL,
  `novo_status` enum('pendente','verificando','aprovado','rejeitado') DEFAULT NULL,
  `data_alteracao` datetime DEFAULT current_timestamp(),
  `observacoes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `mensagem`
--

CREATE TABLE `mensagem` (
  `id` int(11) NOT NULL,
  `reserva_id` int(11) NOT NULL,
  `remetente_id` int(11) NOT NULL,
  `mensagem` text NOT NULL,
  `data_envio` datetime NOT NULL DEFAULT current_timestamp(),
  `lida` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamento`
--

CREATE TABLE `pagamento` (
  `id` int(11) NOT NULL,
  `reserva_id` int(11) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `metodo_pagamento` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pendente',
  `data_pagamento` datetime DEFAULT NULL,
  `data_criacao` datetime NOT NULL DEFAULT current_timestamp(),
  `comprovante_url` varchar(255) DEFAULT NULL,
  `codigo_transacao` varchar(100) DEFAULT NULL,
  `detalhes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estrutura para tabela `password_resets`
--

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conta_usuario_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `requester_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_password_resets_token_hash` (`token_hash`),
  KEY `idx_password_resets_user` (`conta_usuario_id`),
  KEY `idx_password_resets_expires_at` (`expires_at`),
  CONSTRAINT `fk_password_resets_user`
    FOREIGN KEY (`conta_usuario_id`) REFERENCES `conta_usuario` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estrutura para tabela `reserva`
--

CREATE TABLE `reserva` (
  `id` int(11) NOT NULL,
  `veiculo_id` int(11) DEFAULT NULL,
  `conta_usuario_id` int(11) DEFAULT NULL,
  `reserva_data` date DEFAULT NULL,
  `devolucao_data` date DEFAULT NULL,
  `diaria_valor` decimal(10,2) DEFAULT NULL,
  `taxas_de_uso` decimal(10,2) DEFAULT NULL,
  `taxas_de_limpeza` decimal(10,2) DEFAULT NULL,
  `valor_total` decimal(10,2) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pendente',
  `observacoes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estrutura para tabela `veiculo`
--

CREATE TABLE `veiculo` (
  `id` int(11) NOT NULL,
  `local_id` int(11) DEFAULT NULL,
  `categoria_veiculo_id` int(11) DEFAULT NULL,
  `dono_id` int(11) DEFAULT NULL,
  `veiculo_marca` varchar(50) DEFAULT NULL,
  `veiculo_modelo` varchar(100) DEFAULT NULL,
  `veiculo_ano` int(11) DEFAULT NULL,
  `veiculo_km` int(11) DEFAULT NULL,
  `veiculo_placa` varchar(50) DEFAULT NULL,
  `veiculo_cambio` varchar(50) DEFAULT NULL,
  `veiculo_combustivel` varchar(50) DEFAULT NULL,
  `veiculo_portas` int(11) DEFAULT NULL,
  `veiculo_acentos` int(11) DEFAULT NULL,
  `veiculo_tracao` varchar(50) DEFAULT NULL,
  `disponivel` tinyint(1) DEFAULT 1 COMMENT '0=Indisponível, 1=Disponível',
  `preco_diaria` decimal(10,2) NOT NULL DEFAULT 150.00,
  `descricao` text DEFAULT NULL,
  `media_avaliacao` decimal(3,1) DEFAULT NULL,
  `total_avaliacoes` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_dashboard_admin`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_dashboard_admin` (
`total_usuarios` bigint(21)
,`total_veiculos` bigint(21)
,`total_reservas` bigint(21)
,`docs_pendentes` bigint(21)
,`receita_total` decimal(32,2)
,`reservas_pendentes` bigint(21)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_reservas_completas`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_reservas_completas` (
`id` int(11)
,`status` varchar(20)
,`reserva_data` date
,`devolucao_data` date
,`valor_total` decimal(10,2)
,`locatario_nome` varchar(101)
,`locatario_email` varchar(100)
,`veiculo_completo` varchar(163)
,`nome_local` varchar(100)
,`cidade_nome` varchar(100)
,`proprietario_nome` varchar(101)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_stats_usuarios`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_stats_usuarios` (
`id` int(11)
,`nome_completo` varchar(101)
,`e_mail` varchar(100)
,`status_docs` enum('pendente','verificando','aprovado','rejeitado')
,`tem_cnh` tinyint(1)
,`total_reservas` bigint(21)
,`total_veiculos_cadastrados` bigint(21)
,`media_locatario` decimal(3,1)
,`media_proprietario` decimal(3,1)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_veiculos_disponiveis`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_veiculos_disponiveis` (
`id` int(11)
,`veiculo_marca` varchar(50)
,`veiculo_modelo` varchar(100)
,`veiculo_ano` int(11)
,`preco_diaria` decimal(10,2)
,`media_avaliacao` decimal(3,1)
,`total_avaliacoes` int(11)
,`descricao` text
,`categoria` varchar(100)
,`nome_local` varchar(100)
,`endereco` varchar(255)
,`cidade_nome` varchar(100)
,`estado_nome` varchar(100)
,`estado_sigla` char(2)
,`proprietario_nome` varchar(101)
,`media_avaliacao_proprietario` decimal(3,1)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_veiculos_por_categoria`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_veiculos_por_categoria` (
`categoria` varchar(100)
,`estado_nome` varchar(100)
,`cidade_nome` varchar(100)
,`total_veiculos` bigint(21)
,`preco_medio` decimal(14,6)
,`disponiveis` bigint(21)
);

-- --------------------------------------------------------

--
-- Estrutura para view `vw_dashboard_admin`
--
DROP TABLE IF EXISTS `vw_dashboard_admin`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_dashboard_admin`  AS SELECT (select count(0) from `conta_usuario` where `conta_usuario`.`is_admin` = 0) AS `total_usuarios`, (select count(0) from `veiculo`) AS `total_veiculos`, (select count(0) from `reserva`) AS `total_reservas`, (select count(0) from `conta_usuario` where `conta_usuario`.`status_docs` = 'pendente') AS `docs_pendentes`, (select sum(`reserva`.`valor_total`) from `reserva` where `reserva`.`status` = 'confirmada') AS `receita_total`, (select count(0) from `reserva` where `reserva`.`status` = 'pendente') AS `reservas_pendentes` ;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_reservas_completas`
--
DROP TABLE IF EXISTS `vw_reservas_completas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_reservas_completas`  AS SELECT `r`.`id` AS `id`, `r`.`status` AS `status`, `r`.`reserva_data` AS `reserva_data`, `r`.`devolucao_data` AS `devolucao_data`, `r`.`valor_total` AS `valor_total`, concat(`cu`.`primeiro_nome`,' ',`cu`.`segundo_nome`) AS `locatario_nome`, `cu`.`e_mail` AS `locatario_email`, concat(`v`.`veiculo_marca`,' ',`v`.`veiculo_modelo`,' ',`v`.`veiculo_ano`) AS `veiculo_completo`, `l`.`nome_local` AS `nome_local`, `c`.`cidade_nome` AS `cidade_nome`, concat(`prop`.`primeiro_nome`,' ',`prop`.`segundo_nome`) AS `proprietario_nome` FROM ((((((`reserva` `r` join `conta_usuario` `cu` on(`r`.`conta_usuario_id` = `cu`.`id`)) join `veiculo` `v` on(`r`.`veiculo_id` = `v`.`id`)) join `local` `l` on(`v`.`local_id` = `l`.`id`)) join `cidade` `c` on(`l`.`cidade_id` = `c`.`id`)) join `dono` `d` on(`v`.`dono_id` = `d`.`id`)) join `conta_usuario` `prop` on(`d`.`conta_usuario_id` = `prop`.`id`)) ;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_stats_usuarios`
--
DROP TABLE IF EXISTS `vw_stats_usuarios`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_stats_usuarios`  AS SELECT `cu`.`id` AS `id`, concat(`cu`.`primeiro_nome`,' ',`cu`.`segundo_nome`) AS `nome_completo`, `cu`.`e_mail` AS `e_mail`, `cu`.`status_docs` AS `status_docs`, `cu`.`tem_cnh` AS `tem_cnh`, count(distinct `r`.`id`) AS `total_reservas`, count(distinct `v`.`id`) AS `total_veiculos_cadastrados`, coalesce(`cu`.`media_avaliacao_locatario`,0) AS `media_locatario`, coalesce(`cu`.`media_avaliacao_proprietario`,0) AS `media_proprietario` FROM (((`conta_usuario` `cu` left join `reserva` `r` on(`cu`.`id` = `r`.`conta_usuario_id`)) left join `dono` `d` on(`cu`.`id` = `d`.`conta_usuario_id`)) left join `veiculo` `v` on(`d`.`id` = `v`.`dono_id`)) WHERE `cu`.`is_admin` = 0 GROUP BY `cu`.`id` ;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_veiculos_disponiveis`
--
DROP TABLE IF EXISTS `vw_veiculos_disponiveis`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_veiculos_disponiveis`  AS SELECT `v`.`id` AS `id`, `v`.`veiculo_marca` AS `veiculo_marca`, `v`.`veiculo_modelo` AS `veiculo_modelo`, `v`.`veiculo_ano` AS `veiculo_ano`, `v`.`preco_diaria` AS `preco_diaria`, `v`.`media_avaliacao` AS `media_avaliacao`, `v`.`total_avaliacoes` AS `total_avaliacoes`, `v`.`descricao` AS `descricao`, `cv`.`categoria` AS `categoria`, `l`.`nome_local` AS `nome_local`, `l`.`endereco` AS `endereco`, `c`.`cidade_nome` AS `cidade_nome`, `e`.`estado_nome` AS `estado_nome`, `e`.`sigla` AS `estado_sigla`, concat(`cu`.`primeiro_nome`,' ',`cu`.`segundo_nome`) AS `proprietario_nome`, `cu`.`media_avaliacao_proprietario` AS `media_avaliacao_proprietario` FROM ((((((`veiculo` `v` join `categoria_veiculo` `cv` on(`v`.`categoria_veiculo_id` = `cv`.`id`)) join `local` `l` on(`v`.`local_id` = `l`.`id`)) join `cidade` `c` on(`l`.`cidade_id` = `c`.`id`)) join `estado` `e` on(`c`.`estado_id` = `e`.`id`)) join `dono` `d` on(`v`.`dono_id` = `d`.`id`)) join `conta_usuario` `cu` on(`d`.`conta_usuario_id` = `cu`.`id`)) WHERE `v`.`disponivel` = 1 ;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_veiculos_por_categoria`
--
DROP TABLE IF EXISTS `vw_veiculos_por_categoria`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_veiculos_por_categoria`  AS SELECT `cv`.`categoria` AS `categoria`, `e`.`estado_nome` AS `estado_nome`, `c`.`cidade_nome` AS `cidade_nome`, count(`v`.`id`) AS `total_veiculos`, avg(`v`.`preco_diaria`) AS `preco_medio`, count(case when `v`.`disponivel` = 1 then 1 end) AS `disponiveis` FROM ((((`veiculo` `v` join `categoria_veiculo` `cv` on(`v`.`categoria_veiculo_id` = `cv`.`id`)) join `local` `l` on(`v`.`local_id` = `l`.`id`)) join `cidade` `c` on(`l`.`cidade_id` = `c`.`id`)) join `estado` `e` on(`c`.`estado_id` = `e`.`id`)) GROUP BY `cv`.`categoria`, `e`.`estado_nome`, `c`.`cidade_nome` ;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `administrador`
--
ALTER TABLE `administrador`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `conta_usuario_id` (`conta_usuario_id`);

--
-- Índices de tabela `atributo`
--
ALTER TABLE `atributo`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `atributos_veiculos`
--
ALTER TABLE `atributos_veiculos`
  ADD PRIMARY KEY (`veiculo_id`,`atributo_id`),
  ADD KEY `atributo_id` (`atributo_id`);

--
-- Índices de tabela `avaliacao_locatario`
--
ALTER TABLE `avaliacao_locatario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reserva_id` (`reserva_id`),
  ADD KEY `proprietario_id` (`proprietario_id`),
  ADD KEY `locatario_id` (`locatario_id`);

--
-- Índices de tabela `avaliacao_proprietario`
--
ALTER TABLE `avaliacao_proprietario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reserva_id` (`reserva_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `proprietario_id` (`proprietario_id`);

--
-- Índices de tabela `avaliacao_veiculo`
--
ALTER TABLE `avaliacao_veiculo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reserva_id` (`reserva_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `veiculo_id` (`veiculo_id`);

--
-- Índices de tabela `categoria_veiculo`
--
ALTER TABLE `categoria_veiculo`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `cidade`
--
ALTER TABLE `cidade`
  ADD PRIMARY KEY (`id`),
  ADD KEY `estado_id` (`estado_id`);

--
-- Índices de tabela `conta_usuario`
--
ALTER TABLE `conta_usuario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `e_mail` (`e_mail`);

--
-- Índices de tabela `dono`
--
ALTER TABLE `dono`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `conta_usuario_id` (`conta_usuario_id`);

--
-- Índices de tabela `estado`
--
ALTER TABLE `estado`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `favoritos`
--
ALTER TABLE `favoritos`
  ADD PRIMARY KEY (`veiculo_id`,`conta_usuario_id`),
  ADD KEY `conta_usuario_id` (`conta_usuario_id`);

--
-- Índices de tabela `historico_pagamento`
--
ALTER TABLE `historico_pagamento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pagamento_id` (`pagamento_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `imagem`
--
ALTER TABLE `imagem`
  ADD PRIMARY KEY (`id`),
  ADD KEY `veiculo_id` (`veiculo_id`);

--
-- Índices de tabela `indisponibilidade_veiculo`
--
ALTER TABLE `indisponibilidade_veiculo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_indisp_veiculo_periodo` (`veiculo_id`,`data_inicio`,`data_fim`);

--
-- Índices de tabela `local`
--
ALTER TABLE `local`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cidade_id` (`cidade_id`);

--
-- Índices de tabela `log_verificacao_docs`
--
ALTER TABLE `log_verificacao_docs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conta_usuario_id` (`conta_usuario_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Índices de tabela `mensagem`
--
ALTER TABLE `mensagem`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reserva_id` (`reserva_id`),
  ADD KEY `remetente_id` (`remetente_id`);

--
-- Índices de tabela `pagamento`
--
ALTER TABLE `pagamento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reserva_id` (`reserva_id`);

ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_password_resets_token_hash` (`token_hash`),
  ADD KEY `idx_password_resets_user` (`conta_usuario_id`),
  ADD KEY `idx_password_resets_expires_at` (`expires_at`);

--
-- Índices de tabela `reserva`
--
ALTER TABLE `reserva`
  ADD PRIMARY KEY (`id`),
  ADD KEY `veiculo_id` (`veiculo_id`),
  ADD KEY `conta_usuario_id` (`conta_usuario_id`);

--
-- Índices de tabela `veiculo`
--
ALTER TABLE `veiculo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `local_id` (`local_id`),
  ADD KEY `categoria_veiculo_id` (`categoria_veiculo_id`),
  ADD KEY `dono_id` (`dono_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `administrador`
--
ALTER TABLE `administrador`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `atributo`
--
ALTER TABLE `atributo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `avaliacao_locatario`
--
ALTER TABLE `avaliacao_locatario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `avaliacao_proprietario`
--
ALTER TABLE `avaliacao_proprietario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `avaliacao_veiculo`
--
ALTER TABLE `avaliacao_veiculo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `categoria_veiculo`
--
ALTER TABLE `categoria_veiculo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `cidade`
--
ALTER TABLE `cidade`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `conta_usuario`
--
ALTER TABLE `conta_usuario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `dono`
--
ALTER TABLE `dono`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `estado`
--
ALTER TABLE `estado`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `historico_pagamento`
--
ALTER TABLE `historico_pagamento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `imagem`
--
ALTER TABLE `imagem`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `indisponibilidade_veiculo`
--
ALTER TABLE `indisponibilidade_veiculo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `local`
--
ALTER TABLE `local`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `log_verificacao_docs`
--
ALTER TABLE `log_verificacao_docs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `mensagem`
--
ALTER TABLE `mensagem`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pagamento`
--
ALTER TABLE `pagamento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `reserva`
--
ALTER TABLE `reserva`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `veiculo`
--
ALTER TABLE `veiculo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `administrador`
--
ALTER TABLE `administrador`
  ADD CONSTRAINT `fk_admin_usuario` FOREIGN KEY (`conta_usuario_id`) REFERENCES `conta_usuario` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `atributos_veiculos`
--
ALTER TABLE `atributos_veiculos`
  ADD CONSTRAINT `atributos_veiculos_ibfk_1` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `atributos_veiculos_ibfk_2` FOREIGN KEY (`atributo_id`) REFERENCES `atributo` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `avaliacao_locatario`
--
ALTER TABLE `avaliacao_locatario`
  ADD CONSTRAINT `avaliacao_locatario_ibfk_1` FOREIGN KEY (`reserva_id`) REFERENCES `reserva` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `avaliacao_locatario_ibfk_2` FOREIGN KEY (`proprietario_id`) REFERENCES `conta_usuario` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `avaliacao_locatario_ibfk_3` FOREIGN KEY (`locatario_id`) REFERENCES `conta_usuario` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `avaliacao_proprietario`
--
ALTER TABLE `avaliacao_proprietario`
  ADD CONSTRAINT `avaliacao_proprietario_ibfk_1` FOREIGN KEY (`reserva_id`) REFERENCES `reserva` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `avaliacao_proprietario_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `conta_usuario` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `avaliacao_proprietario_ibfk_3` FOREIGN KEY (`proprietario_id`) REFERENCES `conta_usuario` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `avaliacao_veiculo`
--
ALTER TABLE `avaliacao_veiculo`
  ADD CONSTRAINT `avaliacao_veiculo_ibfk_1` FOREIGN KEY (`reserva_id`) REFERENCES `reserva` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `avaliacao_veiculo_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `conta_usuario` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `avaliacao_veiculo_ibfk_3` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculo` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `cidade`
--
ALTER TABLE `cidade`
  ADD CONSTRAINT `cidade_ibfk_1` FOREIGN KEY (`estado_id`) REFERENCES `estado` (`id`);

--
-- Restrições para tabelas `dono`
--
ALTER TABLE `dono`
  ADD CONSTRAINT `fk_dono_conta_usuario` FOREIGN KEY (`conta_usuario_id`) REFERENCES `conta_usuario` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `favoritos`
--
ALTER TABLE `favoritos`
  ADD CONSTRAINT `favoritos_ibfk_1` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favoritos_ibfk_2` FOREIGN KEY (`conta_usuario_id`) REFERENCES `conta_usuario` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `historico_pagamento`
--
ALTER TABLE `historico_pagamento`
  ADD CONSTRAINT `historico_pagamento_ibfk_1` FOREIGN KEY (`pagamento_id`) REFERENCES `pagamento` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `historico_pagamento_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `conta_usuario` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `imagem`
--
ALTER TABLE `imagem`
  ADD CONSTRAINT `imagem_ibfk_1` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculo` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `indisponibilidade_veiculo`
--
ALTER TABLE `indisponibilidade_veiculo`
  ADD CONSTRAINT `fk_indisp_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chk_indisp_periodo` CHECK (`data_fim` >= `data_inicio`);

--
-- Restrições para tabelas `local`
--
ALTER TABLE `local`
  ADD CONSTRAINT `local_ibfk_1` FOREIGN KEY (`cidade_id`) REFERENCES `cidade` (`id`);

--
-- Restrições para tabelas `log_verificacao_docs`
--
ALTER TABLE `log_verificacao_docs`
  ADD CONSTRAINT `log_verificacao_docs_ibfk_1` FOREIGN KEY (`conta_usuario_id`) REFERENCES `conta_usuario` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `log_verificacao_docs_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `administrador` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `mensagem`
--
ALTER TABLE `mensagem`
  ADD CONSTRAINT `mensagem_ibfk_1` FOREIGN KEY (`reserva_id`) REFERENCES `reserva` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mensagem_ibfk_2` FOREIGN KEY (`remetente_id`) REFERENCES `conta_usuario` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `pagamento`
--
ALTER TABLE `pagamento`
  ADD CONSTRAINT `pagamento_ibfk_1` FOREIGN KEY (`reserva_id`) REFERENCES `reserva` (`id`) ON DELETE CASCADE;

ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`conta_usuario_id`) REFERENCES `conta_usuario` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `reserva`
--
ALTER TABLE `reserva`
  ADD CONSTRAINT `reserva_ibfk_1` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculo` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reserva_ibfk_2` FOREIGN KEY (`conta_usuario_id`) REFERENCES `conta_usuario` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `veiculo`
--
ALTER TABLE `veiculo`
  ADD CONSTRAINT `veiculo_ibfk_1` FOREIGN KEY (`local_id`) REFERENCES `local` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `veiculo_ibfk_2` FOREIGN KEY (`categoria_veiculo_id`) REFERENCES `categoria_veiculo` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `veiculo_ibfk_3` FOREIGN KEY (`dono_id`) REFERENCES `dono` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
