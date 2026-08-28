<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function api_request(string $method, string $endpoint, $data = null) {
    $url = API_BASE_URL . $endpoint;
    $ch = curl_init();

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    if (isset($_SESSION['access_token'])) {
        $headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
    }

    if (isset($_SESSION['gym_id'])) {
        $headers[] = 'X-Gym-ID: ' . $_SESSION['gym_id'];
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Skip SSL verification in development
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if ($data !== null) {
        $json_data = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);

    return [
        'status' => $http_code,
        'data' => $decoded
    ];
}

function require_login() {
    if (!isset($_SESSION['access_token'])) {
        header('Location: index.php');
        exit;
    }
}

function has_role(array $allowed_roles): bool {
    if (!isset($_SESSION['user_roles'])) {
        return false;
    }
    
    foreach ($_SESSION['user_roles'] as $r) {
        if (in_array($r['role'], $allowed_roles)) {
            return true;
        }
    }
    return false;
}

function require_roles(array $allowed_roles) {
    require_login();
    if (!has_role($allowed_roles)) {
        http_response_code(403);
        echo "<h1 style='color:red; text-align:center; margin-top:50px;'>Access Denied. Unauthorized Role.</h1>";
        exit;
    }
}
