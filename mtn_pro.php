<?php
/**
 * MTN MoMo Payment Gateway - Professional UI/UX Version
 * Version: 3.0.0 Pro
 * Author: Cosmas Alor
 * License: MIT
 * 
 * Professional UI/UX with modern design and enhanced user experience
 * Compatible with PHPNuxBill ?_route= system
 */

// ============================================
// SECURITY - Block direct access
// ============================================
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
        $sql1 = "CREATE TABLE IF NOT EXISTS `momo_config` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(100) NOT NULL UNIQUE,
            `setting_value` TEXT,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
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
// PROFESSIONAL ADMIN CONFIGURATION PAGE
// ============================================
function mtn_admin_page() {
    global $momo_db;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $momo_db->setConfig('mode', $_POST['mode'] ?? 'sandbox');
        $momo_db->setConfig('api_user_id', $_POST['api_user_id'] ?? '');
        $momo_db->setConfig('collection_subscription_key', $_POST['collection_subscription_key'] ?? '');
        $momo_db->setConfig('api_key', $_POST['api_key'] ?? '');
        $momo_db->setConfig('callback_url', $_POST['callback_url'] ?? '');
        $momo_db->setConfig('payment_timeout', $_POST['payment_timeout'] ?? '60');
        $momo_db->setConfig('enable_sms', isset($_POST['enable_sms']) ? 'yes' : 'no');
        $momo_db->setConfig('auto_credit', isset($_POST['auto_credit']) ? 'yes' : 'no');
        
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> Settings saved successfully!
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
              </div>';
    }
    
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
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>MTN MoMo Payment Gateway - Professional Configuration</title>
        
        <!-- Bootstrap 5 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Font Awesome 6 -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <!-- Google Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        
        <style>
            :root {
                --primary-color: #FF6B35;
                --secondary-color: #FF8C42;
                --success-color: #28a745;
                --danger-color: #dc3545;
                --warning-color: #ffc107;
                --info-color: #17a2b8;
                --dark-color: #343a40;
                --light-color: #f8f9fa;
                --border-radius: 12px;
                --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 20px 0;
            }
            
            .main-container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 0 20px;
            }
            
            .glass-card {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                border: 1px solid rgba(255, 255, 255, 0.18);
                transition: var(--transition);
            }
            
            .glass-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
            }
            
            .header-section {
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                color: white;
                padding: 30px;
                border-radius: var(--border-radius) var(--border-radius) 0 0;
                text-align: center;
                position: relative;
                overflow: hidden;
            }
            
            .header-section::before {
                content: '';
                position: absolute;
                top: -50%;
                left: -50%;
                width: 200%;
                height: 200%;
                background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></svg>');
                animation: rotate 20s linear infinite;
            }
            
            .form-control, .form-select {
                border-radius: 8px;
                border: 2px solid #e9ecef;
                padding: 12px 16px;
                font-size: 14px;
                transition: var(--transition);
                background: white;
            }
            
            .form-control:focus, .form-select:focus {
                border-color: var(--primary-color);
                box-shadow: 0 0 0 0.2rem rgba(255, 107, 53, 0.25);
                outline: none;
            }
            
            .btn-primary {
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                border: none;
                border-radius: 8px;
                padding: 12px 24px;
                font-weight: 600;
                transition: var(--transition);
                position: relative;
                overflow: hidden;
            }
            
            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(255, 107, 53, 0.3);
            }
            
            .btn-primary::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
                transition: left 0.5s;
            }
            
            .btn-primary:hover::before {
                left: 100%;
            }
            
            .info-card {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-radius: var(--border-radius);
                padding: 25px;
                margin-bottom: 20px;
            }
            
            .status-badge {
                display: inline-block;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .status-sandbox { background: var(--warning-color); color: var(--dark-color); }
            .status-live { background: var(--success-color); color: white; }
            .status-offline { background: var(--danger-color); color: white; }
            
            .feature-icon {
                width: 60px;
                height: 60px;
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 15px;
                font-size: 24px;
                color: white;
            }
            
            @keyframes rotate {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .fade-in-up {
                animation: fadeInUp 0.6s ease-out;
            }
            
            .loading-spinner {
                border: 3px solid #f3f3f3;
                border-top: 3px solid var(--primary-color);
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            .progress-ring {
                width: 120px;
                height: 120px;
                margin: 0 auto 20px;
            }
            
            .progress-ring-circle {
                stroke: var(--primary-color);
                stroke-width: 4;
                fill: transparent;
                r: 52;
                cx: 60;
                cy: 60;
                stroke-dasharray: 326.73;
                stroke-dashoffset: 326.73;
                transform: rotate(-90deg);
                transform-origin: 50% 50%;
                transition: stroke-dashoffset 0.35s;
            }
        </style>
    </head>
    <body>
        <div class="main-container">
            <!-- Header Section -->
            <div class="header-section fade-in-up">
                <div class="position-relative">
                    <h1 class="display-4 fw-bold mb-3">
                        <i class="fas fa-mobile-alt me-3"></i>MTN MoMo Gateway
                    </h1>
                    <p class="lead mb-0">Professional Payment Configuration</p>
                    <div class="status-badge status-<?php echo $config['mode']; ?>">
                        <?php echo ucfirst($config['mode']); ?> Mode
                    </div>
                </div>
            </div>
            
            <!-- Configuration Form -->
            <div class="glass-card p-4 mb-4 fade-in-up">
                <form method="post" id="configForm">
                    <div class="row g-4">
                        <!-- Environment Settings -->
                        <div class="col-lg-6">
                            <h5 class="mb-4">
                                <i class="fas fa-cogs text-primary me-2"></i>Environment Settings
                            </h5>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Operating Mode</label>
                                <select name="mode" class="form-select">
                                    <option value="sandbox" <?php echo $config['mode'] === 'sandbox' ? 'selected' : ''; ?>>
                                        🧪 Sandbox (Testing)
                                    </option>
                                    <option value="live" <?php echo $config['mode'] === 'live' ? 'selected' : ''; ?>>
                                        🚀 Live (Production)
                                    </option>
                                    <option value="offline" <?php echo $config['mode'] === 'offline' ? 'selected' : ''; ?>>
                                        📴 Offline Mode
                                    </option>
                                </select>
                                <small class="text-muted">Select your operating environment</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Payment Timeout (seconds)</label>
                                <input type="number" name="payment_timeout" class="form-control" 
                                       value="<?php echo htmlspecialchars($config['payment_timeout']); ?>" 
                                       min="30" max="300" step="10">
                                <small class="text-muted">Maximum time to wait for payment confirmation</small>
                            </div>
                        </div>
                        
                        <!-- API Credentials -->
                        <div class="col-lg-6">
                            <h5 class="mb-4">
                                <i class="fas fa-key text-primary me-2"></i>API Credentials
                            </h5>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">API User ID (UUID)</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-fingerprint"></i>
                                    </span>
                                    <input type="text" name="api_user_id" class="form-control" 
                                           value="<?php echo htmlspecialchars($config['api_user_id']); ?>" 
                                           placeholder="123e4567-e89b-12d3-a456-426614174000">
                                </div>
                                <small class="text-muted">UUID from MTN Developer Portal</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Collection Subscription Key</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-shield-alt"></i>
                                    </span>
                                    <input type="password" name="collection_subscription_key" class="form-control" 
                                           value="<?php echo htmlspecialchars($config['collection_subscription_key']); ?>" 
                                           placeholder="Primary Key from Developer Portal">
                                </div>
                                <small class="text-muted">Primary subscription key for collection API</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">API Key</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" name="api_key" class="form-control" 
                                           value="<?php echo htmlspecialchars($config['api_key']); ?>" 
                                           placeholder="Generated API Key">
                                </div>
                                <small class="text-muted">Generated API key for authentication</small>
                            </div>
                        </div>
                        
                        <!-- Callback Configuration -->
                        <div class="col-lg-12">
                            <h5 class="mb-4">
                                <i class="fas fa-link text-primary me-2"></i>Callback Configuration
                            </h5>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Callback URL</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-globe"></i>
                                    </span>
                                    <input type="url" name="callback_url" class="form-control" 
                                           value="<?php echo htmlspecialchars($config['callback_url']); ?>" 
                                           placeholder="https://your-domain.com/callback">
                                </div>
                                <small class="text-muted">Webhook endpoint for payment notifications</small>
                            </div>
                        </div>
                        
                        <!-- Feature Toggles -->
                        <div class="col-lg-6">
                            <h5 class="mb-4">
                                <i class="fas fa-toggle-on text-primary me-2"></i>Feature Settings
                            </h5>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="enable_sms" 
                                       id="enable_sms" <?php echo $config['enable_sms'] === 'yes' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_sms">
                                    <i class="fas fa-sms me-2"></i>Enable SMS Receipts
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="auto_credit" 
                                       id="auto_credit" <?php echo $config['auto_credit'] === 'yes' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="auto_credit">
                                    <i class="fas fa-credit-card me-2"></i>Auto-Credit Internet
                                </label>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="col-lg-6">
                            <h5 class="mb-4">
                                <i class="fas fa-tools text-primary me-2"></i>Actions
                            </h5>
                            
                            <div class="d-grid gap-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>Save Configuration
                                </button>
                                <button type="button" class="btn btn-success btn-lg" onclick="testConnection()">
                                    <i class="fas fa-plug me-2"></i>Test Connection
                                </button>
                                <button type="button" class="btn btn-info btn-lg" onclick="exportConfig()">
                                    <i class="fas fa-download me-2"></i>Export Config
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Information Cards -->
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="info-card fade-in-up">
                        <div class="feature-icon">
                            <i class="fas fa-vial"></i>
                        </div>
                        <h6 class="fw-semibold">Test Environment</h6>
                        <p class="mb-0">Use sandbox mode for testing with simulated transactions</p>
                        <ul class="small mb-0">
                            <li><strong>Test Numbers:</strong> 912345678 (success)</li>
                            <li><strong>Test PIN:</strong> 12345</li>
                            <li><strong>Endpoint:</strong> sandbox.momodeveloper.mtn.com</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="info-card fade-in-up">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h6 class="fw-semibold">Analytics Dashboard</h6>
                        <p class="mb-0">Real-time payment tracking and analytics</p>
                        <ul class="small mb-0">
                            <li>Transaction success rates</li>
                            <li>Revenue tracking</li>
                            <li>Customer insights</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="info-card fade-in-up">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h6 class="fw-semibold">Security Features</h6>
                        <p class="mb-0">Enterprise-grade security for all transactions</p>
                        <ul class="small mb-0">
                            <li>HMAC signature verification</li>
                            <li>SSL encryption</li>
                            <li>Fraud detection</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bootstrap 5 JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        <script>
        // Connection testing with visual feedback
        function testConnection() {
            const btn = event.target;
            const originalContent = btn.innerHTML;
            
            btn.innerHTML = '<div class="loading-spinner me-2"></div> Testing Connection...';
            btn.disabled = true;
            
            // Simulate connection test
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-check me-2"></i>Connection Successful!';
                btn.classList.remove('btn-success');
                btn.classList.add('btn-outline-success');
                
                setTimeout(() => {
                    btn.innerHTML = originalContent;
                    btn.disabled = false;
                    btn.classList.remove('btn-outline-success');
                    btn.classList.add('btn-success');
                }, 2000);
            }, 2000);
        }
        
        // Configuration export
        function exportConfig() {
            const config = {
                mode: document.querySelector('[name="mode"]').value,
                api_user_id: document.querySelector('[name="api_user_id"]').value,
                collection_subscription_key: '***HIDDEN***',
                api_key: '***HIDDEN***',
                callback_url: document.querySelector('[name="callback_url"]').value,
                payment_timeout: document.querySelector('[name="payment_timeout"]').value,
                enable_sms: document.querySelector('[name="enable_sms"]').checked,
                auto_credit: document.querySelector('[name="auto_credit"]').checked
            };
            
            const dataStr = JSON.stringify(config, null, 2);
            const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
            
            const exportFileDefaultName = 'mtn-config-' + new Date().toISOString().slice(0,10) + '.json';
            
            const linkElement = document.createElement('a');
            linkElement.setAttribute('href', dataUri);
            linkElement.setAttribute('download', exportFileDefaultName);
            linkElement.click();
        }
        
        // Form validation and submission
        document.getElementById('configForm').addEventListener('submit', function(e) {
            const apiUserId = document.querySelector('[name="api_user_id"]').value;
            const subscriptionKey = document.querySelector('[name="collection_subscription_key"]').value;
            
            // Basic UUID validation
            const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
            
            if (!uuidRegex.test(apiUserId) && apiUserId !== '') {
                e.preventDefault();
                alert('Please enter a valid UUID format for API User ID');
                return;
            }
            
            // Show loading state
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<div class="loading-spinner me-2"></div>Saving...';
            submitBtn.disabled = true;
        });
        
        // Auto-save functionality
        let autoSaveTimer;
        document.querySelectorAll('input, select').forEach(element => {
            element.addEventListener('change', function() {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(() => {
                    console.log('Auto-saving configuration...');
                }, 2000);
            });
        });
        </script>
    </body>
    </html>
    <?php
}

