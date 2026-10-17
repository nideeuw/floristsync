export default () => ({
    storageUrl: '',
    lowStock: 5,
    loginUrl: '/',

    emojis: ['🌷', '🌸', '🌹', '💐', '🌼', '🌺'],

    categories: [],
    products: [],
    categoryId: null,
    search: '',
    cart: [],
    method: 'cash',
    paid: '',
    loading: false,
    error: '',
    success: null,
    qris: null,
    qrisPollInterval: null,

    member: null,
    memberQuery: '',
    memberResults: [],
    memberSearching: false,
    memberSearched: false,
    notes: '',
    showNote: false,

    init() {
        const data = this.$el.dataset;

        this.storageUrl = data.storageUrl;
        this.lowStock = Number(data.lowStock);
        this.loginUrl = data.loginUrl;

        this.load();
        setInterval(() => this.load(), 15000);
    },

    async load() {
        try {
            const res = await fetch('/api/pos/products', { headers: { Accept: 'application/json' } });

            if (res.status === 401 || res.status === 419) {
                window.location = this.loginUrl;
                return;
            }

            if (!res.ok) throw new Error('Failed to load products');

            const json = await res.json();
            this.categories = json.categories;
            this.products = json.data;
            this.syncCart();
        } catch (e) {
            console.error(e);
        }
    },

    // Keep cart prices and stock limits in sync after every refresh.
    syncCart() {
        this.cart = this.cart
            .map(item => {
                const p = this.products.find(x => x.id === item.product_id);

                return p ? { ...item, price: p.price, stock: p.stock, qty: Math.min(item.qty, p.stock) } : null;
            })
            .filter(item => item && item.qty > 0);
    },

    get filtered() {
        const term = this.search.trim().toLowerCase();

        return this.products.filter(p =>
            (this.categoryId === null || p.category_id === this.categoryId) &&
            (term === '' || p.product_name.toLowerCase().includes(term))
        );
    },

    get total() { return this.cart.reduce((sum, i) => sum + i.price * i.qty, 0); },
    get itemCount() { return this.cart.reduce((sum, i) => sum + i.qty, 0); },
    get change() { return Math.max(0, Number(this.paid) - this.total); },

    get canPay() {
        return this.cart.length > 0 && !this.loading &&
            (this.method !== 'cash' || Number(this.paid) >= this.total);
    },

    add(p) {
        if (p.stock < 1) return;
        this.error = '';

        const item = this.cart.find(i => i.product_id === p.id);

        if (item) {
            if (item.qty < p.stock) item.qty++;
        } else {
            this.cart.push({
                product_id: p.id,
                product_name: p.product_name,
                price: p.price,
                qty: 1,
                stock: p.stock,
            });
        }
    },

    inc(item) { if (item.qty < item.stock) item.qty++; },

    dec(item) {
        item.qty--;
        if (item.qty <= 0) this.remove(item);
    },

    remove(item) { this.cart = this.cart.filter(i => i !== item); },

    clear() {
        this.cart = [];
        this.paid = '';
        this.error = '';
        this.member = null;
        this.memberQuery = '';
        this.memberResults = [];
        this.memberSearched = false;
        this.notes = '';
        this.showNote = false;
    },

    async searchMember() {
        const q = this.memberQuery.trim();
        if (!q) {
            this.memberResults = [];
            this.memberSearched = false;
            return;
        }

        this.memberSearching = true;
        this.memberSearched = false;

        try {
            const res = await fetch(`/api/pos/members?q=${encodeURIComponent(q)}`, {
                headers: { Accept: 'application/json' },
            });

            if (!res.ok) throw new Error('Search failed');

            const json = await res.json();
            this.memberResults = json.data ?? json;
            this.memberSearched = true;
        } catch (e) {
            console.error('Member search error', e);
            this.memberResults = [];
        } finally {
            this.memberSearching = false;
        }
    },

    selectMember(m) {
        this.member = m;
        this.memberQuery = '';
        this.memberResults = [];
        this.memberSearched = false;
    },

    removeMember() {
        this.member = null;
        this.memberQuery = '';
        this.memberResults = [];
        this.memberSearched = false;
    },

    rupiah(n) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
    },

    async pay() {
        if (!this.canPay) return;

        this.loading = true;
        this.error = '';

        try {
            const res = await fetch('/api/pos/transactions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({
                    items: this.cart.map(i => ({ product_id: i.product_id, qty: i.qty })),
                    payment_method: this.method,
                    paid_amount: this.method === 'cash' ? Number(this.paid) : 0,
                    user_id: this.member ? this.member.id : null,
                    notes: this.notes.trim() ? this.notes.trim() : null,
                }),
            });

            if (res.status === 401 || res.status === 419) {
                window.location = this.loginUrl;
                return;
            }

            const json = await res.json();

            if (!res.ok) {
                this.error = json.message ?? 'Transaction failed.';
                this.load(); // stock may have changed
                return;
            }

            if (this.method === 'qris') {
                await this.initQris(json.data.id, json.data.total);
                return;
            }

            this.success = {
                ...json.data,
                method: this.method,
                paid: this.method === 'cash' ? Number(this.paid) : json.data.total,
                member: this.member,
                notes: this.notes.trim() ? this.notes.trim() : null,
            };
        } catch (e) {
            this.error = 'Network error. Please try again.';
        } finally {
            this.loading = false;
        }
    },

    async initQris(transactionId, total) {
        this.qris = {
            id: transactionId,
            total: total,
            qr_url: null,
            status: 'generating',
            error: null,
        };

        try {
            const res = await fetch(`/api/pos/transactions/${transactionId}/qris`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
            });

            if (!res.ok) {
                const err = await res.json();
                this.qris.status = 'error';
                this.qris.error = err.message ?? 'Failed to generate QRIS code.';
                return;
            }

            const json = await res.json();
            const qrData = json.data;

            this.qris.qr_url = qrData.qr_url;
            this.qris.qr_string = qrData.qr_string;
            this.qris.order_id = qrData.order_id;
            this.qris.status = 'pending';

            this.startQrisPolling(transactionId);
        } catch (e) {
            this.qris.status = 'error';
            this.qris.error = 'Network error generating QRIS code.';
        }
    },

    startQrisPolling(transactionId) {
        this.stopQrisPolling();

        this.qrisPollInterval = setInterval(async () => {
            if (!this.qris || this.qris.id !== transactionId || this.qris.status !== 'pending') {
                this.stopQrisPolling();
                return;
            }

            try {
                const res = await fetch(`/api/pos/transactions/${transactionId}/qris`, {
                    headers: { 'Accept': 'application/json' },
                });

                if (!res.ok) return;

                const json = await res.json();
                const status = json.status ?? json.data?.status;

                if (status === 'settlement' || json.is_completed) {
                    this.stopQrisPolling();
                    const txId = this.qris.id;
                    const total = this.qris.total;
                    this.qris = null;

                    this.success = {
                        id: txId,
                        total: total,
                        method: 'qris',
                        paid: total,
                        receipt_url: json.receipt_url ?? `/pos/receipt/${txId}`,
                    };

                    this.cart = [];
                    this.load();
                } else if (status === 'expire' || status === 'cancel' || status === 'deny' || json.is_cancelled) {
                    this.stopQrisPolling();
                    this.qris.status = status;
                    this.load();
                }
            } catch (e) {
                console.error('Polling error', e);
            }
        }, 2500);
    },

    stopQrisPolling() {
        if (this.qrisPollInterval) {
            clearInterval(this.qrisPollInterval);
            this.qrisPollInterval = null;
        }
    },

    async cancelQris() {
        if (!this.qris) return;

        const txId = this.qris.id;
        this.stopQrisPolling();

        try {
            await fetch(`/api/pos/transactions/${txId}/qris`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
            });
        } catch (e) {
            console.error('Cancel QRIS error', e);
        }

        this.qris = null;
        this.load();
    },

    get receiptUrl() {
        return this.success ? this.success.receipt_url + '?paid=' + this.success.paid : '#';
    },

    newOrder() {
        this.stopQrisPolling();
        this.qris = null;
        this.success = null;
        this.cart = [];
        this.paid = '';
        this.method = 'cash';
        this.member = null;
        this.memberQuery = '';
        this.memberResults = [];
        this.memberSearched = false;
        this.notes = '';
        this.showNote = false;
        this.load();
    },
});
