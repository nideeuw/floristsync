# FloristSync demo QA plan

Covers only what exists in the code today. Reading time: about ten minutes.

## 1. Prepare

    php artisan migrate:fresh
    php artisan db:seed --class=RolesMenusSeeder
    php artisan db:seed --class=DemoDataSeeder
    php artisan storage:link
    php artisan serve
    npm run dev

- Start from migrate:fresh. Do not run a plain db:seed twice (DummyDataSeeder creates categories with create). DemoDataSeeder alone is safe to run twice.
- Plain "php artisan migrate:fresh --seed" also works but adds 60 random orders from DummyOrdersSeeder. Run DemoDataSeeder after it if you want the named demo orders below.
- .env needs MySQL (DB_*) and Midtrans Sandbox keys (MIDTRANS_SERVER_KEY, MIDTRANS_CLIENT_KEY, MIDTRANS_IS_PRODUCTION=false). RajaOngkir keys are only needed for delivery orders, so use Pickup in the demo.

### Accounts (password for all: password)

| Role | Email | Login URL |
|---|---|---|
| member | member@floristsync.test | /login |
| admin_order | admin.order@floristsync.test | /admin/login |
| admin_inventory | admin.inventory@floristsync.test | /admin/login |
| owner | owner@floristsync.test | /admin/login |
| cashier | cashier@floristsync.test | /pos/login |
| demo members | demo.member1 to demo.member4@floristsync.test | /login |

### Demo orders (DemoDataSeeder)

| Order number | Status | Fulfillment | Member |
|---|---|---|---|
| FS-DEMO-PENDING_PAYMENT-1 | pending_payment | pickup | demo.member1 |
| FS-DEMO-PROCESSING-2 | processing | delivery (jne) | demo.member2 |
| FS-DEMO-READY_FOR_PICKUP-3 | ready_for_pickup | pickup | demo.member3 |
| FS-DEMO-SHIPPED-4 | shipped (resi FSDEMO00000004) | delivery | demo.member4 |
| FS-DEMO-COMPLETED-5 | completed | pickup | demo.member1 |
| FS-DEMO-CANCELLED-6 | cancelled | pickup | demo.member2 |

Demo orders have no real Midtrans payment, so they cannot be refunded. Use an order you paid yourself in the sandbox for refund checks.

## 2. Happy paths

### 2.1 Guest browsing (no login)

| Step | URL | Expected |
|---|---|---|
| Open home | / | Page loads |
| Open catalog | /catalog | Product list loads |
| Open a product | /products/{id} | Product detail loads |
| Add to cart | button on catalog or product page | Cart count in the header goes up |
| Open cart | /cart | Item listed, quantity can be changed and removed |
| Open checkout | /checkout | Sent to /login |

### 2.2 Member checkout and payment (member@floristsync.test)

| Step | URL | Expected |
|---|---|---|
| Log in | /login | Header shows the member name |
| Add a product, open checkout | /checkout | Form with name, phone, email, Pickup or Delivery |
| Submit with Pickup | /checkout | Payment page "Order FS-..." with the item total and "Pay within 60 minutes". Snap opens by itself. Product stock is already reduced |
| Pay with the sandbox card | Snap popup | Card 4811 1111 1111 1114, any future expiry, CVV 123, OTP 112233 |
| Back to history | /track | Order shows Processing. If it still says Pending payment (local webhook), click Pay now and it syncs |
| Check notifications | /notifications | "Payment received" is listed |
| Print invoice | /track/{id}/invoice | Invoice is shown. Before payment the page says the invoice is available once payment is completed |
| Pay later | /track | Close Snap, then Pay now with "Pay before HH:MM" reopens Snap. After 60 minutes the button is gone |

### 2.3 Cashier POS sale and cancel (cashier@floristsync.test)

| Step | URL | Expected |
|---|---|---|
| Log in | /pos/login | POS opens |
| Sell with Cash, amount at least the total | /pos | Sale completed, stock reduced. Amount below total gives "Cash payment is not enough." |
| Open receipt | /pos/receipt/{id} | Receipt with store name and items |
| Check summary | /pos/history | Shift summary: orders +1, revenue +total, Cash row updated |
| Cancel the sale with a reason | /pos/cancel | Flash "Transaction POS-... has been cancelled and stock has been returned." Reason is required |
| Re-check | /pos/history, receipt URL | Orders and revenue drop. Receipt says "This sale was cancelled. Receipt cannot be printed." |
| Sell with QRIS | /pos | Stays pending until paid in the Midtrans QRIS simulator. Receipt says it cannot be printed until payment is settled |

### 2.4 Admin order: process and cancel (admin.order@floristsync.test)

Process

