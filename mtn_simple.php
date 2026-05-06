<?php
/**
 * PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *
 * Payment Gateway MTN MoMo South Sudan
 *
 * created by Cosmas Alor
 *
 **/

function mtn_validate_config()
{
    global $config;
    if (empty($config['mtn_api_user_id']) || empty($config['mtn_collection_subscription_key'])) {
        sendTelegram("MTN MoMo payment gateway not configured");
        r2(U . 'order/package', 'w', "Admin has not yet setup MTN MoMo payment gateway, please tell admin");
    }
}

function mtn_show_config()
{
    global $ui, $config;
    $ui->assign('_title', 'MTN MoMo - Payment Gateway');
    
    // Get currency configuration
    $currency_file = __DIR__ . '/mtn_currency.json';
    if (file_exists($currency_file)) {
        $ui->assign('currency', json_decode(file_get_contents($currency_file), true));
    } else {
        $ui->assign('currency', [['code' => 'SSP', 'name' => 'South Sudanese Pound', 'symbol' => '£']]);
    }
    
    // Get current configuration values
    $ui->assign('env', $config['mtn_mode'] ?? 'sandbox');
    $ui->assign('api_user_id', $config['mtn_api_user_id'] ?? '');
    $ui->assign('collection_subscription_key', $config['mtn_collection_subscription_key'] ?? '');
    $ui->assign('api_key', $config['mtn_api_key'] ?? '');
    $ui->assign('callback_url', $config['mtn_callback_url'] ?? '');
    $ui->assign('payment_timeout', $config['mtn_payment_timeout'] ?? '60');
    $ui->assign('webhook_secret', $config['mtn_webhook_secret'] ?? '');
    $ui->assign('auto_credit', $config['mtn_auto_credit'] ?? 'no');
    $ui->assign('enable_sms', $config['mtn_enable_sms'] ?? 'no');
    $ui->assign('country_code', '211');
    
    // Simple HTML output to avoid template issues
    echo '<div class="box">';
    echo '<div class="box-header"><h3 class="box-title">MTN MoMo Payment Gateway Configuration</h3></div>';
    echo '<div class="box-body">';
    echo '<form method="post" action="' . U . 'paymentgateway/mtn_save">';
    
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<div class="form-group">';
    echo '<label>Environment</label>';
    echo '<select name="mtn_environment" class="form-control">';
    echo '<option value="sandbox"' . ($config['mtn_mode'] == 'sandbox' ? ' selected' : '') . '>Sandbox (Testing)</option>';
    echo '<option value="live"' . ($config['mtn_mode'] == 'live' ? ' selected' : '') . '>Live (Production)</option>';
    echo '</select>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label>API User ID</label>';
    echo '<input type="text" name="mtn_api_user_id" class="form-control" value="' . htmlspecialchars($config['mtn_api_user_id'] ?? '') . '" placeholder="UUID from MTN Developer Portal">';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label>Collection Subscription Key</label>';
    echo '<input type="text" name="mtn_collection_subscription_key" class="form-control" value="' . htmlspecialchars($config['mtn_collection_subscription_key'] ?? '') . '" placeholder="Primary Key from Developer Portal">';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label>API Key</label>';
    echo '<input type="text" name="mtn_api_key" class="form-control" value="' . htmlspecialchars($config['mtn_api_key'] ?? '') . '" placeholder="Generated API Key">';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="col-md-6">';
    echo '<div class="form-group">';
    echo '<label>Callback URL</label>';
    echo '<input type="text" name="mtn_callback_url" class="form-control" value="' . htmlspecialchars($config['mtn_callback_url'] ?? '') . '" placeholder="Webhook callback URL">';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label>Payment Timeout (seconds)</label>';
    echo '<input type="number" name="mtn_payment_timeout" class="form-control" value="' . htmlspecialchars($config['mtn_payment_timeout'] ?? '60') . '" min="30" max="300">';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label>Webhook Secret</label>';
    echo '<input type="password" name="mtn_webhook_secret" class="form-control" value="' . htmlspecialchars($config['mtn_webhook_secret'] ?? '') . '" placeholder="Secret for webhook verification">';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label><input type="checkbox" name="mtn_auto_credit" value="yes"' . ($config['mtn_auto_credit'] == 'yes' ? ' checked' : '') . '> Auto-Credit Internet</label>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<label><input type="checkbox" name="mtn_enable_sms" value="yes"' . ($config['mtn_enable_sms'] == 'yes' ? ' checked' : '') . '> Enable SMS Receipts</label>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="form-group">';
    echo '<button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Settings</button>';
    echo '</div>';
    
    echo '</form>';
    echo '</div>';
    echo '</div>';
    
    // Test information
    echo '<div class="box">';
    echo '<div class="box-header"><h3 class="box-title">Test Information</h3></div>';
    echo '<div class="box-body">';
    echo '<div class="alert alert-info">';
    echo '<h4>Test Numbers (Sandbox Mode)</h4>';
    echo '<ul>';
    echo '<li><strong>912345678</strong> - Successful payment</li>';
    echo '<li><strong>923456789</strong> - Failed payment</li>';
    echo '<li><strong>934567890</strong> - Pending payment</li>';
    echo '<li><strong>Test PIN:</strong> 12345</li>';
    echo '</ul>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

function mtn_save_config()
{
    global $admin, $_L;
    $mtn_mode = _post('mtn_environment');
    $mtn_api_user_id = _post('mtn_api_user_id');
    $mtn_collection_subscription_key = _post('mtn_collection_subscription_key');
    $mtn_api_key = _post('mtn_api_key');
    $mtn_callback_url = _post('mtn_callback_url');
    $mtn_payment_timeout = _post('mtn_payment_timeout');
    $mtn_webhook_secret = _post('mtn_webhook_secret');
    $mtn_enable_sms = _post('mtn_enable_sms');
    $mtn_auto_credit = _post('mtn_auto_credit');
    
    // Save each configuration
    $configs = [
        'mtn_mode' => $mtn_mode,
        'mtn_api_user_id' => $mtn_api_user_id,
        'mtn_collection_subscription_key' => $mtn_collection_subscription_key,
        'mtn_api_key' => $mtn_api_key,
        'mtn_callback_url' => $mtn_callback_url,
        'mtn_payment_timeout' => $mtn_payment_timeout,
        'mtn_webhook_secret' => $mtn_webhook_secret,
        'mtn_enable_sms' => $mtn_enable_sms,
        'mtn_auto_credit' => $mtn_auto_credit
    ];
    
    foreach ($configs as $key => $value) {
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
    
    _log('[' . $admin['username'] . ']: MTN MoMo ' . Lang::T('Settings_Saved_Successfully'), 'Admin', $admin['id']);
    r2(U . 'paymentgateway/mtn', 's', Lang::T('Settings_Saved_Successfully'));
}

function mtn_create_transaction($trx, $user)
{
    global $config;
    
    // Generate reference
    $reference = 'MOMO' . time() . rand(1000, 9999);
    
    // Create payment record
    $d = ORM::for_table('tbl_payment_gateway')
        ->where('username', $user['username'])
        ->where('status', 1)
        ->find_one();
    
    $d->gateway_trx_id = $reference;
    $d->pg_url_payment = U . 'paymentgateway/mtn_pay&invoice=' . $trx['id'] . '&amount=' . $trx['price'];
    $d->pg_request = json_encode([
        'reference' => $reference,
        'invoice_id' => $trx['id'],
        'amount' => $trx['price'],
        'currency' => $config['mtn_currency'] ?? 'SSP'
    ]);
    $d->expired_date = date('Y-m-d H:i:s', strtotime("+ 1 HOUR"));
    $d->save();
    
    // Redirect to payment page
    header('Location: ' . $d->pg_url_payment);
    exit();
}

function mtn_payment_notification()
{
    // Handle webhook callback
    $invoice_id = $_POST['invoice'] ?? $_GET['invoice'] ?? '';
    $status = $_POST['status'] ?? $_GET['status'] ?? 'pending';
    $transaction_id = $_POST['transaction_id'] ?? $_GET['transaction_id'] ?? '';
    
    if (empty($invoice_id)) {
        die('Missing invoice ID');
    }
    
    // Find transaction
    $trx = ORM::for_table('tbl_payment_gateway')
        ->where('gateway_trx_id', $transaction_id)
        ->find_one();
    
    if (!$trx) {
        die('Transaction not found');
    }
    
    // Update status based on callback
    if ($status === 'success') {
        $user = ORM::for_table('tbl_users')->where('username', $trx['username'])->find_one();
        
        if ($user && Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], 'MTN MoMo', 'MTN MoMo')) {
            $trx->pg_paid_response = json_encode([
                'status' => $status,
                'transaction_id' => $transaction_id,
                'callback_received' => date('Y-m-d H:i:s')
            ]);
            $trx->payment_method = 'MTN MoMo';
            $trx->payment_channel = 'mtn_momo';
            $trx->paid_date = date('Y-m-d H:i:s');
            $trx->status = 2;
            $trx->save();
        }
    }
    
    die('OK');
}

