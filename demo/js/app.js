/**
 * Demo App JS - Blinkit Tabs, Sticky Nav, AI Menu Planner
 */
document.addEventListener('DOMContentLoaded', () => {
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
