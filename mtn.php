<?php
/**
 * MTN MoMo Payment Gateway for PHPNuxBill
 * Version: 2.0.0
 * Author: Cosmas Alor
 * License: MIT
 * 
 * Fully configurable via admin interface
 */

// ============================================
// SECURITY - Block direct access
// ============================================
if (basename($_SERVER["PHP_SELF"]) == basename(__FILE__) && !isset($_GET["_route"])) {
    exit("Direct access denied");
}

// ============================================
// LOAD SAVED CONFIGURATION FROM DATABASE
// ============================================
class MoMoConfig {
    private $db;
    private $config = [];
    
    public function __construct() {
        global $db_host, $db_port, $db_name, $db_user, $db_pass;
        try {
            $this->db = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createConfigTable();
            $this->loadConfig();
        } catch (PDOException $e) {
            error_log("MoMo Config DB Error: " . $e->getMessage());
        }
    }
    
    private function createConfigTable() {
        $this->db->exec("CREATE TABLE IF NOT EXISTS `momo_config` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(100) NOT NULL UNIQUE,
            `setting_value` TEXT,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    }
    
    private function loadConfig() {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM momo_config");
        $defaults = [
            "mode" => "sandbox",
            "subscription_key" => "",
            "api_user" => "",
            "api_key" => "",
            "callback_host" => $this->getServerUrl(),
            "currency" => "SSP",
            "currency_symbol" => "£",
            "target_environment" => "mtnsouthsudan",
            "sandbox_base_url" => "https://sandbox.momodeveloper.mtn.com",
            "live_base_url" => "https://api.mtn.com/v1"
        ];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->config[$row["setting_key"]] = $row["setting_value"];
        }
        
        // Merge with defaults
        foreach ($defaults as $key => $value) {
            if (!isset($this->config[$key])) {
                $this->config[$key] = $value;
            }
        }
    }
    
    private function getServerUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? "localhost";
        return $protocol . "://" . $host;
    }
    
    public function get($key) {
        return $this->config[$key] ?? null;
    }
    
    public function getAll() {
        return $this->config;
    }
    
    public function set($key, $value) {
        $stmt = $this->db->prepare("INSERT INTO momo_config (setting_key, setting_value) VALUES (?, ?) 
                                    ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
        $this->config[$key] = $value;
    }
    
    public function saveAll($settings) {
        foreach ($settings as $key => $value) {
            $this->set($key, $value);
        }
    }
}

// Initialize config
$momo_config_obj = new MoMoConfig();
$mtn_config = $momo_config_obj->getAll();

// ============================================
// MTN API CLASS
// ============================================
class MTNMoMoAPI {
    private $base_url, $subscription_key, $api_user, $api_key, $target_env;
    private $access_token = null;
    private $token_file;
    
    public function __construct($config) {
        $mode = $config["mode"] ?? "sandbox";
        $this->base_url = $mode == "sandbox" ? $config["sandbox_base_url"] : $config["live_base_url"];
        $this->subscription_key = $config["subscription_key"] ?? "";
        $this->api_user = $config["api_user"] ?? "";
        $this->api_key = $config["api_key"] ?? "";
        $this->target_env = $config["target_environment"] ?? "mtnsouthsudan";
        $this->token_file = __DIR__ . "/../../cache/mtn_token.json";
        $this->ensureCacheDirectory();
    }
    
    private function ensureCacheDirectory() {
        $cache_dir = dirname($this->token_file);
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
    }
    
    public function getAccessToken() {
        // Check cached token
        if (file_exists($this->token_file)) {
            $cached = json_decode(file_get_contents($this->token_file), true);
            if ($cached && isset($cached["expires"]) && $cached["expires"] > time()) {
                $this->access_token = $cached["access_token"];
                return true;
            }
        }
        
        // Validate credentials
        if (empty($this->api_user) || empty($this->api_key) || empty($this->subscription_key)) {
            error_log("MTN API: Missing credentials");
            return false;
        }
        
        // Get new token
        $credentials = base64_encode($this->api_user . ":" . $this->api_key);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->base_url . "/collection/token/",
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Basic " . $credentials,
                "Ocp-Apim-Subscription-Key: " . $this->subscription_key,
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => "{}",
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false // For testing only, remove in production
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200 || $http_code == 201) {
            $data = json_decode($response, true);
            $this->access_token = $data["access_token"] ?? $data["token"] ?? null;
            if ($this->access_token) {
                file_put_contents($this->token_file, json_encode([
                    "access_token" => $this->access_token,
                    "expires" => time() + 3500
                ]));
                return true;
            }
        }
        
