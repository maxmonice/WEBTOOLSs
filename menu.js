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
    let lastViewedItem    = null;
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

    function getVariationPrice(basePrice, baseVariation, targetVariation) {
        const baseGrams   = parseGrams(baseVariation);
        const targetGrams = parseGrams(targetVariation);
        if (baseGrams && targetGrams) {
            return basePrice * (targetGrams / baseGrams);
        }
        
        const basePieces  = parsePieces(baseVariation);
        const targetPieces = parsePieces(targetVariation);
        if (basePieces && targetPieces) {
            return basePrice * (targetPieces / basePieces);
        }
        
        return basePrice;
    }

    function parseGrams(label) {
        if (!label) return null;
        const match = String(label).match(/^(\d+(?:\.\d+)?)\s*g(?:rams)?$/i);
        return match ? parseFloat(match[1]) : null;
    }

    function parsePieces(label) {
        if (!label) return null;
        const match = String(label).match(/^(\d+(?:\.\d+)?)\s*pieces$/i);
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
    //  SEARCH BAR
    // =====================================================
    const searchInput    = document.getElementById('menuSearch');
    const searchClear    = document.getElementById('searchClear');
    const searchDropdown = document.getElementById('searchDropdown');

    // Build index: map each menu item element to its data + category
    const allMenuItems = Array.from(document.querySelectorAll('.menu-item')).map(el => {
        const data = JSON.parse(el.getAttribute('data-item'));
        // Walk backwards through siblings to find the category heading
        let sibling = el.closest('.menu-grid')?.previousElementSibling;
        while (sibling && !sibling.classList.contains('category-heading')) {
            sibling = sibling.previousElementSibling;
        }
        return {
            el,
            name:     data.name,
            price:    data.price,
            image:    data.image,
            category: sibling ? sibling.textContent.trim() : ''
        };
    });

    searchInput.addEventListener('input', () => {
        const q = searchInput.value.trim().toLowerCase();
        searchClear.style.display = q ? 'block' : 'none';

        if (!q) {
            searchDropdown.classList.remove('open');
            return;
        }

        const matches = allMenuItems.filter(i => i.name.toLowerCase().includes(q));

        if (matches.length === 0) {
            searchDropdown.innerHTML = '<div class="search-no-results">No items found</div>';
        } else {
            searchDropdown.innerHTML = matches.map((item, idx) => `
                <div class="search-result-item" data-idx="${idx}">
                    <img src="${item.image}" class="search-result-img" alt="${item.name}" onerror="this.style.background='#333'">
                    <div class="search-result-info">
                        <div class="search-result-name">${item.name}</div>
                        <div class="search-result-price">${item.price}</div>
                        <div class="search-result-category">${item.category}</div>
                    </div>
                </div>
            `).join('');

            searchDropdown.querySelectorAll('.search-result-item').forEach((row, idx) => {
                row.addEventListener('click', () => {
                    scrollToItem(matches[idx]);
                    searchInput.value = '';
                    searchClear.style.display = 'none';
                    searchDropdown.classList.remove('open');
                });
            });
        }

        searchDropdown.classList.add('open');
    });

    searchClear.addEventListener('click', () => {
        searchInput.value = '';
        searchClear.style.display = 'none';
        searchDropdown.classList.remove('open');
        searchInput.focus();
    });

    // Close dropdown when clicking outside the search wrapper
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-wrapper')) {
            searchDropdown.classList.remove('open');
        }
    });

    function scrollToItem(item) {
        const HEADER      = 70;  // fixed header height
        const SEARCH_BAR  = 74;  // sticky search wrapper height
        const EXTRA       = 20;  // breathing room
        const top = item.el.getBoundingClientRect().top + window.scrollY - HEADER - SEARCH_BAR - EXTRA;
        window.scrollTo({ top, behavior: 'smooth' });

        // Flash red highlight on the card
        item.el.classList.add('search-highlight');
        setTimeout(() => item.el.classList.remove('search-highlight'), 2000);
    }

    // =====================================================
    //  ITEM MODAL
    // =====================================================
    menuItems.forEach(item => {
        item.addEventListener('click', () => {
            lastViewedItem = item;
            openModal(JSON.parse(item.getAttribute('data-item')));
        });
    });

    function addItemToCart(itemData, qty = 1) {
        if (!itemData) return;
        const variation = itemData.variations ? itemData.variations[0] : null;
        const computedPrice = parseRawPrice(itemData.price);

        const existing = cart.find(i => i.name === itemData.name && i.variation === variation);
        if (existing) {
            existing.quantity += qty;
        } else {
            cart.push({
                name:      itemData.name,
                price:     fmt(computedPrice),
                rawPrice:  computedPrice,
                pieces:    itemData.pieces || null,
                variation: variation,
                quantity:  qty,
                image:     itemData.image,
            });
        }

        updateCartCount();
    }

    function openModal(itemData) {
        currentItem       = itemData;
        selectedVariation = itemData.variations ? itemData.variations[0] : null;
        quantity          = 1;

        document.getElementById('modalImage').src            = itemData.image;
        document.getElementById('modalImage').alt            = itemData.name;
        document.getElementById('modalTitle').textContent    = itemData.name;
        document.getElementById('quantityValue').textContent = 1;

        refreshModalPrice();

        const variationSection = document.getElementById('variationSection');
        const variationLabel   = document.querySelector('.modal-variation-label');
        const dropdownBtn      = document.getElementById('dropdownBtn');
        const dropdownList     = document.getElementById('dropdownList');
        const variationInfo    = document.getElementById('variationInfo');
        const dropdownWrapper  = document.querySelector('.modal-dropdown-wrapper');

        const comboDescriptions = {
            'Combo U': 'Pork tonkatsu, 3 tempura, 50g kani mango salad & rice',
            'Combo L': '2 Tempura, 50g kani mango salad & cali maki',
            'Combo K': '3 Tempura, 50g kani mango salad & rice',
            'Combo E': 'Pork tonkatsu, rice & kani mango salad'
        };

        // Reset state
        dropdownBtn.style.display = '';
        dropdownWrapper.style.display = '';
        variationInfo.textContent = '';
        variationLabel.textContent = 'Available Variation:';
        variationLabel.style.display = 'block';
        variationInfo.style.whiteSpace = 'pre-line';

        const comboDescription = comboDescriptions[itemData.name];

        if (comboDescription) {
            variationSection.style.display = 'flex';
            variationLabel.style.display = 'none';
            variationInfo.textContent = comboDescription;
            variationInfo.style.textAlign = 'right';
            variationInfo.style.whiteSpace = 'nowrap';
            variationInfo.style.overflow = 'hidden';
            variationInfo.style.textOverflow = 'ellipsis';
            variationInfo.style.maxWidth = '220px';
            dropdownWrapper.style.display = 'none';

        } else if (itemData.variations?.length) {
            variationSection.style.display = 'flex';
            variationLabel.textContent = 'Available Variation:';
            dropdownWrapper.style.display = '';

            // Build dropdown options
            dropdownList.innerHTML = '';
            itemData.variations.forEach((v, i) => {
                const opt = document.createElement('div');
                opt.className = 'modal-dropdown-option' + (i === 0 ? ' selected' : '');
                opt.textContent = v;
                opt.addEventListener('click', () => {
                    selectedVariation = v;
                    dropdownBtn.innerHTML = `${v} <i class="fas fa-chevron-down"></i>`;
                    dropdownBtn.classList.remove('open');
                    dropdownList.classList.remove('open');
                    dropdownList.querySelectorAll('.modal-dropdown-option').forEach(o => o.classList.remove('selected'));
                    opt.classList.add('selected');
                    refreshModalPrice();
                });
                dropdownList.appendChild(opt);
            });

            // Set default dropdown label to "Select a variation"
            dropdownBtn.innerHTML = `Select a variation <i class="fas fa-chevron-down"></i>`;
            selectedVariation = null;

            dropdownBtn.onclick = (e) => {
                e.stopPropagation();
                dropdownBtn.classList.toggle('open');
                dropdownList.classList.toggle('open');
            };

        } else if (itemData.pieces) {
            // No variations, but has pieces info — show it without dropdown
            variationSection.style.display = 'flex';
            variationInfo.textContent = itemData.pieces;
            dropdownWrapper.style.display = 'none';
        } else {
            variationSection.style.display = 'none';
        }

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Close dropdown on outside click
        setTimeout(() => document.addEventListener('click', closeDropdownOutside), 0);
    }

    function closeDropdownOutside(e) {
        if (!e.target.closest('.modal-dropdown-wrapper')) {
            const btn  = document.getElementById('dropdownBtn');
            const list = document.getElementById('dropdownList');
            if (btn)  btn.classList.remove('open');
            if (list) list.classList.remove('open');
        }
    }

    function refreshModalPrice() {
        const price = getModalPrice();
        document.getElementById('modalPrice').textContent = fmt(price);
    }

    function closeModal() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        document.removeEventListener('click', closeDropdownOutside);

        if (lastViewedItem) {
            lastViewedItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

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

    // ── ADD TO CART (cart icon) ──
    document.getElementById('addToCartBtn').addEventListener('click', () => {
        if (!currentItem) return;

        const btn = document.getElementById('addToCartBtn');
        if (btn.disabled) return; // prevent spam click

        btn.disabled = true;
        btn.classList.add('added');
        btn.innerHTML = '<i class="fas fa-check" style="color: #7ed181;"></i>';

        addItemToCart(currentItem, quantity);

        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-cart-arrow-down" style="color: #fff;"></i>';
            btn.classList.remove('added');
            btn.disabled = false;
        }, 1500);
    });

    // ── ORDER NOW ──
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

        window.__isLoggedIn = false;

        (async function checkAuth() {
            try {
                const res = await fetch('auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ action: 'check_session' })
                });
                const data = await res.json();
                window.__isLoggedIn = !!data.success;

                if (data.success) {
                    sessionStorage.setItem('user_name',  data.name  || '');
                    sessionStorage.setItem('user_email', data.email || '');
                }
            } catch (e) {
                window.__isLoggedIn = false;
            }
            renderAuthUI();
        })();

        function renderAuthUI() {
            const notice      = document.getElementById('cartAuthNotice');
            const checkoutBtn = document.getElementById('checkoutBtn');

            if (window.__isLoggedIn) {
                const name = sessionStorage.getItem('user_name') || 'User';
                notice.className = 'cart-auth-notice signed-in';
                notice.innerHTML = `<i class="fa-solid fa-circle-check"></i> Signed in as <strong style="margin-left:4px;color:#fff;">${name}</strong>`;
                notice.style.display = 'flex';
                checkoutBtn.classList.remove('locked');
            } else {
                notice.className = 'cart-auth-notice signed-out';
                notice.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> Not signed in — <a href="account.html">log in</a> to place an order.`;
                notice.style.display = 'flex';
                checkoutBtn.classList.add('locked');
            }
        }
    });

    // =====================================================
    //  AUTH MODAL
    // =====================================================
    document.getElementById('authModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeAuthModal();
    });

});

// Global auth modal helpers (called from inline onclick in HTML)
function openAuthModal()  { document.getElementById('authModal').classList.add('open'); }
function closeAuthModal() { document.getElementById('authModal').classList.remove('open'); }
function goToSignIn() {
    sessionStorage.setItem('redirect_after_login', 'menu.html');
    window.location.href = 'account.html';
}