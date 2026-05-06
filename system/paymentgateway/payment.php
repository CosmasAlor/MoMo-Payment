<?php

/**
 * MTN MoMo Payment Gateway for PHPNuxBill
 * 
 * Plugin Name: MTN Mobile Money (South Sudan)
 * Description: MTN Mobile Money payment gateway for PHPNuxBill with South Sudan (SSP) support
 * Version: 2.0
 * Author: PHPNuxBill Community
 * 
 * File: system/paymentgateway/payment.php
 * Template: system/paymentgateway/ui/payment.tpl
 * 
 * Compatible with MTN MoMo Collection API v1.0
 * https://momodeveloper.mtn.com/API-collections
 */

/**
 * Validate that the gateway configuration is complete.
 * Called by PHPNuxBill before allowing transactions.
 */
function payment_validate_config()
{
    global $config;
    if (empty($config['mtnmomo_api_user_id']) || empty($config['mtnmomo_collection_subscription_key'])) {
        Message::sendTelegram("MTN MoMo payment gateway not configured.\nPlease set API User ID and Collection Subscription Key.");
        r2(U . 'order/package', 'w', Lang::T("Admin has not yet setup MTN MoMo payment gateway, please tell admin"));
    }
}

/**
 * Display the gateway configuration form in the admin panel.
 */
function payment_show_config()
{
    global $ui, $config;
    $ui->assign('_title', 'MTN MoMo - Payment Gateway');
    $ui->assign('env', $config['mtnmomo_environment'] ?? 'sandbox');
    $ui->assign('api_user_id', $config['mtnmomo_api_user_id'] ?? '');
    $ui->assign('collection_subscription_key', $config['mtnmomo_collection_subscription_key'] ?? '');
    $ui->assign('api_key', $config['mtnmomo_api_key'] ?? '');
    $ui->assign('callback_url', $config['mtnmomo_callback_url'] ?? '');
    $ui->assign('currency', $config['mtnmomo_currency'] ?? 'SSP');
    $ui->assign('country_code', $config['mtnmomo_country_code'] ?? '211');
    $ui->assign('auto_credit', $config['mtnmomo_auto_credit'] ?? 'yes');
    $ui->assign('payment_timeout', $config['mtnmomo_payment_timeout'] ?? '60');
    $ui->assign('webhook_secret', $config['mtnmomo_webhook_secret'] ?? '');
    $ui->display('payment.tpl');
}

/**
 * Save the gateway configuration from admin panel form.
 */
function payment_save_config()
{
    global $admin, $_L;

    $settings = [
        'mtnmomo_environment',
        'mtnmomo_api_user_id',
        'mtnmomo_collection_subscription_key',
        'mtnmomo_api_key',
        'mtnmomo_callback_url',
        'mtnmomo_currency',
        'mtnmomo_country_code',
        'mtnmomo_payment_timeout',
        'mtnmomo_webhook_secret',
    ];

    foreach ($settings as $key) {
        // Strip the prefix for _post() lookup
        $post_key = str_replace('mtnmomo_', '', $key);
        $value = _post($post_key);

        $d = ORM::for_table('tbl_appconfig')->where('setting', $key)->find_one();
        if ($d) {
            $d->value = $value;
            $d->save();
        } else {
            $d = ORM::for_table('tbl_appconfig')->create();
            $d->setting = $key;
            $d->value = $value;
            $d->save();
        }
    }

    _log('[' . $admin['username'] . ']: MTN MoMo ' . $_L['Settings_Saved_Successfully'], 'Admin', $admin['id']);
    r2(U . 'paymentgateway/payment', 's', $_L['Settings_Saved_Successfully']);
}

/**
 * Create a payment transaction.
 * Called when a customer selects MTN MoMo as their payment method.
 * 
 * PHPNuxBill creates the tbl_payment_gateway row BEFORE calling this function.
 * We update it with our gateway-specific data, then initiate the MTN API call.
 *
 * @param array|object $trx  Transaction data (id, plan_name, price, routers, plan_id, etc.)
 * @param array|object $user User data (id, username, fullname, phonenumber, email, etc.)
 */
