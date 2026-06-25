<?php
declare(strict_types=1);

/**
 * Normaliza un texto (trim y null seguro)
 */
function normalizeText(?string $value): string {
    return trim((string)($value ?? ''));
}

/**
 * Normaliza un valor de precio desde string o número
 */
function normalizePrice(mixed $value): float {
    if (is_string($value)) {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[^\d,.\-]/u', '', $value);
        
        // Caso: 1.234,56 (punto como separador de miles, coma decimal)
        if (substr_count($value, ',') === 1 && substr_count($value, '.') > 1) {
            $value = str_replace('.', '', $value);
        }
        // Caso: 1,50 (coma como decimal)
        if (substr_count($value, ',') === 1) {
            $value = str_replace(',', '.', $value);
        }
    }
    return round((float)$value, 2);
}

/**
 * Obtiene o crea un supermercado por nombre
 */
function getOrCreateSupermercado(PDO $db, string $nome): int {
    $nome = normalizeText($nome);
    if ($nome === '') {
        throw new InvalidArgumentException('supermercado is required');
    }

    $stmt = $db->prepare("SELECT id FROM supermercados WHERE LOWER(nome) = LOWER(:nome) LIMIT 1");
    $stmt->execute([':nome' => $nome]);
    $existing = $stmt->fetch();

    if ($existing) {
        return (int)$existing['id'];
    }

    $stmt = $db->prepare("INSERT INTO supermercados (nome) VALUES (:nome)");
    $stmt->execute([':nome' => $nome]);
    return (int)$db->lastInsertId();
}

/**
 * Obtiene o crea un producto
 */
function getOrCreateProducto(PDO $db, array $row): int {
    $nome = normalizeText($row['nome'] ?? $row['producto'] ?? $row['produto'] ?? '');
    $marca = normalizeText($row['marca'] ?? '');
    $categoria = normalizeText($row['categoria'] ?? '');

    if ($nome === '') {
        throw new InvalidArgumentException('nome/producto is required');
    }

    $stmt = $db->prepare("
        SELECT id FROM productos
        WHERE LOWER(nome) = LOWER(:nome)
          AND COALESCE(LOWER(marca), '') = COALESCE(LOWER(:marca), '')
        LIMIT 1
    ");
    $stmt->execute([':nome' => $nome, ':marca' => $marca]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($categoria !== '') {
            $stmt = $db->prepare("UPDATE productos SET categoria = :categoria WHERE id = :id AND (categoria IS NULL OR categoria = '')");
            $stmt->execute([':categoria' => $categoria, ':id' => $existing['id']]);
        }
        return (int)$existing['id'];
    }

    $stmt = $db->prepare("INSERT INTO productos (nome, marca, categoria) VALUES (:nome, :marca, :categoria)");
    $stmt->execute([
        ':nome' => $nome,
        ':marca' => $marca !== '' ? $marca : null,
        ':categoria' => $categoria !== '' ? $categoria : null,
    ]);

    return (int)$db->lastInsertId();
}

/**
 * Inserta o actualiza un precio de producto en un supermercado
 */
function upsertPrecio(PDO $db, int $productoId, int $supermercadoId, array $row): array {
    $precio = normalizePrice($row['precio'] ?? $row['preco'] ?? $row['price'] ?? 0);
    $url = normalizeText($row['url'] ?? '');

    if ($precio <= 0) {
        throw new InvalidArgumentException('precio must be greater than 0');
    }

    $stmt = $db->prepare("SELECT id FROM precios WHERE producto_id = :producto_id AND supermercado_id = :supermercado_id LIMIT 1");
    $stmt->execute([':producto_id' => $productoId, ':supermercado_id' => $supermercadoId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $db->prepare("
            UPDATE precios
            SET precio = :precio, url = :url, fecha_actualizacion = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':precio' => $precio,
            ':url' => $url !== '' ? $url : null,
            ':id' => $existing['id'],
        ]);
        return ['id' => (int)$existing['id'], 'action' => 'updated'];
    }

    $stmt = $db->prepare("
        INSERT INTO precios (producto_id, supermercado_id, precio, url, fecha_actualizacion)
        VALUES (:producto_id, :supermercado_id, :precio, :url, NOW())
    ");
    $stmt->execute([
        ':producto_id' => $productoId,
        ':supermercado_id' => $supermercadoId,
        ':precio' => $precio,
        ':url' => $url !== '' ? $url : null,
    ]);

    return ['id' => (int)$db->lastInsertId(), 'action' => 'created'];
}