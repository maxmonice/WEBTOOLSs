<?php
/**
 * payment-success-callback.php
 * 
 * This page is used as the success_redirect_url for Xendit Invoices (GCash).
 * When loaded inside an iframe, it signals the parent window to complete the order.
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Successful</title>
    <style>
        body { 
            background: #1a1a1a; 
            color: #fff; 
            font-family: 'Be Vietnam Pro', sans-serif; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            margin: 0; 
            text-align: center;
        }
        .loader {
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top: 4px solid #C22626;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div>
        <div class="loader"></div>
        <h2 style="font-family: 'Aclonica', sans-serif;">Payment Verified!</h2>
        <p style="opacity: 0.8;">Syncing with Luke's Seafood...</p>
    </div>

    <script>
        const isIframe = (window.self !== window.top);
        
        if (isIframe) {
            // SCENARIO A: Still in the floating window
            setTimeout(() => {
                try {
                    window.parent.postMessage({ status: 'VERIFIED', source: 'gcash' }, '*');
                    console.log('✅ Success signal sent to parent');
                } catch (e) {
                    console.error('❌ Failed to signal parent:', e);
                }
            }, 1500);
        } else {
            // SCENARIO B: GCash forced a full-page redirect
            // We jump back to the menu with the success flag
            const urlParams = new URLSearchParams(window.location.search);
            const orderId = urlParams.get('order_id') || localStorage.getItem('order_id') || '';
            
            setTimeout(() => {
                window.location.href = 'menu.php?order_success=true&order_id=' + orderId;
            }, 1000);
        }
    </script>
</body>
</html>
