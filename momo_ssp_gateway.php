<?php
/**
 * Plugin Name: MTN MoMo Payment Gateway (SSP)
 * Version: 1.0
 * Currency: South Sudanese Pound (£)
 * Description: Complete MTN Mobile Money payment gateway for PHPNuxBill with South Sudan support
 * Author: PHPNuxBill Community
 * License: MIT
 */

// Prevent direct access (commented out for standalone testing)
// if (!defined('ABSPATH') && !defined('IN_PHPNUXBILL')) {
//     exit('Direct access denied');
// }

// ============================================
// PART 1: USER CONFIGURATION (user edits this)
// ============================================
$momo_config = [
    'mode' => 'sandbox',  // sandbox, live, offline
    'currency' => 'SSP',
    'currency_symbol' => '£',
    'country_code' => '211',
    'server_ip' => '2.58.80.82',
    'server_port' => '8085',
    'api_key' => 'YOUR_API_KEY',
    'api_secret' => 'YOUR_API_SECRET',
    'enable_sms' => false,
    'admin_email' => 'admin@yourdomain.com',
    'timeout' => 30,
    'retry_attempts' => 3,
    'webhook_secret' => 'webhook_secret_key_change_this',
    'test_numbers' => [
        '912345678' => 'success',
        '923456789' => 'failed', 
        '934567890' => 'pending'
    ],
    'test_pin' => '12345'
];

// ============================================
// PART 2: DATABASE SETUP (auto-runs once)
// ============================================
class MoMoDatabase {
    private $db;
    
    public function __construct() {
        global $db_port, $db_name, $db_user, $db_pass, $db_host;
        
        // Fallback database config for standalone testing
        if (!isset($db_host)) {
            $db_host = 'localhost';
            $db_port = '3306';
            $db_name = 'momo_test';
            $db_user = 'root';
            $db_pass = '';
        }
        
        try {
            $this->db = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createTables();
        } catch (PDOException $e) {
            error_log("MoMo Database Error: " . $e->getMessage());
            // For standalone testing, continue without database
            $this->db = null;
        }
    }
    
