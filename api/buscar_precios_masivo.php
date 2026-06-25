<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';
require_once __DIR__ . '/lib/precios_helpers.php';
require_once __DIR__ . '/lib/search_precios.php';

handlePreflight();
requireMethod('POST');

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$supermercado = $data['supermercado'] ?? 'all';
$categoria = $data['categoria'] ?? '';
$limite = $data['limite'] ?? 0; // 0 = sin límite

// Obtener productos que NO tienen precio en el supermercado especificado
$where = [];
$params = [];

if (!empty($categoria)) {
    $where[] = "p.categoria = :categoria";
    $params[':categoria'] = $categoria;
}

$whereClause = !empty($where) ? ' AND ' . implode(' AND ', $where) : '';

// Obtener supermercados disponibles
$supermercados = [];
$stmt = $db->query("SELECT id, nome FROM supermercados ORDER BY nome");
while ($row = $stmt->fetch()) {
    $supermercados[strtolower(trim($row['nome']))] = $row;
}

// Determinar qué supermercados procesar
$supermercadosAProcesar = [];
if ($supermercado === 'all') {
    $nombres = ['continente', 'pingo doce', 'auchan', 'mercadona', 'lidl'];
    foreach ($nombres as $nombre) {
        if (isset($supermercados[$nombre])) {
            $supermercadosAProcesar[$nombre] = $supermercados[$nombre];
        }
    }
} else {
    $key = strtolower(trim($supermercado));
    if (isset($supermercados[$key])) {
        $supermercadosAProcesar[$key] = $supermercados[$key];
    }
}

if (empty($supermercadosAProcesar)) {
    jsonError('No hay supermercados configurados para búsqueda', 400);
}

$searcher = new SupermercadoSearch();
$resultados = [];
$totalProcesados = 0;
$totalConPrecio = 0;
$totalSinPrecio = 0;
$totalErrores = 0;

foreach ($supermercadosAProcesar as $superKey => $superData) {
    $superNome = $superData['nome'];
    $superId = (int)$superData['id'];
    
    // Obtener productos sin precio en este supermercado
    $sql = "SELECT p.id, p.nome, p.marca, p.categoria 
            FROM productos p 
            WHERE p.id NOT IN (
                SELECT pr.producto_id FROM precios pr WHERE pr.supermercado_id = :sid
            )" . $whereClause . " 
            ORDER BY p.nome ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($params, [':sid' => $superId]));
    $productos = $stmt->fetchAll();
    
    if (empty($productos)) {
        $resultados[] = [
            'supermercado' => $superNome,
            'mensaje' => 'Todos los productos ya tienen precio en este supermercado',
            'procesados' => 0,
            'con_precio' => 0,
        ];
        continue;
    }
    
    // Limitar si se especificó
    if ($limite > 0) {
        $productos = array_slice($productos, 0, $limite);
    }
    
    $procesados = 0;
    $conPrecio = 0;
    $sinPrecio = 0;
    $errores = 0;
    
    foreach ($productos as $producto) {
        $procesados++;
        $searchTerm = $producto['nome'];
        if (!empty($producto['marca'])) {
            $searchTerm .= ' ' . $producto['marca'];
        }
        
        try {
            $results = $searcher->searchAll($searchTerm, $superNome);
            
            if (!empty($results)) {
                // Tomar el primer resultado (mejor match)
                $best = $results[0];
                $price = $best['price'];
                $url = $best['url'];
                
                if ($price > 0) {
                    // Guardar precio
                    upsertPrecio($db, (int)$producto['id'], $superId, [
                        'precio' => $price,
                        'url' => $url,
                    ]);
                    $conPrecio++;
                } else {
                    $sinPrecio++;
                }
            } else {
                $sinPrecio++;
            }
        } catch (Exception $e) {
            $errores++;
        }
        
        // Pequeña pausa para no saturar los servidores
        usleep(500000); // 0.5 segundos
    }
    
    $resultados[] = [
        'supermercado' => $superNome,
        'procesados' => $procesados,
        'con_precio' => $conPrecio,
        'sin_precio' => $sinPrecio,
        'errores' => $errores,
    ];
    
    $totalProcesados += $procesados;
    $totalConPrecio += $conPrecio;
    $totalSinPrecio += $sinPrecio;
    $totalErrores += $errores;
}

jsonResponse([
    'success' => true,
    'message' => "Búsqueda completada. $totalConPrecio precios encontrados de $totalProcesados productos procesados.",
    'total_procesados' => $totalProcesados,
    'total_con_precio' => $totalConPrecio,
    'total_sin_precio' => $totalSinPrecio,
    'total_errores' => $totalErrores,
    'resultados' => $resultados,
]);