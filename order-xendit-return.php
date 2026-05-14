<?php
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$failed = ($_GET['payment'] ?? '') === 'failed';
$presentationDemo = ($_GET['demo'] ?? '') === '1';

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $failed ? 'Payment interrupted' : 'Thanks for your order!' ?> · Luke's Seafood</title>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#0f1419; --card:#1a222c; --text:#eaeef2; --muted:#93a4b8; --accent:#2d9cdb; --bad:#e04f5f;}
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:'Be Vietnam Pro',sans-serif; background: radial-gradient(circle at 20% 20%, #1f2d3d 0, transparent 40%), radial-gradient(circle at 80% 80%, #172028 0, transparent 45%), var(--bg); color:var(--text); padding:24px;}
        .box { max-width:420px; width:100%; background:var(--card); padding:28px 24px 24px; border-radius:14px; box-shadow:0 20px 50px rgba(0,0,0,.35); border:1px solid rgba(255,255,255,.06);}
        h1 { font-size:1.35rem; margin:0 0 12px;}
        p { margin:0 0 10px; line-height:1.55; color:var(--muted); font-size:0.94rem;}
        .id { font-weight:700; color:var(--accent);}
        .actions { margin-top:20px;}
        a.btn { display:inline-block; padding:12px 18px; background:var(--accent); color:#fff; border-radius:8px; text-decoration:none; font-weight:600; font-size:0.9rem;}
        a.btn:hover { filter:brightness(1.08);}
        <?= $failed ? '.warn{color:var(--bad);font-weight:600;} ' : ''; ?>
        .demo-ribbon{background:#eab308;color:#1a1400;font-size:.8rem;text-align:center;padding:10px 14px;margin:-28px -24px 18px;font-weight:700;border-radius:10px 10px 0 0;line-height:1.35;}
    </style>
</head>
<body>
    <div class="box">
        <?php if ($presentationDemo && !$failed): ?>
            <div class="demo-ribbon">Presentation demo — no real payment</div>
        <?php endif; ?>
        <?php if ($failed): ?>
            <h1>Payment not completed</h1>
            <p class="warn">If you cancelled or closed the checkout page, your cart is unchanged until you finish payment.</p>
        <?php else: ?>
            <h1>Order received</h1>
            <?php if ($orderId > 0): ?>
                <?php if ($presentationDemo): ?>
                    <p>Simulated checkout complete. Demo order reference: <span class="id">#<?= htmlspecialchars((string)$orderId, ENT_QUOTES, 'UTF-8') ?></span>.</p>
                    <p>For a real deployment you would return here after paying on Xendit’s hosted page.</p>
                <?php else: ?>
                    <p>If your payment succeeded, your order reference is <span class="id">#<?= htmlspecialchars((string)$orderId, ENT_QUOTES, 'UTF-8') ?></span>. You’ll get confirmation from Xendit by email.</p>
                    <p>We’ll start preparing once the payment clears in our system.</p>
                <?php endif; ?>
            <?php else: ?>
                <p>You’re almost done — we couldn’t determine the order number from the link.</p>
            <?php endif; ?>
        <?php endif; ?>
        <div class="actions">
            <a class="btn" href="menu.php">← Back to menu</a>
        </div>
    </div>
    <script>
        (function () {
            <?php if (!$failed && $orderId > 0): ?>
            try {
                localStorage.removeItem('cart');
                if (typeof window.updateCartCount === 'function') {
                    window.updateCartCount();
                }
                localStorage.setItem('order_pending', 'true');
                localStorage.setItem('order_id', '<?= (int)$orderId ?>');
            } catch (e) {}
            <?php endif; ?>
        })();
    </script>
</body>
</html>


