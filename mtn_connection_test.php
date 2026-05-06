<?php
/**
 * MTN MoMo Connection Test Handler
 * For testing API connectivity from admin panel
 */

// Include PHPNuxBill
require_once '../../../init.php';

function test_mtn_connection() {
    global $config;
    
    $api_user_id = $_POST['api_user_id'] ?? '';
    $collection_subscription_key = $_POST['collection_subscription_key'] ?? '';
    $environment = $_POST['environment'] ?? 'sandbox';
    
    if (empty($api_user_id) || empty($collection_subscription_key)) {
        echo json_encode([
            'success' => false,
            'message' => 'API User ID and Subscription Key are required'
        ]);
        exit;
    }
    
    // Determine server URL
    $server_url = $environment === 'live' 
        ? 'https://momodeveloper.mtn.com' 
        : 'https://sandbox.momodeveloper.mtn.com';
    
    // Test token endpoint
    $token_url = $server_url . '/collection/token/';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'client_credentials'
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($api_user_id . ':' . $collection_subscription_key),
        'Content-Type: application/x-www-form-urlencoded',
        'Ocp-Apim-Subscription-Key: ' . $collection_subscription_key
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['access_token'])) {
            echo json_encode([
                'success' => true,
                'message' => 'Connection successful! Access token obtained.',
                'environment' => $environment,
                'token_type' => $result['token_type'] ?? 'Bearer',
                'expires_in' => $result['expires_in'] ?? 3600
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid response from server: ' . $response
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Connection failed. HTTP Code: ' . $http_code . ', Response: ' . $response
        ]);
    }
}

// Handle the test request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_mtn_connection'])) {
    test_mtn_connection();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}
?>