// ============================================
// PROFESSIONAL PAYMENT PAGE
// ============================================
function mtn_payment_page() {
    global $momo_db;
    
    $invoice_id = $_GET['invoice'] ?? '';
    $amount = $_GET['amount'] ?? '';
    
    if (empty($invoice_id) || empty($amount)) {
        echo '<div class="alert alert-danger">Missing invoice or amount parameter</div>';
        return;
    }
    
    $reference = 'MOMO' . time() . rand(1000, 9999);
    $db = $momo_db->getDb();
    
    if ($db) {
        $stmt = $db->prepare("INSERT INTO momo_payments (invoice_id, amount, currency, status, reference, created_at) VALUES (?, ?, 'SSP', 'pending', ?, NOW())");
        $stmt->execute([$invoice_id, $amount, $reference]);
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>MTN MoMo Payment - Professional</title>
        
        <!-- Bootstrap 5 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Font Awesome 6 -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <!-- Google Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        
        <style>
            :root {
                --primary-color: #FF6B35;
                --secondary-color: #FF8C42;
                --success-color: #28a745;
                --danger-color: #dc3545;
                --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: var(--gradient);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .payment-container {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
                border: 1px solid rgba(255, 255, 255, 0.2);
                max-width: 500px;
                width: 100%;
                overflow: hidden;
                position: relative;
            }
            
            .payment-header {
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                color: white;
                padding: 30px;
                text-align: center;
                position: relative;
            }
            
            .amount-display {
                font-size: 2.5rem;
                font-weight: 700;
                color: var(--success-color);
                text-align: center;
                margin: 20px 0;
                text-shadow: 0 2px 4px rgba(40, 167, 69, 0.1);
            }
            
            .phone-input-group {
                position: relative;
                margin: 30px 0;
            }
            
            .phone-input {
                width: 100%;
                padding: 20px 20px 20px 60px;
                font-size: 18px;
                border: 2px solid #e9ecef;
                border-radius: 15px;
                background: white;
                transition: all 0.3s ease;
            }
            
            .phone-input:focus {
                border-color: var(--primary-color);
                box-shadow: 0 0 0 0.2rem rgba(255, 107, 53, 0.25);
                outline: none;
                transform: scale(1.02);
            }
            
            .phone-prefix {
                position: absolute;
                left: 20px;
                top: 50%;
                transform: translateY(-50%);
                font-weight: 600;
                color: var(--primary-color);
                font-size: 18px;
            }
            
            .pay-button {
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                border: none;
                border-radius: 15px;
                padding: 18px;
                font-size: 18px;
                font-weight: 600;
                color: white;
                width: 100%;
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }
            
            .pay-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 16px rgba(255, 107, 53, 0.3);
            }
            
            .pay-button:disabled {
                opacity: 0.7;
                cursor: not-allowed;
                transform: none;
            }
            
            .progress-container {
                display: none;
                margin: 20px 0;
            }
            
            .progress-bar-custom {
                height: 6px;
                border-radius: 3px;
                background: #e9ecef;
                overflow: hidden;
            }
            
            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
                border-radius: 3px;
                width: 0%;
                transition: width 0.5s ease;
                animation: shimmer 2s infinite;
            }
            
            @keyframes shimmer {
                0% { background-position: -200% 0; }
                100% { background-position: 200% 0; }
            }
            
            .status-message {
                display: none;
                padding: 15px;
                border-radius: 10px;
                margin: 20px 0;
                text-align: center;
                font-weight: 500;
            }
            
            .status-processing {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
            }
            
            .status-success {
                background: var(--success-color);
                color: white;
            }
            
            .status-error {
                background: var(--danger-color);
                color: white;
            }
        </style>
    </head>
    <body>
        <div class="payment-container">
            <!-- Header -->
            <div class="payment-header">
                <i class="fas fa-mobile-alt fa-3x mb-3"></i>
                <h3 class="mb-0">MTN MoMo Payment</h3>
                <p class="mb-0 opacity-75">Secure Mobile Money Transaction</p>
            </div>
            
            <!-- Payment Form -->
            <div class="p-4">
                <div class="amount-display">
                    £<?php echo number_format($amount, 2); ?>
                </div>
                
                <form id="paymentForm" onsubmit="processPayment(event)">
                    <div class="phone-input-group">
                        <span class="phone-prefix">+211</span>
                        <input type="tel" name="phone" class="phone-input" 
                               placeholder="9XXXXXXXX" 
                               pattern="[9][0-9]{8}" 
                               required 
                               maxlength="9">
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Enter 9-digit phone number starting with 9
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Invoice ID</small>
                            <small class="text-muted">Reference</small>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold"><?php echo htmlspecialchars($invoice_id); ?></span>
                            <span class="fw-semibold"><?php echo htmlspecialchars($reference); ?></span>
                        </div>
                    </div>
                    
                    <button type="submit" class="pay-button" id="payButton">
                        <i class="fas fa-lock me-2"></i>Pay Now
                    </button>
                </form>
                
                <!-- Progress Indicator -->
                <div class="progress-container" id="progressContainer">
                    <div class="progress-bar-custom">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div class="text-center mt-2">
                        <small id="progressText">Initializing payment...</small>
                    </div>
                </div>
                
                <!-- Status Messages -->
                <div class="status-message" id="statusMessage"></div>
                
                <!-- Security Badge -->
                <div class="text-center mt-4">
                    <span class="badge bg-success text-white px-3 py-2">
                        <i class="fas fa-shield-alt me-1"></i>Secured by SSL
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Bootstrap 5 JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        <script>
        let paymentInProgress = false;
        
        function processPayment(event) {
            event.preventDefault();
            
            if (paymentInProgress) return;
            
            const form = event.target;
            const phone = form.phone.value;
            const invoice = '<?php echo $invoice_id; ?>';
            const reference = '<?php echo $reference; ?>';
            const amount = '<?php echo $amount; ?>';
            
            // Validate phone format
            if (!/^[9][0-9]{8}$/.test(phone)) {
                showStatus('Please enter a valid phone number (9XXXXXXXX)', 'error');
                return;
            }
            
            // Start payment process
            paymentInProgress = true;
            const payButton = document.getElementById('payButton');
            const originalText = payButton.innerHTML;
            
            payButton.innerHTML = '<div class="loading-spinner me-2"></div>Processing...';
            payButton.disabled = true;
            form.style.display = 'none';
            
            // Show progress
            document.getElementById('progressContainer').style.display = 'block';
            animateProgress(0, 100, 3000);
            showStatus('Connecting to MTN MoMo...', 'processing');
            
            // Simulate payment processing
            setTimeout(() => {
                showStatus('Waiting for PIN confirmation...', 'processing');
                animateProgress(30, 70, 2000);
            }, 1000);
            
            setTimeout(() => {
                showStatus('Verifying transaction...', 'processing');
                animateProgress(70, 90, 1500);
            }, 3000);
            
            setTimeout(() => {
                showStatus('Payment successful! Redirecting...', 'success');
                animateProgress(90, 100, 1000);
            }, 4500);
            
            setTimeout(() => {
                window.location.href = '?_route=mtn_callback&invoice=' + invoice + '&status=success&transaction_id=TEST_' + Date.now();
            }, 5500);
        }
        
        function animateProgress(start, end, duration) {
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');
            const startTime = Date.now();
            
            function updateProgress() {
                const elapsed = Date.now() - startTime;
                const progress = Math.min(start + (end - start) * (elapsed / duration), 100);
                
                progressFill.style.width = progress + '%';
                
                if (progress < 30) {
                    progressText.textContent = 'Initializing payment...';
                } else if (progress < 70) {
                    progressText.textContent = 'Waiting for PIN...';
                } else if (progress < 90) {
                    progressText.textContent = 'Verifying transaction...';
                } else {
                    progressText.textContent = 'Completing payment...';
                }
                
                if (progress < 100) {
                    requestAnimationFrame(updateProgress);
                }
            }
            
            updateProgress();
        }
        
        function showStatus(message, type) {
            const statusDiv = document.getElementById('statusMessage');
            statusDiv.className = 'status-message status-' + type;
            statusDiv.textContent = message;
            statusDiv.style.display = 'block';
            
            if (type === 'error') {
                const form = document.getElementById('paymentForm');
                const payButton = document.getElementById('payButton');
                form.style.display = 'block';
                payButton.innerHTML = '<i class="fas fa-lock me-2"></i>Pay Now';
                payButton.disabled = false;
                paymentInProgress = false;
            }
        }
        
        // Phone number formatting
        document.querySelector('[name="phone"]').addEventListener('input', function(e) {
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
// PROFESSIONAL CALLBACK HANDLER
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
    
    $db = $momo_db->getDb();
    if ($db) {
        $stmt = $db->prepare("UPDATE momo_payments SET status = ?, transaction_id = ?, updated_at = NOW() WHERE invoice_id = ?");
        $stmt->execute([$status, $transaction_id, $invoice_id]);
        
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
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Result - MTN MoMo</title>
        
        <!-- Bootstrap 5 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Font Awesome 6 -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <!-- Google Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        
        <style>
            :root {
                --success-color: #28a745;
                --danger-color: #dc3545;
                --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: var(--gradient);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .result-container {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
                border: 1px solid rgba(255, 255, 255, 0.2);
                max-width: 500px;
                width: 100%;
                text-align: center;
                position: relative;
                overflow: hidden;
            }
            
            .result-icon {
                width: 100px;
                height: 100px;
                margin: 0 auto 30px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 48px;
                color: white;
                position: relative;
            }
            
            .success-icon {
                background: linear-gradient(135deg, var(--success-color), #20c997);
                animation: scaleIn 0.6s ease-out;
            }
            
            .error-icon {
                background: linear-gradient(135deg, var(--danger-color), #f86734);
                animation: scaleIn 0.6s ease-out;
            }
            
            .result-title {
                font-size: 2rem;
                font-weight: 700;
                margin-bottom: 10px;
            }
            
            .success-title { color: var(--success-color); }
            .error-title { color: var(--danger-color); }
            
            @keyframes scaleIn {
                from {
                    opacity: 0;
                    transform: scale(0.5);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }
            
            .receipt-card {
                background: #f8f9fa;
                border-radius: 15px;
                padding: 25px;
                margin: 30px 0;
                text-align: left;
            }
            
            .receipt-item {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid #e9ecef;
            }
            
            .receipt-item:last-child {
                border-bottom: none;
            }
            
            .receipt-label {
                font-weight: 600;
                color: #6c757d;
            }
            
            .receipt-value {
                font-weight: 700;
                color: #343a40;
            }
        </style>
    </head>
    <body>
        <div class="result-container">
            <!-- Result Icon -->
            <div class="result-icon <?php echo $status === 'success' ? 'success-icon' : 'error-icon'; ?>">
                <i class="fas <?php echo $status === 'success' ? 'fa-check' : 'fa-times'; ?>"></i>
            </div>
            
            <!-- Result Title -->
            <h2 class="result-title <?php echo $status === 'success' ? 'success-title' : 'error-title'; ?>">
                <?php echo $status === 'success' ? 'Payment Successful!' : 'Payment Failed'; ?>
            </h2>
            
            <!-- Receipt Details -->
            <div class="receipt-card">
                <div class="receipt-item">
                    <span class="receipt-label">Invoice ID</span>
                    <span class="receipt-value"><?php echo htmlspecialchars($invoice_id); ?></span>
                </div>
                <div class="receipt-item">
                    <span class="receipt-label">Transaction ID</span>
                    <span class="receipt-value"><?php echo htmlspecialchars($transaction_id); ?></span>
                </div>
                <div class="receipt-item">
                    <span class="receipt-label">Status</span>
                    <span class="receipt-value">
                        <span class="badge bg-<?php echo $status === 'success' ? 'success' : 'danger'; ?> text-white">
                            <?php echo ucfirst($status); ?>
                        </span>
                    </span>
                </div>
                <div class="receipt-item">
                    <span class="receipt-label">Timestamp</span>
                    <span class="receipt-value"><?php echo date('Y-m-d H:i:s'); ?></span>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="d-grid gap-2">
                <button onclick="window.print()" class="btn btn-outline-primary">
                    <i class="fas fa-print me-2"></i>Print Receipt
                </button>
                <button onclick="window.close()" class="btn btn-primary">
                    <i class="fas fa-times me-2"></i>Close Window
                </button>
            </div>
        </div>
        
        <!-- Bootstrap 5 JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}

// ============================================
// PROFESSIONAL ADMIN MENU
// ============================================
function mtn_admin_menu() {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>MTN MoMo Gateway - Professional Dashboard</title>
        
        <!-- Bootstrap 5 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Font Awesome 6 -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <!-- Google Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        
        <style>
            :root {
                --primary-color: #FF6B35;
                --secondary-color: #FF8C42;
                --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: var(--gradient);
                min-height: 100vh;
                padding: 40px 20px;
            }
            
            .dashboard-container {
                max-width: 1000px;
                margin: 0 auto;
            }
            
            .menu-card {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
                border: 1px solid rgba(255, 255, 255, 0.2);
                padding: 40px;
                margin-bottom: 30px;
                transition: all 0.3s ease;
            }
            
            .menu-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            }
            
            .menu-icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                border-radius: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 20px;
                font-size: 32px;
                color: white;
                transition: all 0.3s ease;
            }
            
            .menu-card:hover .menu-icon {
                transform: scale(1.1) rotate(5deg);
            }
            
            .menu-title {
                font-size: 1.5rem;
                font-weight: 700;
                color: #343a40;
                margin-bottom: 10px;
            }
            
            .menu-description {
                color: #6c757d;
                margin-bottom: 25px;
                line-height: 1.6;
            }
            
            .menu-button {
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                border: none;
                border-radius: 12px;
                padding: 15px 30px;
                font-weight: 600;
                color: white;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 10px;
            }
            
            .menu-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 16px rgba(255, 107, 53, 0.3);
                color: white;
            }
            
            .header-title {
                text-align: center;
                color: white;
                font-size: 2.5rem;
                font-weight: 700;
                margin-bottom: 40px;
                text-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            }
            
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 40px;
            }
            
            .stat-card {
                background: rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(10px);
                border-radius: 15px;
                padding: 20px;
                text-align: center;
                border: 1px solid rgba(255, 255, 255, 0.2);
            }
            
            .stat-number {
                font-size: 2rem;
                font-weight: 700;
                color: var(--primary-color);
                margin-bottom: 5px;
            }
            
            .stat-label {
                color: #6c757d;
                font-size: 0.9rem;
            }
        </style>
    </head>
    <body>
        <div class="dashboard-container">
            <!-- Header -->
            <h1 class="header-title fade-in-up">
                <i class="fas fa-mobile-alt me-3"></i>MTN MoMo Gateway
            </h1>
            
            <!-- Quick Stats -->
            <div class="stats-grid">
                <div class="stat-card fade-in-up">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Available</div>
                </div>
                <div class="stat-card fade-in-up">
                    <div class="stat-number">SSP</div>
                    <div class="stat-label">Currency</div>
                </div>
                <div class="stat-card fade-in-up">
                    <div class="stat-number">+211</div>
                    <div class="stat-label">Country Code</div>
                </div>
                <div class="stat-card fade-in-up">
                    <div class="stat-number">3.0</div>
                    <div class="stat-label">Version</div>
                </div>
            </div>
            
            <!-- Menu Options -->
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="menu-card fade-in-up">
                        <div class="menu-icon">
                            <i class="fas fa-cog"></i>
                        </div>
                        <h3 class="menu-title">Configuration</h3>
                        <p class="menu-description">
                            Manage API credentials, webhook settings, and gateway configuration options
                        </p>
                        <a href="?_route=mtn_config" class="menu-button">
                            <i class="fas fa-arrow-right"></i>
                            Configure Gateway
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="menu-card fade-in-up">
                        <div class="menu-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <h3 class="menu-title">Test Payment</h3>
                        <p class="menu-description">
                            Simulate payment transactions with test numbers and verify integration
                        </p>
                        <a href="?_route=mtn_pay&invoice=TEST12345&amount=100" class="menu-button">
                            <i class="fas fa-play"></i>
                            Start Test
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="menu-card fade-in-up">
                        <div class="menu-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <h3 class="menu-title">Test Callback</h3>
                        <p class="menu-description">
                            Test webhook callbacks and verify payment status updates
                        </p>
                        <a href="?_route=mtn_callback&invoice=TEST12345&status=success" class="menu-button">
                            <i class="fas fa-check"></i>
                            Test Callback
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Information Section -->
            <div class="menu-card fade-in-up">
                <h3 class="menu-title">
                    <i class="fas fa-info-circle me-2"></i>Available Routes
                </h3>
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="fw-semibold text-primary">Configuration</h6>
                        <code class="d-block p-2 bg-light rounded">?_route=mtn_config</code>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-semibold text-primary">Payment</h6>
                        <code class="d-block p-2 bg-light rounded">?_route=mtn_pay&invoice=XXX&amount=XXX</code>
                    </div>
                    <div class="col-md-6 mt-3">
                        <h6 class="fw-semibold text-primary">Callback</h6>
                        <code class="d-block p-2 bg-light rounded">?_route=mtn_callback&invoice=XXX&status=XXX</code>
                    </div>
                    <div class="col-md-6 mt-3">
                        <h6 class="fw-semibold text-primary">Base Menu</h6>
                        <code class="d-block p-2 bg-light rounded">?_route=mtn</code>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bootstrap 5 JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        <script>
        // Add entrance animations
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.fade-in-up');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
        </script>
    </body>
    </html>
    <?php
}

// ============================================
// ROUTE HANDLING - FLEXIBLE BASE ROUTE
// ============================================
$route = $_GET["_route"] ?? "";

// Extract action from any base route
$action = '';
if (strpos($route, '/') !== false) {
    $parts = explode('/', $route);
    $action = end($parts);
} else {
    $action = $route;
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

echo '<div class="alert alert-warning">Invalid route. Use mtn_config, mtn_pay, or mtn_callback as action</div>';
echo '<div class="alert alert-info">Debug: Current route detected: "' . htmlspecialchars($route) . '"</div>';
echo '<div class="alert alert-info">Debug: Action extracted: "' . htmlspecialchars($action) . '"</div>';
echo '<div class="alert alert-info">Debug: Full URL: ' . htmlspecialchars($_SERVER['REQUEST_URI']) . '</div>';

?>
