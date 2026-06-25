<?php
declare(strict_types=1);

/**
 * Middleware de cabeceras HTTP y CORS
 * Centraliza todas las cabeceras para que no estén en database.php
 */

function sendCorsHeaders(): void {
    if (headers_sent()) return;
    
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
}

function handlePreflight(): bool {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        sendCorsHeaders();
        http_response_code(204);
        exit();
    }
    return true;
}

/**
 * Envía una respuesta JSON y termina la ejecución
 */
function jsonResponse(mixed $data, int $statusCode = 200): void {
    sendCorsHeaders();
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

/**
 * Envía una respuesta de error JSON
 */
function jsonError(string $message, int $statusCode = 400): void {
    jsonResponse(['error' => $message], $statusCode);
}

/**
 * Valida que un método HTTP coincida
 */
function requireMethod(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        jsonError('Method not allowed', 405);
    }
}