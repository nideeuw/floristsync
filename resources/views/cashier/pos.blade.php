<x-app-layout>
    <script>
        function pos() {
            return {
                categories: [],
                products: [],
                categoryId: null,
                cart: [],
                paymentMethod: 'cash',
                paidAmount: 0,
                loading: false,

                async load() {
                    const res = await fetch('/api/pos/products', { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    this.categories = json.categories;
                    this.products = json.data;
                },

                get filtered() {
                    return this.categoryId
                        ? this.products.filter(p => p.category_id === this.categoryId)
                        : this.products;
                },

                get total() {
                    return this.cart.reduce((sum, i) => sum + i.price * i.qty, 0);
                },

                add(product) {
                    const item = this.cart.find(i => i.product_id === product.id);
                    if (item) {
                        if (item.qty < product.stock) item.qty++;
                    } else {
                        this.cart.push({
                            product_id: product.id,
                            product_name: product.product_name,
                            price: product.price,
                            qty: 1,
                        });
                    }
                },

                changeQty(item, delta) {
                    item.qty += delta;
                    if (item.qty <= 0) this.cart = this.cart.filter(i => i !== item);
                },

                rupiah(n) {
                    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
                },

                async pay() {
                    if (!this.cart.length) return;
                    this.loading = true;

                    const res = await fetch('/api/pos/transactions', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify({
                            items: this.cart.map(i => ({ product_id: i.product_id, qty: i.qty })),
                            payment_method: this.paymentMethod,
                            paid_amount: Number(this.paidAmount) || 0,
                        }),
                    });

                    const json = await res.json();
                    this.loading = false;

                    if (!res.ok) return alert(json.message ?? 'Transaction failed.');

                    window.open(json.data.receipt_url, '_blank'); // print receipt
                    this.cart = [];
                    this.paidAmount = 0;
                    this.load(); // refresh stock
                },
            };
        }
    </script>

    <div x-data="pos()" x-init="load(); setInterval(() => load(), 15000)"
         class="grid grid-cols-3 gap-6 p-6">

        {{-- Products --}}
        <div class="col-span-2">
            <div class="mb-4 flex flex-wrap gap-2">
                <button @click="categoryId = null"
                        :class="categoryId === null ? 'bg-black text-white' : 'bg-gray-100'"
                        class="rounded-full px-4 py-2 text-sm">All</button>
                <template x-for="c in categories" :key="c.id">
                    <button @click="categoryId = c.id" x-text="c.category_name"
                            :class="categoryId === c.id ? 'bg-black text-white' : 'bg-gray-100'"
                            class="rounded-full px-4 py-2 text-sm"></button>
                </template>
            </div>

            <div class="grid grid-cols-4 gap-4">
                <template x-for="p in filtered" :key="p.id">
                    <button @click="add(p)" :disabled="p.stock < 1"
                            class="rounded-lg border p-3 text-left disabled:opacity-40">
                        <div class="font-semibold" x-text="p.product_name"></div>
                        <div class="text-lg" x-text="rupiah(p.price)"></div>
                        <div class="text-xs text-gray-500" x-text="'Stock: ' + p.stock"></div>
                    </button>
                </template>
            </div>
        </div>

        {{-- Cart --}}
        <div class="rounded-lg border p-4">
            <h2 class="mb-3 text-lg font-bold">Current Order</h2>

            <template x-for="item in cart" :key="item.product_id">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <div x-text="item.product_name"></div>
                        <div class="text-sm text-gray-500" x-text="rupiah(item.price * item.qty)"></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="changeQty(item, -1)" class="rounded border px-2">-</button>
                        <span x-text="item.qty"></span>
                        <button @click="changeQty(item, 1)" class="rounded border px-2">+</button>
                    </div>
                </div>
            </template>

            <div class="mt-4 border-t pt-4">
                <div class="mb-3 flex justify-between text-xl font-bold">
                    <span>Total</span>
                    <span x-text="rupiah(total)"></span>
                </div>

                <select x-model="paymentMethod" class="mb-2 w-full rounded border p-2">
                    <option value="cash">Cash</option>
                    <option value="qris">QRIS</option>
                    <option value="debit">Debit</option>
                </select>

                <input x-show="paymentMethod === 'cash'" x-model="paidAmount" type="number" min="0"
                       placeholder="Cash received" class="mb-2 w-full rounded border p-2">

                <button @click="pay()" :disabled="loading || !cart.length"
                        class="w-full rounded bg-black py-3 font-semibold text-white disabled:opacity-40">
                    <span x-text="loading ? 'Processing...' : 'Pay / Complete Order'"></span>
                </button>
            </div>
        </div>
    </div>
</x-app-layout>
