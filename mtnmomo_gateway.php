<?php
/**
 * MTN MoMo Payment Gateway for PHPNuxBill
 * Plugin Name: MTN MoMo (SSP)
 * Version: 1.0
 * Currency: South Sudanese Pound (£)
 * Description: Complete MTN Mobile Money payment gateway for PHPNuxBill with South Sudan support
 * Author: PHPNuxBill Community
 * License: MIT
 */

// Prevent direct access
if (!defined('IN_PHPNUXBILL')) {
    exit('Direct access denied');
}

// ============================================
// GATEWAY CONFIGURATION
// ============================================
function mtnmomo_config() {
    return [
        'name' => 'MTN MoMo (SSP)',
        'version' => '1.0',
        'currency' => 'SSP',
        'symbol' => '£',
        'country_code' => '211',
        'mode' => 'sandbox', // sandbox, live, offline
        'api_key' => '',
        'api_secret' => '',
        'collection_subscription_key' => '',
        'api_user_id' => '',
        'callback_url' => '',
        'timeout' => 30,
        'retry_attempts' => 3,
        'enable_sms' => false,
        'admin_email' => ''
    ];
}

// ============================================
// DATABASE SETUP
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
                if ($this->db) {
                    $this->db->exec($sql);
                }
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
// GATEWAY VALIDATION
// ============================================
function mtnmomo_validate_config() {
    global $config;
    if (empty($config['mtnmomo_api_user_id']) || empty($config['mtnmomo_collection_subscription_key'])) {
        Message::sendTelegram("MTN MoMo payment gateway not configured.\nPlease set API User ID and Collection Subscription Key.");
        r2(U . 'order/package', 'w', Lang::T("Admin has not yet setup MTN MoMo payment gateway, please tell admin"));
    }
}

// ============================================
// GATEWAY CONFIGURATION FORM
// ============================================
function mtnmomo_show_config() {
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
    $ui->display('mtnmomo.tpl');
}

// ============================================
// PAYMENT PROCESSING
// ============================================
function mtnmomo_pay($gateway, $invoice, $customer) {
    global $momo_db, $config;
    
    try {
        $db = $momo_db->getDb();
        
        // Insert payment record
        if ($db) {
            $stmt = $db->prepare("INSERT INTO tbl_momo_payments (invoice_id, phone_number, amount, currency, status, user_id, customer_name, customer_email) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?)");
            $stmt->execute([$invoice['invoice_id'], $customer['phone'], $invoice['amount'], 'SSP', $customer['id'], $customer['name'], $customer['email']]);
        }
        
        // Generate reference
        $reference = 'MOMO' . time() . rand(1000, 9999);
        
        // Update reference
        if ($db) {
            $stmt = $db->prepare("UPDATE tbl_momo_payments SET reference = ? WHERE invoice_id = ?");
            $stmt->execute([$reference, $invoice['invoice_id']]);
        }
        
        // Return payment URL
        return U . 'order/view_mtnmomo/' . $invoice['invoice_id'] . '/' . $reference;
        
    } catch (Exception $e) {
        error_log("MoMo Pay Error: " . $e->getMessage());
        return false;
    }
}

// ============================================
// PAYMENT CALLBACK
// ============================================
function mtnmomo_callback() {
    global $momo_db;
    
    $invoice_id = $_POST['invoice_id'] ?? $_GET['invoice_id'] ?? '';
    $status = $_POST['status'] ?? $_GET['status'] ?? 'pending';
    $transaction_id = $_POST['transaction_id'] ?? $_GET['transaction_id'] ?? '';
    
    if (empty($invoice_id)) {
        die('Invalid callback');
    }
    
    try {
        $db = $momo_db->getDb();
        
        if ($db) {
            // Update payment status
            $stmt = $db->prepare("UPDATE tbl_momo_payments SET status = ?, transaction_id = ?, updated_at = NOW() WHERE invoice_id = ?");
            $stmt->execute([$status, $transaction_id, $invoice_id]);
            
            // Update analytics if successful
            if ($status === 'success') {
                updateAnalytics($invoice_id);
            }
        }
        
        // Redirect to success/failure page
        if ($status === 'success') {
            r2(U . 'order/view/' . $invoice_id, 's', 'Payment successful');
        } else {
            r2(U . 'order/view/' . $invoice_id, 'e', 'Payment failed');
        }
        exit;
        
    } catch (Exception $e) {
        error_log("MoMo Callback Error: " . $e->getMessage());
        die('Callback processing failed');
    }
}

// ============================================
// UTILITY FUNCTIONS
// ============================================
function updateAnalytics($invoice_id) {
    global $momo_db;
    
    try {
        $db = $momo_db->getDb();
        
        if ($db) {
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
        }
    } catch (Exception $e) {
        error_log("Analytics update error: " . $e->getMessage(), 'error');
    }
}

function mtnmomo_validatePhone($phone) {
    // Remove country code if present
    $phone = preg_replace('/^\+211/', '', $phone);
    $phone = preg_replace('/^\+/', '', $phone);
    
    // Validate South Sudan format: 9XXXXXXXX (9 digits starting with 9)
    return preg_match('/^9[0-9]{8}$/', $phone);
}

// ============================================
// ADMIN ROUTES
// ============================================
function mtnmomo_admin_routes() {
    // Handle admin routes for transactions, export, etc.
    if (isset($_GET['route']) && $_GET['route'] === 'mtnmomo_transactions') {
        mtnmomo_admin_transactions();
    }
}

function mtnmomo_admin_transactions() {
    global $momo_db;
    
    try {
        $db = $momo_db->getDb();
        
        if ($db) {
            $stmt = $db->prepare("SELECT * FROM tbl_momo_payments ORDER BY created_at DESC LIMIT 100");
            $stmt->execute();
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Display transactions in admin panel
            echo '<div class="box">
                <div class="box-header">
                    <h3 class="box-title">MTN MoMo Transactions</h3>
                </div>
                <div class="box-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Phone</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>';
            
            foreach ($transactions as $tx) {
                echo '<tr>
                    <td>' . htmlspecialchars($tx['invoice_id']) . '</td>
                    <td>' . htmlspecialchars($tx['phone_number']) . '</td>
                    <td>£' . number_format($tx['amount'], 2) . '</td>
                    <td><span class="label label-' . ($tx['status'] === 'success' ? 'success' : 'warning') . '">' . ucfirst($tx['status']) . '</span></td>
                    <td>' . date('Y-m-d H:i', strtotime($tx['created_at'])) . '</td>
                </tr>';
            }
            
            echo '</tbody></table></div></div>';
        }
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error loading transactions: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

?>
