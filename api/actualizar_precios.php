<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';
require_once __DIR__ . '/lib/precios_helpers.php';
require_once __DIR__ . '/lib/search_precios.php';

$database = new Database();
$db = $database->getConnection();

function ensureAutomationTables(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS fuentes_precios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            producto_id INT NOT NULL,
            supermercado_id INT NOT NULL,
            url TEXT NOT NULL,
            tipo VARCHAR(30) NOT NULL DEFAULT 'html',
            selector_precio VARCHAR(255) NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            ultimo_precio DECIMAL(10,2) NULL,
            ultimo_estado VARCHAR(30) NULL,
            ultimo_error TEXT NULL,
            ultima_actualizacion DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function fetchUrl(string $url): string {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('URL invalida');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'CompraDelMes/1.0 (+local price monitor)',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,application/json;q=0.8,*/*;q=0.7',
            'Accept-Language: pt-PT,pt;q=0.9,en;q=0.7',
        ],
    ]);

    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $status >= 400) {
        throw new RuntimeException($error ?: "HTTP {$status}");
    }

    return $body;
}

function extractPriceFromJson(array $data): ?float {
    $keys = ['price', 'precio', 'preco', 'salePrice', 'currentPrice', 'value'];
    foreach ($keys as $key) {
        if (isset($data[$key]) && normalizePrice($data[$key]) > 0) {
            return normalizePrice($data[$key]);
        }
    }

    foreach ($data as $value) {
        if (is_array($value)) {
            $found = extractPriceFromJson($value);
            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

function extractJsonLdPrice(string $html): ?float {
    if (!preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
        return null;
    }

    foreach ($matches[1] as $json) {
        $data = json_decode(html_entity_decode(trim($json), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
        if (is_array($data)) {
            $price = extractPriceFromJson($data);
            if ($price !== null) {
                return $price;
            }
        }
    }

    return null;
}

function extractMetaPrice(string $html): ?float {
    $patterns = [
        '/<meta[^>]+property=["\']product:price:amount["\'][^>]+content=["\']([^"\']+)["\']/i',
        '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']product:price:amount["\']/i',
        '/"price"\s*:\s*"?([0-9]+(?:[,.][0-9]{1,2})?)"?/i',
        '/data-price=["\']([^"\']+)["\']/i',
        '/(?:€|&euro;)\s*([0-9]+(?:[,.][0-9]{1,2})?)/i',
        '/([0-9]+(?:[,.][0-9]{1,2})?)\s*(?:€|&euro;)/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $match)) {
            $price = normalizePrice($match[1]);
            if ($price > 0) {
                return $price;
            }
        }
    }

    return null;
}

function extractPriceFromHtml(string $html): ?float {
    return extractJsonLdPrice($html) ?? extractMetaPrice($html);
}

function updateSource(PDO $db, array $source): float {
    $html = fetchUrl($source['url']);
    $price = extractPriceFromHtml($html);

    if ($price === null || $price <= 0) {
        throw new RuntimeException('No se pudo detectar precio en la pagina');
    }

    upsertPrecio($db, (int)$source['producto_id'], (int)$source['supermercado_id'], [
        'precio' => $price,
        'url' => $source['url'],
    ]);

    $stmt = $db->prepare("
        UPDATE fuentes_precios
        SET ultimo_precio = :precio,
            ultimo_estado = 'ok',
            ultimo_error = NULL,
            ultima_actualizacion = NOW()
        WHERE id = :id
    ");
    $stmt->execute([':precio' => $price, ':id' => $source['id']]);

    return $price;
}

ensureAutomationTables($db);

try {
    handlePreflight();

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $stmt = $db->query("
                SELECT f.*, p.nome AS producto_nome, p.marca, s.nome AS supermercado_nome
                FROM fuentes_precios f
                JOIN productos p ON p.id = f.producto_id
                JOIN supermercados s ON s.id = f.supermercado_id
                ORDER BY f.id DESC
            ");
            jsonResponse($stmt->fetchAll());
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $action = $data['action'] ?? 'add_source';

            if ($action === 'add_source') {
                $url = normalizeText($data['url'] ?? '');
                $productoId = (int)$data['producto_id'];
                $supermercadoId = (int)$data['supermercado_id'];

                // Si no se proporcionó URL, intentar búsqueda automática
                if (empty($url)) {
                    $stmt = $db->prepare("SELECT nome, marca FROM productos WHERE id = :id");
                    $stmt->execute([':id' => $productoId]);
                    $producto = $stmt->fetch();

                    $stmt = $db->prepare("SELECT nome FROM supermercados WHERE id = :id");
                    $stmt->execute([':id' => $supermercadoId]);
                    $supermercado = $stmt->fetch();

                    if (!$producto || !$supermercado) {
                        jsonError('Producto o supermercado no encontrado', 400);
                    }

                    $searcher = new SupermercadoSearch();
                    $searchTerm = $producto['nome'];
                    if (!empty($producto['marca'])) {
                        $searchTerm .= ' ' . $producto['marca'];
                    }

                    try {
                        $result = $searcher->searchAll($searchTerm, $supermercado['nome']);
                        $first = $result[0];
                        $url = $first['url'];
                        $price = $first['price'];

                        $stmt = $db->prepare("
                            INSERT INTO fuentes_precios (producto_id, supermercado_id, url, tipo, ultimo_precio, ultimo_estado, ultima_actualizacion)
                            VALUES (:producto_id, :supermercado_id, :url, 'html', :precio, 'ok', NOW())
                        ");
                        $stmt->execute([
                            ':producto_id' => $productoId,
                            ':supermercado_id' => $supermercadoId,
                            ':url' => $url,
                            ':precio' => $price,
                        ]);
                        $id = (int)$db->lastInsertId();

                        upsertPrecio($db, $productoId, $supermercadoId, [
                            'precio' => $price,
                            'url' => $url,
                        ]);

                        jsonResponse([
                            'id' => $id,
                            'precio' => $price,
                            'url' => $url,
                            'auto' => true,
                            'producto_encontrado' => $first['name'],
                        ]);
                    } catch (Exception $e) {
                        jsonError($e->getMessage(), 422);
                    }
                }

                $stmt = $db->prepare("
                    INSERT INTO fuentes_precios (producto_id, supermercado_id, url, tipo)
                    VALUES (:producto_id, :supermercado_id, :url, 'html')
                ");
                $stmt->execute([
                    ':producto_id' => $productoId,
                    ':supermercado_id' => $supermercadoId,
                    ':url' => $url,
                ]);
                jsonResponse(['id' => (int)$db->lastInsertId()]);
                break;
            }

            if ($action === 'search_auto') {
                $productoId = (int)$data['producto_id'];
                $supermercadoId = (int)$data['supermercado_id'];

                $stmt = $db->prepare("SELECT nome, marca FROM productos WHERE id = :id");
                $stmt->execute([':id' => $productoId]);
                $producto = $stmt->fetch();

                $stmt = $db->prepare("SELECT nome FROM supermercados WHERE id = :id");
                $stmt->execute([':id' => $supermercadoId]);
                $supermercado = $stmt->fetch();

                if (!$producto || !$supermercado) {
                    jsonError('Producto o supermercado no encontrado', 400);
                }

                $searcher = new SupermercadoSearch();
                $searchTerm = $producto['nome'];
                if (!empty($producto['marca'])) {
                    $searchTerm .= ' ' . $producto['marca'];
                }

                try {
                    $results = $searcher->searchAll($searchTerm, $supermercado['nome']);
                    jsonResponse(['productos' => $results, 'supermercado' => $supermercado['nome']]);
                } catch (Exception $e) {
                    jsonError($e->getMessage(), 422);
                }
                break;
            }

            if ($action === 'test_url') {
                $price = extractPriceFromHtml(fetchUrl($data['url'] ?? ''));
                if ($price === null) {
                    jsonError('No se pudo detectar precio', 422);
                }
                jsonResponse(['precio' => $price]);
                break;
            }

            if ($action === 'update_one') {
                $stmt = $db->prepare("SELECT * FROM fuentes_precios WHERE id = :id AND activo = 1");
                $stmt->execute([':id' => (int)$data['id']]);
                $source = $stmt->fetch();
                if (!$source) {
                    jsonError('Fuente no encontrada', 404);
                }
                jsonResponse(['precio' => updateSource($db, $source)]);
                break;
            }

            if ($action === 'update_all') {
                $stmt = $db->query("SELECT * FROM fuentes_precios WHERE activo = 1 ORDER BY id");
                $sources = $stmt->fetchAll();
                $summary = ['updated' => 0, 'errors' => []];

                foreach ($sources as $source) {
                    try {
                        updateSource($db, $source);
                        $summary['updated']++;
                    } catch (Exception $e) {
                        $summary['errors'][] = ['id' => (int)$source['id'], 'error' => $e->getMessage()];
                        $stmt = $db->prepare("
                            UPDATE fuentes_precios
                            SET ultimo_estado = 'error', ultimo_error = :error, ultima_actualizacion = NOW()
                            WHERE id = :id
                        ");
                        $stmt->execute([':error' => $e->getMessage(), ':id' => $source['id']]);
                    }
                }

                jsonResponse($summary);
                break;
            }

            jsonError('Invalid action', 400);
            break;

        case 'DELETE':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $stmt = $db->prepare("DELETE FROM fuentes_precios WHERE id = :id");
            $stmt->execute([':id' => $id]);
            jsonResponse(['message' => 'Fuente eliminada']);
            break;

        default:
            jsonError('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('actualizar_precios error: ' . $e->getMessage());
    jsonError($e->getMessage(), 500);
}