<?php
declare(strict_types=1);

function sendOrderReceiptEmail(string $payerEmail, string $userName, int $orderId, array $items, float $subtotal, float $shipping, float $total, string $paymentMethod, float $tax = 0): bool {
    if (empty($payerEmail)) return false;

    $subject = "Order Confirmation - Luke's Seafood (#{$orderId})";
    
    // Fallback names
    $displayName = trim($userName) !== '' ? htmlspecialchars($userName) : 'Customer';
    $paymentMethodLabel = strtoupper($paymentMethod);
    if ($paymentMethod === 'card') $paymentMethodLabel = 'Credit/Debit Card';
    if ($paymentMethod === 'gcash') $paymentMethodLabel = 'GCash';
    if ($paymentMethod === 'cod') $paymentMethodLabel = 'Cash on Delivery';

    $itemsHtml = '';
    foreach ($items as $item) {
        $name = htmlspecialchars($item['name'] ?? 'Item');
        $qty = (int)($item['quantity'] ?? 1);
        $price = number_format((float)($item['price'] ?? $item['rawPrice'] ?? 0), 2);
        $lineTotal = number_format(((float)($item['price'] ?? $item['rawPrice'] ?? 0)) * $qty, 2);
        
        $itemsHtml .= "
        <tr>
            <td style='padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.05);'>
                <div style='font-weight: 600; color: #fff;'>{$name}</div>
                <div style='font-size: 13px; color: rgba(255,255,255,0.5);'>Qty: {$qty} × ₱{$price}</div>
            </td>
            <td style='padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: right; color: #fff; font-weight: 600;'>
                ₱{$lineTotal}
            </td>
        </tr>";
    }

    $subtotalF = number_format($subtotal, 2);
    $taxF = number_format($tax, 2);
    $shippingF = number_format($shipping, 2);
    $totalF = number_format($total, 2);

    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Order Receipt</title>
    </head>
    <body style='margin: 0; padding: 0; background-color: #111; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; color: #eee; -webkit-font-smoothing: antialiased;'>
        <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #111; padding: 40px 20px;'>
            <tr>
                <td align='center'>
                    <table width='100%' max-width='500' cellpadding='0' cellspacing='0' style='background-color: #222; border-radius: 16px; overflow: hidden; max-width: 500px; width: 100%; border: 1px solid rgba(194, 38, 38, 0.2); box-shadow: 0 10px 30px rgba(0,0,0,0.5);'>
                        
                        <!-- Header -->
                        <tr>
                            <td style='background: linear-gradient(135deg, #C22626, #8B0A1E); padding: 30px 20px; text-align: center;'>
                                <h1 style='color: #fff; margin: 0; font-size: 24px; letter-spacing: 1px; text-transform: uppercase;'>Luke's Seafood</h1>
                                <p style='color: rgba(255,255,255,0.8); margin: 8px 0 0; font-size: 14px;'>Order Receipt</p>
                            </td>
                        </tr>

                        <!-- Greeting -->
                        <tr>
                            <td style='padding: 30px 30px 10px;'>
                                <h2 style='margin: 0 0 10px; font-size: 18px; color: #fff;'>Hi {$displayName},</h2>
                                <p style='margin: 0; font-size: 14px; color: rgba(255,255,255,0.7); line-height: 1.5;'>
                                    Thank you for your order! We've successfully received your payment and are now preparing your delicious seafood.
                                </p>
                            </td>
                        </tr>

                        <!-- Order Details -->
                        <tr>
                            <td style='padding: 20px 30px;'>
                                <div style='background-color: #1a1a1a; border-radius: 12px; padding: 20px;'>
                                    <div style='margin-bottom: 15px;'>
                                        <span style='font-size: 12px; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 1px;'>Order Number</span>
                                        <div style='font-size: 16px; color: #fff; font-weight: bold; margin-top: 4px;'>#{$orderId}</div>
                                    </div>
                                    <div>
                                        <span style='font-size: 12px; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 1px;'>Payment Method</span>
                                        <div style='font-size: 15px; color: #fff; margin-top: 4px;'>{$paymentMethodLabel}</div>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <!-- Items Table -->
                        <tr>
                            <td style='padding: 10px 30px 30px;'>
                                <h3 style='margin: 0 0 15px; font-size: 16px; color: #fff; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px;'>Order Summary</h3>
                                <table width='100%' cellpadding='0' cellspacing='0'>
                                    {$itemsHtml}
                                </table>
                                
                                <table width='100%' cellpadding='0' cellspacing='0' style='margin-top: 20px;'>
                                    <tr>
                                        <td style='padding: 6px 0; color: rgba(255,255,255,0.6); font-size: 14px;'>Subtotal</td>
                                        <td style='padding: 6px 0; color: rgba(255,255,255,0.8); text-align: right; font-size: 14px;'>₱{$subtotalF}</td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 6px 0; color: rgba(255,255,255,0.6); font-size: 14px;'>VAT (12%)</td>
                                        <td style='padding: 6px 0; color: rgba(255,255,255,0.8); text-align: right; font-size: 14px;'>₱{$taxF}</td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 6px 0; color: rgba(255,255,255,0.6); font-size: 14px;'>Shipping</td>
                                        <td style='padding: 6px 0; color: rgba(255,255,255,0.8); text-align: right; font-size: 14px;'>₱{$shippingF}</td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 15px 0 5px; color: #fff; font-weight: bold; font-size: 18px; border-top: 1px solid rgba(255,255,255,0.1);'>Total</td>
                                        <td style='padding: 15px 0 5px; color: #C22626; font-weight: bold; font-size: 18px; text-align: right; border-top: 1px solid rgba(255,255,255,0.1);'>₱{$totalF}</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style='background-color: #191919; padding: 25px 30px; text-align: center;'>
                                <p style='margin: 0; font-size: 12px; color: rgba(255,255,255,0.4);'>
                                    If you have any questions, reply to this email or contact us at support@lukesseafood.com.
                                </p>
                                <p style='margin: 10px 0 0; font-size: 11px; color: rgba(255,255,255,0.2);'>
                                    &copy; " . date('Y') . " Luke's Seafood. All rights reserved.
                                </p>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ";

    $smtpHost = getenv('OTP_SMTP_HOST') ?: '';
    $smtpPort = (int)(getenv('OTP_SMTP_PORT') ?: 587);
    $smtpUser = getenv('OTP_SMTP_USER') ?: '';
    $smtpPass = getenv('RECEIPT_SMTP_PASS') ?: (getenv('OTP_SMTP_PASS') ?: '');
    $smtpSecure = strtolower(getenv('OTP_SMTP_SECURE') ?: 'tls');
    $fromEmail = getenv('OTP_FROM_EMAIL') ?: 'no-reply@lukesseafood.com';
    $fromName = getenv('OTP_FROM_NAME') ?: "Luke's Seafood Trading";

    if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
        require_once dirname(__DIR__) . '/vendor/autoload.php';
    }

    if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function($str, $level) {
                file_put_contents(__DIR__ . '/email_debug.log', date('Y-m-d H:i:s') . " - $str\n", FILE_APPEND);
            };
            if ($smtpHost !== '') {
                $mail->isSMTP();
                $mail->Host = $smtpHost;
                $mail->Port = $smtpPort;
                $mail->SMTPAuth = $smtpUser !== '' || $smtpPass !== '';
                if ($mail->SMTPAuth) {
                    $mail->Username = $smtpUser;
                    $mail->Password = $smtpPass;
                }
                if ($smtpSecure === 'tls') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($smtpSecure === 'ssl') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                }
            }
            
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($payerEmail, $userName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            
            return $mail->send();
        } catch (\Throwable $e) {
            file_put_contents(__DIR__ . '/email_debug.log', date('Y-m-d H:i:s') . ' - PHPMailer Receipt Error: ' . $e->getMessage() . "\n", FILE_APPEND);
            // Fall through to basic mail
        }
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        "From: {$fromName} <{$fromEmail}>",
        "Reply-To: support@lukesseafood.com",
        'X-Mailer: PHP/' . phpversion()
    ];

    return @mail($payerEmail, $subject, $htmlBody, implode("\r\n", $headers));
}


