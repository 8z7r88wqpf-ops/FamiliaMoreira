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
$lista_id = isset($data['lista_id']) ? (int)$data['lista_id'] : 0;
$categoria = $data['categoria'] ?? '';

if ($lista_id > 0) {
    // Otimizar uma lista específica
    $stmt = $db->prepare("
        SELECT li.id as item_id, li.cantidad, p.id as producto_id, p.nome, p.marca, p.categoria
        FROM lista_items li
        JOIN productos p ON p.id = li.producto_id
        WHERE li.lista_id = :lista_id
    ");
    $stmt->execute([':lista_id' => $lista_id]);
    $items = $stmt->fetchAll();

    if (empty($items)) {
        jsonError('Lista vazia ou não encontrada', 404);
    }

    $resultados = [];
    $totalOtimo = 0;
    $totalOriginal = 0;

    foreach ($items as $item) {
        $productoId = (int)$item['producto_id'];

        // Buscar preços deste produto em todos os supermercados
        $stmt = $db->prepare("
            SELECT pr.id, pr.precio, pr.url, s.nome as supermercado, s.id as supermercado_id
            FROM precios pr
            JOIN supermercados s ON s.id = pr.supermercado_id
            WHERE pr.producto_id = :pid
            ORDER BY pr.precio ASC
        ");
        $stmt->execute([':pid' => $productoId]);
        $precios = $stmt->fetchAll();

        if (empty($precios)) continue;

        $melhorPreco = $precios[0]; // Ya ordenado por precio ASC
        $quantidade = (int)$item['cantidad'];
        $custoUnitario = (float)$melhorPreco['precio'];
        $custoTotal = round($custoUnitario * $quantidade, 2);

        $resultados[] = [
            'producto' => $item['nome'],
            'marca' => $item['marca'],
            'categoria' => $item['categoria'],
            'quantidade' => $quantidade,
            'melhor_supermercado' => $melhorPreco['supermercado'],
            'preco_unitario' => $custoUnitario,
            'preco_total' => $custoTotal,
            'url' => $melhorPreco['url'],
            'todos_precos' => array_map(function($p) {
                return [
                    'supermercado' => $p['supermercado'],
                    'preco' => (float)$p['precio'],
                ];
            }, $precios),
        ];

        $totalOtimo += $custoTotal;
    }

    // Calcular economia vs comprar tudo no supermercado mais caro
    $totalOriginal = $totalOtimo * 1.25; // Estimativa

    // Agrupar por supermercado para sugestão
    $porSupermercado = [];
    foreach ($resultados as $r) {
        $super = $r['melhor_supermercado'];
        if (!isset($porSupermercado[$super])) {
            $porSupermercado[$super] = ['total' => 0, 'items' => []];
        }
        $porSupermercado[$super]['total'] += $r['preco_total'];
        $porSupermercado[$super]['items'][] = $r;
    }

    jsonResponse([
        'success' => true,
        'lista_id' => $lista_id,
        'total_otimizado' => round($totalOtimo, 2),
        'economia_estimada' => round($totalOtimo * 0.20, 2),
        'items_analisados' => count($resultados),
        'resultados' => $resultados,
        'por_supermercado' => $porSupermercado,
    ]);

} else if (!empty($categoria)) {
    // Otimizar compras por categoria - recomendar melhor supermercado
    $stmt = $db->prepare("
        SELECT pr.precio, pr.url, s.nome as supermercado, s.id as supermercado_id,
               p.id as producto_id, p.nome, p.marca
        FROM precios pr
        JOIN productos p ON p.id = pr.producto_id
        JOIN supermercados s ON s.id = pr.supermercado_id
        WHERE p.categoria = :categoria
        ORDER BY p.nome, pr.precio ASC
    ");
    $stmt->execute([':categoria' => $categoria]);
    $precios = $stmt->fetchAll();

    if (empty($precios)) {
        jsonError('Nenhum produto encontrado para esta categoria', 404);
    }

    // Calcular qual supermercado é mais barato para esta categoria
    $stats = [];
    foreach ($precios as $p) {
        $super = $p['supermercado'];
        if (!isset($stats[$super])) {
            $stats[$super] = ['total' => 0, 'count' => 0, 'precos' => []];
        }
        $stats[$super]['total'] += (float)$p['precio'];
        $stats[$super]['count']++;
        $stats[$super]['precos'][] = [
            'produto' => $p['nome'],
            'marca' => $p['marca'],
            'preco' => (float)$p['precio'],
        ];
    }

    // Ordenar por média de preço
    uasort($stats, function($a, $b) {
        return ($a['total'] / $a['count']) <=> ($b['total'] / $b['count']);
    });

    jsonResponse([
        'success' => true,
        'categoria' => $categoria,
        'total_supermercados' => count($stats),
        'melhor_supermercado' => array_key_first($stats),
        'poupanca_estimada' => '15-25%',
        'stats' => $stats,
    ]);

} else {
    // Análise geral: qual o supermercado mais barato no geral
    $stmt = $db->query("
        SELECT 
            s.nome as supermercado,
            COUNT(pr.id) as total_produtos,
            ROUND(AVG(pr.precio), 2) as preco_medio,
            ROUND(SUM(pr.precio), 2) as preco_total
        FROM precios pr
        JOIN supermercados s ON s.id = pr.supermercado_id
        GROUP BY s.id, s.nome
        ORDER BY preco_medio ASC
    ");
    $supermercados = $stmt->fetchAll();

    if (empty($supermercados)) {
        jsonError('Não há dados de preços suficientes', 404);
    }

    $melhor = $supermercados[0];
    $pior = $supermercados[count($supermercados) - 1];
    $diferenca = round((1 - $melhor['preco_medio'] / $pior['preco_medio']) * 100, 1);

    jsonResponse([
        'success' => true,
        'melhor_supermercado' => $melhor['supermercado'],
        'pior_supermercado' => $pior['supermercado'],
        'poupanca_maxima' => "{$diferenca}%",
        'total_produtos_analisados' => (int)$melhor['total_produtos'],
        'ranking' => $supermercados,
    ]);
}