        error_log("MTN API: Token error (HTTP $http_code): " . substr($response, 0, 200));
        return false;
    }
    
    public function requestPayment($phone, $amount, $external_id, $callback_url) {
        if (!$this->getAccessToken()) {
            return ["success" => false, "message" => "API authentication failed. Check your credentials."];
        }
        
        // Format phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $phone = ltrim($phone, "0");
        
        // Generate UUID v4 for reference
        $reference_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        $payload = [
            "amount" => (string)round($amount, 2),
            "currency" => "EUR",
            "externalId" => $external_id,
            "payer" => [
                "partyIdType" => "MSISDN",
                "partyId" => $phone
            ],
            "payerMessage" => "Payment for Invoice #" . $external_id,
            "payeeNote" => "Thank you for your payment"
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->base_url . "/collection/v1_0/requesttopay",
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->access_token,
                "Ocp-Apim-Subscription-Key: " . $this->subscription_key,
                "X-Reference-Id: " . $reference_id,
                "X-Target-Environment: " . $this->target_env,
                "X-Callback-Url: " . $callback_url,
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 202) {
            return ["success" => true, "reference_id" => $reference_id];
        }
        
        error_log("MTN API: Request payment error: $response");
        return ["success" => false, "message" => "API error: HTTP $http_code"];
    }
    
    public function checkTransactionStatus($reference_id) {
        if (!$this->getAccessToken()) {
            return ["status" => "PENDING"];
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->base_url . "/collection/v1_0/requesttopay/" . $reference_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->access_token,
                "Ocp-Apim-Subscription-Key: " . $this->subscription_key,
                "X-Target-Environment: " . $this->target_env
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            return json_decode($response, true);
        }
        
        return ["status" => "PENDING"];
    }
    
    public function testConnection() {
        return $this->getAccessToken();
    }
}

// ============================================
// DATABASE SETUP FOR TRANSACTIONS
// ============================================
class MoMoDatabase {
    private $db;
    
    public function __construct() {
        global $db_host, $db_port, $db_name, $db_user, $db_pass;
        try {
            $this->db = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createTables();
        } catch (PDOException $e) {
            error_log("MoMo DB Error: " . $e->getMessage());
        }
    }
    
    private function createTables() {
        $this->db->exec("CREATE TABLE IF NOT EXISTS `momo_transactions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `invoice_id` VARCHAR(50) NOT NULL,
            `phone` VARCHAR(20) NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `reference_id` VARCHAR(100),
            `mtn_reference` VARCHAR(100),
            `transaction_id` VARCHAR(100),
            `status` ENUM('pending','success','failed','cancelled') DEFAULT 'pending',
            `response` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `invoice_id` (`invoice_id`),
            INDEX `status` (`status`)
        )");
        
        $this->db->exec("CREATE TABLE IF NOT EXISTS `momo_webhooks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `reference_id` VARCHAR(100),
            `payload` TEXT,
            `processed` TINYINT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
    
    public function getDB() { return $this->db; }
}

// Initialize
$momo_db = new MoMoDatabase();
$mtn_api = new MTNMoMoAPI($mtn_config);

// ============================================
// ADMIN CONFIGURATION PAGE
// ============================================
function momo_admin_page() {
    global $momo_config_obj, $mtn_config, $mtn_api;
    
    $message = "";
    $error = "";
    
    // Handle form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST["save_settings"])) {
            $settings = [
                "mode" => $_POST["mode"] ?? "sandbox",
                "subscription_key" => trim($_POST["subscription_key"] ?? ""),
                "api_user" => trim($_POST["api_user"] ?? ""),
                "api_key" => trim($_POST["api_key"] ?? ""),
                "callback_host" => trim($_POST["callback_host"] ?? ""),
                "currency" => $_POST["currency"] ?? "SSP",
                "currency_symbol" => $_POST["currency_symbol"] ?? "£",
                "target_environment" => $_POST["target_environment"] ?? "mtnsouthsudan",
                "sandbox_base_url" => $_POST["sandbox_base_url"] ?? "https://sandbox.momodeveloper.mtn.com",
                "live_base_url" => $_POST["live_base_url"] ?? "https://api.mtn.com/v1"
            ];
            
            $momo_config_obj->saveAll($settings);
            $mtn_config = $momo_config_obj->getAll();
            $message = "✅ Settings saved successfully!";
        }
        
        if (isset($_POST["test_connection"])) {
            $mtn_api = new MTNMoMoAPI($momo_config_obj->getAll());
            if ($mtn_api->testConnection()) {
                $message = "✅ Connection successful! API credentials are valid.";
            } else {
                $error = "❌ Connection failed. Please check your API credentials.";
            }
        }
    }
    
