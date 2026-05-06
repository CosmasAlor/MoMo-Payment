{include file="sections/header.tpl"}

<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">
                <div class="panel-title">MTN Mobile Money (South Sudan) - Configuration</div>
            </div>
            <div class="panel-body">
                <form class="form-horizontal" method="post" role="form" action="{$_url}paymentgateway/mtnmomo">

                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">API Credentials</h4>
                        </div>
                        <div class="panel-body">
                            <div class="form-group">
                                <label class="col-md-3 control-label">Environment</label>
                                <div class="col-md-6">
                                    <select name="environment" class="form-control">
                                        <option value="sandbox" {if $env == 'sandbox'}selected{/if}>Sandbox (Testing)</option>
                                        <option value="live" {if $env == 'live'}selected{/if}>Live (Production)</option>
                                    </select>
                                    <span class="help-block">Use Sandbox for testing, Live for production payments.</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-md-3 control-label">API User ID (UUID)</label>
                                <div class="col-md-6">
                                    <input type="text" class="form-control" name="api_user_id" value="{$api_user_id}" placeholder="e.g. 123e4567-e89b-12d3-a456-426614174000">
                                    <span class="help-block">Your API User UUID from the MTN Developer Portal.</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-md-3 control-label">Collection Subscription Key</label>
                                <div class="col-md-6">
                                    <input type="text" class="form-control" name="collection_subscription_key" value="{$collection_subscription_key}" placeholder="Primary Key from Developer Portal">
                                    <span class="help-block">Primary or secondary key from the Collection product on MTN Developer Portal.</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-md-3 control-label">API Key</label>
                                <div class="col-md-6">
                                    <input type="password" class="form-control" name="api_key" value="{$api_key}" placeholder="Generated API Key">
                                    <span class="help-block">API Key generated from MTN Developer Portal.</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-md-3 control-label">Callback URL</label>
                                <div class="col-md-6">
                                    <input type="url" class="form-control" name="callback_url" value="{$callback_url}" placeholder="https://yourdomain.com/callback">
                                    <span class="help-block">URL for MTN MoMo to send payment notifications. Leave empty to use PHPNuxBill default callback.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">Payment Settings</h4>
                        </div>
                        <div class="panel-body">
                            <div class="form-group">
                                <label class="col-md-3 control-label">Currency</label>
                                <div class="col-md-6">
                                    <select name="currency" class="form-control">
                                        <option value="SSP" {if $currency == 'SSP'}selected{/if}>SSP - South Sudanese Pound (£)</option>
                                        <option value="EUR" {if $currency == 'EUR'}selected{/if}>EUR - Euro (€)</option>
                                        <option value="USD" {if $currency == 'USD'}selected{/if}>USD - US Dollar ($)</option>
                                        <option value="UGX" {if $currency == 'UGX'}selected{/if}>UGX - Ugandan Shilling</option>
                                        <option value="KES" {if $currency == 'KES'}selected{/if}>KES - Kenyan Shilling</option>
                                        <option value="GHS" {if $currency == 'GHS'}selected{/if}>GHS - Ghanaian Cedi</option>
                                        <option value="XAF" {if $currency == 'XAF'}selected{/if}>XAF - CFA Franc</option>
                                        <option value="RWF" {if $currency == 'RWF'}selected{/if}>RWF - Rwandan Franc</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-md-3 control-label">Country Code</label>
                                <div class="col-md-6">
                                    <input type="text" class="form-control" name="country_code" value="{$country_code}" placeholder="211">
                                    <span class="help-block">Phone country code without + (e.g. 211 for South Sudan, 256 for Uganda).</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-md-3 control-label">Auto-Credit Internet</label>
                                <div class="col-md-6">
                                    <select name="auto_credit" class="form-control">
                                        <option value="yes" {if $auto_credit == 'yes'}selected{/if}>Yes - Activate plan immediately</option>
                                        <option value="no" {if $auto_credit == 'no'}selected{/if}>No - Manual activation</option>
                                    </select>
                                    <span class="help-block">Automatically activate the customer's internet plan after successful payment.</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-md-3 control-label">Payment Timeout</label>
                                <div class="col-md-6">
                                    <select name="payment_timeout" class="form-control">
                                        <option value="30" {if $payment_timeout == '30'}selected{/if}>30 Seconds</option>
                                        <option value="60" {if $payment_timeout == '60'}selected{/if}>60 Seconds</option>
                                        <option value="90" {if $payment_timeout == '90'}selected{/if}>90 Seconds</option>
                                        <option value="120" {if $payment_timeout == '120'}selected{/if}>120 Seconds</option>
                                    </select>
                                    <span class="help-block">Maximum time to wait for customer to enter their MoMo PIN.</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-md-3 control-label">Webhook Secret</label>
                                <div class="col-md-6">
                                    <input type="text" class="form-control" name="webhook_secret" value="{$webhook_secret}" placeholder="Optional webhook secret key">
                                    <span class="help-block">Optional. Used to verify incoming webhook notifications from MTN MoMo.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-lg-offset-3 col-lg-6">
                            <button class="btn btn-primary waves-effect waves-light" type="submit">
                                <i class="fa fa-save"></i> Save Configuration
                            </button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-hovered mb20">
            <div class="panel-heading">
                <div class="panel-title">Setup Guide</div>
            </div>
            <div class="panel-body">
                <h5><strong>Step 1:</strong> Register at MTN MoMo Developer Portal</h5>
                <p>Visit <a href="https://momodeveloper.mtn.com" target="_blank">momodeveloper.mtn.com</a> and create an account.</p>

                <h5><strong>Step 2:</strong> Subscribe to Collection API</h5>
                <p>Subscribe to the Collection product and copy your Primary Key (Subscription Key).</p>

                <h5><strong>Step 3:</strong> Create API User & Generate API Key</h5>
                <p>Use the MTN Sandbox User Provisioning API to create an API User and generate an API Key.</p>

                <h5><strong>Step 4:</strong> Configure Callback URL</h5>
                <p>Set the callback URL in the MTN Developer Portal to receive payment notifications.</p>

                <h5><strong>Step 5:</strong> Test in Sandbox</h5>
                <p>Use Sandbox mode to test payments before going live. Use phone number <code>+211912345678</code> for successful test payments.</p>

                <h5><strong>Step 6:</strong> Switch to Live</h5>
                <p>Once testing is complete, change the environment to Live and update your API credentials with production keys.</p>
            </div>
        </div>
    </div>
</div>

{include file="sections/footer.tpl"}