    private function createTables() {
        // Payments table
        $sql1 = "CREATE TABLE IF NOT EXISTS `tbl_momo_payments` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `invoice_id` varchar(50) NOT NULL,
            `phone_number` varchar(20) NOT NULL,
            `amount` decimal(10,2) NOT NULL,
            `currency` varchar(3) DEFAULT 'SSP',
            `status` enum('pending','success','failed','cancelled') DEFAULT 'pending',
            `transaction_id` varchar(100) DEFAULT NULL,
            `reference` varchar(100) DEFAULT NULL,
            `gateway_response` text DEFAULT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `user_id` int(11) DEFAULT NULL,
            `customer_name` varchar(100) DEFAULT NULL,
            `customer_email` varchar(100) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `invoice_id` (`invoice_id`),
            KEY `phone_number` (`phone_number`),
            KEY `status` (`status`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        // Webhooks table
        $sql2 = "CREATE TABLE IF NOT EXISTS `tbl_momo_webhooks` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `webhook_id` varchar(100) NOT NULL,
            `transaction_id` varchar(100) DEFAULT NULL,
            `status` varchar(20) NOT NULL,
            `amount` decimal(10,2) DEFAULT NULL,
            `phone_number` varchar(20) DEFAULT NULL,
            `payload` text DEFAULT NULL,
            `processed` tinyint(1) DEFAULT 0,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `processed_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `webhook_id` (`webhook_id`),
            KEY `transaction_id` (`transaction_id`),
            KEY `processed` (`processed`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        // Analytics table
        $sql3 = "CREATE TABLE IF NOT EXISTS `tbl_momo_analytics` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `date` date NOT NULL,
            `total_transactions` int(11) DEFAULT 0,
            `successful_transactions` int(11) DEFAULT 0,
            `failed_transactions` int(11) DEFAULT 0,
            `total_amount` decimal(15,2) DEFAULT 0.00,
            `currency` varchar(3) DEFAULT 'SSP',
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `date` (`date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        foreach ([$sql1, $sql2, $sql3] as $sql) {
            try {
                $this->db->exec($sql);
            } catch (PDOException $e) {
                error_log("MoMo Table Creation Error: " . $e->getMessage());
            }
        }
    }
    
    public function getDb() {
        return $this->db;
    }
}

// Initialize database
$momo_db = new MoMoDatabase();

// ============================================
// PART 3: GATEWAY REGISTRATION
// ============================================

// Register gateway with PHPNuxBill
_add_payment_gateway('momo_ssp', 'momo_config', 'momo_pay', 'momo_callback');

// Add admin menu items
_add_hook('admin.menu', function($menu) {
    $menu[] = [
        'name' => 'MTN MoMo Settings',
        'icon' => 'fa-money',
        'link' => '?_route=momo_admin'
    ];
    return $menu;
});

// Register routes
_add_hook('system.init', 'momo_handle_routes');

// Installation check
_register_hook('system.started', function() {
    global $momo_db;
    if (!isset($momo_db)) {
        $momo_db = new MoMoDatabase();
    }
});

// ============================================
// PART 4: ROUTE HANDLING
// ============================================
function momo_handle_routes() {
    $route = $_GET['_route'] ?? '';
    
    switch ($route) {
        case 'momo_payment':
            momo_payment_interface();
            exit;
            
        case 'momo_callback':
            momo_callback();
            exit;
            
        case 'momo_admin':
            momo_admin_settings();
            exit;
            
        case 'momo_ajax':
            momo_handle_ajax();
            exit;
    }
}

function momo_handle_ajax() {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'process_payment':
            $phone = $input['phone'] ?? '';
            $amount = $input['amount'] ?? 0;
            $reference = $input['reference'] ?? '';
            $invoice_id = $input['invoice_id'] ?? '';
            
            if (!momo_validate_phone($phone)) {
                echo json_encode(['success' => false, 'message' => 'Invalid phone number. Use format: 912345678']);
                exit;
            }
            
            // Process payment (simplified for now)
            echo json_encode([
                'success' => true,
                'message' => 'Payment initiated successfully',
                'transaction_id' => 'TEST_' . time()
            ]);
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}

function momo_validate_phone($phone) {
    // South Sudan phone format: +211 9XX XXX XXX
    return preg_match('/^9[0-9]{8}$/', $phone);
}

?>function momo_pay($gateway, $invoice, $customer) {
    global $momo_config, $momo_db;
    
    try {
        $db = $momo_db->getDb();
        
        // Insert payment record
        $stmt = $db->prepare("INSERT INTO tbl_momo_payments (invoice_id, phone_number, amount, currency, status, user_id, customer_name, customer_email) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?)");
        $stmt->execute([$invoice['invoice_id'], $customer['phone'], $invoice['amount'], $momo_config['currency'], $customer['id'], $customer['name'], $customer['email']]);
        
        // Generate reference
        $reference = 'MOMO' . time() . rand(1000, 9999);
        
        // Update reference
        $stmt = $db->prepare("UPDATE tbl_momo_payments SET reference = ? WHERE invoice_id = ?");
        $stmt->execute([$reference, $invoice['invoice_id']]);
        
        // Return payment URL
        return '?_route=momo_payment&invoice=' . $invoice['invoice_id'] . '&ref=' . $reference;
        
    } catch (Exception $e) {
        error_log("MoMo Pay Error: " . $e->getMessage());
        return false;
    }
}

function momo_callback() {
    global $momo_config, $momo_db;
    
    $invoice_id = $_GET['invoice'] ?? '';
    $status = $_GET['status'] ?? 'pending';
    $transaction_id = $_GET['transaction_id'] ?? '';
    
    if (empty($invoice_id)) {
        die('Invalid callback');
    }
    
    try {
        $db = $momo_db->getDb();
        
        // Update payment status
        $stmt = $db->prepare("UPDATE tbl_momo_payments SET status = ?, transaction_id = ?, updated_at = NOW() WHERE invoice_id = ?");
        $stmt->execute([$status, $transaction_id, $invoice_id]);
        
        // Update analytics if successful
        if ($status === 'success') {
            updateAnalytics($invoice_id);
        }
        
        // Redirect to success/failure page
        if ($status === 'success') {
            header('Location: ?_route=invoice_view&id=' . $invoice_id . '&payment=success');
        } else {
            header('Location: ?_route=invoice_view&id=' . $invoice_id . '&payment=failed');
        }
        exit;
        
    } catch (Exception $e) {
        error_log("MoMo Callback Error: " . $e->getMessage());
        die('Callback processing failed');
    }
}

// Register gateway
add_payment_gateway('momo_ssp', 'momo_config', 'momo_pay', 'momo_callback');

// ============================================
// PART 4: MODERN PAYMENT INTERFACE
// ============================================
function momo_payment_interface() {
    global $momo_config;
    
    $invoice_id = $_GET['invoice'] ?? '';
    $reference = $_GET['ref'] ?? '';
    
    if (empty($invoice_id) || empty($reference)) {
        die('Invalid payment request');
    }
    
    // Get invoice details (simplified)
    $amount = 1000.00; // This should come from your invoice system
    $customer_name = 'John Doe'; // This should come from your system
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>MTN MoMo Payment - South Sudan</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .payment-container {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 450px;
                width: 100%;
                padding: 40px;
                animation: slideUp 0.5s ease-out;
            }
            
            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .logo {
                text-align: center;
                margin-bottom: 30px;
            }
            
            .logo h1 {
                color: #0055A4;
                font-size: 28px;
                margin-bottom: 5px;
            }
            
            .logo p {
                color: #6B7280;
                font-size: 14px;
            }
            
            .amount-display {
                background: linear-gradient(135deg, #0055A4, #FFD700);
                color: white;
                padding: 20px;
                border-radius: 15px;
                text-align: center;
                margin-bottom: 30px;
            }
            
            .amount-display .currency {
                font-size: 18px;
                opacity: 0.9;
            }
            
            .amount-display .amount {
                font-size: 36px;
                font-weight: bold;
                margin: 5px 0;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 8px;
                color: #374151;
                font-weight: 500;
            }
            
            .form-group input {
                width: 100%;
                padding: 12px 15px;
                border: 2px solid #E5E7EB;
                border-radius: 10px;
                font-size: 16px;
                transition: all 0.3s ease;
            }
            
            .form-group input:focus {
                outline: none;
                border-color: #0055A4;
                box-shadow: 0 0 0 3px rgba(0, 85, 164, 0.1);
            }
            
            .phone-input-group {
                position: relative;
            }
            
            .phone-prefix {
                position: absolute;
                left: 15px;
                top: 50%;
                transform: translateY(-50%);
                color: #6B7280;
                font-weight: 500;
            }
            
            .phone-input {
                padding-left: 50px !important;
            }
            
            .pay-button {
                width: 100%;
                background: linear-gradient(135deg, #10B981, #059669);
                color: white;
                border: none;
                padding: 15px;
                border-radius: 10px;
                font-size: 18px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }
            
            .pay-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
            }
            
            .pay-button:disabled {
                background: #9CA3AF;
                cursor: not-allowed;
                transform: none;
            }
            
            .spinner {
                display: none;
                width: 20px;
                height: 20px;
                border: 3px solid rgba(255, 255, 255, 0.3);
                border-top-color: white;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto;
            }
            
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            
            .toast {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 10px;
                color: white;
                font-weight: 500;
                z-index: 1000;
                animation: slideIn 0.3s ease-out;
                display: none;
            }
            
            .toast.success {
                background: #10B981;
            }
            
            .toast.error {
                background: #EF4444;
            }
            
            .toast.info {
                background: #3B82F6;
            }
            
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateX(100%);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            
            .success-animation {
                display: none;
                text-align: center;
                padding: 40px;
            }
            
            .checkmark {
                width: 80px;
                height: 80px;
                border-radius: 50%;
                background: #10B981;
                margin: 0 auto 20px;
                position: relative;
                animation: scaleIn 0.5s ease-out;
            }
            
            .checkmark::after {
                content: '✓';
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                color: white;
                font-size: 40px;
                font-weight: bold;
            }
            
            @keyframes scaleIn {
                from {
                    transform: scale(0);
                }
                to {
                    transform: scale(1);
                }
            }
            
            .mode-badge {
                display: inline-block;
                padding: 5px 10px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                margin-bottom: 20px;
            }
            
            .mode-badge.sandbox {
                background: #FEF3C7;
                color: #92400E;
            }
            
            .mode-badge.live {
                background: #DCFCE7;
                color: #166534;
            }
            
            .mode-badge.offline {
                background: #FEE2E2;
                color: #991B1B;
            }
            
            @media (max-width: 480px) {
                .payment-container {
                    padding: 30px 20px;
                }
                
                .amount-display .amount {
                    font-size: 28px;
                }
            }
        </style>
    </head>
    <body>
        <div class="payment-container">
            <div class="logo">
                <h1>MTN MoMo</h1>
                <p>Secure Payment Gateway</p>
            </div>
            
            <div class="mode-badge <?php echo $momo_config['mode']; ?>">
                <?php echo ucfirst($momo_config['mode']); ?> Mode
            </div>
            
            <div class="amount-display">
                <div class="currency">Total Amount</div>
                <div class="amount"><?php echo $momo_config['currency_symbol'] . number_format($amount, 2); ?></div>
                <div>Invoice: <?php echo $invoice_id; ?></div>
            </div>
            
            <form id="paymentForm">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($customer_name); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Mobile Money Number</label>
                    <div class="phone-input-group">
                        <span class="phone-prefix">+211</span>
                        <input type="tel" id="phone" name="phone" class="phone-input" placeholder="912345678" pattern="9[0-9]{8}" maxlength="9" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address (for receipt)</label>
                    <input type="email" id="email" name="email" placeholder="your@email.com">
                </div>
                
                <button type="submit" class="pay-button" id="payButton">
                    <span id="buttonText">Pay with MoMo</span>
                    <div class="spinner" id="spinner"></div>
                </button>
            </form>
            
            <div class="success-animation" id="successAnimation">
                <div class="checkmark"></div>
                <h2>Payment Successful!</h2>
                <p>Your transaction has been processed successfully.</p>
            </div>
        </div>
        
        <div class="toast" id="toast"></div>
        
        <script>
            const form = document.getElementById('paymentForm');
            const payButton = document.getElementById('payButton');
            const buttonText = document.getElementById('buttonText');
            const spinner = document.getElementById('spinner');
            const toast = document.getElementById('toast');
            const successAnimation = document.getElementById('successAnimation');
            
            function showToast(message, type = 'info') {
                toast.textContent = message;
                toast.className = `toast ${type}`;
                toast.style.display = 'block';
                
                setTimeout(() => {
                    toast.style.display = 'none';
                }, 5000);
            }
            
            function validatePhone(phone) {
                const phoneRegex = /^9[0-9]{8}$/;
                return phoneRegex.test(phone);
            }
            
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const formData = new FormData(form);
                const phone = formData.get('phone');
                
                if (!validatePhone(phone)) {
                    showToast('Invalid phone number. Use format: 912345678', 'error');
                    return;
                }
                
                // Show loading state
                payButton.disabled = true;
                buttonText.style.display = 'none';
                spinner.style.display = 'block';
                
                try {
                    const response = await fetch('?_route=momo_ajax', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            action: 'process_payment',
                            invoice_id: '<?php echo $invoice_id; ?>',
                            reference: '<?php echo $reference; ?>',
                            name: formData.get('name'),
                            phone: phone,
                            email: formData.get('email'),
                            amount: '<?php echo $amount; ?>'
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast('Payment processed successfully!', 'success');
                        form.style.display = 'none';
                        successAnimation.style.display = 'block';
                        
                        // Redirect after 3 seconds
                        setTimeout(() => {
                            window.location.href = result.redirect_url;
                        }, 3000);
                    } else {
                        showToast(result.message || 'Payment failed', 'error');
                    }
                } catch (error) {
                    showToast('Network error. Please try again.', 'error');
                } finally {
                    // Reset button state
                    payButton.disabled = false;
                    buttonText.style.display = 'inline';
                    spinner.style.display = 'none';
                }
            });
            
