// =====================================================
//  menu.js
// =====================================================

document.addEventListener('DOMContentLoaded', () => {

    // menu.js is also loaded on carT.html — only run menu UI when the item modal exists
    if (!document.getElementById('itemModal')) return;

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

    // ── Menu Item State ──
    window.currentItem       = null;
    let selectedVariation = null;
    let quantity          = 1;
    let lastViewedItem    = null;

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
                const headerOffset = 120; // header + search + padding
                const targetPos = target.getBoundingClientRect().top + window.scrollY - headerOffset;
                window.scrollTo({
                    top: targetPos,
                    behavior: 'smooth'
                });
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

        const basePieces   = parsePieces(baseVariation);
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

    window.getModalPrice = function() {
        const basePrice     = parseRawPrice(window.currentItem.price);
        const baseVariation = window.currentItem.variations ? window.currentItem.variations[0] : null;
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

    const allMenuItems = Array.from(document.querySelectorAll('.menu-item')).map((el) => ({
        el,
        name:  JSON.parse(el.getAttribute('data-item')).name,
        price: JSON.parse(el.getAttribute('data-item')).price,
        image: JSON.parse(el.getAttribute('data-item')).image
    }));

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

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-wrapper')) {
            searchDropdown.classList.remove('open');
        }
    });

    function scrollToItem(item) {
        const HEADER   = 70;
        const SEARCH   = 50;
        const PADDING  = 10;
        const top = item.el.getBoundingClientRect().top + window.scrollY - HEADER - SEARCH - PADDING;
        window.scrollTo(0, top);

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

    function openModal(itemData) {
        window.currentItem       = itemData;
        selectedVariation = itemData.variations?.length ? itemData.variations[0] : null;
        quantity          = 1;

        document.getElementById('modalImage').src            = itemData.image;
        document.getElementById('modalImage').alt            = itemData.name;
        document.getElementById('modalTitle').textContent    = itemData.name;
        document.getElementById('quantityValue').textContent = 1;

        refreshModalPrice();

        const variationSection = document.getElementById('variationSection');
        const variationLabel   = document.getElementById('variationLabel');
        const dropdownBtn      = document.getElementById('dropdownBtn');
        const dropdownList     = document.getElementById('dropdownList');
        const variationInfo    = document.getElementById('variationInfo');
        const dropdownWrapper  = document.querySelector('.modal-dropdown-wrapper');

        const comboDescriptions = {
            'Combo E': 'Pork tonkatsu, rice & kani mango salad',
            'Combo K': '3 Tempura, 50g kani mango salad & rice',
            'Combo L': '2 Tempura, 50g kani mango salad & cali maki',
            'Combo U': 'Pork tonkatsu, 3 tempura, 50g kani mango salad & rice'
        };

        // Reset all
        dropdownBtn.style.display     = '';
        dropdownWrapper.style.display = '';
        variationInfo.textContent     = '';
        variationLabel.textContent    = '';
        variationLabel.style.display  = '';

        const comboDesc = comboDescriptions[itemData.name];

        // Handle items with variations (like California Maki)
        if (itemData.variations && itemData.variations.length > 0) {
            variationSection.style.display = 'flex';
            variationLabel.textContent = itemData.variations.length === 1 
                ? 'Available Variation:' 
                : 'Available Variations:';
            variationLabel.style.display = '';
            variationInfo.textContent = itemData.pieces || '';
            variationInfo.style.textAlign = 'right';
            variationInfo.style.whiteSpace = 'normal';
            dropdownWrapper.style.display = '';

            dropdownList.innerHTML = '';
            itemData.variations.forEach((v, i) => {
                const opt = document.createElement('div');
                opt.className = 'modal-dropdown-option' + (i === 0 ? ' selected' : '');
                opt.textContent = v;
                opt.addEventListener('click', () => {
                    selectedVariation = v;
                    dropdownBtn.innerHTML = `${v} <i class="fas fa-chevron-down"></i>`;
                    dropdownList.querySelectorAll('.modal-dropdown-option').forEach(o => o.classList.remove('selected'));
                    opt.classList.add('selected');
                    refreshModalPrice();
                });
                dropdownList.appendChild(opt);
            });

            dropdownBtn.innerHTML = `${selectedVariation} <i class="fas fa-chevron-down"></i>`;

            dropdownBtn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                const isOpen = dropdownList.classList.contains('open');
                dropdownBtn.classList.toggle('open');
                dropdownList.classList.toggle('open');
                
                if (!isOpen) {
                    const btnRect = dropdownBtn.getBoundingClientRect();
                    dropdownList.style.position = 'fixed';
                    dropdownList.style.left = btnRect.left + 'px';
                    dropdownList.style.top = (btnRect.bottom) + 'px';
                    dropdownList.style.width = btnRect.width + 'px';
                }
            };

        // Handle combo items (like Combo E, K, L, U)
        } else if (comboDesc) {
            variationSection.style.display = 'flex';
            variationLabel.style.display = 'none';
            variationInfo.textContent = comboDesc;
            variationInfo.style.whiteSpace = 'normal';
            variationInfo.style.textAlign = 'right';
            variationInfo.style.maxWidth = '100%';
            variationInfo.style.wordBreak = 'break-word';
            dropdownWrapper.style.display = 'none';

        // Handle items with pieces only (no variations)
        } else if (itemData.pieces) {
            variationSection.style.display = 'flex';
            variationLabel.textContent = 'Available Variation:';
            variationLabel.style.display = '';
            variationInfo.textContent = itemData.pieces;
            variationInfo.style.textAlign = 'right';
            variationInfo.style.whiteSpace = 'normal';
            dropdownWrapper.style.display = 'none';

        // No info at all
        } else {
            variationSection.style.display = 'none';
        }

        modal.classList.add('active');

        document.addEventListener('click', closeDropdownOutside);

    }

    function closeDropdownOutside(e) {
        if (!e.target.closest('.modal-dropdown-wrapper')) {
            const btn  = document.getElementById('dropdownBtn');
            const list = document.getElementById('dropdownList');
            if (btn)  btn.classList.remove('open');
            if (list) list.classList.remove('open');
            document.removeEventListener('click', closeDropdownOutside);
        }
    }

    function refreshModalPrice() {
        const price = window.getModalPrice();
        document.getElementById('modalPrice').textContent = fmt(price);
    }

    function closeModal() {
        modal.classList.remove('active');
        
        const btn  = document.getElementById('dropdownBtn');
        const list = document.getElementById('dropdownList');
        if (btn)  btn.classList.remove('open');
        if (list) list.classList.remove('open');
        document.removeEventListener('click', closeDropdownOutside);

        window.currentItem = null; selectedVariation = null; quantity = 1;
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
        if (!window.currentItem) return;

        const btn = document.getElementById('addToCartBtn');
        if (btn.disabled) return;

        btn.disabled = true;
        btn.classList.add('added');
        btn.innerHTML = '<i class="fas fa-check" style="color: #7ed181;"></i>';

        if (window.addItemToCart) {
            window.addItemToCart(window.currentItem, quantity, selectedVariation);
        }
        
// Pulse cart count animation
        const cartCount = document.getElementById('cartCount');
        if (cartCount) {
            cartCount.classList.add('pulse');
            setTimeout(() => cartCount.classList.remove('pulse'), 600);
            // Update cart count display
            if (window.updateCartCount) window.updateCartCount();
        }

        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-cart-arrow-down" style="color: #fff;"></i>';
            btn.classList.remove('added');
            btn.disabled = false;
        }, 1500);
    });

