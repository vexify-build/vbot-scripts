<?php
/**
 * JSQL Plugin Registry — Main entry point
 *
 * Routes:
 *   GET /api/registry              → Registry metadata
 *   GET /api/plugins                → List plugins (filter/sort/paginate)
 *   GET /api/plugins/:name          → Single plugin
 *   GET /api/plugins/:name/download → Download plugin archive or file
 *   GET /api/search                 → Full-text search
 */

require_once __DIR__ . '/lib/response.php';
require_once __DIR__ . '/lib/error.php';
require_once __DIR__ . '/lib/registry.php';

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip /registry/ prefix if present
$base_prefix = '/registry';
if (strpos($path, $base_prefix) === 0) {
    $path = substr($path, strlen($base_prefix));
}
$path = '/' . ltrim($path, '/');

// Parse path segments
$segments = explode('/', trim($path, '/'));
$resource = $segments[0] ?? '';

// Only handle /api/* routes
if ($resource !== 'api') {
    header('HTTP/1.1 400 Bad Request');
    error_response('BAD_REQUEST', 'Invalid route. Use /api/* endpoints.', 400);
}

$sub = $segments[1] ?? '';

try {
    switch ($sub) {
        case 'registry':
            handle_registry();
            break;
        case 'plugins':
            handle_plugins($segments);
            break;
        case 'search':
            handle_search();
            break;
        default:
            header('HTTP/1.1 404 Not Found');
            error_response('PLUGIN_NOT_FOUND', "Unknown endpoint: /api/$sub", 404);
    }
} catch (RuntimeException $e) {
    send_error($e->getCode() ?: 500, $e->getMessage());
} catch (Throwable $e) {
    send_error(500, 'Internal server error: ' . $e->getMessage());
}

// ─── Handlers ───────────────────────────────────────────────

function handle_registry() {
    $registry = load_registry();

    $categories = array_values(array_unique(array_map(
        fn($p) => $p['category'] ?? 'other',
        $registry['plugins']
    )));

    success_response([
        'schemaVersion' => $registry['schemaVersion'] ?? '1.0',
        'pluginCount' => count($registry['plugins']),
        'categories' => $categories,
        'updatedAt' => $registry['updatedAt'] ?? null,
    ]);
}

function handle_plugins($segments) {
    $registry = load_registry();
    $name = $segments[2] ?? null;

    // /api/plugins/:name/download
    if ($name && ($segments[3] ?? '') === 'download') {
        handle_download($registry, $name);
        return;
    }

    // /api/plugins/:name
    if ($name) {
        handle_single_plugin($registry, $name);
        return;
    }

    // /api/plugins — list with filters
    $filters = [
        'category' => $_GET['category'] ?? null,
        'tag' => isset($_GET['tag']) ? (array) $_GET['tag'] : null,
        'author' => $_GET['author'] ?? null,
        'q' => $_GET['q'] ?? null,
    ];

    $plugins = filter_plugins($registry['plugins'], $filters);

    $sort = $_GET['sort'] ?? 'updatedAt';
    $order = $_GET['order'] ?? 'desc';
    $plugins = sort_plugins($plugins, $sort, $order);

    $page = (int) ($_GET['page'] ?? 1);
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));

    $result = paginate($plugins, $page, $limit);

    success_response([
        'plugins' => $result['items'],
        'pagination' => $result['pagination'],
    ]);
}

function handle_single_plugin($registry, $name) {
    $plugin = find_plugin($registry, $name);
    if (!$plugin) {
        header('HTTP/1.1 404 Not Found');
        error_response('PLUGIN_NOT_FOUND', "Plugin '$name' not found in registry", 404);
    }
    success_response($plugin);
}