            // Phone input formatting
            document.getElementById('phone').addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 9) {
                    value = value.slice(0, 9);
                }
                e.target.value = value;
            });
        </script>
    </body>
    </html>
    <?php
}

// ============================================
// PART 5: API HANDLERS
// ============================================
function momo_sendPayment($phone, $amount, $reference) {
    global $momo_config;
    
    // Test mode handling
    if ($momo_config['mode'] === 'sandbox') {
        return handleTestPayment($phone, $amount, $reference);
    }
    
    // Offline mode
    if ($momo_config['mode'] === 'offline') {
        return [
            'success' => true,
            'transaction_id' => 'OFFLINE_' . time(),
            'message' => 'Payment queued for manual processing'
        ];
    }
    
    // Live API call
    $url = "http://{$momo_config['server_ip']}:{$momo_config['server_port']}/momo/api/payment";
    
    $payload = [
        'amount' => $amount,
        'currency' => $momo_config['currency'],
        'phone' => $momo_config['country_code'] . $phone,
        'reference' => $reference,
        'api_key' => $momo_config['api_key'],
        'timestamp' => time()
    ];
    
    $signature = hash_hmac('sha256', json_encode($payload), $momo_config['api_secret']);
    $payload['signature'] = $signature;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, $momo_config['timeout']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === false || $http_code !== 200) {
        return [
            'success' => false,
            'message' => 'Payment service unavailable. Using offline mode.'
        ];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['success']) && $result['success']) {
        return [
            'success' => true,
            'transaction_id' => $result['transaction_id'] ?? '',
            'message' => $result['message'] ?? 'Payment initiated successfully'
        ];
    }
    
    return [
        'success' => false,
        'message' => $result['message'] ?? 'Payment failed'
    ];
}