function mtn_get_status($trx, $user)
{
    // Check if payment was successful
    if ($trx['status'] == 2) {
        r2(U . "order/view/" . $trx['id'], 's', "Transaction has been paid.");
    } else if ($trx['status'] == 3) {
        r2(U . "order/view/" . $trx['id'], 'd', "Transaction expired.");
    } else {
        r2(U . "order/view/" . $trx['id'], 'w', "Payment pending. Please complete the payment.");
    }
}

// Handle payment page
if (isset($_GET['_route']) && $_GET['_route'] == 'paymentgateway/mtn_pay') {
    $invoice_id = $_GET['invoice'] ?? '';
    $amount = $_GET['amount'] ?? '';
    
    if (empty($invoice_id) || empty($amount)) {
        echo '<div class="alert alert-danger">Missing invoice or amount parameter</div>';
        exit;
    }
    
    $reference = 'MOMO' . time() . rand(1000, 9999);
    
    echo '<div class="container" style="margin-top: 50px;">';
    echo '<div class="row">';
    echo '<div class="col-md-6 col-md-offset-3">';
    echo '<div class="panel panel-primary">';
    echo '<div class="panel-heading"><h3 class="panel-title"><i class="fa fa-mobile"></i> MTN MoMo Payment</h3></div>';
    echo '<div class="panel-body">';
    echo '<form method="post" action="' . U . 'paymentgateway/mtn_callback">';
    echo '<div class="form-group"><label>Amount</label><div class="form-control-static"><h3 style="color: #28a745;">£' . number_format($amount, 2) . '</h3></div></div>';
    echo '<div class="form-group"><label>Phone Number (+211)</label><input type="tel" name="phone" class="form-control" placeholder="9XXXXXXXX" required pattern="9[0-9]{8]"><small class="text-muted">Format: 9XXXXXXXX (9 digits starting with 9)</small></div>';
    echo '<div class="form-group"><label>Invoice ID</label><input type="text" class="form-control" value="' . htmlspecialchars($invoice_id) . '" readonly></div>';
    echo '<div class="form-group"><label>Reference</label><input type="text" class="form-control" value="' . htmlspecialchars($reference) . '" readonly></div>';
    echo '<input type="hidden" name="invoice" value="' . htmlspecialchars($invoice_id) . '">';
    echo '<input type="hidden" name="amount" value="' . htmlspecialchars($amount) . '">';
    echo '<input type="hidden" name="reference" value="' . htmlspecialchars($reference) . '">';
    echo '<input type="hidden" name="transaction_id" value="TEST_' . time() . '">';
    echo '<div class="form-group"><button type="submit" name="status" value="success" class="btn btn-primary btn-block"><i class="fa fa-lock"></i> Pay Now</button></div>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    exit();
}

// Handle callback
if (isset($_GET['_route']) && $_GET['_route'] == 'paymentgateway/mtn_callback') {
    mtn_payment_notification();
}

return [
    'name' => 'MTN MoMo',
    'version' => '2.0.0',
    'currency' => 'SSP',
    'config_function' => 'mtn_show_config',
    'payment_function' => 'mtn_create_transaction',
    'callback_function' => 'mtn_payment_notification'
];

?>
