/* ==========================================================================
   iPharmaLink :: Front-end behaviour
   Progressive enhancement only — every page works without JS.
   ========================================================================== */

(function () {
    'use strict';

    const IPL = {
        csrf: () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
        base: () => document.body.dataset.base || '',

        /**
         * POST JSON to an endpoint. Always sends the CSRF token.
         * Returns the parsed payload; throws an Error carrying .payload.
         */
        async post(url, data = {}) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': IPL.csrf(),
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify(Object.assign({ _token: IPL.csrf() }, data))
            });
            let payload = {};
            try { payload = await response.json(); } catch (e) { /* non-JSON */ }
            if (!response.ok || payload.ok === false) {
                const error = new Error(payload.message || 'Something went wrong. Please try again.');
                error.payload = payload;
                error.status = response.status;
                throw error;
            }
            return payload;
        },

        async get(url) {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            return response.json();
        },

        toast(message, type = 'success') {
            let host = document.querySelector('.toast-container');
            if (!host) {
                host = document.createElement('div');
                host.className = 'toast-container position-fixed top-0 end-0 p-3';
                document.body.appendChild(host);
            }
            const id = 'toast-' + Date.now() + '-' + Math.random().toString(36).slice(2, 7);
            const bg = { success: 'text-bg-success', danger: 'text-bg-danger', warning: 'text-bg-warning', info: 'text-bg-dark' }[type] || 'text-bg-dark';
            const el = document.createElement('div');
            el.id = id;
            el.className = 'toast align-items-center border-0 ' + bg;
            el.setAttribute('role', 'alert');
            el.innerHTML = '<div class="d-flex"><div class="toast-body"></div>' +
                '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
            el.querySelector('.toast-body').textContent = message;
            host.appendChild(el);

            const instance = new bootstrap.Toast(el, { delay: 3500 });
            el.addEventListener('hidden.bs.toast', () => el.remove());
            instance.show();
        },

        money(value) {
            return '₦' + Number(value || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        updateCartCount(count) {
            document.querySelectorAll('[data-cart-count]').forEach((node) => {
                node.textContent = count;
                node.style.display = count > 0 ? '' : 'none';
            });
        }
    };

    window.IPL = IPL;

    // ---------------------------------------------------------------- toasts
    document.querySelectorAll('[data-flash]').forEach((el) => {
        IPL.toast(el.dataset.flash, el.dataset.flashType || 'success');
        el.remove();
    });

    // -------------------------------------------------------- add to cart
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-add-to-cart]');
        if (!button) return;
        event.preventDefault();

        if (button.dataset.busy === '1') return;
        button.dataset.busy = '1';
        const original = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const payload = await IPL.post(IPL.base() + '/api/cart/add', {
                product_id: button.dataset.addToCart,
                quantity: button.dataset.quantity || 1
            });
            IPL.updateCartCount(payload.count);
            IPL.toast(payload.message || 'Added to your cart.');
            button.innerHTML = '<i class="bi bi-check-lg"></i> Added';
            setTimeout(() => { button.innerHTML = original; button.dataset.busy = '0'; }, 1600);
        } catch (error) {
            IPL.toast(error.message, 'danger');
            button.innerHTML = original;
            button.dataset.busy = '0';
        }
    });

    // -------------------------------------------------------- wishlist
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-wishlist]');
        if (!button) return;
        event.preventDefault();

        if (button.dataset.busy === '1') return;
        button.dataset.busy = '1';

        try {
            const payload = await IPL.post(IPL.base() + '/wishlist/toggle', { product_id: button.dataset.wishlist });
            IPL.toast(payload.message || (payload.in_wishlist ? 'Saved to your wishlist.' : 'Removed from your wishlist.'));
            button.classList.toggle('is-active', !!payload.in_wishlist);
            document.querySelectorAll('[data-wishlist-count]').forEach((n) => {
                n.textContent = payload.count;
            });
        } catch (error) {
            if (error.status === 401) { window.location.href = IPL.base() + '/login'; return; }
            IPL.toast(error.message, 'danger');
        } finally {
            button.dataset.busy = '0';
        }
    });

    // -------------------------------------------------------- quantity steppers
    document.addEventListener('click', (event) => {
        const stepper = event.target.closest('[data-qty-step]');
        if (!stepper) return;
        event.preventDefault();
        const input = stepper.parentElement.querySelector('input');
        if (!input) return;
        const step = parseInt(stepper.dataset.qtyStep, 10);
        const min = parseInt(input.min || '1', 10);
        const max = parseInt(input.max || '99', 10);
        let value = (parseInt(input.value, 10) || min) + step;
        if (value < min) value = min;
        if (value > max) value = max;
        input.value = value;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    // -------------------------------------------------------- live cart totals
    document.addEventListener('change', async (event) => {
        const input = event.target.closest('[data-cart-quantity]');
        if (!input) return;

        const line = input.closest('[data-cart-line]');
        const quantity = Math.max(1, parseInt(input.value, 10) || 1);

        try {
            const payload = await IPL.post(IPL.base() + '/api/cart/update', {
                product_id: input.dataset.cartQuantity,
                quantity: quantity
            });
            IPL.updateCartCount(payload.count);
            if (payload.subtotal !== undefined) {
                const target = document.querySelector('[data-cart-subtotal]');
                if (target) target.textContent = IPL.money(payload.subtotal);
            }
            if (line) {
                const lineTotal = line.querySelector('[data-line-total]');
                if (lineTotal && payload.line_totals && payload.line_totals[input.dataset.cartQuantity] !== undefined) {
                    lineTotal.textContent = IPL.money(payload.line_totals[input.dataset.cartQuantity]);
                }
            }
        } catch (error) {
            IPL.toast(error.message, 'danger');
            if (error.payload && error.payload.remaining) input.value = error.payload.remaining;
        }
    });

    // -------------------------------------------------------- remove from cart
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-cart-remove]');
        if (!button) return;
        event.preventDefault();

        const line = button.closest('[data-cart-line]');
        try {
            const payload = await IPL.post(IPL.base() + '/api/cart/remove', { product_id: button.dataset.cartRemove });
            IPL.updateCartCount(payload.count);
            if (line) {
                line.style.transition = 'opacity .2s ease';
                line.style.opacity = '0';
                setTimeout(() => line.remove(), 200);
            }
            IPL.toast('Removed from your cart.', 'info');
            if (payload.count === 0) setTimeout(() => window.location.reload(), 350);
        } catch (error) {
            IPL.toast(error.message, 'danger');
        }
    });

    // -------------------------------------------------------- search autocomplete
    const searchInput = document.querySelector('[data-search-input]');
    if (searchInput) {
        const results = document.querySelector('[data-search-results]');
        let timer = null;

        const render = (items) => {
            if (!results) return;
            if (!items.length) {
                results.innerHTML = '<div class="list-group-item text-muted small">No medicines found. Try a generic or brand name.</div>';
                return;
            }
            results.innerHTML = items.map((item) =>
                '<a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="' +
                IPL.base() + '/product/' + item.slug + '">' +
                (item.image ? '<img src="' + item.image + '" alt="" width="34" height="34" style="object-fit:contain" class="rounded">' : '') +
                '<span class="flex-grow-1"><span class="d-block fw-semibold small">' + item.name + '</span>' +
                '<small class="text-muted">' + (item.brand_name ? item.brand_name + ' · ' : '') + item.pharmacy_name + '</small></span>' +
                '<span class="fw-bold small">' + IPL.money(item.price) + '</span></a>'
            ).join('');
        };

        searchInput.addEventListener('input', () => {
            const term = searchInput.value.trim();
            clearTimeout(timer);
            if (term.length < 2) { if (results) results.innerHTML = ''; return; }
            timer = setTimeout(async () => {
                try {
                    const payload = await IPL.get(IPL.base() + '/api/search/suggest?q=' + encodeURIComponent(term));
                    render(payload.items || []);
                } catch (e) { /* suggestions are optional */ }
            }, 250);
        });

        searchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && searchInput.form) searchInput.form.submit();
        });

        document.addEventListener('click', (event) => {
            if (results && !searchInput.contains(event.target) && event.target !== searchInput) {
                results.innerHTML = '';
            }
        });
    }

    // -------------------------------------------------------- gallery
    document.addEventListener('click', (event) => {
        const thumb = event.target.closest('[data-gallery-thumb]');
        if (!thumb) return;
        const main = document.querySelector('[data-gallery-main]');
        if (!main) return;
        main.src = thumb.dataset.galleryThumb;
        document.querySelectorAll('[data-gallery-thumb]').forEach((t) => t.classList.remove('is-active'));
        thumb.classList.add('is-active');
    });

    // -------------------------------------------------------- radio-card selection
    document.addEventListener('change', (event) => {
        const input = event.target.closest('[data-select-group]');
        if (!input) return;
        const group = input.dataset.selectGroup;
        document.querySelectorAll('[data-select-group="' + group + '"]').forEach((node) => {
            node.classList.toggle('is-selected', node === input.closest('.address-card, .method-option'));
        });
        const hidden = document.querySelector('[data-select-target="' + group + '"]');
        if (hidden) hidden.value = input.value;
    });

    // -------------------------------------------------------- filters auto-submit
    document.querySelectorAll('[data-auto-submit]').forEach((node) => {
        node.addEventListener('change', () => node.form && node.form.submit());
    });

    // -------------------------------------------------------- confirm dialogs
    document.querySelectorAll('[data-confirm]').forEach((node) => {
        node.addEventListener('click', (event) => {
            if (!window.confirm(node.dataset.confirm)) event.preventDefault();
        });
    });

    // -------------------------------------------------------- flash + password
    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = button.parentElement.querySelector('input');
            if (!input) return;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            button.querySelector('i').className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });

    document.querySelectorAll('[data-password]').forEach((input) => {
        input.addEventListener('input', () => {
            const target = document.querySelector(input.dataset.password);
            if (target) target.classList.toggle('is-valid', input.value.length > 0 && input.value === target.value);
        });
    });

    // -------------------------------------------------------- table filter
    const tableFilter = document.querySelector('[data-table-filter]');
    if (tableFilter) {
        tableFilter.addEventListener('input', () => {
            const term = tableFilter.value.toLowerCase();
            document.querySelectorAll('[data-filterable-row]').forEach((row) => {
                row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        });
    }

    // -------------------------------------------------------- image previews
    document.querySelectorAll('[data-preview]').forEach((input) => {
        input.addEventListener('change', () => {
            const target = document.querySelector(input.dataset.preview);
            if (!target) return;
            const file = input.files && input.files[0];
            if (!file) { target.src = target.dataset.placeholder || target.src; return; }
            target.src = URL.createObjectURL(file);
        });
    });

    // -------------------------------------------------------- delivery dashboard
    const deliveryInput = document.querySelector('[data-delivery-geo]');
    if (deliveryInput && navigator.geolocation) {
        deliveryInput.addEventListener('click', (event) => {
            event.preventDefault();
            deliveryInput.textContent = 'Locating…';
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    deliveryInput.textContent = position.coords.latitude.toFixed(5) + ', ' + position.coords.longitude.toFixed(5);
                    const lat = document.querySelector('[name="latitude"]');
                    const lon = document.querySelector('[name="longitude"]');
                    if (lat) lat.value = position.coords.latitude.toFixed(7);
                    if (lon) lon.value = position.coords.longitude.toFixed(7);
                },
                () => { deliveryInput.textContent = 'Location unavailable'; },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });
    }

    // -------------------------------------------------------- simple sparkline chart
    (function charts() {
        if (typeof Chart === 'undefined') return;
        document.querySelectorAll('[data-chart]').forEach((canvas) => {
            let config;
            try { config = JSON.parse(canvas.dataset.chart); } catch (e) { return; }
            new Chart(canvas, config);
        });
    })();

    // -------------------------------------------------------- mobile sidebar
    const sidebar = document.querySelector('.app-sidebar');
    if (sidebar) {
        const backdrop = document.createElement('div');
        backdrop.className = 'sidebar-backdrop d-lg-none';
        backdrop.style.display = 'none';
        document.body.appendChild(backdrop);

        const close = () => { sidebar.classList.remove('is-open'); backdrop.style.display = 'none'; };
        const open = () => { sidebar.classList.add('is-open'); backdrop.style.display = 'block'; };

        document.querySelectorAll('.sidebar-toggle').forEach((button) => {
            button.addEventListener('click', () => (sidebar.classList.contains('is-open') ? close() : open()));
        });
        backdrop.addEventListener('click', close);
    }

    // -------------------------------------------------------- auto-dismiss alerts
    document.querySelectorAll('.alert[data-autodismiss]').forEach((alert) => {
        setTimeout(() => {
            alert.style.transition = 'opacity .3s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 6000);
    });
})();
