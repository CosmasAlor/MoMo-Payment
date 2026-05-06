# MTN MoMo Payment Gateway - Deployment Guide

## Quick Deployment

### Step 1: Copy Files to PHPNuxBill
```bash
# Copy gateway files to PHPNuxBill system
cp mtn.php /var/www/html/system/paymentgateway/
cp mtn.tpl /var/www/html/system/paymentgateway/ui/
cp mtn_currency.json /var/www/html/system/paymentgateway/
cp mtn_connection_test.php /var/www/html/system/paymentgateway/

# Set proper permissions
chmod 644 /var/www/html/system/paymentgateway/mtn.php
chmod 644 /var/www/html/system/paymentgateway/ui/mtn.tpl
chmod 644 /var/www/html/system/paymentgateway/mtn_currency.json
chmod 644 /var/www/html/system/paymentgateway/mtn_connection_test.php
```

### Step 2: Configure Gateway
1. Access PHPNuxBill admin panel
2. Navigate to Settings → Payment Gateways
3. Select "MTN MoMo" from the list
4. Configure the following settings:

#### Required Configuration
- **Environment**: Sandbox (testing) or Live (production)
- **API User ID**: UUID from MTN Developer Portal
- **Collection Subscription Key**: Primary subscription key
- **API Key**: Generated API key
- **Callback URL**: Webhook endpoint URL

#### Optional Configuration
- **Payment Timeout**: 60-300 seconds (default: 60)
- **Webhook Secret**: Secret for webhook verification
- **Auto-Credit Internet**: Enable automatic package activation
- **Enable SMS Receipts**: Send confirmation SMS to customers

### Step 3: Test Connection
1. Click "Test Connection" button in admin panel
2. Verify successful API connection
3. Test with sandbox numbers before going live

## File Structure After Deployment

```
/var/www/html/system/paymentgateway/
├── mtn.php                    # Main gateway file
├── ui/
│   └── mtn.tpl               # Admin configuration template
├── mtn_currency.json         # Currency configuration
└── mtn_connection_test.php   # Connection test handler
```

## Configuration Details

### MTN Developer Portal Setup

1. **Register Account**
   - Visit: https://momodeveloper.mtn.com/
   - Create developer account
   - Select South Sudan as country

2. **Create API Product**
   - Product Type: Collection
   - Callback URL: `https://your-domain.com/system/paymentgateway/mtn_connection_test.php`
   - Environment: Start with Sandbox

3. **Get Credentials**
   - API User ID (UUID format)
   - Collection Subscription Key (Primary Key)
   - API Key (Generated)

### Test Environment Details

#### Sandbox Test Numbers
- **912345678** → Successful payment
- **923456789** → Failed payment
- **934567890** → Pending payment
- **Test PIN**: 12345

#### API Endpoints
- **Sandbox**: `https://sandbox.momodeveloper.mtn.com`
- **Live**: `https://momodeveloper.mtn.com`
- **Token API**: `/collection/token/`
- **Collection API**: `/collection/v1_0/requesttopay`

## Payment Flow

### Customer Experience
1. Customer selects package and chooses MTN MoMo payment
2. Redirected to MTN MoMo payment page
3. Enters phone number (+211 9XXXXXXXX)
4. Receives USSD prompt on phone
5. Enters PIN to confirm payment
6. Payment processed and package activated

### Technical Flow
1. System creates transaction with reference
2. Customer redirected to payment page
3. Payment request sent to MTN API
4. Customer receives USSD prompt
5. Payment confirmation via webhook
6. System activates package automatically

## Security Considerations

### Webhook Security
- Use HTTPS for callback URLs
- Implement webhook secret verification
- Validate all incoming requests
- Log all webhook events

### API Security
- Store credentials securely in database
- Use environment-specific keys
- Implement rate limiting
- Monitor API usage

## Troubleshooting

### Common Issues

#### "Payment Gateway Not Found"
- Ensure `mtn.php` is in correct directory
- Check file permissions
- Verify PHP syntax errors
- Check PHPNuxBill logs

#### Connection Test Failed
- Verify API credentials
- Check network connectivity
- Validate subscription key
- Ensure correct environment (sandbox/live)

#### Payment Not Processing
- Check webhook URL accessibility
- Verify phone number format (+211 9XXXXXXXX)
- Check MTN service status
- Review error logs

### Debug Information
Enable debug mode by adding to `mtn.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Production Checklist

### Before Going Live
- [ ] Test all payment scenarios
- [ ] Verify webhook connectivity
- [ ] Check SSL certificate
- [ ] Monitor error logs
- [ ] Set up monitoring alerts
- [ ] Backup configuration

### Post-Deployment
- [ ] Monitor transaction success rates
- [ ] Check webhook response times
- [ ] Review customer feedback
- [ ] Update documentation
- [ ] Schedule regular security audits

## Support

### Technical Support
- **Documentation**: Check `CODING_STANDARDS.md`
- **Error Logs**: `/var/log/phpnuxbill/`
- **Database**: Check `tbl_payment_gateway` table

### MTN Support
- **Developer Portal**: https://momodeveloper.mtn.com/
- **API Documentation**: Available in portal
- **Support Contact**: Through developer portal

## Updates and Maintenance

### Version Updates
1. Backup current configuration
2. Download new version files
3. Replace gateway files
4. Test functionality
5. Update documentation

### Regular Maintenance
- Monitor API changes
- Update security patches
- Review performance metrics
- Backup configuration data

---

**Note**: Always test thoroughly in sandbox environment before deploying to production.
