// Menu functionality
document.addEventListener('DOMContentLoaded', () => {
    // Elements
    const mobileMenuBtn = document.getElementById('mobile-menu');
    const navMenu = document.getElementById('nav-menu');
    const sidebarItems = document.querySelectorAll('.sidebar-item');
    const navLinks = document.querySelectorAll('.nav-menu a');
    const menuItems = document.querySelectorAll('.menu-item');
    const modal = document.getElementById('itemModal');
    const modalClose = document.getElementById('modalClose');
    const cartBtn = document.getElementById('cartBtn');
    const cartCount = document.getElementById('cartCount');
    
    // Cart data
    let cart = [];
    let currentItem = null;
    let selectedVariation = null;
    let quantity = 1;

    // Toggle mobile menu
    mobileMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        navMenu.classList.toggle('active');
        
        const icon = mobileMenuBtn.querySelector('i');
        if (navMenu.classList.contains('active')) {
            icon.classList.remove('fa-bars');
            icon.classList.add('fa-times');
        } else {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    });

    // Close menu when clicking nav links
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            navMenu.classList.remove('active');
            const icon = mobileMenuBtn.querySelector('i');
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        });
    });

    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!navMenu.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
            navMenu.classList.remove('active');
            const icon = mobileMenuBtn.querySelector('i');
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    });

    // Sidebar smooth scrolling
    sidebarItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = item.getAttribute('data-target');
            const targetElement = document.getElementById(targetId);

            if (targetElement) {
                const elementPosition = targetElement.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.scrollY - 20;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    // Menu item click - open modal
    menuItems.forEach(item => {
        item.addEventListener('click', () => {
            const itemData = JSON.parse(item.getAttribute('data-item'));
            openModal(itemData);
        });
    });

    // Open modal function
    function openModal(itemData) {
        currentItem = itemData;
        selectedVariation = null;
        quantity = 1;

        // Set modal content
        document.getElementById('modalImage').src = itemData.image;
        document.getElementById('modalImage').alt = itemData.name;
        document.getElementById('modalTitle').textContent = itemData.name;
        document.getElementById('modalPrice').textContent = itemData.price;
        document.getElementById('quantityValue').textContent = quantity;

        // Handle variations
        const variationSection = document.getElementById('variationSection');
        const variationOptions = document.getElementById('variationOptions');
        
        if (itemData.variations && itemData.variations.length > 0) {
            variationSection.style.display = 'block';
            variationOptions.innerHTML = '';
            
            itemData.variations.forEach((variation, index) => {
                const btn = document.createElement('button');
                btn.className = 'variation-btn';
                btn.textContent = variation;
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.variation-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    selectedVariation = variation;
                });
                
                // Auto-select first variation
                if (index === 0) {
                    btn.classList.add('active');
                    selectedVariation = variation;
                }
                
                variationOptions.appendChild(btn);
            });
        } else {
            variationSection.style.display = 'none';
        }

        // Show modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Close modal function
    function closeModal() {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
        currentItem = null;
        selectedVariation = null;
        quantity = 1;
    }

    // Modal close button
    modalClose.addEventListener('click', closeModal);

    // Close modal when clicking outside
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    // Quantity controls
    document.getElementById('decreaseQty').addEventListener('click', () => {
        if (quantity > 1) {
            quantity--;
            document.getElementById('quantityValue').textContent = quantity;
        }
    });

    document.getElementById('increaseQty').addEventListener('click', () => {
        quantity++;
        document.getElementById('quantityValue').textContent = quantity;
    });

    // Add to cart button
    document.getElementById('addToCartBtn').addEventListener('click', () => {
        if (currentItem) {
            const cartItem = {
                name: currentItem.name,
                price: currentItem.price,
                variation: selectedVariation,
                quantity: quantity,
                image: currentItem.image
            };

            cart.push(cartItem);
            updateCartCount();
            
            // Show success message
            const btn = document.getElementById('addToCartBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Added to Cart!';
            btn.style.backgroundColor = '#45a049';
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.style.backgroundColor = '#4CAF50';
            }, 2000);

            // Optional: close modal after adding to cart
            // closeModal();
        }
    });

    // Order Now button
    document.getElementById('orderNowBtn').addEventListener('click', () => {
        // Redirect to Foodpanda or open in new tab
        window.open('https://www.foodpanda.ph/', '_blank');
    });

    // Update cart count
    function updateCartCount() {
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        cartCount.textContent = totalItems;
        
        // Animate cart button
        cartBtn.style.transform = 'scale(1.2)';
        setTimeout(() => {
            cartBtn.style.transform = 'scale(1)';
        }, 300);
    }

    // Cart button click - show cart summary
    cartBtn.addEventListener('click', () => {
        if (cart.length === 0) {
            alert('Your cart is empty!');
            return;
        }

        let cartSummary = 'Your Cart:\n\n';
        cart.forEach((item, index) => {
            cartSummary += `${index + 1}. ${item.name}`;
            if (item.variation) {
                cartSummary += ` (${item.variation})`;
            }
            cartSummary += ` - ${item.price} x ${item.quantity}\n`;
        });
        
        cartSummary += '\nWould you like to proceed to order?';
        
        if (confirm(cartSummary)) {
            window.open('https://www.foodpanda.ph/', '_blank');
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            closeModal();
        }
    });
});