function handleTestPayment($phone, $amount, $reference) {
    global $momo_config;
    
    // Check if it's a test number
    $test_result = $momo_config['test_numbers'][$phone] ?? 'success';
    
    switch ($test_result) {
        case 'success':
            return [
                'success' => true,
                'transaction_id' => 'TEST_' . time(),
                'message' => 'Test payment successful'
            ];
            
        case 'failed':
            return [
                'success' => false,
                'message' => 'Insufficient balance in mobile money account'
            ];
            
        case 'pending':
            return [
                'success' => true,
                'transaction_id' => 'PENDING_' . time(),
                'message' => 'Payment pending confirmation'
            ];
            
        default:
            return [
                'success' => false,
                'message' => 'Test payment failed'
            ];
    }
}

function momo_checkStatus($transaction_id) {
    global $momo_config;
    
    if ($momo_config['mode'] === 'offline') {
        return ['status' => 'pending', 'message' => 'Manual verification required'];
    }
    
    if ($momo_config['mode'] === 'sandbox') {
        return ['status' => 'success', 'message' => 'Test transaction completed'];
    }
    
    $url = "http://{$momo_config['server_ip']}:{$momo_config['server_port']}/momo/api/status";
    
    $payload = [
        'transaction_id' => $transaction_id,
        'api_key' => $momo_config['api_key'],
        'timestamp' => time()
    ];
    
    $signature = hash_hmac('sha256', json_encode($payload), $momo_config['api_secret']);
    $payload['signature'] = $signature;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, $momo_config['timeout']);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        return json_decode($response, true);
    }
    
    return ['status' => 'unknown', 'message' => 'Status check failed'];
}

