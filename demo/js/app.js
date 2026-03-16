/**
 * Demo App JS - Blinkit Tabs, Sticky Nav, Services Lightbox, AI Menu Planner
 */
document.addEventListener('DOMContentLoaded', () => {
    // --- Services Full-Screen Lightbox ---
    const lightbox = document.getElementById('mw-services-lightbox');
    const lightboxSwipe = lightbox?.querySelector('.mw-lightbox-swipe');
    const lightboxClose = lightbox?.querySelector('.mw-lightbox-close');
    const lightboxPrev = lightbox?.querySelector('.mw-lightbox-prev');
    const lightboxNext = lightbox?.querySelector('.mw-lightbox-next');
    const lightboxCurrent = document.getElementById('mw-lightbox-current');
    const serviceCards = document.querySelectorAll('.mw-service-card');
    const slides = lightbox?.querySelectorAll('.mw-lightbox-slide') || [];
    const totalSlides = slides.length;

    function openLightbox(index) {
        if (!lightbox || index < 0 || index >= totalSlides) return;
        lightbox.classList.add('open');
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        scrollToSlide(index);
        updateCounter(index);
    }

    function closeLightbox() {
        if (!lightbox) return;
        lightbox.classList.remove('open');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function scrollToSlide(index) {
        if (!lightboxSwipe || !slides[index]) return;
        const slideWidth = lightboxSwipe.offsetWidth;
        lightboxSwipe.scrollLeft = index * slideWidth;
    }

    function updateCounter(index) {
        if (lightboxCurrent) lightboxCurrent.textContent = index + 1;
    }

    function getCurrentSlideIndex() {
        if (!lightboxSwipe) return 0;
        const scrollLeft = lightboxSwipe.scrollLeft;
        const slideWidth = lightboxSwipe.offsetWidth;
        return Math.round(scrollLeft / slideWidth);
    }

    if (serviceCards.length && lightbox) {
        serviceCards.forEach(card => {
            card.addEventListener('click', (e) => {
                const idx = parseInt(card.getAttribute('data-svc-index'), 10);
                if (!isNaN(idx)) openLightbox(idx);
            });
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    const idx = parseInt(card.getAttribute('data-svc-index'), 10);
                    if (!isNaN(idx)) openLightbox(idx);
                }
            });
        });
    }

    if (lightboxClose) lightboxClose.addEventListener('click', closeLightbox);

    if (lightboxPrev) lightboxPrev.addEventListener('click', () => {
        const idx = Math.max(0, getCurrentSlideIndex() - 1);
        scrollToSlide(idx);
        updateCounter(idx);
    });

    if (lightboxNext) lightboxNext.addEventListener('click', () => {
        const idx = Math.min(totalSlides - 1, getCurrentSlideIndex() + 1);
        scrollToSlide(idx);
        updateCounter(idx);
    });

    if (lightboxSwipe) {
        lightboxSwipe.addEventListener('scroll', () => {
            if (lightbox?.classList.contains('open')) updateCounter(getCurrentSlideIndex());
        });
    }

    // Double-tap to close
    if (lightbox) {
        let lastTap = 0;
        lightbox.addEventListener('touchend', (e) => {
            const now = Date.now();
            if (now - lastTap < 300) { closeLightbox(); lastTap = 0; return; }
            lastTap = now;
        });
        lightbox.addEventListener('dblclick', closeLightbox);
    }

    // Close on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && lightbox?.classList.contains('open')) closeLightbox();
    });

    // --- Product Detail Lightbox ---
    const productLightbox = document.getElementById('mw-product-lightbox');
    const productLightboxSwipe = productLightbox?.querySelector('.mw-product-lightbox-swipe');
    const productLightboxClose = productLightbox?.querySelector('.mw-lightbox-close');
    const productLightboxPrev = productLightbox?.querySelector('.mw-product-lightbox-prev');
    const productLightboxNext = productLightbox?.querySelector('.mw-product-lightbox-next');
    const productLightboxCurrent = document.getElementById('mw-product-lightbox-current');
    const productCards = document.querySelectorAll('.mw-product-card-clickable');
    const productSlides = productLightbox?.querySelectorAll('.mw-product-lightbox-slide') || [];
    const totalProductSlides = productSlides.length;

    function openProductLightbox(index) {
        if (!productLightbox || !productLightboxSwipe || index < 0 || index >= totalProductSlides) return;
        productLightbox.classList.add('open');
        productLightbox.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        productLightboxSwipe.scrollLeft = index * productLightboxSwipe.offsetWidth;
        if (productLightboxCurrent) productLightboxCurrent.textContent = index + 1;
    }

    function closeProductLightbox() {
        if (!productLightbox) return;
        productLightbox.classList.remove('open');
        productLightbox.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function getProductSlideIndex() {
        if (!productLightboxSwipe) return 0;
        return Math.round(productLightboxSwipe.scrollLeft / productLightboxSwipe.offsetWidth);
    }

    if (productCards.length && productLightbox) {
        productCards.forEach(card => {
            card.addEventListener('click', (e) => {
                if (e.target.closest('a')) return;
                const idx = parseInt(card.getAttribute('data-product-index'), 10);
                if (!isNaN(idx)) openProductLightbox(idx);
            });
        });
    }

    if (productLightboxClose) productLightboxClose.addEventListener('click', closeProductLightbox);
    if (productLightboxPrev && productLightboxSwipe) productLightboxPrev.addEventListener('click', () => {
        const idx = Math.max(0, getProductSlideIndex() - 1);
        productLightboxSwipe.scrollLeft = idx * productLightboxSwipe.offsetWidth;
        if (productLightboxCurrent) productLightboxCurrent.textContent = idx + 1;
    });
    if (productLightboxNext && productLightboxSwipe) productLightboxNext.addEventListener('click', () => {
        const idx = Math.min(totalProductSlides - 1, getProductSlideIndex() + 1);
        productLightboxSwipe.scrollLeft = idx * productLightboxSwipe.offsetWidth;
        if (productLightboxCurrent) productLightboxCurrent.textContent = idx + 1;
    });

    if (productLightboxSwipe && productLightboxCurrent) {
        productLightboxSwipe.addEventListener('scroll', () => {
            if (productLightbox?.classList.contains('open')) productLightboxCurrent.textContent = getProductSlideIndex() + 1;
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && productLightbox?.classList.contains('open')) closeProductLightbox();
    });

    if (productLightbox) {
        let lastTap = 0;
        productLightbox.addEventListener('touchend', (e) => {
            const now = Date.now();
            if (now - lastTap < 300) { closeProductLightbox(); lastTap = 0; return; }
            lastTap = now;
        });
        productLightbox.addEventListener('dblclick', closeProductLightbox);
    }

    // --- Videos Load More ---
    const loadMoreBtn = document.getElementById('mw-videos-load-more');
    const videoHiddenItems = document.querySelectorAll('.mw-video-item.mw-video-hidden');
    if (loadMoreBtn && videoHiddenItems.length) {
        loadMoreBtn.addEventListener('click', () => {
            videoHiddenItems.forEach(el => el.classList.remove('mw-video-hidden'));
            loadMoreBtn.parentElement.style.display = 'none';
        });
    }

    // --- Sticky Nav: Smooth scroll + active state ---
    const navItems = document.querySelectorAll('.mw-sticky-nav .mw-nav-item');
    const sections = ['mw-hero', 'mw-services', 'mw-offers', 'mw-products', 'mw-gallery', 'mw-pay'];

    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            const targetId = item.getAttribute('href');
            if (targetId && targetId.startsWith('#')) {
                const el = document.querySelector(targetId);
                if (el) {
                    e.preventDefault();
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    });

    // Update active nav item based on scroll position
    function updateActiveNav() {
        const scrollY = window.scrollY || window.pageYOffset;
        let activeId = 'mw-hero';
        const offset = 120;

        for (let i = sections.length - 1; i >= 0; i--) {
            const el = document.getElementById(sections[i]);
            if (el) {
                const top = el.getBoundingClientRect().top + scrollY;
                if (scrollY >= top - offset) {
                    activeId = sections[i];
                    break;
                }
            }
        }

        navItems.forEach(item => {
            const section = item.getAttribute('data-section');
            item.classList.toggle('active', section === activeId);
        });
    }

    let scrollTicking = false;
    window.addEventListener('scroll', () => {
        if (!scrollTicking) {
            requestAnimationFrame(() => {
                updateActiveNav();
                scrollTicking = false;
            });
            scrollTicking = true;
        }
    }, { passive: true });
    updateActiveNav(); // Initial state

    // --- Shop Cart: Add multiple products, then Share on WhatsApp ---
    const products = window.MW_PRODUCTS || [];
    const whatsappNum = (window.MW_WHATSAPP_NUMBER || '').replace(/[^0-9]/g, '');
    const cartBar = document.getElementById('mw-shop-cart-bar');
    const cartCountEl = document.getElementById('mw-cart-count');
    const cartShareBtn = document.getElementById('mw-cart-share-wa');
    const floatingWaBtn = document.getElementById('mw-floating-wa-btn');

    const cart = []; // { index, name, price, qty }

    function isInCart(idx) {
        return cart.some(c => c.index === idx);
    }

    function updateCartUI() {
        const totalQty = cart.reduce((s, i) => s + i.qty, 0);
        if (cartCountEl) cartCountEl.textContent = totalQty;
        if (cartBar) {
            if (totalQty > 0) {
                cartBar.classList.remove('hidden');
                if (floatingWaBtn) floatingWaBtn.classList.add('hidden');
            } else {
                cartBar.classList.add('hidden');
                if (floatingWaBtn) floatingWaBtn.classList.remove('hidden');
            }
        }
        // Update ADD/REMOVE button text
        document.querySelectorAll('.mw-add-to-cart').forEach(btn => {
            const idx = btn.getAttribute('data-product-index');
            btn.textContent = idx != null && isInCart(parseInt(idx, 10)) ? 'REMOVE' : 'ADD';
        });
    }

    function addToCart(productIndex) {
        const idx = parseInt(productIndex, 10);
        if (isNaN(idx) || !products[idx]) return;
        const p = products[idx];
        const existing = cart.find(c => c.index === idx);
        if (existing) existing.qty += 1;
        else cart.push({ index: idx, name: p.name, price: p.price, qty: 1 });
        updateCartUI();
    }

    function removeFromCart(productIndex) {
        const idx = parseInt(productIndex, 10);
        const i = cart.findIndex(c => c.index === idx);
        if (i >= 0) {
            cart.splice(i, 1);
            updateCartUI();
        }
    }

    document.querySelectorAll('.mw-add-to-cart').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const idx = btn.getAttribute('data-product-index');
            if (idx == null) return;
            const n = parseInt(idx, 10);
            if (isInCart(n)) removeFromCart(idx);
            else addToCart(idx);
        });
    });

    if (cartShareBtn && whatsappNum) {
        cartShareBtn.addEventListener('click', () => {
            if (cart.length === 0) return;
            let msg = 'Hi! I want to order:\n\n';
            let total = 0;
            cart.forEach(item => {
                msg += `• ${item.name} x ${item.qty} = ₹${(item.price * item.qty).toLocaleString()}\n`;
                total += item.price * item.qty;
            });
            msg += `\nTotal: ₹${total.toLocaleString()}`;
            window.open(`https://wa.me/${whatsappNum}?text=${encodeURIComponent(msg)}`, '_blank');
        });
    }

    // --- Blinkit Product UI Tab Switching Logic ---
    const categoryItems = document.querySelectorAll('.mw-cat-item');
    const productGrids = document.querySelectorAll('.product-category-grid');

    categoryItems.forEach(item => {
        item.addEventListener('click', () => {
            // Remove active from all tabs
            categoryItems.forEach(c => c.classList.remove('active'));
            // Add active to clicked
            item.classList.add('active');

            // Hide all grids
            productGrids.forEach(g => {
                g.classList.add('hidden');
                g.classList.remove('active');
            });

            // Show target grid
            const targetCat = item.getAttribute('data-cat');
            const targetGrid = document.getElementById('grid-' + targetCat);
            if (targetGrid) {
                targetGrid.classList.remove('hidden');
                targetGrid.classList.add('active');
            }
        });
    });

    // --- Gemini AI Menu Logic ---
    const apiKey = window.MW_AI_API_KEY || '';
    const inputEl = document.getElementById('ai-event-input');
    const generateBtn = document.getElementById('ai-generate-btn');
    const loadingEl = document.getElementById('ai-loading');
    const errorEl = document.getElementById('ai-error');
    const resultContainer = document.getElementById('ai-result-container');
    const resultContent = document.getElementById('ai-result-content');
    const bookBtn = document.getElementById('ai-book-btn');
    const whatsappNumber = window.MW_WHATSAPP_NUMBER || '1234567890';

    async function fetchWithRetry(url, options, maxRetries = 5) {
        let delays = [1000, 2000, 4000, 8000, 16000];
        for (let i = 0; i < maxRetries; i++) {
            try {
                const response = await fetch(url, options);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return await response.json();
            } catch (error) {
                if (i === maxRetries - 1) throw error;
                await new Promise(res => setTimeout(res, delays[i]));
            }
        }
    }

    if (generateBtn) {
        generateBtn.addEventListener('click', async () => {
            const prompt = inputEl ? inputEl.value.trim() : '';
            if (!prompt) {
                if (errorEl) {
                    errorEl.textContent = "Please describe your event first!";
                    errorEl.classList.remove('hidden');
                }
                return;
            }

            if (errorEl) errorEl.classList.add('hidden');
            if (resultContainer) resultContainer.classList.add('hidden');
            generateBtn.classList.add('hidden');
            if (loadingEl) loadingEl.classList.remove('hidden');

            const systemInstruction = "You are an AI assistant for Executive Chef Olivia Murray. Generate a custom, elegant 3-course gourmet menu based on the user's event. Respond in a warm, appetizing tone. Format the output cleanly with headings. Keep it concise.";
            const payload = { contents: [{ parts: [{ text: prompt }] }], systemInstruction: { parts: [{ text: systemInstruction }] } };

            try {
                const data = await fetchWithRetry(`https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=${apiKey}`, {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
                });
                const text = data.candidates?.[0]?.content?.parts?.[0]?.text;
                if (text) {
                    if (resultContent) {
                        resultContent.innerHTML = text.replace(/\*\*(.*?)\*\*/g, '<strong class="text-heading font-medium">$1</strong>').replace(/\n/g, '<br>');
                    }
                    if (resultContainer) resultContainer.classList.remove('hidden');
                    const waText = encodeURIComponent(`Hi Chef Olivia! I used your AI Menu Planner. Request: ${prompt}\n\nMenu:\n${text.replace(/\*\*/g, '')}`);
                    if (bookBtn) bookBtn.onclick = () => window.open(`https://wa.me/${whatsappNumber.replace(/^\+/, '')}?text=${waText}`, '_blank');
                } else throw new Error("No menu generated.");
            } catch (error) {
                if (errorEl) {
                    errorEl.textContent = "Oops! Error generating menu. Please try again.";
                    errorEl.classList.remove('hidden');
                }
            } finally {
                if (loadingEl) loadingEl.classList.add('hidden');
                generateBtn.classList.remove('hidden');
                generateBtn.innerHTML = "Regenerate Menu ✨";
            }
        });
    }
});
