<?php
/**
 * Registry — loads and caches registry.json from GitHub via gh-proxy CDN
 */

define('REGISTRY_CDN_URL', 'https://gh-proxy.com/https://raw.githubusercontent.com/vexify-org/jsql-plugins/main/registry.json');
define('CACHE_DIR', __DIR__ . '/../cache/');
define('CACHE_FILE', CACHE_DIR . 'registry.json');
define('CACHE_TTL', 300); // 5 minutes

/**
 * Load registry.json with file-based caching.
 *
 * @return array Parsed registry data
 * @throws RuntimeException on upstream failure or malformed data
 */
function load_registry() {
    // Ensure cache directory exists (fall back to system temp if not writable)
    if (!is_dir(CACHE_DIR)) {
        @mkdir(CACHE_DIR, 0755, true);
    }
    $cache_dir = is_writable(CACHE_DIR) ? CACHE_DIR : (rtrim(sys_get_temp_dir(), '/') . '/jsql_registry/');
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }
    $cache_file = $cache_dir . 'registry.json';

    // Check cache (primary file, then legacy CACHE_FILE path)
    foreach (array_unique([$cache_file, CACHE_FILE]) as $cf) {
        if (is_file($cf) && (time() - filemtime($cf)) < CACHE_TTL) {
            $raw = @file_get_contents($cf);
            $data = json_decode($raw, true);
            if ($data !== null && isset($data['plugins'])) {
                return $data;
            }
        }
    }

    // Fetch from CDN
    $ch = curl_init(REGISTRY_CDN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'JSQL-Registry/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    $body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        // If we have a stale cache, serve it
        foreach (array_unique([$cache_file, CACHE_FILE]) as $cf) {
            if (is_file($cf)) {
                $raw = @file_get_contents($cf);
                $data = json_decode($raw, true);
                if ($data !== null && isset($data['plugins'])) {
                    return $data;
                }
            }
        }
        throw new RuntimeException("Failed to fetch registry from CDN: $curl_error", 502);
    }

    if ($http_code !== 200) {
        // Serve stale cache if available
        foreach (array_unique([$cache_file, CACHE_FILE]) as $cf) {
            if (is_file($cf)) {
                $raw = @file_get_contents($cf);
                $data = json_decode($raw, true);
                if ($data !== null && isset($data['plugins'])) {
                    return $data;
                }
            }
        }
        throw new RuntimeException("CDN returned HTTP $http_code", 502);
    }

    $data = json_decode($body, true);
    if ($data === null || !isset($data['plugins'])) {
        // Serve stale cache if available
        foreach (array_unique([$cache_file, CACHE_FILE]) as $cf) {
            if (is_file($cf)) {
                $raw = @file_get_contents($cf);
                $data = json_decode($raw, true);
                if ($data !== null && isset($data['plugins'])) {
                    return $data;
                }
            }
        }
        throw new RuntimeException("Invalid registry.json received from CDN", 503);
    }

    // Write cache (failure must not block the request)
    @file_put_contents($cache_file, $body, LOCK_EX);

    return $data;
}

/**
 * Find a plugin by name.
 *
 * @param array $registry  Full registry data
 * @param string $name     Plugin name
 * @return array|null      Plugin data or null
 */
function find_plugin($registry, $name) {
    foreach ($registry['plugins'] as $plugin) {
        if ($plugin['name'] === $name) {
            return $plugin;
        }
    }
    return null;
}

/**
 * Filter plugins by query parameters.
 *
 * @param array $plugins  Plugin array
 * @param array $filters  Query parameters
 * @return array          Filtered plugins
 */
function filter_plugins($plugins, $filters) {
    // Category filter
    if (!empty($filters['category'])) {
        $cat = $filters['category'];
        $plugins = array_filter($plugins, function($p) use ($cat) {
            return ($p['category'] ?? '') === $cat;
        });
    }

    // Tag filter (AND logic for multiple tags)
    if (!empty($filters['tag'])) {
        $tags = (array) $filters['tag'];
        $plugins = array_filter($plugins, function($p) use ($tags) {
            $ptags = $p['tags'] ?? [];
            foreach ($tags as $t) {
                if (!in_array($t, $ptags)) return false;
            }
            return true;
        });
    }

    // Author filter
    if (!empty($filters['author'])) {
        $author = $filters['author'];
        $plugins = array_filter($plugins, function($p) use ($author) {
            return ($p['author'] ?? '') === $author;
        });
    }

    // Full-text search
    if (!empty($filters['q'])) {
        $q = strtolower($filters['q']);
        $plugins = array_filter($plugins, function($p) use ($q) {
            $haystack = strtolower(($p['name'] ?? '') . ' ' . ($p['description'] ?? '') . ' ' . implode(' ', $p['tags'] ?? []));
            return strpos($haystack, $q) !== false;
        });
    }

    return array_values($plugins);
}

/**
 * Sort plugins.
 *
 * @param array  $plugins  Plugin array
 * @param string $sort     Sort field
 * @param string $order    asc or desc
 * @return array
 */
function sort_plugins($plugins, $sort, $order) {
    $field = match ($sort) {
        'name' => 'name',
        'downloads' => 'downloads',
        'createdAt' => 'createdAt',
        'updatedAt' => 'updatedAt',
        default => 'updatedAt',
    };

    usort($plugins, function($a, $b) use ($field, $order) {
        $cmp = ($a[$field] ?? '') <=> ($b[$field] ?? '');
        return $order === 'asc' ? $cmp : -$cmp;
    });

    return $plugins;
}

/**
 * Paginate results.
 *
 * @param array $items     Full list
 * @param int   $page      Page number (1-indexed)
 * @param int   $limit     Items per page
 * @return array           Paginated slice + metadata
 */
function paginate($items, $page, $limit) {
    $total = count($items);
    $total_pages = (int) ceil($total / $limit);
    $page = max(1, min($page, $total_pages ?: 1));
    $offset = ($page - 1) * $limit;

    return [
        'items' => array_slice($items, $offset, $limit),
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $total_pages,
        ],
    ];
}
