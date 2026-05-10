<?php
// ✅ FIX 1: session_start() MUST be before ANY output
// This file is included by other pages — ensure session is already started there.
// If this file is included AFTER html output, move this block to the very top of the parent file.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/env-bootstrap.php';
webtools_load_env(__DIR__);
$sessionUserEmail = $_SESSION['user_email'] ?? '';
$paymentsDemoUi = webtools_truthy_env(getenv('PAYMENTS_DEMO') ?: null)
    || webtools_truthy_env(getenv('XENDIT_DEMO') ?: null);
$xenditPublicKey = getenv('XENDIT_PUBLIC_KEY') ?: '';
?>
<!-- Xendit.js -->
<script src="https://js.xendit.co/v1/xendit.min.js"></script>
<script>
window.XENDIT_PUBLIC_KEY = '<?php echo $xenditPublicKey; ?>';
</script>
<!-- Cart Button -->
<div class="cart-btn cart-btn-fixed" id="cartBtn" role="button" tabindex="0"
     onclick="if(typeof openCart==='function') openCart();"
     style="z-index: 10010 !important; pointer-events: auto;">
    <i class="fas fa-shopping-cart"></i>
    <span class="cart-count" id="cartCount">0</span>
</div>
<!-- Top Notification Bar -->
<div class="top-notif" id="topNotif">
    <span class="top-notif-icon"></span>
    <span class="top-notif-text" id="topNotifText">Order Successful!</span>
</div>
<!-- =====================================================
CART OVERLAY (everything lives inside here)
===================================================== -->
<div class="cart-overlay" id="cartOverlay">
    <div class="cart-panel">
        <!-- LEFT: Items list -->
        <div class="cart-left">
            <button type="button" class="cart-back-btn" id="cartBackBtn">
                <i class="fas fa-chevron-left"></i> Back to menu
            </button>
            <div class="cart-divider"></div>
            <div class="cart-heading">
                Shopping cart
                <span class="cart-subheading" id="cartSubheading">You have 0 items in your cart</span>
            </div>
            <div class="cart-items-list" id="cartItemsList"></div>
            <div class="cart-empty" id="cartEmpty">
                <i class="fas fa-shopping-basket"></i>
                <p>Your cart is empty</p>
                <small>Add some items from the menu!</small>
            </div>
        </div>
        <!-- RIGHT: Payment & checkout -->
        <div class="cart-right">
            <div id="cartAuthNotice" class="cart-auth-notice" style="display:none;"></div>
            <div class="cart-right-scrollable">
                <!-- Address -->
                <div class="cart-right-section">
                    <h3 class="cart-right-title">Address</h3>
                    <div class="cart-address-field">
                        <input type="text" class="cart-input" placeholder="Street, Barangay, and City" id="cartAddress">
                        <button type="button" class="cart-address-icon" id="cartAddressIcon" title="Select from Map">
                            <i class="fas fa-map-marked-alt"></i>
                        </button>
                    </div>
                    <span class="cart-error-msg" id="error-cartAddress"></span>
                </div>
                <!-- Payment -->
                <div class="cart-right-section">
                    <h3 class="cart-right-title">Payment details</h3>
                    <p class="cart-label">Type of payment</p>
                    <div class="payment-methods">
                        <button class="payment-method-btn active" data-method="card" title="Pay with Card">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/a/a4/Mastercard_2019_logo.svg"
                                 alt="MasterCard" class="payment-icon">
                        </button>
                        <button class="payment-method-btn" data-method="cod" title="Cash on Delivery">
                            <img src="https://github.com/maxmonice/WEBTOOLSs/raw/main/images/codhand.png" alt="COD"
                                 class="payment-icon">
                        </button>
                        <button class="payment-method-btn" data-method="gcash" title="GCash">
                            <img src="https://github.com/maxmonice/WEBTOOLSs/raw/main/images/gcashLogo.png" alt="GCash"
                                 class="payment-icon">
                        </button>
                    </div>
                    <!-- Card / GCash fields -->
                    <div id="onlinePayFields" class="cart-online-pay">
                        <div class="cart-label-row">
                            <span class="cart-label cart-label-inline">Secure payment</span>
                            <?php if ($paymentsDemoUi): ?><span class="cart-demo-badge">Demo</span><?php endif; ?>
                        </div>
                        <!-- GCash Fields -->
                    <div id="gcashFields" style="display: none;">
                        <div class="xendit-recipient-badge">
                            <i class="fas fa-store"></i>
                            <span>09392999912 <strong style="color:#fff;">&nbsp;</strong></span>
                        </div>
