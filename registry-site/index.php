<?php
/**
 * JSQL Plugin Registry - API Server (Query String Routing)
 * No URL rewriting needed — uses ?action=xxx
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

// --- Config ---
const REGISTRY_URL = 'https://gh-proxy.com/https://raw.githubusercontent.com/vexify-org/jsql-plugins/main/registry.json';
const CACHE_TTL = 300; // 5 minutes

// --- Cache path (ensure cache dir exists, fall back to system temp) ---
$cache_dir = __DIR__ . '/registry/';
if (!is_dir($cache_dir)) {
    @mkdir($cache_dir, 0755, true) || $cache_dir = sys_get_temp_dir() . '/jsql_registry/';
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }
}
$CACHE_FILE = $cache_dir . 'cache.json';

// --- Helpers ---
function respond($data, $meta = []) {
    $requestId = 'req_' . bin2hex(random_bytes(8));
    $timestamp = date('c');
    echo json_encode([
        'status' => 'success',
        'data'   => $data,
        'meta'   => array_merge(['requestId' => $requestId, 'timestamp' => $timestamp], $meta),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function respondError($message, $code = 404) {
    http_response_code($code);
    echo json_encode([
        'status' => 'error',
        'error'  => $message,
        'meta'   => ['requestId' => 'req_' . bin2hex(random_bytes(8)), 'timestamp' => date('c')],
    ], JSON_PRETTY_PRINT);
    exit;
}

function fetchRegistry() {
    global $CACHE_FILE;

    // Check cache
    if (file_exists($CACHE_FILE) && (time() - filemtime($CACHE_FILE)) < CACHE_TTL) {
        $cached = @json_decode(@file_get_contents($CACHE_FILE), true);
        if ($cached !== null) return $cached;
    }

    // Fetch from GitHub
    $ctx = stream_context_create(['http' => ['timeout' => 10]]);
    $json = @file_get_contents(REGISTRY_URL, false, $ctx);

    if ($json === false) {
        // If we have a stale cache, use it
        if (file_exists($CACHE_FILE)) {
            $stale = @json_decode(@file_get_contents($CACHE_FILE), true);
            if ($stale !== null) return $stale;
        }
        respondError('Failed to fetch registry from GitHub', 502);
    }

    $data = json_decode($json, true);
    if ($data === null) {
        respondError('Invalid registry data', 502);
    }

    // Save cache (failure must not block the request)
    @file_put_contents($CACHE_FILE, $json);
    return $data;
}

// --- Router (Query String) ---
$action = $_GET['action'] ?? '';

if ($action === '') {
    respondError('Missing action parameter. Use ?action=registry, ?action=plugins, ?action=plugin&name=xxx, ?action=search&q=xxx', 400);
}

$registry = fetchRegistry();

// --- Route: ?action=registry ---
if ($action === 'registry') {
    respond([
        'schemaVersion' => '1.0',
        'pluginCount'   => count($registry['plugins'] ?? []),
        'categories'    => $registry['categories'] ?? [],
        'updatedAt'     => $registry['updatedAt'] ?? date('c'),
    ]);
}

// --- Route: ?action=plugins ---
if ($action === 'plugins') {
    respond($registry['plugins'] ?? []);
}

// --- Route: ?action=plugin&name=xxx ---
if ($action === 'plugin') {
    $name = trim($_GET['name'] ?? '');
    if ($name === '') {
        respondError('Missing name parameter', 400);
    }

    $plugins = $registry['plugins'] ?? [];
    foreach ($plugins as $plugin) {
        if (($plugin['name'] ?? '') === $name) {
            respond($plugin);
        }
    }
    respondError("Plugin '{$name}' not found", 404);
}

// --- Route: ?action=search&q=xxx ---
if ($action === 'search') {
    $q = trim($_GET['q'] ?? '');
    if ($q === '') {
        respondError('Missing search query parameter: q', 400);
    }

    $q = strtolower($q);
    $results = [];
    $plugins = $registry['plugins'] ?? [];

    foreach ($plugins as $plugin) {
        $searchable = strtolower(implode(' ', [
            $plugin['name'] ?? '',
            $plugin['description'] ?? '',
            $plugin['category'] ?? '',
            implode(' ', $plugin['tags'] ?? []),
        ]));
        if (strpos($searchable, $q) !== false) {
            $results[] = $plugin;
        }
    }

    respond($results, ['query' => $q, 'total' => count($results)]);
}

// --- Unknown action ---
respondError('Unknown action: ' . $action, 400);
