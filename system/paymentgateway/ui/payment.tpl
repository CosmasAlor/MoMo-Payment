<div class="box">
    <div class="box-header">
        <h3 class="box-title">MTN MoMo Payment Gateway Configuration</h3>
    </div>
    <div class="box-body">
        <form method="post" action="">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="mtnmomo_environment">Environment</label>
                        <select name="mtnmomo_environment" id="mtnmomo_environment" class="form-control">
                            <option value="sandbox" {if $env eq 'sandbox'}selected{/if}>Sandbox (Testing)</option>
                            <option value="live" {if $env eq 'live'}selected{/if}>Live (Production)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="mtnmomo_api_user_id">API User ID</label>
                        <input type="text" name="mtnmomo_api_user_id" id="mtnmomo_api_user_id" class="form-control" value="{$api_user_id}" placeholder="UUID from MTN Developer Portal">
                    </div>
                    
                    <div class="form-group">
                        <label for="mtnmomo_collection_subscription_key">Collection Subscription Key</label>
                        <input type="text" name="mtnmomo_collection_subscription_key" id="mtnmomo_collection_subscription_key" class="form-control" value="{$collection_subscription_key}" placeholder="Primary Key from Developer Portal">
                    </div>
                    
                    <div class="form-group">
                        <label for="mtnmomo_api_key">API Key</label>
                        <input type="text" name="mtnmomo_api_key" id="mtnmomo_api_key" class="form-control" value="{$api_key}" placeholder="Generated API Key">
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="mtnmomo_callback_url">Callback URL</label>
                        <input type="text" name="mtnmomo_callback_url" id="mtnmomo_callback_url" class="form-control" value="{$callback_url}" placeholder="Webhook callback URL">
                    </div>
                    
                    <div class="form-group">
                        <label for="mtnmomo_currency">Currency</label>
                        <input type="text" name="mtnmomo_currency" id="mtnmomo_currency" class="form-control" value="{$currency}" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="mtnmomo_country_code">Country Code</label>
                        <input type="text" name="mtnmomo_country_code" id="mtnmomo_country_code" class="form-control" value="{$country_code}" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="mtnmomo_payment_timeout">Payment Timeout (seconds)</label>
                        <input type="number" name="mtnmomo_payment_timeout" id="mtnmomo_payment_timeout" class="form-control" value="{$payment_timeout}" min="30" max="300">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="mtnmomo_webhook_secret">Webhook Secret</label>
                        <input type="password" name="mtnmomo_webhook_secret" id="mtnmomo_webhook_secret" class="form-control" value="{$webhook_secret}" placeholder="Secret for webhook verification">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-save"></i> Save Settings
                </button>
                <a href="{$_url}order/view_mtnmomo_transactions" class="btn btn-info">
                    <i class="fa fa-list"></i> View Transactions
                </a>
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
            </ul>
        </div>
    </div>
</div>
