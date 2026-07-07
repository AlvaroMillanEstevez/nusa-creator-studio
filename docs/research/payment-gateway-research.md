# Payment Gateway Research

This document tracks payment providers for a future paid launch. It is research-only; do not implement payments, subscriptions, checkout, invoices, Stripe, or billing from this document without a separate implementation task.

## Research goals

- Identify payment gateways suitable for Indonesia-first launch and SEA/global expansion.
- Compare subscription support, local payment methods, webhook quality, settlement, and onboarding.
- Keep future payments compatible with internal platform credits.

## Indonesia-first payment requirements

- Support local methods such as bank transfer/VA, e-wallets, QRIS, cards, and retail payments where relevant.
- Support IDR pricing/settlement or clear currency conversion rules.
- Provide reliable webhooks for success, failure, expiry, refund, and dispute events.
- Provide a workable recurring payment or renewal flow if subscriptions are added later.
- Offer onboarding suitable for an early-stage SaaS.

## SEA/global expansion requirements

- Multi-currency support or a clear expansion path.
- Coverage options for Philippines, Malaysia, Singapore, Thailand, and global card users if strategic.
- Good test mode, documentation, dashboard logs, and reconciliation exports.
- Clear behavior for refunds, disputes, expired payments, and idempotency.

## Gateway comparison table

| Provider | Primary regions | Local methods | Cards | Subscriptions | Webhooks | Settlement currency | Onboarding notes | Source/date verified |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Xendit | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| Midtrans | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| DOKU | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| Antom | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| dLocal | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| Stripe | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| PayMongo | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| Billplz | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| iPay88 | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| HitPay | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |

## Subscription support

| Provider | Native recurring | Trials | Plan changes | Proration | Retry/dunning | Notes | Source/date verified |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Xendit | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| Midtrans | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| DOKU | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| Antom | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| dLocal | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| Stripe | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| PayMongo | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| Billplz | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| iPay88 | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| HitPay | TODO | TODO | TODO | TODO | TODO | TODO | TODO |

## Webhook/API quality

| Provider | Idempotency | Signature verification | Event replay | Test mode | API docs quality | Dashboard logs | Source/date verified |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Xendit | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| Midtrans | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| DOKU | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| Antom | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| dLocal | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| Stripe | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| PayMongo | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| Billplz | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| iPay88 | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| HitPay | TODO | TODO | TODO | TODO | TODO | TODO | TODO |

## Local payment methods

| Payment method | Indonesia priority | Providers to verify | Notes |
| --- | --- | --- | --- |
| Bank transfer / Virtual Account | High | Xendit, Midtrans, DOKU, others TODO | Important for local SaaS accessibility. |
| E-wallets | High | Xendit, Midtrans, DOKU, Antom, others TODO | Verify specific wallets and recurring support. |
| QRIS | High | Xendit, Midtrans, DOKU, others TODO | Verify static vs dynamic QR flow. |
| Credit/debit cards | Medium/high | All TODO | Useful for global users and subscription flows. |
| Retail convenience store payments | Medium | TODO | Useful for prepaid credit packs if supported. |
| International alternative methods | Later | dLocal, Stripe, Antom, others TODO | Verify market-by-market. |

## Settlement/currency notes

- TODO: Verify settlement currencies for each provider.
- TODO: Verify settlement timing, payout fees, minimum payout, tax documents, and invoice exports.
- TODO: Decide whether Nusa prices in IDR first, USD first, or market-specific currencies.
- TODO: Decide whether internal credits are purchased as packs, renewed by plans, or both.

## Compliance/onboarding notes

- TODO: Verify business entity requirements for each provider.
- TODO: Verify required documents, KYB/KYC timelines, prohibited categories, and chargeback rules.
- TODO: Confirm consumer disclosure requirements for credits, expiry, refunds, and subscriptions.
- TODO: Confirm tax invoice requirements for Indonesia and future markets.

## Recommended shortlist

Research shortlist, not implementation approval:

1. Xendit — likely relevant for Indonesia-first local payment methods. TODO: verify.
2. Midtrans — likely relevant for Indonesia-first local payment methods. TODO: verify.
3. DOKU — likely relevant for Indonesian payment coverage. TODO: verify.
4. Stripe — useful benchmark for subscription/product architecture and global cards. TODO: verify local fit.
5. dLocal or Antom — possible expansion providers. TODO: verify target markets and onboarding.

## Open questions

- Should launch monetization use credit packs, monthly plans, or both?
- Which provider has the fastest onboarding for an Indonesian SaaS?
- Which provider handles recurring payments best for local methods?
- How should failed payments affect internal credits and plan status?
- What refund policy should apply to unused internal platform credits?
