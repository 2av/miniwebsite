/**
 * MiniWebsite Core UI Engine – MW_SelectionStore, modals, sticky nav, WhatsApp CTA
 * Same behaviour for all templates.
 */
(function () {
  'use strict';

  var SelectionStore = {
    items: [],
    add: function (product) {
      if (this.items.some(function (p) { return p.id === product.id; })) return;
      this.items.push(product);
      this._sync();
    },
    remove: function (id) {
      this.items = this.items.filter(function (p) { return p.id !== id; });
      this._sync();
    },
    toggle: function (product) {
      var idx = this.items.findIndex(function (p) { return p.id === product.id; });
      if (idx >= 0) this.remove(product.id);
      else this.add(product);
    },
    has: function (id) {
      return this.items.some(function (p) { return p.id === id; });
    },
    clear: function () {
      this.items = [];
      this._sync();
    },
    _sync: function () {
      document.querySelectorAll('.mw-btn-add').forEach(function (btn) {
        var id = btn.getAttribute('data-product-id');
        if (!id) return;
        btn.textContent = SelectionStore.has(id) ? '✔ ADDED' : 'ADD';
        btn.classList.toggle('added', SelectionStore.has(id));
      });
      document.querySelectorAll('.mw-product-modal [data-product-id]').forEach(function (el) {
        var id = el.getAttribute('data-product-id');
        if (!id) return;
        var toggle = el.querySelector('.mw-btn-add, [data-add-toggle]');
        if (toggle) {
          toggle.textContent = SelectionStore.has(id) ? '✔ ADDED' : 'ADD';
          toggle.classList.toggle('added', SelectionStore.has(id));
        }
      });
      updateFloatingCTA();
    }
  };

  function updateFloatingCTA() {
    var cta = document.getElementById('mw-floating-cta');
    if (!cta) return;
    if (SelectionStore.items.length > 0) {
      cta.setAttribute('data-state', 'send_products');
      var label = cta.querySelector('.mw-floating-cta__label');
      if (label) label.textContent = '🛒 Send ' + SelectionStore.items.length + ' Product(s)';
    } else {
      cta.setAttribute('data-state', 'whatsapp');
      var label = cta.querySelector('.mw-floating-cta__label');
      if (label) label.textContent = '';
    }
  }

  function getWhatsAppUrl() {
    var cta = document.getElementById('mw-floating-cta');
    var phone = (cta && cta.getAttribute('data-phone')) ? cta.getAttribute('data-phone').replace(/\D/g, '') : '';
    var base = 'https://api.whatsapp.com/send?phone=' + (phone || '91');
    if (SelectionStore.items.length > 0) {
      var lines = ['Hello,', 'I am interested in the below products:'];
      SelectionStore.items.forEach(function (p, i) {
        lines.push((i + 1) + '. ' + p.name + ' – ₹' + (p.price || ''));
      });
      var url = (typeof window.MW_SITE_URL !== 'undefined' && window.MW_SITE_URL) ? window.MW_SITE_URL : (window.location.origin + window.location.pathname);
      lines.push('MiniWebsite: ' + url);
      var text = lines.join('\n');
      return base + '&text=' + encodeURIComponent(text);
    }
    var defaultText = (cta && cta.getAttribute('data-default-text')) ? cta.getAttribute('data-default-text') : 'Hi, I found your MiniWebsite.';
    return base + '&text=' + encodeURIComponent(defaultText);
  }

  function initFloatingCTA() {
    var cta = document.getElementById('mw-floating-cta');
    if (!cta) return;
    cta.addEventListener('click', function (e) {
      e.preventDefault();
      window.open(getWhatsAppUrl(), '_blank');
    });
    updateFloatingCTA();
  }

  function initProductCards() {
    document.addEventListener('click', function (e) {
      var addBtn = e.target.closest('.mw-btn-add');
      var card = e.target.closest('.mw-product-card');
      if (addBtn && addBtn.getAttribute('data-product-id')) {
        e.preventDefault();
        var id = addBtn.getAttribute('data-product-id');
        var name = addBtn.getAttribute('data-product-name');
        var price = addBtn.getAttribute('data-product-price');
        var cat = addBtn.getAttribute('data-product-category');
        SelectionStore.toggle({ id: id, name: name, price: price, category: cat });
        return;
      }
      if (card && (e.target.classList.contains('mw-product-card__img') || e.target.closest('.mw-product-card__img'))) {
        e.preventDefault();
        var modal = document.getElementById('mw-product-modal');
        if (!modal) return;
        var pid = card.getAttribute('data-product-id');
        openProductModal(pid);
      }
    });
  }

  var currentProductIndex = 0;
  var currentCategoryProducts = [];

  function openProductModal(productId) {
    var modal = document.getElementById('mw-product-modal');
    if (!modal) return;
    var products = Array.from(document.querySelectorAll('.mw-product-card[data-product-id]'));
    currentCategoryProducts = products.map(function (p) {
      return {
        id: p.getAttribute('data-product-id'),
        name: p.querySelector('.mw-product-card__name') && p.querySelector('.mw-product-card__name').textContent,
        price: p.querySelector('.mw-price-selling') && p.querySelector('.mw-price-selling').textContent,
        desc: p.querySelector('.mw-product-card__desc') && p.querySelector('.mw-product-card__desc').textContent,
        img: p.querySelector('.mw-product-card__img') && p.querySelector('.mw-product-card__img').src
      };
    });
    currentProductIndex = currentCategoryProducts.findIndex(function (p) { return p.id === productId; });
    if (currentProductIndex < 0) currentProductIndex = 0;
    renderProductModalContent();
    modal.classList.add('active');
  }

  function renderProductModalContent() {
    var modal = document.getElementById('mw-product-modal');
    if (!modal || !currentCategoryProducts.length) return;
    var p = currentCategoryProducts[currentProductIndex];
    if (!p) return;
    var imgEl = modal.querySelector('.mw-product-modal__img');
    if (imgEl) { imgEl.src = p.img || ''; imgEl.alt = (p.name || '') + ' by ' + (window.MW_BUSINESS_NAME || ''); }
    var nameEl = modal.querySelector('.mw-product-modal__name');
    if (nameEl) nameEl.textContent = p.name || '';
    var priceEl = modal.querySelector('.mw-product-modal__price');
    if (priceEl) priceEl.textContent = p.price || '';
    var descEl = modal.querySelector('.mw-product-description');
    if (descEl) descEl.textContent = p.desc || '';
    var addToggle = modal.querySelector('[data-add-toggle]');
    if (addToggle) {
      addToggle.setAttribute('data-product-id', p.id);
      addToggle.setAttribute('data-product-name', p.name);
      addToggle.setAttribute('data-product-price', (p.price || '').replace(/[^\d.]/g, ''));
      addToggle.textContent = SelectionStore.has(p.id) ? '✔ ADDED' : 'ADD';
      addToggle.classList.toggle('added', SelectionStore.has(p.id));
    }
  }

  function closeProductModal() {
    var modal = document.getElementById('mw-product-modal');
    if (modal) modal.classList.remove('active');
  }

  function initProductModal() {
    var modal = document.getElementById('mw-product-modal');
    if (!modal) return;
    var backBtn = modal.querySelector('.mw-modal__back');
    if (backBtn) backBtn.addEventListener('click', closeProductModal);
    var addToggle = modal.querySelector('[data-add-toggle]');
    if (addToggle) addToggle.addEventListener('click', function () {
      var id = this.getAttribute('data-product-id');
      var name = this.getAttribute('data-product-name');
      var price = this.getAttribute('data-product-price');
      SelectionStore.toggle({ id: id, name: name, price: price });
    });
    var leftBtn = modal.querySelector('[data-swipe-prev]');
    var rightBtn = modal.querySelector('[data-swipe-next]');
    if (leftBtn) leftBtn.addEventListener('click', function () {
      if (currentProductIndex > 0) { currentProductIndex--; renderProductModalContent(); }
    });
    if (rightBtn) rightBtn.addEventListener('click', function () {
      if (currentProductIndex < currentCategoryProducts.length - 1) { currentProductIndex++; renderProductModalContent(); }
    });
  }

  function initCategoryBar() {
    function showCategory(catId) {
      document.querySelectorAll('.mw-category-item').forEach(function (c) {
        c.classList.toggle('active', c.getAttribute('data-category-id') === catId);
      });
      document.querySelectorAll('.mw-product-card').forEach(function (card) {
        card.style.display = (card.getAttribute('data-category-id') === catId) ? '' : 'none';
      });
    }
    var firstCat = document.querySelector('.mw-category-item.active');
    if (firstCat) showCategory(firstCat.getAttribute('data-category-id'));
    document.querySelectorAll('.mw-category-item').forEach(function (el) {
      el.addEventListener('click', function () { showCategory(this.getAttribute('data-category-id')); });
    });
  }

  function initImageZoom() {
    document.querySelectorAll('.mw-gallery-img, .mw-service-card__img, .mw-payment__img').forEach(function (img) {
      img.addEventListener('click', function () {
        var wrap = document.getElementById('mw-image-zoom-modal');
        if (!wrap) return;
        wrap.querySelector('img').src = this.src;
        wrap.classList.add('active');
      });
    });
    var zoomModal = document.getElementById('mw-image-zoom-modal');
    if (zoomModal) {
      var closeBtn = zoomModal.querySelector('.mw-modal-close');
      if (closeBtn) closeBtn.addEventListener('click', function () { zoomModal.classList.remove('active'); });
      zoomModal.addEventListener('click', function (e) {
        if (e.target === zoomModal) zoomModal.classList.remove('active');
      });
    }
  }

  function initVideoModal() {
    document.querySelectorAll('.mw-video-card').forEach(function (card) {
      card.addEventListener('click', function () {
        var url = this.getAttribute('data-embed-url');
        var modal = document.getElementById('mw-video-modal');
        if (!modal || !url) return;
        var iframe = modal.querySelector('iframe');
        if (iframe) iframe.src = url;
        modal.classList.add('active');
      });
    });
    var modal = document.getElementById('mw-video-modal');
    if (modal) {
      var closeBtn = modal.querySelector('.mw-modal-close');
      if (closeBtn) closeBtn.addEventListener('click', function () {
        var iframe = modal.querySelector('iframe');
        if (iframe) iframe.src = '';
        modal.classList.remove('active');
      });
    }
  }

  function initStickyNav() {
    var nav = document.querySelector('.mw-sticky-nav');
    if (!nav) return;
    var sectionIds = { home: 'mw-hero', about: 'mw-about', shop: 'mw-products', videos: 'mw-videos', gallery: 'mw-gallery', payment: 'mw-payment' };
    nav.querySelectorAll('.mw-nav-item').forEach(function (item) {
      var key = item.getAttribute('data-nav');
      var id = sectionIds[key];
      if (!id) return;
      item.addEventListener('click', function () {
        var el = document.getElementById(id);
        if (el) el.scrollIntoView({ behavior: 'smooth' });
      });
    });
    function setActiveNav() {
      var sections = ['mw-hero', 'mw-about', 'mw-products', 'mw-videos', 'mw-gallery', 'mw-payment'];
      var top = window.scrollY + 80;
      var activeId = 'home';
      sections.forEach(function (id) {
        var el = document.getElementById(id);
        if (el && el.offsetTop <= top) activeId = id === 'mw-hero' ? 'home' : id.replace('mw-', '');
      });
      nav.querySelectorAll('.mw-nav-item').forEach(function (item) {
        var key = item.getAttribute('data-nav');
        item.classList.toggle('active', (key === activeId));
      });
    }
    window.addEventListener('scroll', setActiveNav);
    setActiveNav();
  }

  function init() {
    initFloatingCTA();
    initProductCards();
    initProductModal();
    initCategoryBar();
    initImageZoom();
    initVideoModal();
    initStickyNav();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.MW_SelectionStore = SelectionStore;
})();
