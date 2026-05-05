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
            <div class="amount"><?php echo $momo_config['currency_symbol'] . number_format($payment['amount'], 2); ?></div>
            <div>Invoice: <?php echo htmlspecialchars($payment['invoice_id']); ?></div>
        </div>
        
        <form id="paymentForm">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($payment['customer_name'] ?? ''); ?>" required>
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
                <input type="email" id="email" name="email" placeholder="your@email.com" value="<?php echo htmlspecialchars($payment['customer_email'] ?? ''); ?>">
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
                        invoice_id: '<?php echo $payment['invoice_id']; ?>',
                        reference: '<?php echo $payment['reference']; ?>',
                        name: formData.get('name'),
                        phone: phone,
                        email: formData.get('email'),
                        amount: '<?php echo $payment['amount']; ?>'
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