function payment_create_transaction($trx, $user)
{
    global $config;

    $environment = $config['mtnmomo_environment'] ?: 'sandbox';
    $api_user_id = $config['mtnmomo_api_user_id'];
    $subscription_key = $config['mtnmomo_collection_subscription_key'];
    $api_key = $config['mtnmomo_api_key'];
    $currency = $config['mtnmomo_currency'] ?: 'SSP';
    $country_code = $config['mtnmomo_country_code'] ?: '211';
    $callback_url = $config['mtnmomo_callback_url'];
    $timeout = intval($config['mtnmomo_payment_timeout'] ?: 60);

    // Determine the API endpoint
    $base_url = payment_get_server();

    // Generate a unique reference ID (UUID v4) for this transaction
    $reference_id = payment_uuid_v4();

    // Prepare the phone number: strip +, leading 0, or existing country code, then prepend country code
    $phone = $user['phonenumber'];
    $phone = preg_replace('/[^0-9]/', '', $phone); // strip non-digits
    $phone = preg_replace('/^' . $country_code . '/', '', $phone); // remove country code if present
    $phone = preg_replace('/^0/', '', $phone); // remove leading 0
    $phone = $country_code . $phone;

    // =========================================
    // Step 1: Get an OAuth2 access token
    // =========================================
    $token = payment_get_access_token($base_url, $api_user_id, $api_key, $subscription_key);
    if (!$token) {
        Message::sendTelegram("MTN MoMo: Failed to obtain access token\nTransaction: " . $trx['id']);
        r2(U . 'order/package', 'e', Lang::T("MTN MoMo: Unable to authenticate. Please try again later."));
    }

    // =========================================
    // Step 2: Request to Pay (Collection API v1.0)
    // =========================================
    $payload = [
        'amount'       => (string) $trx['price'],
        'currency'     => $currency,
        'externalId'   => (string) $trx['id'],
        'payer'        => [
            'partyIdType' => 'MSISDN',
            'partyId'     => $phone,
        ],
        'payerMessage' => 'Payment for ' . $trx['plan_name'],
        'payeeNote'    => 'PHPNuxBill Transaction #' . $trx['id'],
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
        'X-Reference-Id: ' . $reference_id,
        'X-Target-Environment: ' . $environment,
        'Ocp-Apim-Subscription-Key: ' . $subscription_key,
    ];

    if (!empty($callback_url)) {
        $headers[] = 'X-Callback-Url: ' . $callback_url;
    }

    $ch = curl_init($base_url . 'collection/v1_0/requesttopay');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // HTTP 202 = Accepted (Request to Pay is being processed)
    if ($http_code == 202) {
        // Update the existing tbl_payment_gateway row (created by PHPNuxBill before this call)
        $d = ORM::for_table('tbl_payment_gateway')
            ->where('username', $user['username'])
            ->where('status', 1)
            ->find_one();

        if ($d) {
            $d->gateway_trx_id = $reference_id;
            $d->pg_url_payment = '';
            $d->pg_request = json_encode($payload);
            $d->expired_date = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $d->save();
        }

        _log('[MoMo] Request to Pay accepted. Reference: ' . $reference_id . ', TRX: ' . $trx['id'] . ', Phone: ' . $phone);

        // Redirect user to check status page — PHPNuxBill will poll via get_status
        r2(U . 'order/view/' . $trx['id'], 's', Lang::T("Payment request sent to your phone. Please enter your MoMo PIN to confirm."));
    } else {
        // Request was rejected
        $error_msg = $response ? json_decode($response, true) : [];
        $reason = '';
        if (is_array($error_msg)) {
            $reason = $error_msg['message'] ?? $error_msg['reason'] ?? '';
        }
        if (empty($reason)) {
            $reason = $curl_error ?: 'Unknown error (HTTP ' . $http_code . ')';
        }

        Message::sendTelegram("MTN MoMo payment failed\n\nTRX: " . $trx['id'] . "\nHTTP: " . $http_code . "\nError: " . $reason . "\nResponse: " . $response);
        _log('[MoMo] Request to Pay REJECTED: ' . $reason . ' | HTTP: ' . $http_code . ' | Ref: ' . $reference_id);
        r2(U . 'order/package', 'e', Lang::T("Failed to create transaction.\n") . $reason);
    }
}