// ── ORDER NOW ──
    document.getElementById('orderNowBtn').addEventListener('click', () => {
        if (!window.currentItem) return;

        if (window.addItemToCart) {
            window.addItemToCart(window.currentItem, quantity, selectedVariation);
        }
        
        // Pulse cart count animation
        const cartCount = document.getElementById('cartCount');
        if (cartCount) {
            cartCount.classList.add('pulse');
            setTimeout(() => cartCount.classList.remove('pulse'), 600);
            if (window.updateCartCount) window.updateCartCount();
        }
        
        closeModal();
        if (window.openCart) window.openCart();
    });

    function updateCartCountDisplay() {
        if (window.updateCartCount) {
            window.updateCartCount();
        } else {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            const total = cart.reduce((s, i) => s + i.quantity, 0);
            cartCountEl.textContent = total;
        }
    }

    // Initialize cart count on page load
    updateCartCountDisplay();

    // ── CART BUTTON ──
    cartBtn.addEventListener('click', () => {
        if (window.openCart) window.openCart();
    });
    cartBtn.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            if (window.openCart) window.openCart();
        }
    });

});

// Global auth modal helpers
function openAuthModal()  { document.getElementById('authModal').classList.add('open'); }
function closeAuthModal() { document.getElementById('authModal').classList.remove('open'); }
function goToSignIn() {
    sessionStorage.setItem('redirect_after_login', 'menu.html');
    window.location.href = 'account.html';
}