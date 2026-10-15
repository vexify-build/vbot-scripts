<?php
/**
 * Response helper — consistent JSON envelope
 */

function json_response($status, $data = null, $error = null, $http_code = 200) {
    $request_id = 'req_' . bin2hex(random_bytes(6));
    $timestamp = gmdate('Y-m-d\TH:i:s\Z');

    header('Content-Type: application/json; charset=utf-8');
    header("X-Request-ID: $request_id");
    http_response_code($http_code);

    $envelope = [
        'status' => $status,
        'meta' => [
            'requestId' => $request_id,
            'timestamp' => $timestamp,
        ],
    ];

    if ($status === 'success') {
        $envelope['data'] = $data;
    } else {
        $envelope['error'] = $error;
    }

    echo json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function success_response($data) {
    json_response('success', $data, null, 200);
}

function error_response($code, $message, $http_code = 400) {
    json_response('error', null, [
        'code' => $code,
        'message' => $message,
    ], $http_code);
}
