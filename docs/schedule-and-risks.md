# Schedule and risks

Status as of 18 October 2026. All feature tasks (K1 to K10, D1, D2) are done. What is left is testing and fixing bugs until Milestone 2 (Week 12). No new feature tasks.

## Plan Week 9 to Week 12

| Week | Focus | What to do | Blocks demo |
|---|---|---|---|
| 9 | Prepare | Freeze features. Check the deployed app. Set Midtrans and RajaOngkir sandbox keys. Run migrate:fresh, RolesMenusSeeder and DemoDataSeeder | Yes |
| 10 | Integration test | Test Midtrans (pay, webhook, refund) and RajaOngkir. Check stock goes down on payment and back on cancel | Yes |
| 11 | Role test and fix | Run docs/qa-plan.md for every role. Write each bug and its fix in a bug table. Run composer test until all green. Result on 19 October 2026: 195 passed, 2 skipped, 1136 assertions, no failures. The 2 skipped tests are in EmailVerificationTest and are skipped on purpose because the app does not use email verification | Yes |
| 12 | Milestone 2 | Demo the main workflow. Hand in evidence: test result, bug table, screenshots | Yes |

## Risks

| Risk | Impact | PIC | Fallback |
|---|---|---|---|
| Midtrans sandbox fails or is down | Online payment, refund and cancel cannot be shown | nita | Use Pay now in /track to sync. Show POS Cash sale and the seeded demo orders. Run PaymentWebhookTest and PaymentReversalRefundTest |
| Midtrans keys missing or expired | Snap does not open, checkout cannot be paid | nida | Set MIDTRANS_SERVER_KEY, MIDTRANS_CLIENT_KEY, MIDTRANS_IS_PRODUCTION=false. Retest before the demo. Demo POS Cash meanwhile |
| RajaOngkir keys missing or expired | Delivery orders get no shipping cost | nida | Use Pickup in the demo. Show delivery with the seeded demo orders only |
| Demo stock data wrong or seeded twice | Wrong stock, duplicate categories, no low stock alert | justin | Start from migrate:fresh. Do not run plain db:seed twice. DemoDataSeeder alone is safe to run twice. Check /admin/products before the demo |
| Tests fail again after a fix | Demo not safe to run | all | Run composer test before every merge to development |

## Decisions already made

1. Soft delete rule: users, products, categories and staff are never deleted. is_active is set to false, and a deactivated account cannot be used.
2. Refund rule: refunds go through the Midtrans API only, with no manual refund. If Midtrans rejects the refund, the error is shown and the order is not marked cancelled. Admin cancel is allowed only while the order is in processing. A paid order that was cancelled earlier is refunded when the late payment arrives.
3. Notification rule: notifications are inside the web only, no email, for logged in members and for admin order. Admin order gets "New paid order" for online orders only, never for POS sales. Owner and cashier receive no notifications. Low stock is not repeated while unread.
