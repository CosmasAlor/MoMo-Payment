<?php
/**
 * MTN MoMo Payment Gateway for PHPNuxBill
 * Version: 2.0.0
 * Author: Cosmas Alor
 * License: MIT
 * 
 * Uses ?_route= URL pattern for routing
 * Compatible with PHPNuxBill system
 */

// ============================================
// SECURITY - Block direct access
// ============================================
// Allow access with ?_route= parameter
if (basename($_SERVER["PHP_SELF"]) == basename(__FILE__) && !isset($_GET["_route"])) {
    exit("Direct access denied");
}

// ============================================
// DATABASE CONFIGURATION
// ============================================
class MoMoDatabase {
    private $db;
    
    public function __construct() {
        global $db_host, $db_port, $db_name, $db_user, $db_pass;
        
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
            $this->db = null;
        }
    }
    
    private function createTables() {
        // Config table
        $sql1 = "CREATE TABLE IF NOT EXISTS `momo_config` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(100) NOT NULL UNIQUE,
            `setting_value` TEXT,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        // Payments table
        $sql2 = "CREATE TABLE IF NOT EXISTS `momo_payments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `invoice_id` VARCHAR(50) NOT NULL UNIQUE,
            `phone_number` VARCHAR(20) NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `currency` VARCHAR(3) DEFAULT 'SSP',
            `status` ENUM('pending','success','failed','cancelled') DEFAULT 'pending',
            `transaction_id` VARCHAR(100) DEFAULT NULL,
            `reference` VARCHAR(100) DEFAULT NULL,
            `gateway_response` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `user_id` INT DEFAULT NULL,
            `customer_name` VARCHAR(100) DEFAULT NULL,
            `customer_email` VARCHAR(100) DEFAULT NULL
        )";
        
        // Analytics table
        $sql3 = "CREATE TABLE IF NOT EXISTS `momo_analytics` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `date` DATE NOT NULL UNIQUE,
            `total_transactions` INT DEFAULT 0,
            `successful_transactions` INT DEFAULT 0,
            `failed_transactions` INT DEFAULT 0,
            `total_amount` DECIMAL(15,2) DEFAULT 0.00,
            `currency` VARCHAR(3) DEFAULT 'SSP',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        foreach ([$sql1, $sql2, $sql3] as $sql) {
            if ($this->db) {
                $this->db->exec($sql);
            }
        }
    }
    
    public function getDb() {
        return $this->db;
    }
    
    public function getConfig($key, $default = null) {
        if (!$this->db) return $default;
        
        $stmt = $this->db->prepare("SELECT setting_value FROM momo_config WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_COLUMN);
        return $result !== false ? $result : $default;
    }
    
    public function setConfig($key, $value) {
        if (!$this->db) return false;
        
        $stmt = $this->db->prepare("INSERT INTO momo_config (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        return $stmt->execute([$key, $value, $value]);
    }
}

// Initialize database
$momo_db = new MoMoDatabase();

// ============================================
// ADMIN CONFIGURATION PAGE
// ============================================
function mtn_admin_page() {
    global $momo_db;
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $momo_db->setConfig('mode', $_POST['mode'] ?? 'sandbox');
        $momo_db->setConfig('api_user_id', $_POST['api_user_id'] ?? '');
        $momo_db->setConfig('collection_subscription_key', $_POST['collection_subscription_key'] ?? '');
        $momo_db->setConfig('api_key', $_POST['api_key'] ?? '');
        $momo_db->setConfig('callback_url', $_POST['callback_url'] ?? '');
        $momo_db->setConfig('payment_timeout', $_POST['payment_timeout'] ?? '60');
        $momo_db->setConfig('enable_sms', isset($_POST['enable_sms']) ? 'yes' : 'no');
        $momo_db->setConfig('auto_credit', isset($_POST['auto_credit']) ? 'yes' : 'no');
        
        echo '<div class="alert alert-success">Settings saved successfully!</div>';
    }
    
    // Get current configuration
    $config = [
        'mode' => $momo_db->getConfig('mode', 'sandbox'),
        'api_user_id' => $momo_db->getConfig('api_user_id', ''),
        'collection_subscription_key' => $momo_db->getConfig('collection_subscription_key', ''),
        'api_key' => $momo_db->getConfig('api_key', ''),
        'callback_url' => $momo_db->getConfig('callback_url', 'https://wifi.cjinnovate.com/api/webhooks/momo/customer/249ba5c3-e5c8-4406-a022-3a8516c148a2'),
        'payment_timeout' => $momo_db->getConfig('payment_timeout', '60'),
        'enable_sms' => $momo_db->getConfig('enable_sms', 'no'),
        'auto_credit' => $momo_db->getConfig('auto_credit', 'yes')
    ];
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>MTN MoMo Payment Gateway Configuration</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container">
            <div class="row" style="margin-top: 30px;">
                <div class="col-md-8 col-md-offset-2">
                    <div class="panel panel-primary">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                <i class="fa fa-money"></i> MTN MoMo Payment Gateway Configuration
                            </h3>
                        </div>
                        <div class="panel-body">
                            <form method="post">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Environment</label>
                                            <select name="mode" class="form-control">
                                                <option value="sandbox" <?php echo $config['mode'] === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Testing)</option>
                                                <option value="live" <?php echo $config['mode'] === 'live' ? 'selected' : ''; ?>>Live (Production)</option>
                                                <option value="offline" <?php echo $config['mode'] === 'offline' ? 'selected' : ''; ?>>Offline</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>API User ID (UUID)</label>
                                            <input type="text" name="api_user_id" class="form-control" value="<?php echo htmlspecialchars($config['api_user_id']); ?>" placeholder="e.g. 123e4567-e89b-12d3-a456-426614174000">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Collection Subscription Key</label>
                                            <input type="text" name="collection_subscription_key" class="form-control" value="<?php echo htmlspecialchars($config['collection_subscription_key']); ?>" placeholder="Primary Key from Developer Portal">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>API Key</label>
                                            <input type="text" name="api_key" class="form-control" value="<?php echo htmlspecialchars($config['api_key']); ?>" placeholder="Generated API Key">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Callback URL</label>
                                            <input type="url" name="callback_url" class="form-control" value="<?php echo htmlspecialchars($config['callback_url']); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Payment Timeout (seconds)</label>
                                            <input type="number" name="payment_timeout" class="form-control" value="<?php echo htmlspecialchars($config['payment_timeout']); ?>" min="30" max="300">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>
                                                <input type="checkbox" name="enable_sms" <?php echo $config['enable_sms'] === 'yes' ? 'checked' : ''; ?>>
                                                Enable SMS Receipts
                                            </label>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>
                                                <input type="checkbox" name="auto_credit" <?php echo $config['auto_credit'] === 'yes' ? 'checked' : ''; ?>>
                                                Auto-Credit Internet
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save"></i> Save Settings
                                    </button>
                                    <button type="button" class="btn btn-success" onclick="testConnection()">
                                        <i class="fa fa-plug"></i> Test Connection
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="panel panel-info">
                        <div class="panel-heading">
                            <h4>Test Information</h4>
                        </div>
                        <div class="panel-body">
                            <h5>Test Numbers (Sandbox Mode)</h5>
                            <ul>
                                <li><strong>912345678</strong> - Successful payment</li>
                                <li><strong>923456789</strong> - Failed payment</li>
                                <li><strong>934567890</strong> - Pending payment</li>
                                <li><strong>Test PIN:</strong> 12345</li>
                            </ul>
                            
                            <h5>API Endpoints</h5>
                            <ul>
                                <li><strong>Sandbox:</strong> https://sandbox.momodeveloper.mtn.com</li>
                                <li><strong>Live:</strong> https://momodeveloper.mtn.com</li>
                            </ul>
                            
                            <h5>URL Patterns</h5>
                            <ul>
                                <li><strong>Admin:</strong> ?_route=paymentgateway/mtn_config</li>
                                <li><strong>Payment:</strong> ?_route=paymentgateway/mtn_pay&invoice=XXX&amount=XXX</li>
                                <li><strong>Callback:</strong> ?_route=paymentgateway/mtn_callback</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        function testConnection() {
            alert('Connection test feature - implement API testing here');
        }
        </script>
    </body>
    </html>
    <?php
}

