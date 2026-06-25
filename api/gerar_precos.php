<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';
require_once __DIR__ . '/lib/precios_helpers.php';

handlePreflight();
requireMethod('POST');

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$supermercado = $data['supermercado'] ?? 'all';
$categoria = $data['categoria'] ?? '';

// Precios base realistas por categoría (precio mínimo en €)
$preciosBase = [
    'Arroz'             => ['min' => 0.65, 'max' => 3.50],
    'Massas'            => ['min' => 0.55, 'max' => 2.80],
    'Laticínios'        => ['min' => 0.45, 'max' => 5.00],
    'Carnes'            => ['min' => 2.50, 'max' => 12.00],
    'Peixaria'          => ['min' => 3.00, 'max' => 15.00],
    'Frutas'            => ['min' => 0.50, 'max' => 4.00],
    'Legumes'           => ['min' => 0.40, 'max' => 3.00],
    'Refrigerantes'     => ['min' => 0.60, 'max' => 3.50],
    'Águas'             => ['min' => 0.25, 'max' => 1.50],
    'Bebidas Alcoólicas'=> ['min' => 1.50, 'max' => 25.00],
    'Café'              => ['min' => 1.50, 'max' => 8.00],
    'Chás'              => ['min' => 1.00, 'max' => 4.00],
    'Cereais'           => ['min' => 1.20, 'max' => 5.00],
    'Conservas'         => ['min' => 0.60, 'max' => 4.50],
    'Molhos'            => ['min' => 0.80, 'max' => 4.00],
    'Azeite e Óleos'    => ['min' => 1.50, 'max' => 8.00],
    'Temperos'          => ['min' => 0.50, 'max' => 3.00],
    'Açúcar'            => ['min' => 0.60, 'max' => 4.50],
    'Congelados'        => ['min' => 1.00, 'max' => 6.00],
    'Farinhas'          => ['min' => 0.50, 'max' => 3.00],
    'Padaria'           => ['min' => 0.60, 'max' => 3.50],
    'Higiene'           => ['min' => 1.00, 'max' => 8.00],
    'Limpeza'           => ['min' => 0.80, 'max' => 7.00],
    'Ovos'              => ['min' => 1.20, 'max' => 3.50],
    'Charcutaria'       => ['min' => 1.00, 'max' => 6.00],
    'Mercearia'         => ['min' => 0.50, 'max' => 5.00],
    'Frescos'           => ['min' => 1.00, 'max' => 8.00],
    'Bebé'              => ['min' => 1.50, 'max' => 10.00],
    'Animais'           => ['min' => 1.00, 'max' => 8.00],
];

// Factores de precio por supermercado (unos más caros que otros)
$factoresSupermercado = [
    'Continente'  => 1.0,
    'Pingo Doce'  => 0.95,
    'Auchan'      => 0.90,
    'Mercadona'   => 0.92,
    'Lidl'        => 0.85,
];

// Obtener supermercados
$supermercados = [];
$stmt = $db->query("SELECT id, nome FROM supermercados ORDER BY nome");
while ($row = $stmt->fetch()) {
    $supermercados[] = $row;
}

// Determinar qué supermercados procesar
$supermercadosAProcesar = [];
if ($supermercado === 'all') {
    $supermercadosAProcesar = $supermercados;
} else {
    foreach ($supermercados as $s) {
        if (strtolower(trim($s['nome'])) === strtolower(trim($supermercado))) {
            $supermercadosAProcesar[] = $s;
            break;
        }
    }
}

if (empty($supermercadosAProcesar)) {
    jsonError('Supermercado no encontrado', 400);
}

// Obtener productos sin precio
$where = [];
$params = [];

if (!empty($categoria)) {
    $where[] = "p.categoria = :categoria";
    $params[':categoria'] = $categoria;
}

$whereClause = !empty($where) ? ' AND ' . implode(' AND ', $where) : '';

$totalGenerados = 0;
$resultados = [];

foreach ($supermercadosAProcesar as $superData) {
    $superId = (int)$superData['id'];
    $superNome = $superData['nome'];
    $factor = $factoresSupermercado[strtolower(trim($superNome))] ?? $factoresSupermercado[$superNome] ?? 1.0;
    
    // Productos sin precio en este supermercado
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
            'mensaje' => 'Todos os produtos já têm preço',
            'gerados' => 0,
        ];
        continue;
    }
    
    $gerados = 0;
    
    foreach ($productos as $producto) {
        $cat = $producto['categoria'] ?? 'Mercearia';
        $base = $preciosBase[$cat] ?? ['min' => 0.50, 'max' => 5.00];
        
        // Generar precio aleatorio realista
        $preco = round(mt_rand((int)($base['min'] * 100), (int)($base['max'] * 100)) / 100 * $factor, 2);
        if ($preco < 0.10) $preco = 0.10;
        
        // URL de ejemplo (placeholder)
        $url = 'https://www.' . strtolower(str_replace(' ', '', $superNome)) . '.pt/produto/' . $producto['id'];
        
        try {
            upsertPrecio($db, (int)$producto['id'], $superId, [
                'precio' => $preco,
                'url' => $url,
            ]);
            $gerados++;
        } catch (Exception $e) {
            // Ignorar errores
        }
    }
    
    $resultados[] = [
        'supermercado' => $superNome,
        'gerados' => $gerados,
        'total_sem_preco' => count($productos),
    ];
    
    $totalGenerados += $gerados;
}

jsonResponse([
    'success' => true,
    'message' => "$totalGenerados preços gerados com sucesso!",
    'total_gerados' => $totalGenerados,
    'resultados' => $resultados,
]);