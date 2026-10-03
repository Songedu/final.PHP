-- Desativa a verificação de chaves estrangeiras temporariamente para importação limpa
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS final_projecto
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE final_projecto;

-- ==========================================
-- 1. ESTRUTURA DAS TABELAS
-- ==========================================

-- Tabela: Eventos
CREATE TABLE IF NOT EXISTS eventos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT,
    data DATE NOT NULL,
    hora TIME NOT NULL,
    local VARCHAR(200) NOT NULL,
    capacidade INT UNSIGNED NOT NULL,
    imagem VARCHAR(255) DEFAULT NULL,
    CONSTRAINT chk_eventos_capacidade CHECK (capacidade > 0)
) ENGINE=InnoDB;

-- Tabela: Bilhetes
CREATE TABLE IF NOT EXISTS bilhetes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evento_id INT UNSIGNED NOT NULL,
    preco DECIMAL(10, 2) NOT NULL,
    numero_assento VARCHAR(20),
    CONSTRAINT chk_bilhetes_preco CHECK (preco >= 0),
    CONSTRAINT uq_bilhetes_evento_assento UNIQUE (evento_id, numero_assento),
    CONSTRAINT fk_bilhetes_evento
        FOREIGN KEY (evento_id) REFERENCES eventos (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabela: Users (Autenticação)
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(254) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    profile_pic VARCHAR(255) DEFAULT 'default-profile-pic.jpg',
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabela: Clientes (Dados pessoais vinculados ao User)
CREATE TABLE IF NOT EXISTS clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(254) NOT NULL,
    telefone VARCHAR(20) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_clientes_user 
        FOREIGN KEY (user_id) REFERENCES users (id) 
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Tabela: Vendas
CREATE TABLE IF NOT EXISTS vendas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bilhete_id INT UNSIGNED NOT NULL UNIQUE,
    data_venda DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id INT UNSIGNED NULL,
    CONSTRAINT fk_vendas_bilhete
        FOREIGN KEY (bilhete_id) REFERENCES bilhetes (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_vendas_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;


-- ==========================================
-- 2. INSERÇÃO DE DADOS
-- ==========================================

-- Não são incluídas contas nem dados pessoais de demonstração.
-- Crie utilizadores localmente através do formulário de registo.

-- Inserção de Eventos
INSERT INTO eventos (id, nome, descricao, data, hora, local, capacidade, imagem) VALUES
(1, 'Workshop de Fotografia', 'Aprenda técnicas simples para tirar melhores fotografias.', '2026-10-15', '14:00:00', 'Sala Criativa', 50, 'fotografia.jpg'),
(2, 'Feira de Tecnologia', 'Uma tarde para descobrir projetos e ideias tecnológicas.', '2026-11-08', '10:00:00', 'Centro da Cidade', 200, 'festival.jpg'),
(3, 'Concerto ao Vivo', 'Música, amigos e uma noite especial.', '2026-12-20', '21:00:00', 'Estádio da Maia', 100, 'concerto.jpg'),
(4, 'MEO Marés Vivas', 'Concerto de Da Weasel.', '2027-07-18', '21:00:00', 'Vila Nova de Gaia', 5000, 'maresvivas.jpeg'),
(5, 'World Tour 2027', 'Concerto de Coldplay.', '2027-09-12', '20:30:00', 'Estádio da Luz, Lisboa', 64000, 'coldplay.webp'),
(6, 'MEO Arena 2027', 'Espetáculo ao vivo no MEO Arena.', '2027-09-12', '20:30:00', 'MEO Arena, Lisboa', 20000, 'meoarena.jpg'),
(7, 'Noite de Fado', 'Concerto de Mariza.', '2026-11-05', '21:30:00', 'Coliseu do Porto', 3000, 'Mariza.jpeg'),
(8, 'Plutonio', 'Concerto solo no Coliseu dos Recreios.', '2027-09-12', '20:30:00', 'Coliseu dos Recreios, Lisboa', 4000, 'Plutonio.jpeg'),
(9, 'Festival de Jazz', 'Uma noite de jazz com artistas internacionais.', '2027-08-22', '19:00:00', 'Teatro Nacional', 800, 'concerto de jazz.jpeg'),
(10, 'Exposição de Arte Moderna', 'Explore obras de artistas contemporâneos.', '2026-10-05', '11:00:00', 'Museu de Arte Moderna', 300, 'artemoderna.jpeg'),
(11, 'Festival de Rock', 'Concerto de bandas de rock na Maia.', '2027-09-12', '20:30:00', 'Estádio Municipal da Maia', 6400, 'rock.jpeg'),
(12, 'São João no Porto', 'Concerto e festejos de São João.', '2027-06-23', '22:30:00', 'Rua da Boavista, Porto', 4000, 'sao joao.jpeg')
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    data = VALUES(data),
    hora = VALUES(hora),
    local = VALUES(local),
    capacidade = VALUES(capacidade),
    imagem = VALUES(imagem);

-- Inserção de Bilhetes
INSERT INTO bilhetes (evento_id, preco, numero_assento) VALUES
(1, 15.00, 'A1'),
(2, 0.00, 'LIVRE'),
(3, 25.00, 'B10'),
(4, 45.00, 'A12'),
(4, 45.00, 'A13'),
(5, 85.00, 'B01'),
(5, 85.00, 'B02'),
(6, 35.00, 'C05'),
(7, 30.00, 'D20'),
(8, 20.00, 'E15'),
(10, 10.00, 'G05'),
(11, 50.00, 'H10'),
(9, 30.00, 'F01'),
(12, 15.00, 'J01')
ON DUPLICATE KEY UPDATE
    numero_assento = VALUES(numero_assento);

-- Reativa a verificação de chaves estrangeiras
SET FOREIGN_KEY_CHECKS = 1;