function handle_download($registry, $name) {
    $plugin = find_plugin($registry, $name);
    if (!$plugin) {
        header('HTTP/1.1 404 Not Found');
        error_response('PLUGIN_NOT_FOUND', "Plugin '$name' not found in registry", 404);
    }

    $file = $_GET['file'] ?? null;

    if ($file) {
        // Download single file
        $file = ltrim($file, '/');
        $base_url = 'https://gh-proxy.com/https://raw.githubusercontent.com/vexify-org/jsql-plugins/main/';
        $url = $base_url . $file;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'JSQL-Registry/1.0',
        ]);
        $content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($http_code !== 200 || $content === false) {
            header('HTTP/1.1 404 Not Found');
            error_response('FILE_NOT_FOUND', "File '$file' not found in plugin '$name'", 404);
        }

        $filename = basename($file);
        header('Content-Type: ' . ($content_type ?: 'application/octet-stream'));
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header('X-Request-ID: req_' . bin2hex(random_bytes(6)));
        echo $content;
        exit;
    }

    // Download full archive as .tgz
    $base_url = 'https://gh-proxy.com/https://raw.githubusercontent.com/vexify-org/jsql-plugins/main/';

    // Create temporary directory
    $tmp_dir = sys_get_temp_dir() . '/jsql_' . uniqid();
    mkdir($tmp_dir, 0755, true);

    // Download all files
    $downloaded = [];
    foreach ($plugin['files'] as $file_path) {
        $url = $base_url . $file_path;
        $local_path = $tmp_dir . '/' . $file_path;
        $local_dir = dirname($local_path);
        if (!is_dir($local_dir)) {
            mkdir($local_dir, 0755, true);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'JSQL-Registry/1.0',
        ]);
        $content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $content !== false) {
            file_put_contents($local_path, $content);
            $downloaded[] = $local_path;
        }
    }

    if (empty($downloaded)) {
        // Cleanup
        exec('rm -rf ' . escapeshellarg($tmp_dir));
        header('HTTP/1.1 502 Bad Gateway');
        error_response('UPSTREAM_ERROR', 'Failed to download plugin files from CDN', 502);
    }

    // Create .tgz archive
    $archive_name = $plugin['name'] . '-' . $plugin['version'] . '.tgz';
    $archive_path = sys_get_temp_dir() . '/' . $archive_name;

    $file_list = implode(' ', array_map('escapeshellarg', $downloaded));
    exec("tar -czf " . escapeshellarg($archive_path) . " -C " . escapeshellarg($tmp_dir) . " " . $file_list . " 2>&1", $output, $ret);

    if ($ret !== 0 || !file_exists($archive_path)) {
        exec('rm -rf ' . escapeshellarg($tmp_dir));
        header('HTTP/1.1 500 Internal Server Error');
        error_response('INTERNAL_ERROR', 'Failed to create plugin archive', 500);
    }

    // Serve archive
    header('Content-Type: application/gzip');
    header("Content-Disposition: attachment; filename=\"$archive_name\"");
    header('Content-Length: ' . filesize($archive_path));
    header('X-Request-ID: req_' . bin2hex(random_bytes(6)));
    readfile($archive_path);

    // Cleanup
    exec('rm -rf ' . escapeshellarg($tmp_dir) . ' ' . escapeshellarg($archive_path));
    exit;
}

function handle_search() {
    $q = $_GET['q'] ?? '';
    $q = trim($q);

    if ($q === '') {
        header('HTTP/1.1 400 Bad Request');
        error_response('INVALID_QUERY', 'Search query "q" is required', 400);
    }

    $registry = load_registry();

    $filters = [
        'category' => $_GET['category'] ?? null,
        'tag' => isset($_GET['tag']) ? (array) $_GET['tag'] : null,
    ];

    $plugins = filter_plugins($registry['plugins'], $filters);

    $sort = $_GET['sort'] ?? 'relevance';
    $order = $_GET['order'] ?? 'desc';

    // Score and sort by relevance
    if ($sort === 'relevance') {
        $q_lower = strtolower($q);
        $q_terms = explode(' ', $q_lower);

        $scored = [];
        foreach ($plugins as $plugin) {
            $score = 0;
            $matched = [];

            foreach ($q_terms as $term) {
                $name_lower = strtolower($plugin['name'] ?? '');
                $desc_lower = strtolower($plugin['description'] ?? '');
                $tags_lower = array_map('strtolower', $plugin['tags'] ?? []);

                if (strpos($name_lower, $term) !== false) {
                    $score += 10;
                    $matched['name'] = true;
                }
                if (strpos($desc_lower, $term) !== false) {
                    $score += 5;
                    $matched['description'] = true;
                }
                foreach ($tags_lower as $tag) {
                    if (strpos($tag, $term) !== false) {
                        $score += 7;
                        $matched['tags'] = true;
                        break;
                    }
                }
            }

            if ($score > 0) {
                $scored[] = [
                    'plugin' => $plugin,
                    'score' => $score,
                    'matchedFields' => array_keys($matched),
                ];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $results = array_column($scored, null);
    } else {
        $plugins = sort_plugins($plugins, $sort, $order);
        $results = array_map(fn($p) => ['plugin' => $p, 'score' => 0, 'matchedFields' => []], $plugins);
    }

    $page = (int) ($_GET['page'] ?? 1);
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));

    $result = paginate($results, $page, $limit);

    success_response([
        'query' => $q,
        'results' => $result['items'],
        'pagination' => $result['pagination'],
    ]);
}