/**
 * Handle payment notification callback from MTN MoMo.
 * Called when MTN sends a webhook or when user returns from payment.
 */
function payment_payment_notification()
{
    global $config;

    // MTN MoMo sends callbacks as POST with JSON body
    $payload_raw = file_get_contents('php://input');
    $data = json_decode($payload_raw, true);

    // Verify webhook signature if configured
    $webhook_secret = $config['mtnmomo_webhook_secret'] ?? '';
    if (!empty($webhook_secret) && !empty($payload_raw)) {
        $headers = getallheaders();
        $signature = $headers['X-Momo-Signature'] ?? $headers['X-Webhook-Signature'] ?? '';
        if (!empty($signature)) {
            $expected = hash_hmac('sha256', $payload_raw, $webhook_secret);
            if (!hash_equals($expected, $signature)) {
                _log('[MoMo] Webhook signature verification FAILED');
                http_response_code(401);
                echo json_encode(['status' => 'unauthorized']);
                return;
            }
        }
    }

    if (!empty($data) && isset($data['externalId'])) {
        // This is an async callback from MTN
        $external_id = $data['externalId'];
        $status = $data['status'] ?? '';
        $financial_txn_id = $data['financialTransactionId'] ?? '';

        _log('[MoMo] Webhook received for externalId: ' . $external_id . ' status: ' . $status);

        // Look up the pending payment by externalId (which is our trx id)
        $d = ORM::for_table('tbl_payment_gateway')
            ->where('gateway', 'payment')
            ->where('status', 1)
            ->find_one();

        if (!$d) {
            _log('[MoMo] No pending payment found for externalId: ' . $external_id);
            http_response_code(404);
            echo json_encode(['status' => 'not found']);
            return;
        }

        if ($status === 'SUCCESSFUL') {
            $d->gateway_trx_id = $financial_txn_id ?: $d->gateway_trx_id;
            $d->pg_paid_response = $payload_raw;
            $d->payment_method = 'MTN MoMo';
            $d->payment_channel = 'Mobile Money';
            $d->paid_date = date('Y-m-d H:i:s');
            $d->status = 2;
            $d->save();

            // Activate the plan
            $user = ORM::for_table('tbl_customers')
                ->where('username', $d['username'])
                ->find_one();

            if ($user) {
                if (!Package::rechargeUser($user['id'], $d['routers'], $d['plan_id'], $d['gateway'], 'MoMo')) {
                    _log('[MoMo] Webhook: Failed to activate package for user ' . $d['username']);
                }
                _log('[MoMo] Webhook: Payment SUCCESSFUL, plan activated for ' . $d['username']);
            }
        } elseif ($status === 'FAILED') {
            $d->pg_paid_response = $payload_raw;
            $d->status = 3;
            $d->save();
            _log('[MoMo] Webhook: Payment FAILED for reference: ' . $d['gateway_trx_id']);
        }

        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        return;
    }

    // If no POST data, just redirect back
    r2(U . 'order/package', 'w', Lang::T("Invalid payment notification."));
}

/**
 * Check the payment status. Called when user clicks "Check Payment" in PHPNuxBill.
 * This function follows the exact same pattern as flutterwave_get_status().
 *
 * @param array|object $trx  Transaction record from tbl_payment_gateway
 * @param array|object $user User record from tbl_customers
 */
