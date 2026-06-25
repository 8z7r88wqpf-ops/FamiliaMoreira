<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';
$db = (new Database())->getConnection();

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? $data['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // GET: obtener histórico de un producto
    $productoId = (int)($_GET['producto_id'] ?? 0);
    $dias = (int)($_GET['dias'] ?? 30);
    if (!$productoId) { jsonError('producto_id requerido', 400); }
    
    $stmt = $db->prepare("
        SELECT h.*, s.nome as supermercado_nome
        FROM historico_precos h
        JOIN supermercados s ON s.id = h.supermercado_id
        WHERE h.producto_id = :pid AND h.fecha >= DATE_SUB(NOW(), INTERVAL :dias DAY)
        ORDER BY h.fecha ASC
    ");
    $stmt->execute([':pid' => $productoId, ':dias' => $dias]);
    jsonResponse($stmt->fetchAll());
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'registrar') {
    // Registrar preço atual no histórico (chamado após atualizar preço)
    $productoId = (int)($data['producto_id'] ?? 0);
    $supermercadoId = (int)($data['supermercado_id'] ?? 0);
    $precio = (float)($data['precio'] ?? 0);
    
    if (!$productoId || !$supermercadoId || $precio <= 0) jsonError('Datos inválidos', 400);
    
    $stmt = $db->prepare("INSERT INTO historico_precos (producto_id, supermercado_id, precio) VALUES (:pid, :sid, :precio)");
    $stmt->execute([':pid' => $productoId, ':sid' => $supermercadoId, ':precio' => $precio]);
    jsonResponse(['success' => true]);
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'analise') {
    // Análise de tendências
    $stmt = $db->query("
        SELECT p.id, p.nome, p.categoria,
               ROUND(AVG(h.precio), 2) as preco_medio,
               MIN(h.precio) as preco_min,
               MAX(h.precio) as preco_max,
               COUNT(*) as leituras,
               ROUND(((MAX(h.precio) - MIN(h.precio)) / MIN(h.precio)) * 100, 1) as variacao
        FROM historico_precos h
        JOIN productos p ON p.id = h.producto_id
        WHERE h.fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY p.id, p.nome, p.categoria
        HAVING variacao > 5
        ORDER BY variacao DESC
        LIMIT 20
    ");
    jsonResponse($stmt->fetchAll());
}