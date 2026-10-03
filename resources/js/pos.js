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

            this.success = {
                ...json.data,
                method: this.method,
                paid: this.method === 'cash' ? Number(this.paid) : json.data.total,
            };
        } catch (e) {
            this.error = 'Network error. Please try again.';
        } finally {
            this.loading = false;
        }
    },

    get receiptUrl() {
        return this.success ? this.success.receipt_url + '?paid=' + this.success.paid : '#';
    },

    newOrder() {
        this.success = null;
        this.cart = [];
        this.paid = '';
        this.method = 'cash';
        this.load();
    },
});
