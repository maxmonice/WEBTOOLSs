<!-- Cart Button -->
<div class="cart-btn" id="cartBtn" role="button" tabindex="0">
    <i class="fas fa-shopping-cart"></i>
    <span class="cart-count" id="cartCount">0</span>
</div>

<!-- Delivery Notice Overlay -->
<div class="delivery-notice-overlay" id="deliveryNoticeOverlay">
    <div class="delivery-notice-modal">
        <div class="delivery-notice-icon"><i class="fas fa-truck"></i></div>
        <div class="delivery-notice-title">Local Delivery Only</div>
        <div class="delivery-notice-text">
            To ensure your food arrives hot and fresh, we currently focus on serving our local community within Taguig City and its neighboring areas. By keeping our delivery radius close to home, we can guarantee the quality and taste you expect from Luke's Seafood. Thank you for supporting local!
        </div>
        <button class="delivery-notice-btn" id="deliveryNoticeClose">Continue to Menu</button>
    </div>
</div>

<!-- Top Notification Bar -->
<div class="top-notif" id="topNotif">
    <span class="top-notif-icon"><i class="fas fa-check-circle"></i></span>
    <span class="top-notif-text" id="topNotifText">Order Successful!</span>
</div>

<!-- Cart Overlay -->
<div class="cart-overlay" id="cartOverlay">
    <div class="cart-panel">
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

<div class="cart-right">
            <div id="cartAuthNotice" class="cart-auth-notice" style="display:none;"></div>
            <div class="cart-notif" id="cartNotif">
                <span class="cart-notif-text" id="cartNotifText">Cart is empty - add items from the menu!</span>
            </div>
<div class="cart-right-section">
                <h3 class="cart-right-title">Address</h3>
                <div class="cart-address-field">
                    <input type="text" class="cart-input" placeholder="Street, Barangay, and City" id="cartAddress">
                    <button type="button" class="cart-address-icon" id="cartAddressIcon" title="Select from Map">
                        <i class="fas fa-map-marked-alt"></i>
                    </button>
                </div>
            </div>
            <div class="cart-right-section">
                <h3 class="cart-right-title">Payment details</h3>
                <p class="cart-label">Type of payment</p>
                <div class="payment-methods">
                    <button class="payment-method-btn active" data-method="mastercard">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Mastercard-logo.svg/1280px-Mastercard-logo.svg.png" alt="MasterCard">
                    </button>
                    <button class="payment-method-btn" data-method="cod">
                        <img src="https://github.com/maxmonice/WEBTOOLSs/raw/main/images/codhand.png" alt="COD">
                    </button>
                    <button class="payment-method-btn" data-method="gcash">
                        <img src="https://github.com/maxmonice/WEBTOOLSs/raw/main/images/gcashLogo.png" alt="GCash">
                    </button>
                </div>

                <!-- Card Fields -->
                <div id="cardFields">
                    <p class="cart-label" style="margin-top:14px;">Name on card</p>
                    <input type="text" class="cart-input dark" placeholder="Name" id="cardName">
                    <p class="cart-label" style="margin-top:12px;">Card Number</p>
                    <input type="text" class="cart-input dark" placeholder="1111 2222 3333 4444" id="cardNumber" maxlength="19">
                    <div class="card-row">
                        <div>
                            <p class="cart-label">Expiration date</p>
                            <input type="text" class="cart-input dark" placeholder="mm/yy" id="cardExpiry" maxlength="5">
                        </div>
                        <div>
                            <p class="cart-label">CVV</p>
                            <input type="text" class="cart-input dark" placeholder="123" id="cardCvv" maxlength="3">
                        </div>
                    </div>
                </div>

                <!-- COD Fields -->
                <div id="codFields" style="display:none;">
                    <p class="cart-label" style="margin-top:14px;">Receiver's Name</p>
                    <input type="text" class="cart-input dark" placeholder="Full name" id="codName">
                    <p class="cart-label" style="margin-top:12px;">Mobile Number</p>
                    <input type="text" class="cart-input dark" placeholder="09XX XXX XXXX" id="codMobile" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                </div>

                <!-- GCash Fields -->
                <div id="gcashFields" style="display:none;">
                    <p class="cart-label gcash-section-label">Send via</p>

                    <div class="gcash-top-row" style="display:flex; flex-direction:row; align-items:flex-end; gap:0; width:100%;">
                        <div class="gcash-col" style="flex:1; min-width:0; display:flex; flex-direction:column;">
                            <p class="cart-label">Gcash number</p>
                            <div class="gcash-number-box" id="gcashCopyNumber" title="Click to copy">09392999912</div>
                        </div>
                        <div class="gcash-or-divider" style="display:flex; align-items:center; justify-content:center; padding:0 8px; padding-bottom:10px; flex-shrink:0;">
                            <span class="gcash-or-text">Or</span>
                        </div>
                        <div class="gcash-col" style="flex:1; min-width:0; display:flex; flex-direction:column;">
                            <p class="cart-label">Scan QR code</p>
                            <div class="gcash-qr-box" id="viewQrBtn">
                                <span class="gcash-qr-link">View Qr code</span>
                            </div>
                        </div>
                    </div>

                    <p class="cart-label" style="margin-top:12px;">GCash Reference Number</p>
                    <div class="input-group">
                        <input 
                            type="text" 
                            class="cart-input dark" 
                            id="gcashRef" 
                            placeholder="09XX XXX XXXX" 
                            maxlength="13" 
                            oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                    <p class="cart-label" style="margin-top:10px;">GCash Mobile Number</p>
                    <div class="input-group">
                    <input 
                            type="text" 
                            class="cart-input dark" 
                            id="gcashNumber" 
                            placeholder="09XX XXX XXXX" 
                            maxlength="11" 
                            oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                </div>
            </div>

            <div class="cart-totals">
                <div class="cart-total-row">
                    <span>Subtotal</span>
                    <span id="cartSubtotal">â‚±0</span>
                </div>
                <div class="cart-total-row">
                    <span>Shipping</span>
                    <span>â‚±50</span>
                </div>
                <div class="cart-total-row total">
                    <span>Total (Tax incl.)</span>
                    <span id="cartTotal">â‚±50</span>
                </div>
            </div>
            <button class="checkout-btn" id="checkoutBtn">
                <span id="checkoutTotal">â‚±50</span>
                <span>Checkout <i class="fas fa-arrow-right"></i><i class="fas fa-lock lock-icon"></i></span>
            </button>
        </div>
    </div>
