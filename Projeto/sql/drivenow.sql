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

--
-- Despejando dados para a tabela `avaliacao_proprietario`
--

INSERT INTO `avaliacao_proprietario` (`id`, `reserva_id`, `usuario_id`, `proprietario_id`, `nota`, `comentario`, `data_avaliacao`) VALUES
(1, 2, 6, 1, 5, 'teste', '2026-04-16 16:13:24'),
(2, 4, 2, 1, 5, 'teste', '2026-04-26 13:52:36');

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

--
-- Despejando dados para a tabela `avaliacao_veiculo`
--

INSERT INTO `avaliacao_veiculo` (`id`, `reserva_id`, `usuario_id`, `veiculo_id`, `nota`, `comentario`, `data_avaliacao`) VALUES
(1, 2, 6, 2, 5, 'teste', '2026-04-16 16:13:24'),
(2, 4, 2, 1, 5, 'teste', '2026-04-26 13:52:36');

-- --------------------------------------------------------

--
-- Estrutura para tabela `categoria_veiculo`
--

CREATE TABLE `categoria_veiculo` (
  `id` int(11) NOT NULL,
  `categoria` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `categoria_veiculo`
--

INSERT INTO `categoria_veiculo` (`id`, `categoria`) VALUES
(1, 'Coupé'),
(2, 'Sedan'),
(3, 'SUV'),
(4, 'Hatch'),
(5, 'Picape'),
(6, 'Conversível'),
(7, 'Perua'),
(8, 'Minivan');

-- --------------------------------------------------------

--
-- Estrutura para tabela `cidade`
--

CREATE TABLE `cidade` (
  `id` int(11) NOT NULL,
  `estado_id` int(11) NOT NULL,
  `cidade_nome` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `cidade`
--

INSERT INTO `cidade` (`id`, `estado_id`, `cidade_nome`) VALUES
(7, 4, 'Manaus'),
(9, 5, 'Salvador'),
(12, 6, 'Fortaleza'),
(14, 7, 'Brasília'),
(25, 13, 'Belo Horizonte'),
(32, 16, 'Curitiba'),
(34, 16, 'Foz do Iguaçu'),
(36, 17, 'Recife'),
(40, 19, 'Rio de Janeiro'),
(45, 21, 'Porto Alegre'),
(52, 24, 'Florianópolis'),
(55, 25, 'São Paulo');

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
-- Despejando dados para a tabela `conta_usuario`
--

INSERT INTO `conta_usuario` (`id`, `is_admin`, `ativo`, `primeiro_nome`, `segundo_nome`, `telefone`, `e_mail`, `senha`, `data_de_entrada`, `foto_perfil`, `cpf`, `foto_cnh_frente`, `foto_cnh_verso`, `status_docs`, `data_verificacao`, `admin_verificacao`, `tem_cnh`, `cadastro_completo`, `observacoes_docs`, `mensagens_nao_lidas`, `media_avaliacao_proprietario`, `total_avaliacoes_proprietario`, `media_avaliacao_locatario`, `total_avaliacoes_locatario`) VALUES
(1, 1, 1, 'Andryus', 'Zolet', '(41) 99796-3268', 'zoletandryus@gmail.com', '$2y$10$q7m/RxOCrcZvSi.1SKShC.u3.5DKHyyVQR7Nk3oFgX/9F0.zCm2wa', '2025-05-10', NULL, '138.477.099-25', 'uploads/user_1/docs/foto_cnh_frente_68484ff76d484_1749569527.jpg', 'uploads/user_1/docs/foto_cnh_verso_68484ff76dde1_1749569527.jpg', 'aprovado', '2025-05-10 12:32:16', 1, 1, 1, '', 3, 5.0, 2, NULL, 0),
(2, 0, 1, 'Stuart', 'Capivas', '', 'capivara@gmail.com', '$2y$10$U.zVCHqT.YRRxWpPezMUhO1zLcze44j/aX9xz9mA34uZ15DG8d8ri', '2025-06-10', NULL, '567.372.568-05', 'uploads/user_2/docs/foto_cnh_frente_684851b9eb915_1749569977.jpg', 'uploads/user_2/docs/foto_cnh_verso_684851b9ec0b6_1749569977.jpg', 'aprovado', '2025-06-10 12:40:11', 1, 1, 1, 'Bob Esponja', 3, NULL, 0, NULL, 0),
(4, 0, 1, 'Scoobert', 'Doo', '', 'scoobydoo@gmail.com', '$2y$10$9OMuyhMBeF4XL60ExWUCEOI3bCy8ZmdrRiS7R.BSYaKckLSsfv9H.', '2025-06-10', NULL, '309.088.147-04', 'uploads/user_4/docs/foto_cnh_frente_684897b3d2961_1749587891.jpg', 'uploads/user_4/docs/foto_cnh_verso_684897b3d327b_1749587891.jpg', 'aprovado', '2025-06-10 17:39:21', 1, 1, 1, 'Biscoito Scooby', 0, NULL, 0, 5.0, 2),
(5, 0, 1, 'Valentin', 'Rojas', '', 'valentin@gmail.com', '$2y$10$2Enp.jb4mT1ARyMNzGsRLeIW.kpzN65UQn6Va1AA8kjSW3SFqP4ca', '2025-06-10', NULL, '594.635.580-55', 'uploads/user_5/docs/foto_cnh_frente_685021829ace5_1750081922.png', 'uploads/user_5/docs/foto_cnh_verso_685021829b58d_1750081922.png', 'aprovado', '2025-06-16 10:52:21', 1, 1, 1, 'Validado', 1, NULL, 0, NULL, 0),
(6, 0, 1, 'Norville', 'Rogers', '', 'salsicha@gmail.com', '$2y$10$LBFFy7x.IshlyyJaSEV5R.hi1TYILIDURBmUi6ztFiP1ZxIlPTXS6', '2025-06-11', NULL, '853.243.670-60', 'uploads/user_6/docs/foto_cnh_frente_6849640f26be4_1749640207.jpg', 'uploads/user_6/docs/foto_cnh_verso_6849640f2a79e_1749640207.jpg', 'aprovado', '2025-06-11 08:10:41', 1, 1, 1, 'salsicha', 0, NULL, 0, NULL, 0),
(7, 0, 1, 'Anakin', 'Skywalker', '', 'darthvader@gmail.com', '$2y$10$8959IB2rAJsNuhePmJvsf.PkTxnUTKShyrYejgutF.CXULbHgYERG', '2025-06-13', NULL, '540.120.230-04', 'uploads/user_7/docs/foto_cnh_frente_685023298c75d_1750082345.jpg', 'uploads/user_7/docs/foto_cnh_verso_685023298d0bd_1750082345.jpg', 'aprovado', '2026-04-25 17:08:15', 1, 1, 1, '', 0, NULL, 0, NULL, 0),
(8, 0, 1, 'Rafael', 'Souza', '(11) 99111-0001', 'rafael@gmail.com', '$2y$10$jjBwq2tNsiv4SSW9Px77TuNmgSe3TaSEt/bycEOKub/MUpASiu/CW', '2026-04-16', NULL, NULL, NULL, NULL, 'aprovado', NULL, NULL, 1, 1, NULL, 0, NULL, 0, NULL, 0),
(9, 0, 1, 'Mariana', 'Lima', '(21) 99222-0002', 'mariana@gmail.com', '$2y$10$jjBwq2tNsiv4SSW9Px77TuNmgSe3TaSEt/bycEOKub/MUpASiu/CW', '2026-04-16', NULL, NULL, NULL, NULL, 'aprovado', NULL, NULL, 1, 1, NULL, 0, NULL, 0, NULL, 0),
(10, 0, 1, 'Camila', 'Rocha', '(41) 99333-0003', 'camila@gmail.com', '$2y$10$jjBwq2tNsiv4SSW9Px77TuNmgSe3TaSEt/bycEOKub/MUpASiu/CW', '2026-04-16', NULL, NULL, NULL, NULL, 'aprovado', NULL, NULL, 1, 1, NULL, 0, NULL, 0, NULL, 0),
(11, 0, 1, 'Bruno', 'Ferreira', '(31) 99444-0004', 'bruno@gmail.com', '$2y$10$jjBwq2tNsiv4SSW9Px77TuNmgSe3TaSEt/bycEOKub/MUpASiu/CW', '2026-04-16', NULL, NULL, NULL, NULL, 'aprovado', NULL, NULL, 1, 1, NULL, 0, NULL, 0, NULL, 0),
(12, 0, 1, 'Lucas', 'Carvalho', '(51) 99555-0005', 'lucas@gmail.com', '$2y$10$jjBwq2tNsiv4SSW9Px77TuNmgSe3TaSEt/bycEOKub/MUpASiu/CW', '2026-04-16', NULL, NULL, NULL, NULL, 'aprovado', NULL, NULL, 1, 1, NULL, 0, NULL, 0, NULL, 0),
(13, 0, 1, 'Juliana', 'Martins', '(61) 99666-0006', 'juliana@gmail.com', '$2y$10$jjBwq2tNsiv4SSW9Px77TuNmgSe3TaSEt/bycEOKub/MUpASiu/CW', '2026-04-16', NULL, NULL, NULL, NULL, 'aprovado', NULL, NULL, 1, 1, NULL, 0, NULL, 0, NULL, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `dono`
--

CREATE TABLE `dono` (
  `id` int(11) NOT NULL,
  `conta_usuario_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `dono`
--

INSERT INTO `dono` (`id`, `conta_usuario_id`) VALUES
(1, 1),
(2, 5),
(3, 8),
(4, 10),
(5, 12);

-- --------------------------------------------------------

--
-- Estrutura para tabela `estado`
--

CREATE TABLE `estado` (
  `id` int(11) NOT NULL,
  `estado_nome` varchar(100) NOT NULL,
  `sigla` char(2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `estado`
--

INSERT INTO `estado` (`id`, `estado_nome`, `sigla`) VALUES
(1, 'Acre', 'AC'),
(2, 'Alagoas', 'AL'),
(3, 'Amapá', 'AP'),
(4, 'Amazonas', 'AM'),
(5, 'Bahia', 'BA'),
(6, 'Ceará', 'CE'),
(7, 'Distrito Federal', 'DF'),
(8, 'Espírito Santo', 'ES'),
(9, 'Goiás', 'GO'),
(10, 'Maranhão', 'MA'),
(11, 'Mato Grosso', 'MT'),
(12, 'Mato Grosso do Sul', 'MS'),
(13, 'Minas Gerais', 'MG'),
(14, 'Pará', 'PA'),
(15, 'Paraíba', 'PB'),
(16, 'Paraná', 'PR'),
(17, 'Pernambuco', 'PE'),
(18, 'Piauí', 'PI'),
(19, 'Rio de Janeiro', 'RJ'),
(20, 'Rio Grande do Norte', 'RN'),
(21, 'Rio Grande do Sul', 'RS'),
(22, 'Rondônia', 'RO'),
(23, 'Roraima', 'RR'),
(24, 'Santa Catarina', 'SC'),
(25, 'São Paulo', 'SP'),
(26, 'Sergipe', 'SE'),
(27, 'Tocantins', 'TO');

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

--
-- Despejando dados para a tabela `historico_pagamento`
--

INSERT INTO `historico_pagamento` (`id`, `pagamento_id`, `status_anterior`, `novo_status`, `observacao`, `data_alteracao`, `usuario_id`) VALUES
(1, 1, NULL, 'aprovado', 'Pagamento iniciado', '2026-04-16 16:09:16', 1),
(2, 2, NULL, 'aprovado', 'Pagamento iniciado', '2026-04-16 16:11:38', 6),
(3, 3, NULL, 'aprovado', 'Pagamento iniciado', '2026-04-26 11:26:59', 2),
(4, 4, NULL, 'aprovado', 'Pagamento iniciado', '2026-04-26 13:51:33', 2);

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

--
-- Despejando dados para a tabela `imagem`
--

INSERT INTO `imagem` (`id`, `veiculo_id`, `imagem_url`, `imagem_ordem`) VALUES
(1, 1, 'uploads/vehicles/vehicle_1/veiculo_7b3c28179b1e59cb3f2e5bbb2c284c34.jpg', 1),
(2, 1, 'uploads/vehicles/vehicle_1/veiculo_6c93ad4b00de5ab635aad171a9d6691b.jpg', 2),
(3, 1, 'uploads/vehicles/vehicle_1/veiculo_810dae1f90a90d6e2f6d477bfca7f065.jpg', 3),
(4, 1, 'uploads/vehicles/vehicle_1/veiculo_79c841b9725db311a0a23b57c0ed4d25.jpg', 4),
(5, 1, 'uploads/vehicles/vehicle_1/veiculo_201db4b04d1ada0067324608139f2c2b.jpg', 5);

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

--
-- Despejando dados para a tabela `local`
--

INSERT INTO `local` (`id`, `cidade_id`, `nome_local`, `endereco`, `complemento`, `cep`) VALUES
(1, 55, 'Parque Ibirapuera', 'Av. Pedro Álvares Cabral', 'Portão 10', '04094-050'),
(2, 55, 'Avenida Paulista', 'Avenida Paulista', 'Próximo ao MASP', '01310-100'),
(3, 55, 'Mercado Municipal', 'Rua da Cantareira, 306', 'Centro', '01024-000'),
(4, 55, 'Shopping Ibirapuera', 'Av. Ibirapuera, 3103', 'Indianópolis', '04028-000'),
(5, 55, 'Aeroporto de Congonhas', 'Av. Washington Luís', 'Campo Belo', '04626-911'),
(6, 40, 'Cristo Redentor', 'Parque Nacional da Tijuca', 'Alto da Boa Vista', '22241-125'),
(7, 40, 'Praia de Copacabana', 'Av. Atlântica', 'Copacabana', '22070-000'),
(8, 40, 'Pão de Açúcar', 'Av. Pasteur, 520', 'Urca', '22290-240'),
(9, 40, 'Maracanã', 'Av. Pres. Castelo Branco', 'Maracanã', '20271-130'),
(10, 40, 'Aeroporto Santos Dumont', 'Praça Sen. Salgado Filho', 'Centro', '20021-340'),
(11, 32, 'Jardim Botânico', 'Rua Engenheiro Ostoja Roguski', 'Jardim Botânico', '80210-390'),
(12, 32, 'Museu Oscar Niemeyer', 'R. Mal. Hermes, 999', 'Centro Cívico', '80530-230'),
(13, 32, 'Parque Barigui', 'Av. Cândido Hartmann', 'Santo Inácio', '82025-000'),
(14, 32, 'Aeroporto Afonso Pena', 'Av. Rocha Pombo, s/n', 'São José dos Pinhais', '83010-900'),
(15, 32, 'Shopping Estação', 'Av. Sete de Setembro, 2775', 'Rebouças', '80230-010'),
(16, 14, 'Congresso Nacional', 'Praça dos Três Poderes', 'Zona Cívico-Administrativa', '70160-900'),
(17, 14, 'Catedral Metropolitana', 'Esplanada dos Ministérios', 'Lote 12', '70050-000'),
(18, 14, 'Palácio do Planalto', 'Praça dos Três Poderes', 'Zona Cívico-Administrativa', '70150-900'),
(19, 14, 'Aeroporto Internacional de Brasília', 'Lago Sul', '', '71608-900'),
(20, 14, 'Parque da Cidade', 'Eixo Monumental', 'Sudoeste', '70070-350'),
(21, 9, 'Pelourinho', 'Centro Histórico', '', '40026-280'),
(22, 9, 'Farol da Barra', 'Av. Oceânica', 'Barra', '40140-130'),
(23, 9, 'Elevador Lacerda', 'Praça Municipal', 'Centro', '40020-010'),
(24, 9, 'Mercado Modelo', 'Praça Visconde do Cairu', 'Comércio', '40015-970'),
(25, 9, 'Aeroporto Internacional de Salvador', 'Praça Gago Coutinho', 'São Cristóvão', '41520-970'),
(26, 36, 'Marco Zero', 'Av. Alfredo Lisboa', 'Recife Antigo', '50030-150'),
(27, 36, 'Praia de Boa Viagem', 'Av. Boa Viagem', 'Boa Viagem', '51011-000'),
(28, 36, 'Instituto Ricardo Brennand', 'R. Mário Campelo, 700', 'Várzea', '50741-540'),
(29, 36, 'Shopping Recife', 'R. Padre Carapuceiro, 777', 'Boa Viagem', '51020-900'),
(30, 36, 'Aeroporto Internacional do Recife', 'Av. Mascarenhas de Morais', 'Imbiribeira', '51210-000'),
(31, 25, 'Praça da Liberdade', 'Praça da Liberdade', 'Funcionários', '30140-010'),
(32, 25, 'Mercado Central', 'Av. Augusto de Lima, 744', 'Centro', '30190-922'),
(33, 25, 'Mineirão', 'Av. Antônio Abrahão Caram, 1001', 'São José', '31275-000'),
(34, 25, 'Parque Municipal', 'Av. Afonso Pena, 1377', 'Centro', '30130-002'),
(35, 25, 'Aeroporto de Confins', 'MG-010', 'Confins', '33500-900'),
(36, 45, 'Mercado Público', 'Largo Jornalista Glênio Peres', 'Centro Histórico', '90010-120'),
(37, 45, 'Parque Farroupilha (Redenção)', 'Av. João Pessoa', 'Farroupilha', '90040-000'),
(38, 45, 'Casa de Cultura Mario Quintana', 'R. dos Andradas, 736', 'Centro Histórico', '90020-004'),
(39, 45, 'Aeroporto Salgado Filho', 'Av. Severo Dullius, 90010', 'São João', '90200-310'),
(40, 45, 'Shopping Iguatemi', 'Av. João Wallig, 1800', 'Passo dAreia', '91340-000'),
(41, 34, 'Cataratas do Iguaçu', 'Rodovia das Cataratas, km 18', 'Parque Nacional do Iguaçu', '85855-750'),
(42, 34, 'Usina Hidrelétrica de Itaipu', 'Av. Tancredo Neves, 6731', 'Jardim Itaipu', '85856-970'),
(43, 34, 'Marco das Três Fronteiras', 'Av. General Meira', 'Jardim Jupira', '85853-110'),
(44, 34, 'Parque das Aves', 'Av. das Cataratas, 12450', 'Vila Yolanda', '85853-000'),
(45, 34, 'Aeroporto Internacional de Foz do Iguaçu', 'Rod. das Cataratas, km 17', 'Aeroporto', '85863-900'),
(46, 52, 'Praia de Jurerê', 'Av. dos Búzios', 'Jurerê Internacional', '88053-300'),
(47, 52, 'Ponte Hercílio Luz', 'Centro', 'Centro', '88010-970'),
(48, 52, 'Mercado Público', 'R. Jerônimo Coelho', 'Centro', '88010-030'),
(49, 52, 'Praia do Campeche', 'Av. Pequeno Príncipe', 'Campeche', '88063-000'),
(50, 52, 'Aeroporto Hercílio Luz', 'Rod. Ac. ao Aeroporto, 6200', 'Carianos', '88047-902'),
(51, 7, 'Teatro Amazonas', 'Av. Eduardo Ribeiro', 'Centro', '69025-140'),
(52, 7, 'Encontro das Águas', 'Rio Negro', 'Zona Rural', '69000-000'),
(53, 7, 'Mercado Municipal Adolpho Lisboa', 'R. dos Barés', 'Centro', '69005-020'),
(54, 7, 'Aeroporto Internacional de Manaus', 'Av. Santos Dumont', 'Tarumã', '69041-000'),
(55, 7, 'MUSA - Museu da Amazônia', 'Av. Margarita', 'Cidade de Deus', '69099-415'),
(56, 12, 'Praia do Futuro', 'Av. Zezé Diogo', 'Praia do Futuro', '60182-025'),
(57, 12, 'Beach Park', 'Rua Porto das Dunas, 2734', 'Aquiraz', '61700-000'),
(58, 12, 'Mercado Central', 'R. Gen. Bezerril, 115', 'Centro', '60055-100'),
(59, 12, 'Catedral Metropolitana', 'Av. Dom Manuel', 'Centro', '60060-090'),
(60, 12, 'Aeroporto Pinto Martins', 'Av. Senador Carlos Jereissati', 'Serrinha', '60741-000');

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

--
-- Despejando dados para a tabela `mensagem`
--

INSERT INTO `mensagem` (`id`, `reserva_id`, `remetente_id`, `mensagem`, `data_envio`, `lida`) VALUES
(1, 1, 1, 'alo', '2026-04-16 16:25:47', 0),
(2, 3, 1, 'Boa tarde', '2026-04-26 11:28:15', 1),
(3, 3, 2, 'Boa tarde', '2026-04-26 11:28:36', 1),
(4, 3, 1, 'vi que voce reservou o Civic pra esta semana certo?', '2026-04-26 11:29:01', 1),
(5, 3, 2, 'isso mesmo vou passar o final de semana no rio', '2026-04-26 11:29:18', 1),
(6, 3, 1, 'perfeito ja esta tudo certo vou confirmar a reserva e assim que voce chegar no rio marcaremos um lugar para voce pegar o veiculo', '2026-04-26 11:29:56', 1),
(7, 3, 2, 'otimo até logo', '2026-04-26 11:30:06', 1);

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

--
-- Despejando dados para a tabela `pagamento`
--

INSERT INTO `pagamento` (`id`, `reserva_id`, `valor`, `metodo_pagamento`, `status`, `data_pagamento`, `data_criacao`, `comprovante_url`, `codigo_transacao`, `detalhes`) VALUES
(1, 1, 439.00, 'simulacao', 'aprovado', '2026-04-16 16:09:16', '2026-04-16 16:09:16', NULL, 'SIM_D904326433', '{\"simulacao\":true,\"observacao\":\"Pagamento simulado para fins de teste\\/desenvolvimento\"}'),
(2, 2, 200.00, 'cartao', 'aprovado', '2026-04-16 16:11:38', '2026-04-16 16:11:38', NULL, 'CARD_EFAC63A85D', '{\"titular\":\"\",\"ultimos_digitos\":\"\",\"bandeira\":\"Visa\",\"parcelas\":\"1\"}'),
(3, 3, 650.00, 'cartao', 'aprovado', '2026-04-26 11:26:59', '2026-04-26 11:26:59', NULL, 'CARD_3D8BCBBB57', '{\"titular\":\"STUART CAPIVAS\",\"ultimos_digitos\":\"\",\"bandeira\":\"Visa\",\"parcelas\":\"1\"}'),
(4, 4, 500.00, 'cartao', 'aprovado', '2026-04-26 13:51:33', '2026-04-26 13:51:33', NULL, 'CARD_F313217DE4', '{\"titular\":\"\",\"ultimos_digitos\":\"\",\"bandeira\":\"Visa\",\"parcelas\":\"1\"}');

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

--
-- Despejando dados para a tabela `reserva`
--

INSERT INTO `reserva` (`id`, `veiculo_id`, `conta_usuario_id`, `reserva_data`, `devolucao_data`, `diaria_valor`, `taxas_de_uso`, `taxas_de_limpeza`, `valor_total`, `status`, `observacoes`) VALUES
(1, 4, 1, '2026-04-16', '2026-04-17', 389.00, 20.00, 30.00, 439.00, 'pago', ''),
(2, 2, 6, '2026-04-16', '2026-04-17', 150.00, 20.00, 30.00, 200.00, 'finalizada', ''),
(3, 3, 2, '2026-04-26', '2026-04-29', 200.00, 20.00, 30.00, 650.00, 'confirmada', 'Carro para viagens no Rio de Janeiro'),
(4, 1, 2, '2026-04-26', '2026-04-27', 450.00, 20.00, 30.00, 500.00, 'finalizada', 'teste');

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

--
-- Despejando dados para a tabela `veiculo`
--

INSERT INTO `veiculo` (`id`, `local_id`, `categoria_veiculo_id`, `dono_id`, `veiculo_marca`, `veiculo_modelo`, `veiculo_ano`, `veiculo_km`, `veiculo_placa`, `veiculo_cambio`, `veiculo_combustivel`, `veiculo_portas`, `veiculo_acentos`, `veiculo_tracao`, `disponivel`, `preco_diaria`, `descricao`, `media_avaliacao`, `total_avaliacoes`) VALUES
(1, 11, 1, 1, 'Toyota', 'Supra', 2000, 35897, 'ZOL3T11', 'Manual', 'Gasolina', 2, 4, 'Traseira', 1, 450.00, 'Toyota Supra MK4 3.0i Turbo', 5.0, 1),
(2, 1, 8, 1, 'Mercedes-Benz', 'Sprinter 515', 2010, 89032, 'SCO0B17', 'Automático', 'Gasolina', 3, 3, 'Dianteira', 1, 150.00, 'prefeita para uma maquina de mistério', 5.0, 1),
(3, 6, 4, 1, 'Honda', 'Civic Type R', 2001, 94034, 'CAP1V45', 'Manual', 'Gasolina', 2, 5, 'Dianteira', 1, 200.00, 'O Honda Civic Type R é uma série de modelos hot hatchback esportivo', NULL, 0),
(4, 44, 3, 2, 'Audi', 'Q5', 2018, 54879, 'VAL3T14', 'Automático', 'Gasolina', 4, 5, 'Dianteira', 1, 389.00, '', NULL, 0),
(5, 1, 2, 3, 'Toyota', 'Corolla XEi', 2021, 38200, 'RFL1A23', 'Automatico', 'Flex', 4, 5, 'Dianteira', 1, 189.90, 'Sedan confortavel para viagens urbanas e rodoviarias.', NULL, 0),
(6, 2, 3, 3, 'Jeep', 'Compass Longitude', 2022, 26500, 'RFL2B34', 'Automatico', 'Flex', 4, 5, '4x2', 1, 249.90, 'SUV com bom espaco interno e excelente conforto.', NULL, 0),
(7, 11, 4, 4, 'Volkswagen', 'Polo', 2020, 41200, 'CML3C45', 'Manual', 'Flex', 4, 5, 'Dianteira', 1, 159.90, 'Hatch economico para uso diario na cidade.', NULL, 0),
(8, 12, 2, 4, 'Chevrolet', 'Onix Plus', 2023, 11800, 'CML4D56', 'Automatico', 'Flex', 4, 5, 'Dianteira', 1, 179.90, 'Sedan moderno, ideal para corridas longas e conforto.', NULL, 0),
(9, 36, 3, 5, 'Hyundai', 'Creta', 2021, 30700, 'LCS5E67', 'Automatico', 'Flex', 4, 5, '4x2', 1, 229.90, 'SUV com porta-malas amplo para viagens em familia.', NULL, 0),
(10, 37, 5, 5, 'Fiat', 'Toro Volcano', 2022, 22400, 'LCS6F78', 'Automatico', 'Diesel', 4, 5, '4x4', 1, 279.90, 'Picape versatil para uso urbano e trabalho.', NULL, 0);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `avaliacao_veiculo`
--
ALTER TABLE `avaliacao_veiculo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `categoria_veiculo`
--
ALTER TABLE `categoria_veiculo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `cidade`
--
ALTER TABLE `cidade`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT de tabela `conta_usuario`
--
ALTER TABLE `conta_usuario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `dono`
--
ALTER TABLE `dono`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `estado`
--
ALTER TABLE `estado`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de tabela `historico_pagamento`
--
ALTER TABLE `historico_pagamento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `imagem`
--
ALTER TABLE `imagem`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `indisponibilidade_veiculo`
--
ALTER TABLE `indisponibilidade_veiculo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `local`
--
ALTER TABLE `local`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT de tabela `log_verificacao_docs`
--
ALTER TABLE `log_verificacao_docs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `mensagem`
--
ALTER TABLE `mensagem`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `pagamento`
--
ALTER TABLE `pagamento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `reserva`
--
ALTER TABLE `reserva`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `veiculo`
--
ALTER TABLE `veiculo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