function payment_get_status($trx, $user)
{
    global $config;

    $reference_id = $trx['gateway_trx_id'];
    if (empty($reference_id)) {
        r2(U . "order/view/" . $trx['id'], 'w', Lang::T("Transaction reference not found."));
        return;
    }

    $base_url = payment_get_server();
    $api_user_id = $config['mtnmomo_api_user_id'];
    $api_key = $config['mtnmomo_api_key'];
    $subscription_key = $config['mtnmomo_collection_subscription_key'];
    $environment = $config['mtnmomo_environment'] ?: 'sandbox';

    // Get a fresh access token
    $token = payment_get_access_token($base_url, $api_user_id, $api_key, $subscription_key);
    if (!$token) {
        r2(U . "order/view/" . $trx['id'], 'w', Lang::T("Unable to check payment status. Please try again."));
        return;
    }

    // Poll MTN MoMo Collection API: GET /collection/v1_0/requesttopay/{referenceId}
    $ch = curl_init($base_url . 'collection/v1_0/requesttopay/' . $reference_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'X-Target-Environment: ' . $environment,
        'Ocp-Apim-Subscription-Key: ' . $subscription_key,
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || empty($response)) {
        r2(U . "order/view/" . $trx['id'], 'w', Lang::T("Transaction still unpaid."));
        return;
    }

    $result = json_decode($response, true);
    $status = $result['status'] ?? 'UNKNOWN';

    if ($status === 'SUCCESSFUL' && $trx['status'] != 2) {
        // Payment confirmed — activate the plan
        if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'MoMo')) {
            r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Failed to activate your Package, please try again later."));
        }
        $trx->pg_paid_response = $response;
        $trx->payment_method = 'MTN MoMo';
        $trx->payment_channel = 'Mobile Money';
        $trx->paid_date = date('Y-m-d H:i:s');
        $trx->status = 2;
        $trx->save();
        r2(U . "order/view/" . $trx['id'], 's', Lang::T("Transaction successful."));
    } elseif ($status === 'FAILED') {
        $trx->pg_paid_response = $response;
        $trx->status = 3;
        $trx->save();
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction failed."));
    } elseif ($status === 'PENDING') {
        r2(U . "order/view/" . $trx['id'], 'w', Lang::T("Transaction still pending. Please confirm payment on your phone."));
    } elseif ($trx['status'] == 2) {
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction has been paid.."));
    } else {
        Message::sendTelegram("payment_get_status: unknown result\n\n" . json_encode($result, JSON_PRETTY_PRINT));
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Unknown Command."));
    }
}


// ============================================
// HELPER FUNCTIONS (all prefixed with payment_)
// ============================================

/**
 * Get the MTN MoMo API base URL based on environment.
 */
function payment_get_server()
{
    global $config;
    $environment = $config['mtnmomo_environment'] ?? 'sandbox';
    if ($environment === 'live') {
        return 'https://proxy.momoapi.mtn.com/';
    } else {
        return 'https://sandbox.momodeveloper.mtn.com/';
    }
}

/**
 * Get an OAuth2 access token from the MTN MoMo Collection API.
 * 
 * POST /collection/token/
 * Authorization: Basic base64(api_user_id:api_key)
 * Ocp-Apim-Subscription-Key: {subscription_key}
 */
function payment_get_access_token($base_url, $api_user_id, $api_key, $subscription_key)
{
    $ch = curl_init($base_url . 'collection/token/');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $api_user_id . ':' . $api_key);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Ocp-Apim-Subscription-Key: ' . $subscription_key,
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $result = json_decode($response, true);
        return $result['access_token'] ?? null;
    }

    _log('[MoMo] Token request failed: HTTP ' . $http_code . ' | Response: ' . $response);
    return null;
}

/**
 * Generate a UUID v4 for MTN MoMo X-Reference-Id header.
 */
function payment_uuid_v4()
{
    if (function_exists('com_create_guid')) {
        return strtolower(trim(com_create_guid(), '{}'));
    }
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
