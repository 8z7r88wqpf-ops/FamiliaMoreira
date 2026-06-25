<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

handlePreflight();
requireMethod('POST');

$database = new Database();
$db = $database->getConnection();

try {
    // 1. Stock de Cozinha
    $db->exec("CREATE TABLE IF NOT EXISTS stock_cozinha (
        id INT AUTO_INCREMENT PRIMARY KEY,
        producto_id INT NOT NULL,
        quantidade DECIMAL(10,2) NOT NULL DEFAULT 0,
        unidade VARCHAR(20) DEFAULT 'un',
        data_validade DATE NULL,
        localizacao VARCHAR(50) DEFAULT 'Dispensa',
        notas TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 2. Receitas
    $db->exec("CREATE TABLE IF NOT EXISTS receitas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        descricao TEXT NULL,
        tempo_preparo INT NULL,
        tempo_cozimento INT NULL,
        porcoes INT DEFAULT 4,
        instrucoes TEXT NULL,
        imagem_url VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS receita_ingredientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        receita_id INT NOT NULL,
        producto_id INT NOT NULL,
        quantidade DECIMAL(10,2) NOT NULL DEFAULT 1,
        unidade VARCHAR(20) DEFAULT 'un',
        opcional TINYINT(1) NOT NULL DEFAULT 0,
        FOREIGN KEY (receita_id) REFERENCES receitas(id) ON DELETE CASCADE,
        FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 3. Histórico de Preços
    $db->exec("CREATE TABLE IF NOT EXISTS historico_precos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        producto_id INT NOT NULL,
        supermercado_id INT NOT NULL,
        precio DECIMAL(10,2) NOT NULL,
        fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
        FOREIGN KEY (supermercado_id) REFERENCES supermercados(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 4. Utilizadores (Multi-user)
    $db->exec("CREATE TABLE IF NOT EXISTS utilizadores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) DEFAULT 'membro',
        avatar_url VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS lista_partilhada (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lista_id INT NOT NULL,
        utilizador_id INT NOT NULL,
        permissao VARCHAR(20) DEFAULT 'editar',
        FOREIGN KEY (lista_id) REFERENCES listas_compra(id) ON DELETE CASCADE,
        FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 5. Orçamento / Finanças
    $db->exec("CREATE TABLE IF NOT EXISTS orcamento_mensal (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mes INT NOT NULL,
        ano INT NOT NULL,
        categoria VARCHAR(100) NULL,
        valor_limite DECIMAL(10,2) NOT NULL DEFAULT 0,
        utilizador_id INT NULL,
        FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 6. Adicionar coluna user_id a listas_compra se não existir
    try {
        $db->exec("ALTER TABLE listas_compra ADD COLUMN utilizador_id INT NULL AFTER nombre");
    } catch (Exception $e) {
        // Coluna já existe
    }

    // Criar utilizador padrão
    $stmt = $db->prepare("SELECT COUNT(*) FROM utilizadores WHERE email = 'admin@compradelmes.pt'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO utilizadores (nome, email, password_hash, role) VALUES ('Admin', 'admin@compradelmes.pt', :hash, 'admin')")
           ->execute([':hash' => $hash]);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Todos os módulos instalados com sucesso!',
        'tabelas_criadas' => [
            'stock_cozinha', 'receitas', 'receita_ingredientes',
            'historico_precos', 'utilizadores', 'lista_partilhada',
            'orcamento_mensal'
        ]
    ]);
} catch (Exception $e) {
    jsonError('Erro na instalação: ' . $e->getMessage(), 500);
}