    // Get current config
    $config = $momo_config_obj->getAll();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>MTN MoMo Configuration</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                background: #f0f2f5;
                padding: 20px;
            }
            .container { max-width: 800px; margin: 0 auto; }
            .card { 
                background: white; 
                border-radius: 12px; 
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                margin-bottom: 20px;
                overflow: hidden;
            }
            .card-header { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px 25px;
            }
            .card-header h1 { font-size: 24px; margin-bottom: 5px; }
            .card-header p { opacity: 0.9; font-size: 14px; }
            .card-body { padding: 25px; }
            .form-group { margin-bottom: 20px; }
            label { 
                display: block; 
                margin-bottom: 8px; 
                font-weight: 600; 
                color: #333;
                font-size: 14px;
            }
            label .required { color: #e74c3c; }
            input, select {
                width: 100%;
                padding: 12px;
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                font-size: 14px;
                transition: border-color 0.3s;
            }
            input:focus, select:focus {
                outline: none;
                border-color: #667eea;
            }
            input[readonly] {
                background: #f8f9fa;
                cursor: not-allowed;
            }
            .help-text {
                font-size: 12px;
                color: #666;
                margin-top: 5px;
            }
            .help-text a { color: #667eea; text-decoration: none; }
            .btn {
                padding: 12px 24px;
                border: none;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
            }
            .btn-primary {
                background: #667eea;
                color: white;
            }
            .btn-primary:hover {
                background: #5a67d8;
                transform: translateY(-1px);
            }
            .btn-secondary {
                background: #48bb78;
                color: white;
            }
            .btn-secondary:hover {
                background: #38a169;
            }
            .btn-danger {
                background: #e74c3c;
                color: white;
            }
            .btn-group {
                display: flex;
                gap: 10px;
                margin-top: 20px;
            }
            .message {
                padding: 12px 15px;
                border-radius: 8px;
                margin-bottom: 20px;
            }
            .message-success {
                background: #c6f6d5;
                color: #22543d;
                border-left: 4px solid #38a169;
            }
            .message-error {
                background: #fed7d7;
                color: #742a2a;
                border-left: 4px solid #e53e3e;
            }
            .info-box {
                background: #ebf8ff;
                padding: 15px;
                border-radius: 8px;
                margin-top: 20px;
            }
            .info-box h4 { margin-bottom: 10px; color: #2c5282; }
            .info-box code {
                background: #fff;
                padding: 2px 6px;
                border-radius: 4px;
                font-family: monospace;
            }
            hr {
                margin: 20px 0;
                border: none;
                border-top: 1px solid #e0e0e0;
            }
            .status-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
            }
            .status-sandbox { background: #fef5e7; color: #e67e22; }
            .status-live { background: #e8f8f5; color: #27ae60; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h1>📱 MTN MoMo Payment Gateway</h1>
                    <p>Configure your MTN Mobile Money API settings</p>
                </div>
                <div class="card-body">
                    
                    <?php if ($message): ?>
                        <div class="message message-success"><?php echo $message; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="message message-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <!-- Mode Selection -->
                        <div class="form-group">
                            <label>🔧 Operation Mode <span class="required">*</span></label>
                            <select name="mode" id="mode">
                                <option value="sandbox" <?php echo $config["mode"] == "sandbox" ? "selected" : ""; ?>>Sandbox (Testing)</option>
                                <option value="live" <?php echo $config["mode"] == "live" ? "selected" : ""; ?>>Live (Production)</option>
                            </select>
                            <div class="help-text">Use Sandbox for testing, Live for real transactions</div>
                        </div>
                        
                        <!-- API Credentials -->
                        <div class="form-group">
                            <label>🔑 Subscription Key <span class="required">*</span></label>
                            <input type="text" name="subscription_key" value="<?php echo htmlspecialchars($config["subscription_key"]); ?>" 
                                   placeholder="Enter your MTN Subscription Key">
                            <div class="help-text">Found in MTN Developer Portal → Products → Collections</div>
                        </div>
                        
                        <div class="form-group">
                            <label>👤 API User ID (UUID) <span class="required">*</span></label>
                            <input type="text" name="api_user" value="<?php echo htmlspecialchars($config["api_user"]); ?>" 
                                   placeholder="例: 3fa85f64-5717-4562-b3fc-2c963f66afa6">
                            <div class="help-text">Create an API user in MTN Developer Portal</div>
                        </div>
                        
                        <div class="form-group">
                            <label>🔐 API Key <span class="required">*</span></label>
                            <input type="password" name="api_key" value="<?php echo htmlspecialchars($config["api_key"]); ?>" 
                                   placeholder="Enter your API Key">
                            <div class="help-text">Generated after creating API user</div>
                        </div>
                        
                        <!-- Endpoint Settings -->
                        <div class="form-group">
                            <label>🌐 Callback URL</label>
                            <input type="url" name="callback_host" value="<?php echo htmlspecialchars($config["callback_host"]); ?>">
                            <div class="help-text">Your server URL for webhook notifications</div>
                        </div>
                        
                        <div class="form-group">
                            <label>🎯 Target Environment</label>
                            <input type="text" name="target_environment" value="<?php echo htmlspecialchars($config["target_environment"]); ?>">
                            <div class="help-text">例: mtnsouthsudan, mtnuganda, mtncameroon</div>
                        </div>
                        
                        <!-- Currency Settings -->
                        <hr>
                        <h3 style="margin-bottom: 15px;">💱 Currency Settings</h3>
                        
                        <div class="form-group">
                            <label>Currency Code</label>
                            <input type="text" name="currency" value="<?php echo htmlspecialchars($config["currency"]); ?>" maxlength="3">
                            <div class="help-text">例: SSP, UGX, XAF</div>
                        </div>
                        
                        <div class="form-group">
                            <label>Currency Symbol</label>
                            <input type="text" name="currency_symbol" value="<?php echo htmlspecialchars($config["currency_symbol"]); ?>" maxlength="5">
                            <div class="help-text">例: £, ₦, Fr</div>
                        </div>
                        
                        <!-- Advanced Settings -->
                        <hr>
                        <h3 style="margin-bottom: 15px;">⚙️ Advanced Settings</h3>
                        
                        <div class="form-group">
                            <label>Sandbox API URL</label>
                            <input type="url" name="sandbox_base_url" value="<?php echo htmlspecialchars($config["sandbox_base_url"]); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Live API URL</label>
                            <input type="url" name="live_base_url" value="<?php echo htmlspecialchars($config["live_base_url"]); ?>">
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" name="save_settings" class="btn btn-primary">💾 Save Settings</button>
                            <button type="submit" name="test_connection" class="btn btn-secondary">🔌 Test Connection</button>
                        </div>
                    </form>
                    
                    <div class="info-box">
                        <h4>📖 How to Get MTN API Credentials</h4>
                        <ol style="margin-left: 20px; line-height: 1.6;">
                            <li>Sign up at <a href="https://momodeveloper.mtn.com" target="_blank">MTN Developer Portal</a></li>
                            <li>Subscribe to the <strong>Collections</strong> product</li>
                            <li>Copy your <strong>Primary Key</strong> (this is your Subscription Key)</li>
                            <li>Create an API User (generates a UUID)</li>
                            <li>Generate an API Key for that user</li>
                            <li>Save all credentials in this form</li>
                        </ol>
                    </div>
                    
                    <div class="info-box" style="margin-top: 15px; background: #fef5e7;">
                        <h4>🧪 Sandbox Test Numbers</h4>
                        <p>Use these phone numbers in Sandbox mode:</p>
                        <code>912345678</code> - Successful payment<br>
                        <code>923456789</code> - Failed payment<br>
                        <code>934567890</code> - Pending payment
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

// ============================================
// REQUIRED GATEWAY FUNCTIONS
// ============================================
function mtn_momo_config() {
    global $mtn_config;
    return [
        "name" => "MTN Mobile Money",
        "display_name" => "MTN MoMo (SSP)",
        "version" => "2.0.0",
        "currency" => $mtn_config["currency"],
        "symbol" => $mtn_config["currency_symbol"],
        "description" => "Pay with MTN Mobile Money",
        "config_url" => "?_route=mtn_momo_admin"
    ];
}

function mtn_momo_pay($gateway, $invoice, $customer) {
    return "?_route=mtn_momo_pay&invoice=" . $invoice["invoice_id"];
}

function mtn_momo_callback() {
    $invoice_id = $_GET["invoice"] ?? "";
    $status = $_GET["status"] ?? "failed";
    
    if ($status == "success" && function_exists("payment_success")) {
        payment_success($invoice_id, $_GET["transaction_id"] ?? "");
    }
    
    header("Location: index.php?route=invoice/view&id=" . $invoice_id . 
           "&payment=" . ($status == "success" ? "success" : "failed"));
    exit;
}

// ============================================
// WEBHOOK HANDLER
// ============================================
function mtn_momo_webhook() {
    global $momo_db;
    
    $payload = file_get_contents("php://input");
    $data = json_decode($payload, true);
    
    $db = $momo_db->getDB();
    $stmt = $db->prepare("INSERT INTO momo_webhooks (reference_id, payload) VALUES (?, ?)");
    $stmt->execute([$data["referenceId"] ?? null, $payload]);
    
    if (isset($data["status"]) && $data["status"] == "SUCCESSFUL") {
        $stmt = $db->prepare("SELECT invoice_id FROM momo_transactions WHERE mtn_reference = ?");
        $stmt->execute([$data["referenceId"]]);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tx && function_exists("payment_success")) {
            payment_success($tx["invoice_id"], $data["transactionId"] ?? "");
        }
        
        $stmt = $db->prepare("UPDATE momo_transactions SET status = 'success', transaction_id = ? WHERE mtn_reference = ?");
        $stmt->execute([$data["transactionId"] ?? "", $data["referenceId"]]);
    }
    
    http_response_code(200);
    echo json_encode(["status" => "ok"]);
}

// ============================================
// ROUTE: PAYMENT FORM
// ============================================
function momo_payment_form() {
    global $mtn_config, $momo_db, $mtn_api;
    
    $invoice_id = $_GET["invoice"] ?? "";
    $error = "";
    $success = "";
    $amount = 0;
    
    // Get invoice details
    if (function_exists("get_invoice")) {
        $invoice = get_invoice($invoice_id);
        $amount = $invoice["total"] ?? 0;
    }
    
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["phone"])) {
        $phone = trim($_POST["phone"]);
        
        if (empty($phone)) {
            $error = "Please enter your MTN phone number";
        } elseif ($mtn_config["mode"] == "sandbox") {
            // Sandbox simulation
            $clean_phone = ltrim($phone, "0");
            $test_numbers = ["912345678" => "success", "923456789" => "failed", "934567890" => "pending"];
            $status = $test_numbers[$clean_phone] ?? "success";
            $ref = "MOMO_" . time() . "_" . rand(1000, 9999);
            
            $db = $momo_db->getDB();
            $stmt = $db->prepare("INSERT INTO momo_transactions (invoice_id, phone, amount, reference_id, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$invoice_id, $phone, $amount, $ref]);
            
            header("Location: ?_route=mtn_momo_callback&invoice=" . $invoice_id . 
                   "&status=" . $status . "&transaction_id=" . $ref);
            exit;
        } else {
            // Live API call
            $external_id = $invoice_id . "_" . time();
            $callback_url = $mtn_config["callback_host"] . "/?_route=mtn_momo_webhook";
            
            $result = $mtn_api->requestPayment($phone, $amount, $external_id, $callback_url);
            
            if ($result["success"]) {
                $db = $momo_db->getDB();
                $stmt = $db->prepare("INSERT INTO momo_transactions (invoice_id, phone, amount, reference_id, mtn_reference, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$invoice_id, $phone, $amount, $external_id, $result["reference_id"], "pending"]);
                
                $success = "Payment request sent! Check your phone for the prompt.";
                
                // Poll for status
                sleep(5);
                $status = $mtn_api->checkTransactionStatus($result["reference_id"]);
                if (isset($status["status"]) && $status["status"] == "SUCCESSFUL") {
                    if (function_exists("payment_success")) {
                        payment_success($invoice_id, $status["transactionId"] ?? "");
                    }
                    header("Location: ?_route=mtn_momo_callback&invoice=" . $invoice_id . "&status=success");
                    exit;
                }
            } else {
                $error = $result["message"] ?? "Payment initiation failed. Please try again.";
            }
        }
    }
    
    // Display form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>MTN Mobile Money Payment</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 20px;
            }
            .card { 
                background: white; 
                border-radius: 20px; 
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                max-width: 450px; 
                width: 100%; 
                padding: 40px;
            }
            .logo { text-align: center; margin-bottom: 30px; }
            .logo h1 { color: #ffb300; font-size: 32px; margin-bottom: 5px; }
            .logo p { color: #666; font-size: 14px; }
            .details { background: #f8f9fa; padding: 20px; border-radius: 12px; margin-bottom: 25px; text-align: center; }
            .amount { font-size: 32px; font-weight: bold; color: #28a745; margin-top: 10px; }
            .input-group { margin-bottom: 20px; }
            label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
            input { 
                width: 100%; 
                padding: 14px; 
                border: 2px solid #e0e0e0; 
                border-radius: 10px; 
                font-size: 16px;
                transition: border-color 0.3s;
            }
            input:focus { outline: none; border-color: #ffb300; }
            button { 
                width: 100%; 
                padding: 14px; 
                background: #ffb300; 
                color: white; 
                border: none; 
                border-radius: 10px; 
                font-size: 18px; 
                font-weight: bold;
                cursor: pointer;
                transition: transform 0.2s, background 0.2s;
            }
            button:hover { background: #e6a000; transform: translateY(-2px); }
            .error { background: #fee; color: #c00; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #c00; }
            .success { background: #efe; color: #0a0; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #0a0; }
            .info { font-size: 12px; color: #999; text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
            .badge { display: inline-block; background: #f0f0f0; padding: 5px 10px; border-radius: 20px; font-size: 12px; margin-top: 10px; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="logo">
                <h1>MTN MoMo</h1>
                <p>Mobile Money Payment</p>
            </div>
            
            <div class="details">
                <strong>Invoice #<?php echo htmlspecialchars($invoice_id); ?></strong>
                <div class="amount"><?php echo $mtn_config["currency_symbol"]; ?> <?php echo number_format($amount, 2); ?></div>
                <?php if ($mtn_config["mode"] == "sandbox"): ?>
                    <div class="badge">🔬 SANDBOX MODE</div>
                <?php endif; ?>
            </div>
            
            <?php if ($error): ?>
                <div class="error">⚠️ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success">✓ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="input-group">
                    <label>📱 MTN Mobile Money Number</label>
                    <input type="tel" name="phone" placeholder="例: 912345678" required>
                    <small style="color: #666; display: block; margin-top: 5px;">Enter the number registered with MTN Mobile Money</small>
                </div>
                <button type="submit">💳 Pay Now</button>
            </form>
            
            <div class="info">
                <p>✔️ Instant payment confirmation<br>
                ✔️ Secure transaction processing<br>
                ✔️ 24/7 customer support</p>
            </div>
        </div>
    </body>
    </html>
    <?php
}

// ============================================
// ROUTE HANDLING
// ============================================
$route = $_GET["_route"] ?? "";

if ($route == "mtn_momo_admin") {
    momo_admin_page();
    exit;
}

if ($route == "mtn_momo_pay") {
    momo_payment_form();
    exit;
}

if ($route == "mtn_momo_callback") {
    mtn_momo_callback();
    exit;
}

if ($route == "mtn_momo_webhook") {
    mtn_momo_webhook();
    exit;
}

// ============================================
// REGISTER GATEWAY
// ============================================
return [
    "name" => "MTN MoMo (SSP)",
    "display_name" => "MTN Mobile Money - South Sudan",
    "version" => "2.0.0",
    "currency" => $mtn_config["currency"],
    "description" => "Pay instantly with MTN Mobile Money",
    "config_function" => "mtn_momo_config",
    "payment_function" => "mtn_momo_pay",
    "callback_function" => "mtn_momo_callback",
    "webhook_function" => "mtn_momo_webhook"
];