// ============================================
// PAYMENT PAGE
// ============================================
function mtn_payment_page() {
    global $momo_db;
    
    $invoice_id = $_GET['invoice'] ?? '';
    $amount = $_GET['amount'] ?? '';
    
    if (empty($invoice_id) || empty($amount)) {
        echo '<div class="alert alert-danger">Missing invoice or amount parameter</div>';
        return;
    }
    
    // Create payment record
    $reference = 'MOMO' . time() . rand(1000, 9999);
    $db = $momo_db->getDb();
    
    if ($db) {
        $stmt = $db->prepare("INSERT INTO momo_payments (invoice_id, amount, currency, status, reference, created_at) VALUES (?, ?, 'SSP', 'pending', ?, NOW())");
        $stmt->execute([$invoice_id, $amount, $reference]);
    }
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>MTN MoMo Payment</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
        <style>
            .payment-container {
                max-width: 500px;
                margin: 50px auto;
                padding: 20px;
            }
            .payment-header {
                text-align: center;
                margin-bottom: 30px;
            }
            .payment-form {
                background: #f8f9fa;
                padding: 30px;
                border-radius: 10px;
            }
            .amount-display {
                font-size: 24px;
                font-weight: bold;
                color: #28a745;
                text-align: center;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="payment-container">
            <div class="payment-header">
                <h3><i class="fa fa-mobile"></i> MTN MoMo Payment</h3>
                <p>Pay with your MTN Mobile Money account</p>
            </div>
            
            <div class="payment-form">
                <div class="amount-display">
                    Amount: £<?php echo number_format($amount, 2); ?>
                </div>
                
                <form id="paymentForm" onsubmit="processPayment(event)">
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
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fa fa-lock"></i> Pay Now
                        </button>
                    </div>
                </form>
                
                <div id="paymentStatus" style="display: none;">
                    <div class="alert alert-info">
                        <i class="fa fa-spinner fa-spin"></i> Processing payment...
                    </div>
                </div>
            </div>
            
            <div class="text-center" style="margin-top: 20px;">
                <small class="text-muted">
                    Powered by MTN Mobile Money South Sudan
                </small>
            </div>
        </div>
        
        <script>
        function processPayment(event) {
            event.preventDefault();
            
            const form = event.target;
            const phone = form.phone.value;
            const invoice = '<?php echo $invoice_id; ?>';
            const reference = '<?php echo $reference; ?>';
            const amount = '<?php echo $amount; ?>';
            
            // Validate phone format
            if (!/^9[0-9]{8}$/.test(phone)) {
                alert('Invalid phone number. Use format: 9XXXXXXXX');
                return;
            }
            
            // Show processing status
            document.getElementById('paymentStatus').style.display = 'block';
            form.style.display = 'none';
            
            // Simulate payment processing
            setTimeout(function() {
                // Redirect to callback
                window.location.href = '?_route=paymentgateway/mtn_callback&invoice=' + invoice + '&status=success&transaction_id=TEST_' + Date.now();
            }, 3000);
        }
        </script>
    </body>
    </html>
    <?php
}

// ============================================
// ADMIN MENU
// ============================================
function mtn_admin_menu() {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>MTN MoMo Gateway</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container" style="margin-top: 50px;">
            <div class="row">
                <div class="col-md-6 col-md-offset-3">
                    <div class="panel panel-primary">
                        <div class="panel-heading text-center">
                            <h3><i class="fa fa-money"></i> MTN MoMo Payment Gateway</h3>
                        </div>
                        <div class="panel-body">
                            <div class="list-group">
                                <a href="?_route=mtn_config" class="list-group-item">
                                    <i class="fa fa-cog"></i> Configuration Settings
                                    <span class="pull-right"><i class="fa fa-chevron-right"></i></span>
                                </a>
                                <a href="?_route=mtn_pay&invoice=12345&amount=100" class="list-group-item">
                                    <i class="fa fa-credit-card"></i> Test Payment
                                    <span class="pull-right"><i class="fa fa-chevron-right"></i></span>
                                </a>
                                <a href="?_route=mtn_callback&invoice=12345&status=success" class="list-group-item">
                                    <i class="fa fa-exchange"></i> Test Callback
                                    <span class="pull-right"><i class="fa fa-chevron-right"></i></span>
                                </a>
                            </div>
                            
                            <div class="alert alert-info">
                                <h4>Available Routes:</h4>
                                <ul>
                                    <li><strong>Config:</strong> ?_route=mtn_config</li>
                                    <li><strong>Payment:</strong> ?_route=mtn_pay&invoice=XXX&amount=XXX</li>
                                    <li><strong>Callback:</strong> ?_route=mtn_callback&invoice=XXX&status=XXX</li>
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

// ============================================
// CALLBACK HANDLER
// ============================================
function mtn_callback() {
    global $momo_db;
    
    $invoice_id = $_GET['invoice'] ?? $_POST['invoice'] ?? '';
    $status = $_GET['status'] ?? $_POST['status'] ?? 'pending';
    $transaction_id = $_GET['transaction_id'] ?? $_POST['transaction_id'] ?? '';
    
    if (empty($invoice_id)) {
        echo 'Invalid callback - missing invoice ID';
        return;
    }
    
    // Update payment status
    $db = $momo_db->getDb();
    if ($db) {
        $stmt = $db->prepare("UPDATE momo_payments SET status = ?, transaction_id = ?, updated_at = NOW() WHERE invoice_id = ?");
        $stmt->execute([$status, $transaction_id, $invoice_id]);
        
        // Update analytics if successful
        if ($status === 'success') {
            $stmt = $db->prepare("SELECT amount FROM momo_payments WHERE invoice_id = ? AND status = 'success'");
            $stmt->execute([$invoice_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($payment) {
                $date = date('Y-m-d');
                $stmt = $db->prepare("INSERT INTO momo_analytics (date, total_transactions, successful_transactions, total_amount) VALUES (?, 1, 1, ?) ON DUPLICATE KEY UPDATE total_transactions = total_transactions + 1, successful_transactions = successful_transactions + 1, total_amount = total_amount + ?");
                $stmt->execute([$date, $payment['amount'], $payment['amount']]);
            }
        }
    }
    
    // Display result page
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Payment Result</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container" style="margin-top: 50px;">
            <div class="row">
                <div class="col-md-6 col-md-offset-3">
                    <div class="panel panel-<?php echo $status === 'success' ? 'success' : 'danger'; ?>">
                        <div class="panel-heading text-center">
                            <h3>
                                <?php if ($status === 'success'): ?>
                                    <i class="fa fa-check-circle"></i> Payment Successful
                                <?php else: ?>
                                    <i class="fa fa-times-circle"></i> Payment Failed
                                <?php endif; ?>
                            </h3>
                        </div>
                        <div class="panel-body text-center">
                            <h4>Invoice: <?php echo htmlspecialchars($invoice_id); ?></h4>
                            <p>Transaction ID: <?php echo htmlspecialchars($transaction_id); ?></p>
                            <p>Status: <strong><?php echo ucfirst($status); ?></strong></p>
                            
                            <div class="form-group">
                                <button onclick="window.close()" class="btn btn-primary">
                                    <i class="fa fa-close"></i> Close Window
                                </button>
                                <button onclick="window.print()" class="btn btn-default">
                                    <i class="fa fa-print"></i> Print Receipt
                                </button>
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

// ============================================
// ROUTE HANDLING - FLEXIBLE BASE ROUTE
// ============================================
$route = $_GET["_route"] ?? "";

// Extract the action from any base route
$action = '';
if (strpos($route, '/') !== false) {
    $parts = explode('/', $route);
    $action = end($parts);
} else {
    $action = $route;
}

// Debug: Show current route and action (remove in production)
if (empty($route)) {
    echo '<div class="alert alert-info">Debug: No route parameter found. Current URL: ' . htmlspecialchars($_SERVER['REQUEST_URI']) . '</div>';
    echo '<div class="alert alert-info">Debug: GET parameters: ' . htmlspecialchars(print_r($_GET, true)) . '</div>';
}

if ($action == "mtn") {
    mtn_admin_menu();
    exit;
}

if ($action == "mtn_config") {
    mtn_admin_page();
    exit;
}

if ($action == "mtn_pay") {
    mtn_payment_page();
    exit;
}

if ($action == "mtn_callback") {
    mtn_callback();
    exit;
}

// If no route matched, show error with debug info
echo '<div class="alert alert-warning">Invalid route. Use mtn_config, mtn_pay, or mtn_callback as action</div>';
echo '<div class="alert alert-info">Debug: Current route detected: "' . htmlspecialchars($route) . '"</div>';
echo '<div class="alert alert-info">Debug: Action extracted: "' . htmlspecialchars($action) . '"</div>';
echo '<div class="alert alert-info">Debug: Full URL: ' . htmlspecialchars($_SERVER['REQUEST_URI']) . '</div>';

?>
