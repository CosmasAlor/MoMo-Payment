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
    $ui->assign('currency', json_decode(file_get_contents('system/paymentgateway/mtn_currency.json'), true));
    
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
    
    $ui->display('mtn.tpl');
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

function mtn_get_server()
{
    global $_app_stage;
    $config = ORM::for_table('tbl_appconfig')->where('setting', 'mtn_mode')->find_one();
    $mode = $config ? $config['value'] : 'sandbox';
    
    if ($mode == 'live') {
        return 'https://momodeveloper.mtn.com/';
    } else {
        return 'https://sandbox.momodeveloper.mtn.com/';
    }
}

// Payment page handler
function mtn_payment_page()
{
    global $config;
    
    $invoice_id = $_GET['invoice'] ?? '';
    $amount = $_GET['amount'] ?? '';
    
    if (empty($invoice_id) || empty($amount)) {
        echo '<div class="alert alert-danger">Missing invoice or amount parameter</div>';
        return;
    }
    
    $reference = 'MOMO' . time() . rand(1000, 9999);
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>MTN MoMo Payment</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container" style="margin-top: 50px;">
            <div class="row">
                <div class="col-md-6 col-md-offset-3">
                    <div class="panel panel-primary">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                <i class="fa fa-mobile"></i> MTN MoMo Payment
                            </h3>
                        </div>
                        <div class="panel-body">
                            <form method="post" action="<?php echo U; ?>paymentgateway/mtn_callback">
                                <div class="form-group">
                                    <label>Amount</label>
                                    <div class="form-control-static">
                                        <h3 style="color: #28a745;">£<?php echo number_format($amount, 2); ?></h3>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Phone Number (+211)</label>
                                    <input type="tel" name="phone" class="form-control" placeholder="9XXXXXXXX" required pattern="9[0-9]{8}">
                                    <small class="text-muted">Format: 9XXXXXXXX (9 digits starting with 9)</small>
                                </div>
                                
                                <div class="form-group">
                                    <label>Invoice ID</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($invoice_id); ?>" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label>Reference</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($reference); ?>" readonly>
                                </div>
                                
                                <input type="hidden" name="invoice" value="<?php echo htmlspecialchars($invoice_id); ?>">
                                <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">
                                <input type="hidden" name="reference" value="<?php echo htmlspecialchars($reference); ?>">
                                <input type="hidden" name="transaction_id" value="TEST_<?php echo time(); ?>">
                                
                                <div class="form-group">
                                    <button type="submit" name="status" value="success" class="btn btn-primary btn-block">
                                        <i class="fa fa-lock"></i> Pay Now
                                    </button>
                                </div>
                            </form>
                            
                            <div class="alert alert-info">
                                <h4>Test Information</h4>
                                <ul>
                                    <li><strong>Test Numbers:</strong> 912345678 (success), 923456789 (failed)</li>
                                    <li><strong>Test PIN:</strong> 12345</li>
                                    <li><strong>Environment:</strong> <?php echo ucfirst($config['mtn_mode'] ?? 'sandbox'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

// Handle direct payment page access
if (isset($_GET['_route']) && $_GET['_route'] == 'paymentgateway/mtn_pay') {
    mtn_payment_page();
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
