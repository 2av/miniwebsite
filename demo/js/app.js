/**
 * Demo App JS - Blinkit Tabs, Sticky Nav, Services Lightbox, AI Menu Planner
 */
document.addEventListener('DOMContentLoaded', () => {
    // --- Services: Desktop inline expanded box (within product box, with prev/next) ---
    const serviceCards = document.querySelectorAll('.mw-service-card');
    const servicesSection = document.getElementById('mw-services');
    const expandedBox = document.getElementById('mw-service-expanded-box');
    const expandedImg = document.getElementById('mw-service-expanded-img');
    const expandedTitle = document.getElementById('mw-service-expanded-title');
    const expandedDesc = document.getElementById('mw-service-expanded-desc');
    const expandedCounter = document.getElementById('mw-service-expanded-counter');
    const totalServices = serviceCards.length;

    const isMobile = () => window.matchMedia('(max-width: 767px)').matches;

    let currentServiceIndex = 0;

    function updateExpandedContent(index) {
        if (index < 0 || index >= totalServices) return;
        const card = serviceCards[index];
        if (!card) return;
        currentServiceIndex = index;
        const imgEl = card.querySelector('.mw-service-image-wrap img');
        const nameEl = card.querySelector('h3');
        const descEl = card.querySelector('.mw-service-desc-full');
        if (expandedImg) expandedImg.src = imgEl?.src || imgEl?.getAttribute('src') || '';
        if (expandedImg) expandedImg.alt = nameEl?.textContent || '';
        if (expandedTitle) expandedTitle.textContent = nameEl?.textContent || '';
        if (expandedDesc) expandedDesc.innerHTML = descEl?.innerHTML || descEl?.textContent || 'Contact us for details.';
        if (expandedCounter) expandedCounter.textContent = index + 1;
    }

    function openExpandedBox(index) {
        if (index < 0 || index >= totalServices || !servicesSection || !expandedBox) return;
        updateExpandedContent(index);
        servicesSection.classList.add('mw-services-expanded-active');
        expandedBox.setAttribute('aria-hidden', 'false');
        expandedBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function closeExpandedBox() {
        if (!servicesSection || !expandedBox) return;
        servicesSection.classList.remove('mw-services-expanded-active');
        expandedBox.setAttribute('aria-hidden', 'true');
    }

    if (serviceCards.length && expandedBox) {
        serviceCards.forEach(card => {
            card.addEventListener('click', (e) => {
                if (e.target.closest('.mw-service-read-more')) return;
                if (isMobile()) return;
                const idx = parseInt(card.getAttribute('data-svc-index'), 10);
                if (!isNaN(idx)) openExpandedBox(idx);
            });
            card.addEventListener('keydown', (e) => {
                if (e.target.closest('.mw-service-read-more')) return;
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    if (isMobile()) return;
                    const idx = parseInt(card.getAttribute('data-svc-index'), 10);
                    if (!isNaN(idx)) openExpandedBox(idx);
                }
            });
        });
    }

    document.querySelectorAll('.mw-service-read-more').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const card = btn.closest('.mw-service-card');
            if (!card) return;
            const preview = card.querySelector('.mw-service-desc-preview');
            const full = card.querySelector('.mw-service-desc-full');
            const isExpanded = full && !full.classList.contains('hidden');
            if (isExpanded) {
                full.classList.add('hidden');
                preview?.classList.remove('hidden');
                btn.textContent = 'Read more';
            } else {
                preview?.classList.add('hidden');
                full?.classList.remove('hidden');
                btn.textContent = 'Read less';
            }
        });
    });

    document.querySelectorAll('.mw-offer-read-more').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const card = btn.closest('.mw-offer-card');
            if (!card) return;
            const preview = card.querySelector('.mw-offer-desc-preview');
            const full = card.querySelector('.mw-offer-desc-full');
            const isExpanded = full && !full.classList.contains('hidden');
            if (isExpanded) {
                full.classList.add('hidden');
                preview?.classList.remove('hidden');
                btn.textContent = 'Read more';
            } else {
                preview?.classList.add('hidden');
                full?.classList.remove('hidden');
                btn.textContent = 'Read less';
            }
        });
    });

    const expandedClose = document.querySelector('.mw-service-expanded-close');
    const expandedPrev = document.querySelector('.mw-service-expanded-prev');
    const expandedNext = document.querySelector('.mw-service-expanded-next');

    if (expandedClose) expandedClose.addEventListener('click', (e) => { e.stopPropagation(); closeExpandedBox(); });
    if (expandedPrev) expandedPrev.addEventListener('click', (e) => {
        e.stopPropagation();
        const idx = Math.max(0, currentServiceIndex - 1);
        updateExpandedContent(idx);
    });
    if (expandedNext) expandedNext.addEventListener('click', (e) => {
        e.stopPropagation();
        const idx = Math.min(totalServices - 1, currentServiceIndex + 1);
        updateExpandedContent(idx);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && servicesSection?.classList.contains('mw-services-expanded-active')) closeExpandedBox();
    });

    document.querySelectorAll('.mw-product-read-more').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const card = btn.closest('.mw-product-card');
            if (!card) return;
            const preview = card.querySelector('.mw-product-desc-preview');
            const full = card.querySelector('.mw-product-desc-full');
            const isExpanded = full && !full.classList.contains('hidden');
            if (isExpanded) {
                full.classList.add('hidden');
                preview?.classList.remove('hidden');
                btn.textContent = 'Read more';
            } else {
                preview?.classList.add('hidden');
                full?.classList.remove('hidden');
                btn.textContent = 'Read less';
            }
        });
    });

    // --- Products: Desktop inline expanded box (like Services, on image click) ---
    const productsSection = document.getElementById('mw-products');
    const productExpandedBox = document.getElementById('mw-product-expanded-box');
    const productExpandedImg = document.getElementById('mw-product-expanded-img');
    const productExpandedTitle = document.getElementById('mw-product-expanded-title');
    const productExpandedDesc = document.getElementById('mw-product-expanded-desc');
    const productExpandedMrp = document.getElementById('mw-product-expanded-mrp');
    const productExpandedPrice = document.getElementById('mw-product-expanded-price');
    const productExpandedCounter = document.getElementById('mw-product-expanded-counter');
    const productExpandedAdd = document.getElementById('mw-product-expanded-add');
    const productsData = window.MW_PRODUCTS || [];
    const totalProducts = productsData.length;

    let currentProductIndex = 0;

    function updateProductExpandedContent(index) {
        if (index < 0 || index >= totalProducts || !productsData[index]) return;
        const p = productsData[index];
        currentProductIndex = index;
        if (productExpandedImg) productExpandedImg.src = p.image || '';
        if (productExpandedImg) productExpandedImg.alt = p.name || '';
        if (productExpandedTitle) productExpandedTitle.textContent = p.name || '';
        if (productExpandedDesc) productExpandedDesc.textContent = p.desc || 'Contact us for details.';
        if (productExpandedMrp) {
            if (p.mrp && p.mrp > p.price) {
                productExpandedMrp.textContent = '₹' + (p.mrp || 0).toLocaleString();
                productExpandedMrp.classList.remove('hidden');
            } else {
                productExpandedMrp.classList.add('hidden');
            }
        }
        if (productExpandedPrice) productExpandedPrice.textContent = '₹' + (p.price || 0).toLocaleString();
        if (productExpandedCounter) productExpandedCounter.textContent = index + 1;
        if (productExpandedAdd) productExpandedAdd.setAttribute('data-product-index', String(index));
    }

    function openProductExpandedBox(index) {
        if (index < 0 || index >= totalProducts || !productsSection || !productExpandedBox) return;
        updateProductExpandedContent(index);
        productsSection.classList.add('mw-products-expanded-active');
        productExpandedBox.setAttribute('aria-hidden', 'false');
        productExpandedBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function closeProductExpandedBox() {
        if (!productsSection || !productExpandedBox) return;
        productsSection.classList.remove('mw-products-expanded-active');
        productExpandedBox.setAttribute('aria-hidden', 'true');
    }

    document.querySelectorAll('.mw-product-click-area').forEach(area => {
        area.addEventListener('click', (e) => {
            if (e.target.closest('.mw-add-to-cart')) return;
            const idx = parseInt(area.getAttribute('data-product-index'), 10);
            if (!isNaN(idx)) openProductExpandedBox(idx);
        });
        area.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const idx = parseInt(area.getAttribute('data-product-index'), 10);
                if (!isNaN(idx)) openProductExpandedBox(idx);
            }
        });
    });

    const productExpandedClose = document.querySelector('.mw-product-expanded-close');
    const productExpandedPrev = document.querySelector('.mw-product-expanded-prev');
    const productExpandedNext = document.querySelector('.mw-product-expanded-next');

    if (productExpandedClose) productExpandedClose.addEventListener('click', (e) => { e.stopPropagation(); closeProductExpandedBox(); });
    if (productExpandedPrev) productExpandedPrev.addEventListener('click', (e) => {
        e.stopPropagation();
        const idx = Math.max(0, currentProductIndex - 1);
        updateProductExpandedContent(idx);
    });
    if (productExpandedNext) productExpandedNext.addEventListener('click', (e) => {
        e.stopPropagation();
        const idx = Math.min(totalProducts - 1, currentProductIndex + 1);
        updateProductExpandedContent(idx);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && productsSection?.classList.contains('mw-products-expanded-active')) closeProductExpandedBox();
    });

    // --- Videos: Load More + Modal (play within Miniwebsite) ---
    const loadMoreBtn = document.getElementById('mw-videos-load-more');
    const loadMoreWrap = document.getElementById('mw-videos-load-more-wrap');
    if (loadMoreBtn && loadMoreWrap) {
        loadMoreBtn.addEventListener('click', () => {
            document.querySelectorAll('.mw-video-item.mw-video-hidden').forEach(el => el.classList.remove('mw-video-hidden'));
            loadMoreWrap.style.display = 'none';
        });
    }

    const videoModal = document.getElementById('mw-video-modal');
    const videoModalIframe = document.getElementById('mw-video-modal-iframe');
    const videoModalClose = document.querySelector('.mw-video-modal-close');
    document.querySelectorAll('.mw-video-item').forEach(item => {
        item.addEventListener('click', (e) => {
            const embedUrl = item.getAttribute('data-video-url') || item.getAttribute('data-video-fallback');
            if (!embedUrl || !videoModal || !videoModalIframe) return;
            videoModalIframe.src = embedUrl;
            videoModal.classList.add('open');
            videoModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        });
        item.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                item.click();
            }
        });
    });

    function closeVideoModal() {
        if (!videoModal || !videoModalIframe) return;
        videoModal.classList.remove('open');
        videoModal.setAttribute('aria-hidden', 'true');
        videoModalIframe.src = '';
        document.body.style.overflow = '';
    }
    if (videoModalClose) videoModalClose.addEventListener('click', closeVideoModal);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && videoModal?.classList.contains('open')) closeVideoModal();
    });

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

    // --- Share Profile Section ---
    const shareUrl = window.MW_SHARE_URL || '';
    const heroName = window.MW_HERO_NAME || '';
    const location = window.MW_LOCATION || '';
    const phone = window.MW_PHONE || '';
    const email = window.MW_EMAIL || '';
    const shareWaInput = document.getElementById('mw-share-wa-input');
    const shareWaBtn = document.getElementById('mw-share-wa-btn');
    const saveContactBtn = document.getElementById('mw-save-contact-btn');
    const shareLinkBtn = document.getElementById('mw-share-link-btn');

    function showToast(msg) {
        const t = document.createElement('div');
        t.className = 'fixed bottom-24 left-1/2 -translate-x-1/2 bg-heading text-bgbase px-4 py-2 rounded-theme text-sm font-medium shadow-lg z-[60] transition-opacity duration-300';
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 2000);
    }

    if (shareWaBtn && shareUrl) {
        shareWaBtn.addEventListener('click', () => {
            const num = (shareWaInput?.value || '').replace(/[^0-9]/g, '');
            const msg = `Hello 😊

This is ${heroName} from ${location}.

We have created a MiniWebsite of our business. 
Now you can easily check our products/services and offers online here:

👉 ${shareUrl}

If you need anything, just send a message on WhatsApp 👍

Thanks a lot for your support 🙏`;
            const text = encodeURIComponent(msg);
            if (num) {
                window.open(`https://wa.me/${num}?text=${text}`, '_blank');
            } else {
                window.open(`https://api.whatsapp.com/send?text=${text}`, '_blank');
            }
        });
    }

    if (saveContactBtn && heroName) {
        saveContactBtn.addEventListener('click', () => {
            const vcard = [
                'BEGIN:VCARD',
                'VERSION:3.0',
                `FN:${heroName.replace(/[,;\\]/g, '')}`,
                `TEL;TYPE=CELL:${phone ? '+' + phone : ''}`,
                email ? `EMAIL:${email}` : '',
                `URL:${shareUrl}`,
                `NOTE:Profile - ${shareUrl}`,
                'END:VCARD'
            ].filter(Boolean).join('\n');
            const blob = new Blob([vcard], { type: 'text/vcard' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = (heroName.replace(/\s+/g, '_') + '.vcf').replace(/[^a-zA-Z0-9_.-]/g, '');
            a.click();
            URL.revokeObjectURL(a.href);
            showToast('Contact saved!');
        });
    }

    if (shareLinkBtn && shareUrl) {
        shareLinkBtn.addEventListener('click', () => {
            if (navigator.share) {
                navigator.share({
                    title: heroName + ' - Profile',
                    text: 'Check out this profile',
                    url: shareUrl
                }).then(() => showToast('Shared!')).catch(() => copyAndToast());
            } else {
                copyAndToast();
            }
        });
    }

    function copyAndToast() {
        if (navigator.clipboard?.writeText) {
            navigator.clipboard.writeText(shareUrl).then(() => showToast('Link copied!')).catch(() => showToast(shareUrl));
        } else {
            showToast(shareUrl);
        }
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