<div class="cart-right-section" style="margin-top:15px;">
                            <label class="cart-label" for="gcash_mobile">Your GCash Number</label>
                            <input type="tel" class="cart-input dark" id="gcash_mobile" placeholder="0917XXXXXXX" maxlength="11">
                            <span class="cart-error-msg" id="error-gcash_mobile"></span>
                            <div class="cart-right-section" style="margin-top:12px; padding:0;">
                                <label class="cart-label" for="gcash_account">Your GCash Account Name</label>
                                <input type="text" class="cart-input dark" id="gcash_account" placeholder="GCash account name" maxlength="100">
                                <span class="cart-error-msg" id="error-gcash_account"></span>
                            </div>
                        </div>

                    </div>
                        <!-- Seamless Card Fields -->
                        <div id="cardDetailsFields">
                            <div class="cart-right-section" style="margin-top:10px;">
                                <label class="cart-label" for="card_name">Name on card</label>
                                <input type="text" class="cart-input dark" id="card_name" placeholder="Full Name">
                                <span class="cart-error-msg" id="error-card_name"></span>
                            </div>
                            <div class="cart-right-section">
                                <label class="cart-label" for="card_number">Card Number</label>
                                <input type="text" class="cart-input dark" id="card_number"
                                       placeholder="1111 2222 3333 4444" maxlength="19">
                                <span class="cart-error-msg" id="error-card_number"></span>
                            </div>
                            <div class="card-row">
                                <div class="cart-right-section">
                                    <label class="cart-label" for="card_expiry">Expiration date</label>
                                    <input type="text" class="cart-input dark" id="card_expiry" placeholder="mm/yy"
                                           maxlength="5">
                                    <span class="cart-error-msg" id="error-card_expiry"></span>
                                </div>
                                <div class="cart-right-section">
                                    <label class="cart-label" for="card_cvv">CVV</label>
                                    <input type="text" class="cart-input dark" id="card_cvv" placeholder="123"
                                           maxlength="4">
                                    <span class="cart-error-msg" id="error-card_cvv"></span>
                                </div>
                            </div>
                            <div class="cart-right-section" style="margin-top:10px;">
                                <label class="cart-label" for="card_mobile">Mobile Number</label>
                                <input type="text" class="cart-input dark" id="card_mobile"
                                       placeholder="09XX XXX XXXX" maxlength="11" list="phoneSuggestions"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                <span class="cart-error-msg" id="error-card_mobile"></span>
                            </div>
                        </div>
                        <input type="hidden" id="xenditPayerEmail"
                               value="<?php echo htmlspecialchars($sessionUserEmail); ?>">
                    </div>
                    <!-- COD Fields -->
                    <div id="codFields" style="display:none;">
                        <p class="cart-label" style="margin-top:14px;">Receiver's Name</p>
                        <input type="text" class="cart-input dark" placeholder="Full name" id="codName">
                        <span class="cart-error-msg" id="error-codName"></span>
                        <p class="cart-label" style="margin-top:12px;">Mobile Number</p>
                        <input type="text" class="cart-input dark" placeholder="09XX XXX XXXX" id="codMobile"
                               maxlength="11" list="phoneSuggestions"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        <span class="cart-error-msg" id="error-codMobile"></span>
                    </div>
                </div>
            </div><!-- end cart-right-scrollable -->
            <!-- Totals + Checkout — fixed at bottom, never moves -->
            <div class="cart-totals-fixed">
                <div class="cart-totals-divider"></div>
                <div class="cart-totals-inner">
                    <div class="cart-total-row">
                        <span>Subtotal</span>
                        <span id="cartSubtotal">₱0</span>
                    </div>
                    <div class="cart-total-row">
                        <span>VAT (12%)</span>
                        <span id="cartTax">₱0</span>
                    </div>
                    <div class="cart-total-row">
                        <span>Shipping</span>
                        <span>₱50</span>
                    </div>
                    <div class="cart-total-row total">
                        <span>Total (Tax incl.)</span>
                        <span id="cartTotal">₱50</span>
                    </div>
                </div>
                <button type="button" class="checkout-btn" id="checkoutBtn">
                    <span class="checkout-btn-total-block">
                        <span id="checkoutTotal" class="checkout-btn-amount">₱50</span>
                    </span>
                    <span class="checkout-btn-action">
                        Continue to pay
                        <i class="fas fa-arrow-right"></i>
                        <i class="fas fa-lock lock-icon"></i>
                    </span>
                </button>
            </div><!-- end cart-totals-fixed -->
        </div><!-- end cart-right -->
    </div><!-- end cart-panel -->
</div><!-- end cart-overlay -->
<!-- =====================================================
MODALS — outside cart-overlay so they stack correctly
===================================================== -->
<!-- Delivery Notice -->
<div class="delivery-notice-overlay" id="deliveryNoticeOverlay" style="display:none;">
    <div class="delivery-notice-modal">
        <div class="delivery-notice-icon"><i class="fas fa-truck"></i></div>
        <div class="delivery-notice-title">Local Delivery Only</div>
        <div class="delivery-notice-text">
            To ensure your food arrives hot and fresh, we currently focus on serving our local community within
            Taguig City and its neighboring areas. By keeping our delivery radius close to home, we can guarantee
            the quality and taste you expect from Luke's Seafood. Thank you for supporting local!
        </div>
        <button class="delivery-notice-btn" id="deliveryNoticeClose">Continue to Menu</button>
    </div>
