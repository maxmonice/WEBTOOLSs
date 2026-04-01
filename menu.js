// =====================================================
//  menu.js — Luke's Seafood Trading
// =====================================================
document.addEventListener('DOMContentLoaded', () => {

    // ── Elements ──
    const mobileMenuBtn = document.getElementById('mobile-menu');
    const navMenu       = document.getElementById('navMenu');
    const sidebarItems  = document.querySelectorAll('.sidebar-item');
    const navLinks      = document.querySelectorAll('.nav-menu a');
    const menuItems     = document.querySelectorAll('.menu-item');
    const modal         = document.getElementById('itemModal');
    const modalClose    = document.getElementById('modalClose');
    const cartBtn       = document.getElementById('cartBtn');
    const cartCountEl   = document.getElementById('cartCount');
    const cartOverlay   = document.getElementById('cartOverlay');
    const cartBackBtn   = document.getElementById('cartBackBtn');

    // ── State ──
    let cart = [];
    let currentItem       = null;
    let selectedVariation = null;
    let quantity          = 1;
    const SHIPPING        = 50;

    // =====================================================
    //  MOBILE NAV
    // =====================================================
    mobileMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        navMenu.classList.toggle('active');
        const icon = mobileMenuBtn.querySelector('i');
        icon.classList.toggle('fa-bars', !navMenu.classList.contains('active'));
        icon.classList.toggle('fa-times',  navMenu.classList.contains('active'));
    });

    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            navMenu.classList.remove('active');
            const icon = mobileMenuBtn.querySelector('i');
            icon.classList.replace('fa-times', 'fa-bars');
        });
    });

    document.addEventListener('click', (e) => {
        if (!navMenu.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
            navMenu.classList.remove('active');
            const icon = mobileMenuBtn.querySelector('i');
            icon.classList.replace('fa-times', 'fa-bars');
        }
    });

    // =====================================================
    //  SIDEBAR SCROLL
    // =====================================================
    sidebarItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const target = document.getElementById(item.getAttribute('data-target'));
            if (target) {
                window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - 20, behavior: 'smooth' });
            }
        });
    });

    const sectionIds = ['salad-section','fusion-section','a-la-carte-section','platters-section','bento-section'];
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                sidebarItems.forEach(item => {
                    item.classList.toggle('active', item.dataset.target === entry.target.id);
                });
            }
        });
    }, { threshold: 0.3 });
    sectionIds.forEach(id => { const el = document.getElementById(id); if (el) observer.observe(el); });

    // =====================================================
    //  PRICE HELPERS
    // =====================================================
    function parseRawPrice(priceStr) {
        return parseFloat(priceStr.replace(/[₱,]/g, '')) || 0;
    }

    function fmt(n) {
        return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // =====================================================
    //  VARIATION PRICE CALCULATION
    //  Rules:
    //   - Variations are weight-based (e.g. "150g", "300g")
    //   - Extract grams from label, compute price proportionally
    //     relative to the BASE variation (the first one listed)
    //   - Non-gram variations keep the original price unchanged
    // =====================================================
    function getVariationPrice(basePrice, baseVariation, targetVariation) {
        const baseGrams   = parseGrams(baseVariation);
        const targetGrams = parseGrams(targetVariation);

        // If both are gram-based, scale price proportionally
        if (baseGrams && targetGrams) {
            return basePrice * (targetGrams / baseGrams);
        }

        // Non-gram variation — return base price unchanged
        return basePrice;
    }

    function parseGrams(label) {
        if (!label) return null;
        // Matches "150g", "300g", "150 g", "300 G" etc.
        const match = String(label).match(/^(\d+(?:\.\d+)?)\s*g$/i);
        return match ? parseFloat(match[1]) : null;
    }

    function getModalPrice() {
        const basePrice     = parseRawPrice(currentItem.price);
        const baseVariation = currentItem.variations ? currentItem.variations[0] : null;
        if (selectedVariation && baseVariation) {
            return getVariationPrice(basePrice, baseVariation, selectedVariation);
        }
        return basePrice;
    }

    // =====================================================
    //  ITEM MODAL
    // =====================================================
    menuItems.forEach(item => {
        item.addEventListener('click', () => openModal(JSON.parse(item.getAttribute('data-item'))));
    });

    function openModal(itemData) {
        currentItem       = itemData;
        selectedVariation = itemData.variations ? itemData.variations[0] : null;
        quantity          = 1;

        document.getElementById('modalImage').src            = itemData.image;
        document.getElementById('modalImage').alt            = itemData.name;
        document.getElementById('modalTitle').textContent    = itemData.name;
        document.getElementById('quantityValue').textContent = 1;

        // Set initial price (with first variation if applicable)
        refreshModalPrice();

        const variationSection = document.getElementById('variationSection');
        const variationOptions = document.getElementById('variationOptions');

        if (itemData.variations?.length) {
            variationSection.style.display = 'block';
            variationOptions.innerHTML = '';
            itemData.variations.forEach((v, i) => {
                const btn = document.createElement('button');
                btn.className = 'variation-btn' + (i === 0 ? ' active' : '');

                // Show label with computed price for gram-based variations
                const basePrice   = parseRawPrice(itemData.price);
                const baseVar     = itemData.variations[0];
                const varPrice    = getVariationPrice(basePrice, baseVar, v);
                const isGramBased = parseGrams(v) !== null;
                btn.textContent   = isGramBased ? `${v} — ${fmt(varPrice)}` : v;

                btn.addEventListener('click', () => {
                    document.querySelectorAll('.variation-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    selectedVariation = v;
                    refreshModalPrice();
                });
                variationOptions.appendChild(btn);
            });
        } else {
            variationSection.style.display = 'none';
        }

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Update the price displayed in the modal based on current selection
    function refreshModalPrice() {
        const price = getModalPrice();
        document.getElementById('modalPrice').textContent = fmt(price);
    }

    function closeModal() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        currentItem = null; selectedVariation = null; quantity = 1;
    }

    modalClose.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    document.getElementById('decreaseQty').addEventListener('click', () => {
        if (quantity > 1) { quantity--; document.getElementById('quantityValue').textContent = quantity; }
    });
    document.getElementById('increaseQty').addEventListener('click', () => {
        quantity++; document.getElementById('quantityValue').textContent = quantity;
    });

    // ── ADD TO CART ──
    document.getElementById('addToCartBtn').addEventListener('click', () => {
        if (!currentItem) return;

        const computedPrice = getModalPrice();
        const existing = cart.find(i => i.name === currentItem.name && i.variation === selectedVariation);

        if (existing) {
            existing.quantity += quantity;
        } else {
            cart.push({
                name:      currentItem.name,
                price:     fmt(computedPrice),   // formatted price string for display
                rawPrice:  computedPrice,         // exact number for calculations
                pieces:    currentItem.pieces || null,
                variation: selectedVariation,
                quantity:  quantity,
                image:     currentItem.image,
            });
        }

        updateCartCount();

        // Button feedback
        const btn = document.getElementById('addToCartBtn');
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Added to Cart!';
        btn.style.color = '#fff';
        btn.style.background = 'rgba(76,175,80,0.35)';
        setTimeout(() => {
            btn.innerHTML = orig;
            btn.style.color = '';
            btn.style.background = '';
        }, 1800);
    });

    // ── GO TO CART (replaces Order Now) ──
    document.getElementById('orderNowBtn').addEventListener('click', () => {
        closeModal();
        openCart();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (modal.classList.contains('active'))         closeModal();
            if (cartOverlay.classList.contains('active'))   closeCart();
        }
    });

    // =====================================================
    //  CART HELPERS
    // =====================================================
    function updateCartCount() {
        const total = cart.reduce((s, i) => s + i.quantity, 0);
        cartCountEl.textContent = total;
        cartBtn.style.transform = 'scale(1.25)';
        setTimeout(() => cartBtn.style.transform = '', 280);
    }

    function calcSubtotal() {
        return cart.reduce((s, i) => s + i.rawPrice * i.quantity, 0);
    }

    // =====================================================
    //  CART OVERLAY
    // =====================================================
    cartBtn.addEventListener('click', openCart);
    cartBackBtn.addEventListener('click', closeCart);
    cartOverlay.addEventListener('click', (e) => { if (e.target === cartOverlay) closeCart(); });

    function openCart() {
        renderCart();
        cartOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeCart() {
        cartOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    function renderCart() {
        const list    = document.getElementById('cartItemsList');
        const empty   = document.getElementById('cartEmpty');
        const subEl   = document.getElementById('cartSubtotal');
        const totalEl = document.getElementById('cartTotal');
        const checkEl = document.getElementById('checkoutTotal');
        const subhead = document.getElementById('cartSubheading');

        list.innerHTML = '';

        if (cart.length === 0) {
            empty.classList.add('show');
            list.style.display = 'none';
        } else {
            empty.classList.remove('show');
            list.style.display = 'flex';

            cart.forEach((item, idx) => {
                const row = document.createElement('div');
                row.className = 'cart-item-row';
                row.innerHTML = `
                    <img src="${item.image}" alt="${item.name}" class="cart-item-img" onerror="this.style.background='#eee'">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-meta">${item.pieces ? item.pieces : ''}${item.variation ? (item.pieces ? ' · ' : '') + item.variation : ''}</div>
                    </div>
                    <div class="cart-item-qty">
                        <button class="cart-qty-btn" data-action="up" data-idx="${idx}"><i class="fas fa-caret-up"></i></button>
                        <span class="cart-qty-num">${item.quantity}</span>
                        <button class="cart-qty-btn" data-action="down" data-idx="${idx}"><i class="fas fa-caret-down"></i></button>
                    </div>
                    <div class="cart-item-price">${fmt(item.rawPrice * item.quantity)}</div>
                    <button class="cart-item-delete" data-idx="${idx}"><i class="fas fa-trash-alt"></i></button>
                `;
                list.appendChild(row);
            });

            list.querySelectorAll('.cart-qty-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const i = +btn.dataset.idx;
                    if (btn.dataset.action === 'up') {
                        cart[i].quantity++;
                    } else {
                        cart[i].quantity--;
                        if (cart[i].quantity <= 0) cart.splice(i, 1);
                    }
                    updateCartCount();
                    renderCart();
                });
            });

            list.querySelectorAll('.cart-item-delete').forEach(btn => {
                btn.addEventListener('click', () => {
                    cart.splice(+btn.dataset.idx, 1);
                    updateCartCount();
                    renderCart();
                });
            });
        }

        const itemCount = cart.reduce((s, i) => s + i.quantity, 0);
        subhead.textContent = `You have ${itemCount} item${itemCount !== 1 ? 's' : ''} in your cart`;

        const sub   = calcSubtotal();
        const total = sub + (cart.length ? SHIPPING : 0);
        subEl.textContent   = fmt(sub);
        totalEl.textContent = fmt(total);
        checkEl.textContent = fmt(total);
    }

    // =====================================================
    //  PAYMENT METHOD TOGGLE
    // =====================================================
    document.querySelectorAll('.payment-method-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.payment-method-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const isGcash = btn.dataset.method === 'gcash';
            document.getElementById('cardFields').style.display  = isGcash ? 'none' : 'block';
            document.getElementById('gcashFields').style.display = isGcash ? 'block' : 'none';
        });
    });

    document.getElementById('cardNumber')?.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').substring(0, 16);
        this.value = v.replace(/(.{4})/g, '$1 ').trim();
    });

    document.getElementById('cardExpiry')?.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').substring(0, 4);
        if (v.length >= 3) v = v.slice(0,2) + '/' + v.slice(2);
        this.value = v;
    });

    // =====================================================
    //  CHECKOUT
    // =====================================================
    document.getElementById('checkoutBtn')?.addEventListener('click', () => {
        if (cart.length === 0) {
            alert('Your cart is empty!');
            return;
        }
        const address = document.getElementById('cartAddress').value.trim();
        if (!address) {
            document.getElementById('cartAddress').focus();
            document.getElementById('cartAddress').style.boxShadow = '0 0 0 2px #ffaaaa';
            setTimeout(() => document.getElementById('cartAddress').style.boxShadow = '', 2000);
            return;
        }
        window.open('https://www.foodpanda.ph/', '_blank');
    });

});