</div>

<!-- Remove Item Confirmation Modal -->
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

<!-- QR Code Overlay -->
<div class="qr-overlay" id="qrOverlay">
    <div class="qr-modal">
        <button class="qr-close" id="qrClose"><i class="fas fa-times"></i></button>
        <p class="qr-label">Scan to pay via GCash</p>
        <img src="https://github.com/maxmonice/WEBTOOLSs/raw/main/images/gcashqrcode.png" alt="GCash QR Code" class="qr-image">
    </div>
</div>

<!-- Auth Guard Modal -->
<div class="auth-modal-overlay" id="authModal">
    <div class="auth-modal">
        <div class="auth-modal-icon"><i class="fa-solid fa-lock"></i></div>
        <div class="auth-modal-body">
            <h3>Sign In Required</h3>
            <p>You need to be signed in to place an order.<br>
            <strong>Please log in to your account</strong> to continue.</p>
        </div>
<div class="auth-modal-foot">
            <button class="auth-btn-signin" onclick="goToSignIn()">
                <i class="fa-solid fa-right-to-bracket"></i> Sign In to My Account
            </button>
            <button class="auth-btn-cancel" onclick="closeAuthModal()">Maybe Later</button>
        </div>
    </div>
</div>

<!-- Order Confirmation Modal -->
<div class="order-confirm-overlay" id="orderConfirmOverlay">
    <div class="order-confirm-modal">
        <div class="order-confirm-icon"><i class="fas fa-check-circle"></i></div>
        <h3 class="order-confirm-title">Order Confirmed!</h3>
        <p class="order-confirm-text">Thank you for your order. Here's your summary:</p>
        
        <div class="order-confirm-summary" id="orderConfirmSummary"></div>
        
        <div class="order-confirm-divider"></div>
        
        <div class="order-confirm-breakdown">
            <div class="order-confirm-row">
                <span>Subtotal</span>
                <span id="orderSubtotal">₱0</span>
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
        
<div class="order-confirm-actions">
            <button class="order-confirm-close" id="orderConfirmClose">Place Order</button>
            <button class="order-confirm-cancel" id="orderConfirmCancelBtn">Cancel</button>
        </div>
    </div>
</div>