</div>
<!-- Remove Item Confirmation -->
<div class="confirm-remove-overlay" id="confirmRemoveOverlay">
    <div class="confirm-remove-modal">
        <div class="confirm-remove-icon"><i class="fas fa-trash-alt"></i></div>
        <h3 class="confirm-remove-title">Remove Item</h3>
        <p class="confirm-remove-text">Do you want to remove this item from your cart?</p>
        <div class="confirm-remove-actions">
            <button class="confirm-remove-yes" id="confirmRemoveYes">Yes, Remove</button>
            <button class="confirm-remove-no" id="confirmRemoveNo">Cancel</button>
        </div>
    </div>
</div>
<!-- Auth Guard Modal -->
<div class="auth-modal-overlay" id="authModal">
    <div class="auth-modal">
        <div class="auth-modal-icon"><i class="fa-solid fa-lock"></i></div>
        <div class="auth-modal-body">
            <h3>Sign In Required</h3>
            <p>You need to be signed in to place an order.<br>
                <strong>Please log in to your account</strong> to continue.
            </p>
        </div>
        <div class="auth-modal-foot">
            <button class="auth-btn-signin" onclick="goToSignIn()">
                <i class="fa-solid fa-right-to-bracket"></i> Sign In to My Account
            </button>
            <button class="auth-btn-cancel" onclick="closeAuthModal()">Maybe Later</button>
        </div>
    </div>
</div>

<!-- ✅ UPDATED: Order Confirmation Modal (Old Simpler Version) -->
<div class="order-confirm-overlay" id="orderConfirmOverlay">
    <div class="order-confirm-modal">
        <div class="order-confirm-icon"><i class="fas fa-receipt"></i></div>
        <h3 class="order-confirm-title">Confirm Your Order</h3>
        <p class="order-confirm-text">Review your order details before placing.</p>

        <div class="order-confirm-pay-simple">
            Payment method: <strong id="orderConfirmPayText">Card</strong>
        </div>

        <div class="order-confirm-summary" id="orderConfirmSummary"></div>

        <div class="order-confirm-divider"></div>

        <div class="order-confirm-breakdown">
            <div class="order-confirm-row">
                <span>Subtotal</span>
                <span id="orderSubtotal">₱0</span>
            </div>
            <div class="order-confirm-row">
                <span>VAT (12%)</span>
                <span id="orderVat">₱0</span>
            </div>
            <div class="order-confirm-row">
                <span>Shipping</span>
                <span>₱50</span>
            </div>
            <div class="order-confirm-row total">
                <span>Total</span>
                <span id="orderTotal">₱50</span>
            </div>
        </div>

        <div class="order-confirm-actions" id="orderConfirmActions">
            <button class="order-confirm-place" id="orderConfirmClose">
                <i class="fas fa-check-circle"></i> Place Order
            </button>
            <button class="order-confirm-cancel" id="orderConfirmCancelBtn">Cancel</button>
        </div>
    </div>
</div>

<!-- Dedicated Xendit 3DS Overlay -->
<div class="xendit-3ds-overlay" id="xendit3DSOverlay">
    <div class="xendit-3ds-modal">
        <div class="xendit-3ds-header">
            <div class="xendit-3ds-header-icon"><i class="fas fa-shield-alt"></i></div>
            <div class="xendit-3ds-header-text">
                <h3>Secure Verification</h3>
                <p>Please authenticate your payment below</p>
            </div>
        </div>
        <div id="threeDSContainer" class="xendit-3ds-body">
            <iframe id="threeDSFrame"></iframe>
        </div>
        <button class="xendit-3ds-cancel" id="threeDSCancelBtn">Cancel Verification</button>
    </div>
</div>

<!-- GCash redirect form (hidden) -->
<form id="xenditGcashForm" method="post" action="checkout.php" style="display:none;" aria-hidden="true">
    <input type="hidden" name="cart_json" id="gcash_cart_json" value="">
    <input type="hidden" name="address" id="gcash_form_address" value="">
    <input type="hidden" name="subtotal" id="gcash_subtotal" value="">
    <input type="hidden" name="shipping" id="gcash_shipping" value="">
    <input type="hidden" name="total_price" id="gcash_total_price" value="">
    <input type="hidden" name="payment_method" value="gcash">
    <input type="hidden" name="payer_email" id="gcash_form_payer_email" value="">
</form>
<datalist id="phoneSuggestions"></datalist>