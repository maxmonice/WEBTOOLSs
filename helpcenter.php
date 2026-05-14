<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$faqs = [
    [
        'question' => 'How do I track my order?',
        'answer' => 'Open your account dashboard and check Track Your Order. Active orders show the latest status, delivery address, rider details when assigned, and the chat button when delivery is in progress.'
    ],
    [
        'question' => 'How do I book the seafood bar for multiple days?',
        'answer' => 'Go to Book Bar, select each event date on the calendar, then submit the form. Each selected date is checked for availability before the booking is saved.'
    ],
    [
        'question' => 'How do promos work?',
        'answer' => 'Claim a promo from Promos & Rewards in your account dashboard. When the promo applies to your selected menu item, the discounted price is shown in the item view and cart.'
    ],
    [
        'question' => 'Can I cancel or edit an event booking?',
        'answer' => 'Open My Event Bookings in your account dashboard. Pending and active bookings can be viewed, edited when allowed, or cancelled from the booking detail screen.'
    ],
    [
        'question' => 'How can I contact support?',
        'answer' => 'Use Contact Support in your account dashboard to open a chat with the Luke\'s support team. Admin and staff can view and reply to support messages.'
    ],
    [
        'question' => 'What delivery area is supported?',
        'answer' => 'Delivery and event locations are validated against Luke\'s service radius. If an address is too far, the form asks you to choose another location.'
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center - Luke's Seafood Trading</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body { background:#0d0d0d; color:#fff; font-family:'Be Vietnam Pro', sans-serif; }
        .help-wrap { max-width: 920px; margin: 110px auto 60px; padding: 0 20px; }
        .help-top { display:flex; justify-content:space-between; gap:18px; align-items:flex-end; margin-bottom:28px; }
        .help-top h1 { font-family:'Aclonica', sans-serif; font-size: clamp(2rem, 5vw, 3.2rem); margin:0; }
        .help-top p { color:rgba(255,255,255,0.62); margin-top:10px; line-height:1.6; max-width:620px; }
        .back-link { color:#fff; text-decoration:none; border:1px solid rgba(255,255,255,0.14); border-radius:9px; padding:10px 14px; white-space:nowrap; }
        .faq-list { display:grid; gap:12px; }
        .faq-item { background:rgba(255,255,255,0.035); border:1px solid rgba(255,255,255,0.08); border-radius:12px; overflow:hidden; }
        .faq-question { width:100%; border:0; background:transparent; color:#fff; display:flex; justify-content:space-between; align-items:center; gap:14px; padding:18px 20px; font:inherit; font-weight:800; text-align:left; cursor:pointer; }
        .faq-answer { display:none; padding:0 20px 18px; color:rgba(255,255,255,0.68); line-height:1.65; }
        .faq-item.open .faq-answer { display:block; }
        .faq-item.open .faq-question i { transform:rotate(180deg); }
        .support-strip { margin-top:22px; padding:20px; border-radius:12px; background:rgba(194,38,38,0.12); border:1px solid rgba(194,38,38,0.28); display:flex; justify-content:space-between; align-items:center; gap:16px; }
        .support-strip a { color:#fff; background:#C22626; text-decoration:none; padding:11px 16px; border-radius:9px; font-weight:800; }
        @media (max-width:700px) {
            .help-top, .support-strip { align-items:stretch; flex-direction:column; }
        }
    </style>
</head>
<body>
    <div class="help-wrap">
        <div class="help-top">
            <div>
                <h1>Help Center</h1>
                <p>Quick answers for orders, bookings, promos, delivery, and account support.</p>
            </div>
            <a class="back-link" href="account-dashboard.php"><i class="fa-solid fa-chevron-left"></i> Dashboard</a>
        </div>

        <div class="faq-list">
            <?php foreach ($faqs as $index => $faq): ?>
                <div class="faq-item <?= $index === 0 ? 'open' : '' ?>">
                    <button class="faq-question" type="button">
                        <?= htmlspecialchars($faq['question']) ?>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="faq-answer"><?= htmlspecialchars($faq['answer']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="support-strip">
            <div>
                <strong>Still need help?</strong>
                <div style="color:rgba(255,255,255,0.68);margin-top:5px;">Open your dashboard and start a support chat.</div>
            </div>
            <a href="account-dashboard.php">Contact Support</a>
        </div>
    </div>

    <script>
        document.querySelectorAll('.faq-question').forEach(button => {
            button.addEventListener('click', () => {
                button.closest('.faq-item').classList.toggle('open');
            });
        });
    </script>
</body>
</html>
