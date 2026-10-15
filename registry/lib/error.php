<?php
/**
 * Error codes and handling
 */

const ERROR_CODES = [
    400 => 'BAD_REQUEST',
    404 => 'PLUGIN_NOT_FOUND',
    429 => 'RATE_LIMITED',
    500 => 'INTERNAL_ERROR',
    502 => 'UPSTREAM_ERROR',
    503 => 'REGISTRY_UNAVAILABLE',
];

function get_error_code($http_code) {
    return ERROR_CODES[$http_code] ?? 'INTERNAL_ERROR';
}

function send_error($http_code, $custom_message = '') {
    $code = get_error_code($http_code);
    $message = $custom_message ?: "Error $http_code";
    error_response($code, $message, $http_code);
}