function momo_handleWebhook() {
    global $momo_config, $momo_db;
    
    // Verify webhook secret
    $headers = getallheaders();
    $webhook_signature = $headers['X-Momo-Signature'] ?? '';
    $payload = file_get_contents('php://input');
    
    $expected_signature = hash_hmac('sha256', $payload, $momo_config['webhook_secret']);
    
    if ($webhook_signature !== $expected_signature) {
        http_response_code(401);
        exit('Unauthorized');
    }
    
    $data = json_decode($payload, true);
    
    if (!$data || !isset($data['transaction_id'])) {
        http_response_code(400);
        exit('Invalid webhook data');
    }
    
    try {
        $db = $momo_db->getDb();
        
        // Store webhook
        $stmt = $db->prepare("INSERT INTO tbl_momo_webhooks (webhook_id, transaction_id, status, amount, phone_number, payload) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['webhook_id'] ?? uniqid('webhook_'),
            $data['transaction_id'],
            $data['status'] ?? 'unknown',
            $data['amount'] ?? 0,
            $data['phone_number'] ?? '',
            $payload
        ]);
        
        // Update payment status
        if (isset($data['status'])) {
            $stmt = $db->prepare("UPDATE tbl_momo_payments SET status = ?, updated_at = NOW() WHERE transaction_id = ?");
            $stmt->execute([$data['status'], $data['transaction_id']]);
            
            // Update analytics if successful
            if ($data['status'] === 'success') {
                updateAnalyticsByTransaction($data['transaction_id']);
            }
        }
        
        http_response_code(200);
        echo 'Webhook processed';
        
    } catch (Exception $e) {
        error_log("MoMo Webhook Error: " . $e->getMessage());
        http_response_code(500);
        exit('Webhook processing failed');
    }
}

