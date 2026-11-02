# Roadmap

Nusa Creator Studio is moving from a paused SaaS prototype to an **open-source, self-hostable** content studio for social-commerce sellers and creators in Indonesia and Southeast Asia.

The core flow already works end to end (accounts, projects, media library, queued AI generations with mock or real providers, credits, plans, admin panel, history). This roadmap covers what comes next, based on a code audit of the current state. Estimates are focused development hours.

**Priorities**

- **Now**: needed to make the project a solid open-source tool. Done first.
- **Next**: Southeast Asia product focus and provider robustness.
- **Later**: valuable, but only after the above is stable.

Status legend: `[ ]` planned, `[~]` in progress, `[x]` done.

---

## 1. Self-hosting and open source (Now) — 18 h

| Task | Estimate |
| --- | --- |
| [ ] Containerize the app: Dockerfiles for API, queue worker and web (static build behind nginx), added to `docker-compose.yml` so `docker compose up` runs the whole stack. Remove fixed `container_name`s, pin image versions (no `latest`), drop or document the unused n8n service. | 8 h |
| [ ] **Self-hosted mode** (`NUSA_SELF_HOSTED=true`): a single-workspace setup where credits and plan gating are disabled and pricing/upgrade prompts are hidden, since the operator pays their own provider bills. | 5 h |
| [ ] Deployment guide (`docs/deployment.md`): VPS with Docker Compose, environment variables, queue worker supervision, S3/MinIO storage, HTTPS, Sanctum/CORS domains, backups. | 3 h |
| [ ] `CONTRIBUTING.md`, issue and pull request templates. | 2 h |

## 2. Technical debt cleanup (Now) — 20 h

Items found in the audit.

| Task | Estimate |
| --- | --- |
| [ ] Unify the provider manager API: managers expose both `generate()` (always mock) and `generateForUser()` (mock or real). Collapse into one entry point per contract and register `AutoVideoAdProviderManager` in the container like the other managers. | 5 h |
| [ ] Mock-only tools that charge credits: Video Enhancer and Product-to-Video Script are listed as available but only have mock providers. Either connect them to the existing OpenAI text pipeline or label them as demo and stop charging credits. | 6 h |
| [ ] Align Auto Video Ads status: the Shotstack renderer exists but the tool is seeded as `coming_soon`. Decide its status and make seeder, UI and docs consistent. | 1 h |
| [ ] Monthly credit renewal: `monthly_credit_allowance` is stored but never applied. Add a scheduled command that tops users up to their plan allowance with an auditable credit transaction, plus tests. Remove or document the unused `purchase` transaction type. | 4 h |
| [ ] Route cleanup: remove the default `/api/user` route, the duplicated `/health` route and the unused `generate-motion-video` alias. | 1 h |
| [ ] Remove scaffold leftovers: Laravel welcome view and Vite assets in `apps/api`, template READMEs in `apps/api` and `apps/web`. | 1 h |
| [ ] Make the Google Veo SDK bridge (`tools/google-veo-sdk-probe`) an explicit, documented optional build step (and part of the API image when enabled). | 2 h |

## 3. Quality and CI (Now) — 28 h

| Task | Estimate |
| --- | --- |
| [ ] GitHub Actions: PHPUnit, Pint, vue-tsc, ESLint, oxlint and Vitest on every push and pull request. Add a PostgreSQL job, because the credit and role `CHECK` constraints only exist on PostgreSQL and are not exercised by the SQLite test run. | 6 h |
| [ ] OpenAPI description of `/api/v1` and a rendered API reference in `docs/`. | 6 h |
| [ ] End-to-end smoke test (Playwright) in mock mode: register, create a project, run a generation, see the result. | 5 h |
| [ ] Accessibility and performance pass: keyboard navigation, form labels, contrast, Lighthouse baseline for the main views. | 5 h |
| [ ] Responsive header and navigation: on a 390 px phone screen the language selector and nav items are clipped. Fix the app shell for small screens and add a mobile viewport check to the E2E test. | 3 h |
| [ ] Bundle the Poppins brand font: it is named in the CSS but never loaded, so each system falls back to its default font. | 1 h |
| [ ] Upgrade the ESLint toolchain (ESLint, typescript-eslint, eslint-plugin-vue) to clear the remaining moderate `npm audit` advisories, all in dev-only lint dependencies, and add `composer audit` and `npm audit` to CI. | 2 h |

## 4. Southeast Asia product focus (Next) — 36 h

| Task | Estimate |
| --- | --- |
| [ ] Complete Bahasa Indonesia and Spanish translations (about 1,100 entries in English versus 640–680 in Spanish and Indonesian; missing ones silently fall back to English) and add a test that fails when a locale is missing keys. | 6 h |
| [ ] **Template library**: reusable content templates for TikTok Shop, Shopee and Tokopedia listings, and WhatsApp seller broadcasts. Template model, seeded templates and a template picker in the campaign flow. | 10 h |
| [ ] **Live selling script generator** for TikTok Live and Shopee Live: structured segments (opening, product demo, offer, urgency, Q&A prompts) built on the existing campaign generation pipeline (mock and OpenAI providers). | 8 h |
| [ ] **Brand kit**: tone of voice, target audience, banned words and preferred CTAs per workspace, injected into prompt builders, with a banned-word check on generated output. | 10 h |
| [ ] Locale-aware formatting for Rupiah prices, numbers and dates across the UI. | 2 h |

## 5. Creator productivity (Next) — 20 h

| Task | Estimate |
| --- | --- |
| [ ] Caption and hook variants: generate several options and let the user pick and save a favourite (reusing the selection pattern from Fashion Try-On variations). | 6 h |
| [ ] Export generation results to Markdown and CSV. | 4 h |
| [ ] Product catalog import (CSV) and batch generation: one queued generation per product, with a credit preview before submitting. | 10 h |

## 6. AI provider robustness (Next) — 14 h

| Task | Estimate |
| --- | --- |
| [ ] Shared HTTP client behaviour for every provider adapter: retries with backoff, connect/request timeouts and idempotency. Today only the Google Veo client retries. | 5 h |
| [ ] Spend limits: daily and monthly caps per provider and per user based on the `cost_usd` already stored on generations, with an admin overview. | 6 h |
| [ ] Provider-neutral, translated error messages for common failures (quota, timeout, invalid input, moderation). | 3 h |

## 7. Later — 24 h

| Task | Estimate |
| --- | --- |
| [ ] Content calendar to plan generated content per day and channel. | 10 h |
| [ ] Per-user "bring your own API key" for multi-user instances, stored encrypted. | 8 h |
| [ ] Additional provider adapter as a voiceover fallback (for example Google Cloud Text-to-Speech), following the existing contract + mock + real adapter pattern. | 6 h |

---

## Summary

| Area | Priority | Hours |
| --- | --- | --- |
| Self-hosting and open source | Now | 18 |
| Technical debt cleanup | Now | 20 |
| Quality and CI | Now | 28 |
| Southeast Asia product focus | Next | 36 |
| Creator productivity | Next | 20 |
| AI provider robustness | Next | 14 |
| Later | Later | 24 |
| **Total** | | **160** |

## Out of scope

Nusa Creator Studio only **consumes** third-party AI APIs. Training, fine-tuning, hosting or serving models, evaluating or benchmarking model quality, and building datasets are intentionally out of scope.

## Earlier planning notes

Pre-open-source planning documents are kept for context in [`docs/product/roadmap.md`](docs/product/roadmap.md), [`docs/research/integration-roadmap.md`](docs/research/integration-roadmap.md) and [`docs/research/innovation-backlog.md`](docs/research/innovation-backlog.md).
