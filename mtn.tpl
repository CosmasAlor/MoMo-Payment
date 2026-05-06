<div class="box">
    <div class="box-header">
        <h3 class="box-title">MTN MoMo Payment Gateway Configuration</h3>
    </div>
    <div class="box-body">
        <form method="post" action="">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="mtn_environment">Environment</label>
                        <select name="mtn_environment" id="mtn_environment" class="form-control">
                            <option value="sandbox" {if $env eq 'sandbox'}selected{/if}>Sandbox (Testing)</option>
                            <option value="live" {if $env eq 'live'}selected{/if}>Live (Production)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="mtn_api_user_id">API User ID</label>
                        <input type="text" name="mtn_api_user_id" id="mtn_api_user_id" class="form-control" value="{$api_user_id}" placeholder="UUID from MTN Developer Portal">
                    </div>
                    
                    <div class="form-group">
                        <label for="mtn_collection_subscription_key">Collection Subscription Key</label>
                        <input type="text" name="mtn_collection_subscription_key" id="mtn_collection_subscription_key" class="form-control" value="{$collection_subscription_key}" placeholder="Primary Key from Developer Portal">
                    </div>
                    
                    <div class="form-group">
                        <label for="mtn_api_key">API Key</label>
                        <input type="text" name="mtn_api_key" id="mtn_api_key" class="form-control" value="{$api_key}" placeholder="Generated API Key">
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="mtn_callback_url">Callback URL</label>
                        <input type="text" name="mtn_callback_url" id="mtn_callback_url" class="form-control" value="{$callback_url}" placeholder="Webhook callback URL">
                    </div>
                    
                    <div class="form-group">
                        <label for="mtn_currency">Currency</label>
                        <input type="text" name="mtn_currency" id="mtn_currency" class="form-control" value="{$currency}" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="mtn_country_code">Country Code</label>
                        <input type="text" name="mtn_country_code" id="mtn_country_code" class="form-control" value="{$country_code}" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="mtn_payment_timeout">Payment Timeout (seconds)</label>
                        <input type="number" name="mtn_payment_timeout" id="mtn_payment_timeout" class="form-control" value="{$payment_timeout}" min="30" max="300">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="mtn_webhook_secret">Webhook Secret</label>
                        <input type="password" name="mtn_webhook_secret" id="mtn_webhook_secret" class="form-control" value="{$webhook_secret}" placeholder="Secret for webhook verification">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="mtn_auto_credit">
                            <input type="checkbox" name="mtn_auto_credit" id="mtn_auto_credit" value="yes" {if $auto_credit eq 'yes'}checked{/if}>
                            Auto-Credit Internet
                        </label>
                        <small class="text-muted">Activate package immediately after payment</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="mtn_enable_sms">
                            <input type="checkbox" name="mtn_enable_sms" id="mtn_enable_sms" value="yes" {if $enable_sms eq 'yes'}checked{/if}>
                            Enable SMS Receipts
                        </label>
                        <small class="text-muted">Send confirmation SMS to customers</small>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-save"></i> Save Settings
                </button>
                <a href="{$_url}order/view_mtn_transactions" class="btn btn-info">
                    <i class="fa fa-list"></i> View Transactions
                </a>
                <button type="button" class="btn btn-success" onclick="testConnection()">
                    <i class="fa fa-plug"></i> Test Connection
                </button>
            </div>
        </form>
    </div>
</div>

<div class="box">
    <div class="box-header">
        <h3 class="box-title">Test Information</h3>
    </div>
    <div class="box-body">
        <div class="alert alert-info">
            <h4><i class="fa fa-info-circle"></i> Test Numbers (Sandbox Mode)</h4>
            <ul>
                <li><strong>912345678</strong> - Successful payment</li>
                <li><strong>923456789</strong> - Failed payment</li>
                <li><strong>934567890</strong> - Pending payment</li>
                <li><strong>Test PIN:</strong> 12345</li>
            </ul>
        </div>
        
        <div class="alert alert-warning">
            <h4><i class="fa fa-exclamation-triangle"></i> Important Notes</h4>
            <ul>
                <li>Phone format: +211 9XX XXX XXX (South Sudan)</li>
                <li>Ensure your callback URL is accessible from MTN servers</li>
                <li>Test in sandbox mode before going live</li>
                <li>Keep webhook secret secure and unique</li>
                <li>API User ID should be a UUID format</li>
                <li>Collection Subscription Key is the Primary Key from MTN Developer Portal</li>
            </ul>
        </div>
        
        <div class="alert alert-success">
            <h4><i class="fa fa-check-circle"></i> API Endpoints</h4>
            <ul>
                <li><strong>Sandbox:</strong> https://sandbox.momodeveloper.mtn.com</li>
                <li><strong>Live:</strong> https://momodeveloper.mtn.com</li>
                <li><strong>Collection API:</strong> /collection/v1_0/requesttopay</li>
                <li><strong>Token API:</strong> /collection/token/</li>
            </ul>
        </div>
    </div>
</div>

<script>
function testConnection() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Testing...';
    btn.disabled = true;
    
    fetch('{$_url}order/test_mtn_connection', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            api_user_id: document.getElementById('mtn_api_user_id').value,
            collection_subscription_key: document.getElementById('mtn_collection_subscription_key').value,
            environment: document.getElementById('mtn_environment').value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Connection test successful: ' + data.message);
        } else {
            alert('Connection test failed: ' + data.message);
        }
    })
    .catch(error => {
        alert('Connection test error: ' + error.message);
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}
</script>