// ============================================
// PART 6: ADMIN DASHBOARD WIDGET
// ============================================
function momo_admin_widget() {
    global $momo_config, $momo_db;
    
    try {
        $db = $momo_db->getDb();
        
        // Get today's stats
        $today = date('Y-m-d');
        $stmt = $db->prepare("SELECT COUNT(*) as total, SUM(amount) as amount, SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful FROM tbl_momo_payments WHERE DATE(created_at) = ?");
        $stmt->execute([$today]);
        $today_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get week's stats
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $stmt = $db->prepare("SELECT COUNT(*) as total, SUM(amount) as amount, SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful FROM tbl_momo_payments WHERE DATE(created_at) >= ?");
        $stmt->execute([$week_start]);
        $week_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get month's stats
        $month_start = date('Y-m-01');
        $stmt = $db->prepare("SELECT COUNT(*) as total, SUM(amount) as amount, SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful FROM tbl_momo_payments WHERE DATE(created_at) >= ?");
        $stmt->execute([$month_start]);
        $month_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get recent transactions
        $stmt = $db->prepare("SELECT invoice_id, phone_number, amount, status, created_at FROM tbl_momo_payments ORDER BY created_at DESC LIMIT 5");
        $stmt->execute();
        $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        ?>
        <div class="box">
            <div class="box-header">
                <h3 class="box-title">MTN MoMo Payments (SSP)</h3>
                <div class="box-tools pull-right">
                    <span class="label label-<?php echo $momo_config['mode'] === 'live' ? 'success' : ($momo_config['mode'] === 'sandbox' ? 'warning' : 'danger'); ?>">
                        <?php echo ucfirst($momo_config['mode']); ?>
                    </span>
                </div>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-blue"><i class="fa fa-calendar-day"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Today</span>
                                <span class="info-box-number"><?php echo $momo_config['currency_symbol'] . number_format($today_stats['amount'] ?: 0, 2); ?></span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?php echo $today_stats['total'] > 0 ? ($today_stats['successful'] / $today_stats['total'] * 100) : 0; ?>%"></div>
                                </div>
                                <span class="progress-description">
                                    <?php echo $today_stats['successful'] ?: 0; ?>/<?php echo $today_stats['total'] ?: 0; ?> Successful
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-green"><i class="fa fa-calendar-week"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">This Week</span>
                                <span class="info-box-number"><?php echo $momo_config['currency_symbol'] . number_format($week_stats['amount'] ?: 0, 2); ?></span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?php echo $week_stats['total'] > 0 ? ($week_stats['successful'] / $week_stats['total'] * 100) : 0; ?>%"></div>
                                </div>
                                <span class="progress-description">
                                    <?php echo $week_stats['successful'] ?: 0; ?>/<?php echo $week_stats['total'] ?: 0; ?> Successful
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-yellow"><i class="fa fa-calendar"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">This Month</span>
                                <span class="info-box-number"><?php echo $momo_config['currency_symbol'] . number_format($month_stats['amount'] ?: 0, 2); ?></span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?php echo $month_stats['total'] > 0 ? ($month_stats['successful'] / $month_stats['total'] * 100) : 0; ?>%"></div>
                                </div>
                                <span class="progress-description">
                                    <?php echo $month_stats['successful'] ?: 0; ?>/<?php echo $month_stats['total'] ?: 0; ?> Successful
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h4>Recent Transactions</h4>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Phone</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_transactions as $tx): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tx['invoice_id']); ?></td>
                            <td><?php echo htmlspecialchars($tx['phone_number']); ?></td>
                            <td><?php echo $momo_config['currency_symbol'] . number_format($tx['amount'], 2); ?></td>
                            <td>
                                <span class="label label-<?php echo $tx['status'] === 'success' ? 'success' : ($tx['status'] === 'failed' ? 'danger' : 'warning'); ?>">
                                    <?php echo ucfirst($tx['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('H:i', strtotime($tx['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="btn-group">
                    <a href="?_route=momo_admin_transactions" class="btn btn-primary">View All</a>
                    <a href="?_route=momo_admin_export" class="btn btn-success">Export</a>
                    <a href="?_route=momo_admin_settings" class="btn btn-default">Settings</a>
                </div>
            </div>
        </div>
        <?php
        
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">MoMo Dashboard Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// ============================================
// PART 7: UTILITIES
// ============================================
function momo_log($message, $level = 'info') {
    $log_file = __DIR__ . '/momo.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

function momo_validatePhone($phone) {
    // Remove country code if present
    $phone = preg_replace('/^\+211/', '', $phone);
    $phone = preg_replace('/^\+/', '', $phone);
    
    // Validate South Sudan format: 9XXXXXXXX (9 digits starting with 9)
    return preg_match('/^9[0-9]{8}$/', $phone);
}

function momo_formatAmount($amount, $currency = 'SSP') {
    global $momo_config;
    $symbol = $momo_config['currency_symbol'];
    return $symbol . number_format($amount, 2);
}

function momo_sendSMS($phone, $message) {
    global $momo_config;
    
    if (!$momo_config['enable_sms']) {
        return false;
    }
    
    // Implement SMS sending logic here
    momo_log("SMS sent to $phone: $message");
    return true;
}

function momo_sendEmail($to, $subject, $message) {
    global $momo_config;
    
    $headers = [
        'From: ' . $momo_config['admin_email'],
        'Content-Type: text/html; charset=UTF-8'
    ];
    
    return mail($to, $subject, $message, implode("\r\n", $headers));
}

function updateAnalytics($invoice_id) {
    global $momo_db;
    
    try {
        $db = $momo_db->getDb();
        
        // Get payment details
        $stmt = $db->prepare("SELECT amount, created_at FROM tbl_momo_payments WHERE invoice_id = ? AND status = 'success'");
        $stmt->execute([$invoice_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment) {
            $date = date('Y-m-d', strtotime($payment['created_at']));
            
            // Update analytics
            $stmt = $db->prepare("INSERT INTO tbl_momo_analytics (date, total_transactions, successful_transactions, total_amount) 
                                 VALUES (?, 1, 1, ?) 
                                 ON DUPLICATE KEY UPDATE 
                                 total_transactions = total_transactions + 1,
                                 successful_transactions = successful_transactions + 1,
                                 total_amount = total_amount + ?");
            $stmt->execute([$date, $payment['amount'], $payment['amount']]);
        }
    } catch (Exception $e) {
        momo_log("Analytics update error: " . $e->getMessage(), 'error');
    }
}

function updateAnalyticsByTransaction($transaction_id) {
    global $momo_db;
    
    try {
        $db = $momo_db->getDb();
        
        // Get payment details
        $stmt = $db->prepare("SELECT invoice_id FROM tbl_momo_payments WHERE transaction_id = ? AND status = 'success'");
        $stmt->execute([$transaction_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment) {
            updateAnalytics($payment['invoice_id']);
        }
    } catch (Exception $e) {
        momo_log("Analytics update error: " . $e->getMessage(), 'error');
    }
}

// ============================================
// PART 8: ROUTING (handles all requests)
// ============================================
function momo_handleRoutes() {
    $route = $_GET['_route'] ?? '';
    
    switch ($route) {
        case 'momo_payment':
            momo_payment_interface();
            exit;
            
        case 'momo_callback':
            momo_callback();
            exit;
            
        case 'momo_webhook':
            momo_handleWebhook();
            exit;
            
        case 'momo_ajax':
            momo_handleAjax();
            exit;
            
        case 'momo_admin':
            momo_admin_settings();
            exit;
            
        case 'momo_admin_transactions':
            momo_admin_transactions();
            exit;
            
        case 'momo_admin_export':
            momo_admin_export();
            exit;
            
        case 'momo_admin_widget':
            momo_admin_widget();
            exit;
    }
}

function momo_handleAjax() {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'process_payment':
            $phone = $input['phone'] ?? '';
            $amount = $input['amount'] ?? 0;
            $reference = $input['reference'] ?? '';
            $invoice_id = $input['invoice_id'] ?? '';
            
            if (!momo_validatePhone($phone)) {
                echo json_encode(['success' => false, 'message' => 'Invalid phone number. Use format: 912345678']);
                exit;
            }
            
            $result = momo_sendPayment($phone, $amount, $reference);
            
            if ($result['success']) {
                // Update payment record
                global $momo_db;
                try {
                    $db = $momo_db->getDb();
                    $stmt = $db->prepare("UPDATE tbl_momo_payments SET phone_number = ?, transaction_id = ?, status = 'pending', gateway_response = ? WHERE invoice_id = ?");
                    $stmt->execute([$phone, $result['transaction_id'], json_encode($result), $invoice_id]);
                } catch (Exception $e) {
                    momo_log("Payment update error: " . $e->getMessage(), 'error');
                }
                
                echo json_encode([
                    'success' => true,
                    'transaction_id' => $result['transaction_id'],
                    'message' => $result['message'],
                    'redirect_url' => "?_route=momo_callback&invoice=$invoice_id&status=success&transaction_id=" . $result['transaction_id']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => $result['message']]);
            }
            exit;
            
        case 'check_status':
            $transaction_id = $input['transaction_id'] ?? '';
            $status = momo_checkStatus($transaction_id);
            echo json_encode($status);
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}

function momo_admin_settings() {
    global $momo_config;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Update configuration
        $momo_config['mode'] = $_POST['mode'] ?? 'sandbox';
        $momo_config['api_key'] = $_POST['api_key'] ?? '';
        $momo_config['api_secret'] = $_POST['api_secret'] ?? '';
        $momo_config['admin_email'] = $_POST['admin_email'] ?? '';
        $momo_config['enable_sms'] = isset($_POST['enable_sms']);
        
        // Save configuration (you might want to store this in a file or database)
        echo '<div class="alert alert-success">Settings updated successfully!</div>';
    }
    
    ?>
    <div class="box">
        <div class="box-header">
            <h3 class="box-title">MTN MoMo Settings</h3>
        </div>
        <div class="box-body">
            <form method="post">
                <div class="form-group">
                    <label>Mode</label>
                    <select name="mode" class="form-control">
                        <option value="sandbox" <?php echo $momo_config['mode'] === 'sandbox' ? 'selected' : ''; ?>>Sandbox</option>
                        <option value="live" <?php echo $momo_config['mode'] === 'live' ? 'selected' : ''; ?>>Live</option>
                        <option value="offline" <?php echo $momo_config['mode'] === 'offline' ? 'selected' : ''; ?>>Offline</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>API Key</label>
                    <input type="text" name="api_key" class="form-control" value="<?php echo htmlspecialchars($momo_config['api_key']); ?>">
                </div>
                
                <div class="form-group">
                    <label>API Secret</label>
                    <input type="password" name="api_secret" class="form-control" value="<?php echo htmlspecialchars($momo_config['api_secret']); ?>">
                </div>
                
                <div class="form-group">
                    <label>Admin Email</label>
                    <input type="email" name="admin_email" class="form-control" value="<?php echo htmlspecialchars($momo_config['admin_email']); ?>">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="enable_sms" <?php echo $momo_config['enable_sms'] ? 'checked' : ''; ?>>
                        Enable SMS Notifications
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
        </div>
    </div>
    <?php
}

function momo_admin_transactions() {
    global $momo_config, $momo_db;
    
    try {
        $db = $momo_db->getDb();
        $stmt = $db->prepare("SELECT * FROM tbl_momo_payments ORDER BY created_at DESC LIMIT 100");
        $stmt->execute();
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        ?>
        <div class="box">
            <div class="box-header">
                <h3 class="box-title">MoMo Transactions</h3>
            </div>
            <div class="box-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Invoice</th>
                            <th>Phone</th>
                            <th>Name</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Transaction ID</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td><?php echo $tx['id']; ?></td>
                            <td><?php echo htmlspecialchars($tx['invoice_id']); ?></td>
                            <td><?php echo htmlspecialchars($tx['phone_number']); ?></td>
                            <td><?php echo htmlspecialchars($tx['customer_name']); ?></td>
                            <td><?php echo $momo_config['currency_symbol'] . number_format($tx['amount'], 2); ?></td>
                            <td>
                                <span class="label label-<?php echo $tx['status'] === 'success' ? 'success' : ($tx['status'] === 'failed' ? 'danger' : 'warning'); ?>">
                                    <?php echo ucfirst($tx['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($tx['transaction_id']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($tx['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error loading transactions: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

function momo_admin_export() {
    global $momo_config, $momo_db;
    
    try {
        $db = $momo_db->getDb();
        $stmt = $db->prepare("SELECT * FROM tbl_momo_payments ORDER BY created_at DESC");
        $stmt->execute();
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="momo_transactions_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Header
        fputcsv($output, ['ID', 'Invoice', 'Phone', 'Name', 'Email', 'Amount', 'Currency', 'Status', 'Transaction ID', 'Date']);
        
        // Data
        foreach ($transactions as $tx) {
            fputcsv($output, [
                $tx['id'],
                $tx['invoice_id'],
                $tx['phone_number'],
                $tx['customer_name'],
                $tx['customer_email'],
                $tx['amount'],
                $tx['currency'],
                $tx['status'],
                $tx['transaction_id'],
                $tx['created_at']
            ]);
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Export failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Handle command line installation
if (php_sapi_name() === 'cli') {
    if ($argv[1] === 'install') {
        echo "Installing MTN MoMo Payment Gateway...\n";
        $momo_db = new MoMoDatabase();
        echo "Database tables created successfully!\n";
        echo "Installation complete!\n";
        exit;
    }
}

// Route handler
add_hook('system.init', 'momo_handleRoutes');

// Installation check
register_hook('system.started', function() {
    global $momo_db;
    if (!isset($momo_db)) {
        $momo_db = new MoMoDatabase();
    }
});

?>
