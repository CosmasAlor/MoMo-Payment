<?php
/**
 * MTN MoMo Payment Gateway Plugin Initialization
 * Plugin Name: MTN MoMo (SSP) Plugin
 * Version: 1.0
 * Description: Plugin initialization for MTN MoMo payment gateway in PHPNuxBill
 * Author: PHPNuxBill Community
 * License: MIT
 */

// Prevent direct access
if (!defined('IN_PHPNUXBILL')) {
    exit('Direct access denied');
}

// ============================================
// PLUGIN INITIALIZATION
// ============================================
function initiatemtn_plugin() {
    // Register the MTN MoMo payment gateway
    // This will make PHPNuxBill automatically discover the gateway
    // by including the paymentgateway/mtn.php file
    
    // Check if gateway file exists
    $gateway_file = __DIR__ . '/../paymentgateway/mtn.php';
    if (!file_exists($gateway_file)) {
        error_log("MTN MoMo Gateway file not found: " . $gateway_file);
        return false;
    }
    
    // Create plugin activation record
    try {
        // Log plugin activation
        error_log("MTN MoMo Payment Gateway plugin initialized successfully");
        
        // Return success
        return [
            'status' => 'success',
            'message' => 'MTN MoMo Gateway plugin initialized',
            'version' => '1.0',
            'gateway_file' => $gateway_file
        ];
        
    } catch (Exception $e) {
        error_log("MTN MoMo Plugin initialization error: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

// ============================================
// PLUGIN HOOKS
// ============================================
function initiatemtn_add_hooks() {
    // Add admin menu item for MTN MoMo settings
    if (function_exists('_add_hook')) {
        _add_hook('admin.menu', function($menu) {
            $menu[] = [
                'name' => 'MTN MoMo Settings',
                'icon' => 'fa-money',
                'link' => U . 'order/gateway/mtn',
                'target' => '_blank'
            ];
            return $menu;
        });
        
        // Add dashboard widget
        _add_hook('admin.dashboard.widgets', function() {
            return '<div class="box">
                <div class="box-header">
                    <h3 class="box-title">MTN MoMo (SSP)</h3>
                </div>
                <div class="box-body">
                    <p>South Sudan Mobile Money Gateway</p>
                    <a href="' . U . 'order/gateway/mtn" class="btn btn-primary btn-sm">Configure</a>
                </div>
            </div>';
        });
    }
}

// ============================================
// PLUGIN INSTALLATION
// ============================================
function initiatemtn_install() {
    // Create necessary database tables for plugin
    $gateway_file = __DIR__ . '/../paymentgateway/mtn.php';
    
    if (file_exists($gateway_file)) {
        // Include gateway file to trigger database table creation
        include_once $gateway_file;
        
        error_log("MTN MoMo Gateway installation completed");
        return true;
    }
    
    return false;
}

// ============================================
// PLUGIN UNINSTALLATION
// ============================================
function initiatemtn_uninstall() {
    // Clean up plugin data
    try {
        // Remove plugin settings if needed
        // Keep payment records for audit trail
        
        error_log("MTN MoMo Gateway plugin uninstalled");
        return true;
        
    } catch (Exception $e) {
        error_log("MTN MoMo Plugin uninstallation error: " . $e->getMessage());
        return false;
    }
}

// ============================================
// PLUGIN ACTIVATION
// ============================================
if (php_sapi_name() !== 'cli') {
    // Auto-initialize plugin when included
    $init_result = initiatemtn_plugin();
    
    if ($init_result && $init_result['status'] === 'success') {
        initiatemtn_add_hooks();
    }
}

// ============================================
// COMMAND LINE INSTALLATION
// ============================================
if (php_sapi_name() === 'cli') {
    if (isset($argv[1])) {
        switch ($argv[1]) {
            case 'install':
                echo "Installing MTN MoMo Payment Gateway Plugin...\n";
                if (initiatemtn_install()) {
                    echo "✓ Plugin installation completed successfully!\n";
                } else {
                    echo "✗ Plugin installation failed!\n";
                }
                break;
                
            case 'uninstall':
                echo "Uninstalling MTN MoMo Payment Gateway Plugin...\n";
                if (initiatemtn_uninstall()) {
                    echo "✓ Plugin uninstalled successfully!\n";
                } else {
                    echo "✗ Plugin uninstallation failed!\n";
                }
                break;
                
            case 'status':
                echo "MTN MoMo Payment Gateway Plugin Status:\n";
                echo "Version: 1.0\n";
                echo "Status: Active\n";
                echo "Gateway File: " . __DIR__ . "/../paymentgateway/mtn.php\n";
                break;
                
            default:
                echo "Usage: php initiatemtn.php [install|uninstall|status]\n";
                break;
        }
    }
}

?>