| Step | URL | Expected |
|---|---|---|
| List online orders | /admin/orders | Status counts, filters by status, fulfillment, search. POS sales are not listed |
| Open a processing delivery order | /admin/orders/{id} (try FS-DEMO-PROCESSING-2) | Order detail |
| Enter a tracking number | order detail | "Tracking number saved. Order marked as shipped." Member is notified |
| Move to completed | order detail | "Order status updated." |
| Pickup order you paid | order detail | Processing, then Ready for pickup, then Completed. Member sees each step in /track |
| New paid order alert | /admin/notifications | "New paid order" after a member pays |

Cancel (needs two orders paid in the sandbox)

| Step | Account and URL | Expected |
|---|---|---|
| Request | member, /track, Cancel order, reason of 5+ characters | "Your cancellation request for order #N has been sent. We will review it soon." Row shows "Cancellation requested" |
| Review list | admin_order, /admin/order-cancellations | Request with the reason. "Cancellation request" notification arrived |
| Reject | admin_order, Reject | "Cancellation request rejected. The order stays in processing." Member sees "Cancellation request declined. Please contact us via WhatsApp." |
| Approve (second order) | admin_order, Approve | "Cancellation approved. The order was cancelled, the stock returned and the payment refunded." Member is notified |

Approve and Reject need the approve flag on the menu permission, not a fixed role.

### 2.5 Admin inventory (admin.inventory@floristsync.test)

| Step | URL | Expected |
|---|---|---|
| Product list | /admin/products | Stock badge is red at or below 5 (LOW_STOCK_THRESHOLD) |
| Deactivate a product | /admin/products, Deactivate, confirm | Status shows Inactive. Nothing is deleted |
| Restock | /admin/stock/restock | Stock goes up |
| Adjust stock | /admin/stock/adjustments | Going below zero: "Stock of X cannot go below zero." Zero change: "Quantity change cannot be zero." |
| Low stock alert | sell a product down to 5 or less, then /admin/notifications | "Low stock: <product>", not repeated while unread |
| History | /admin/stock/history | Each movement is listed |

### 2.6 Owner report export (owner@floristsync.test)

| Step | URL | Expected |
|---|---|---|
| Open reports | /admin/reports | Sales tab, last 30 days. Other tabs include Products and Stock. Filters: period, type, category |
| Export PDF | /admin/reports/export/pdf | Downloads report-sales-YYYYMMDD-YYYYMMDD.pdf, titled "Sales report" with the period |
| Export Excel | /admin/reports/export/excel | Downloads report-sales-YYYYMMDD-YYYYMMDD.xlsx |
| Stock tab export | same URLs with tab=stock | File is report-stock-YYYYMMDD with the current date |
| Bad range | to date before from date | Validation error, no file |

## 3. Money checks

Use /admin/products (admin_inventory) to read stock before and after each case.

| Case | Do | Expected |
|---|---|---|
| Cancel an unpaid order | Member checks out (Pickup), closes Snap without paying. Note stock before and after ordering. Cancel in /track with a reason | "Order #N has been cancelled." Status Cancelled. Stock is back to the value before ordering. Payment status is cancel. No refund is made. Pay now is gone. /admin/stock/history shows a cancel movement "Order #N cancelled" |
| Refund a paid order | Pay an order in the sandbox (2.2), member requests cancellation, admin_order Approves | Order Cancelled. Stock returns once. Payment status becomes refund (a card payment not yet settled is voided and shows cancel). The refund amount is the full order total including shipping. Check status in the Midtrans Sandbox dashboard |
| Double click on Approve | Click Approve twice quickly | One refund, one stock return (the order row is locked and the refund key is fixed) |
| Refund fails | Approve a request on a demo order (no Midtrans record) | Error message. Order stays Processing and stock is unchanged |
| POS cancel | See 2.3 | Revenue and order count in /pos/history drop by the sale. Stock returns |

## 4. Automated tests

    composer test

- It clears the config cache and runs php artisan test on in-memory SQLite (phpunit.xml). It does not touch the MySQL demo data.
- One file: php artisan test --filter=PaymentReversalRefundTest
- Money tests by name: CheckoutStockTest, PaymentWebhookTest, PaymentReversalRefundTest, LatePaymentRefundTest, OrderCancellationRequestTest, AdminOrderCancellationTest, PosCancellationTest.
- Access tests: RouteAccessControlTest, MenuRouteConsistencyTest.
- Go for the demo only when the whole run is green.

## 5. Sandbox notes

- The Midtrans webhook cannot reach localhost. Use Pay now in /track to sync a paid order. For automatic updates, expose the app with a tunnel and set the notification URL in the Midtrans Sandbox dashboard.
- QRIS and GoPay in the sandbox are paid in the Midtrans simulator, not with a real e-wallet.
