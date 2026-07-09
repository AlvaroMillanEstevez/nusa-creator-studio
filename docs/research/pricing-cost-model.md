# Pricing and Cost Model

This document models how Nusa Creator Studio could price internal platform credits against future provider costs. It intentionally avoids exact provider prices until they are verified from official sources.

## Pricing strategy goals

- Keep pricing understandable for creators and small sellers.
- Protect gross margin as real AI providers are added.
- Let cheaper tools feel lightweight while expensive tools remain controlled.
- Support Indonesia-first pricing while preserving SEA/global expansion options.
- Avoid billing complexity until the product model is validated.

## Current placeholder plans

The app already has dynamic pricing/plans and internal platform credits. Current plan names, allowances, and prices should be copied from admin/pricing configuration only after verification.

| Plan | Target user | Monthly credits | Price | Key limits | Source/date verified |
| --- | --- | --- | --- | --- | --- |
| TODO | Free/testing creator | TODO | TODO | TODO | TODO |
| TODO | Solo creator | TODO | TODO | TODO | TODO |
| TODO | Seller/team | TODO | TODO | TODO | TODO |
| TODO | Agency/internal | TODO | TODO | TODO | TODO |

## Cost drivers

| Cost driver | Applies to | Unit to track | Notes |
| --- | --- | --- | --- |
| Text generation | Campaigns, captions, scripts | Tokens or request | Usually lower cost; still needs output validation. |
| Voiceover/TTS | Voiceover, dubbing | Characters, seconds, or minutes | Good first real integration candidate. |
| Image generation/editing | Product visuals, concepts | Image or resolution | Cost depends on model and output size. |
| Fashion try-on | Try-on concepts | Output image/job | Requires input validation and failure handling. |
| Motion video | Motion AI Video | Seconds, resolution, or job | Likely high cost and latency; needs strict controls. |
| Storage/bandwidth | Media library, generated assets | GB stored/transferred | Monitor as users upload and generate media. |
| Queue/compute | Async jobs | Job count and duration | Infrastructure cost, separate from provider fees. |

## Credit model assumptions

- Credits are internal platform credits, not payment cards.
- User credit balances must never go negative.
- A generation should check available credits before starting real provider work.
- Credit charges should map to expected provider cost plus target margin and risk buffer.
- Failed provider jobs need a clear refund/no-charge policy before launch.
- Expensive tools may require higher minimum balance or confirmation.

## Action card pricing and access visibility

Project workspace action cards should surface the practical pricing/access decision before a generation starts:

| Metadata | Why it matters |
| --- | --- |
| `service_tier` | Helps creators understand whether an action is Basic, Advanced or Premium. |
| `credit_cost` | Shows the expected internal platform credit charge before the user clicks generate. |
| `user_has_access` / `upgrade_required` | Separates plan access from available credits. |
| `primary_cta` | Lets locked actions route to pricing/account instead of failing after setup. |
| `requirements` | Keeps input readiness in launcher forms rather than cluttering pricing copy. |

This does not implement billing or subscriptions. It only makes future cost controls more transparent: users should see the subscription tier and credit cost before provider work starts, and the backend should continue preventing negative balances.

## Service tier cost differences

Credits must account for Basic, Advanced and Premium service tier cost differences.

| Service tier | Expected cost profile | Credit strategy | Notes |
| --- | --- | --- | --- |
| Basic | Lower provider and infrastructure cost | Keep accessible with low credit costs | Scripts, captions, hooks, insights and localization should activate creators quickly. |
| Advanced | Moderate provider and processing cost | Charge more when media inputs, voiceover or processing are involved | Voiceover, subtitles, transcripts and edit plans may use longer jobs or media storage. |
| Premium | High provider cost and higher failure/retry risk | Require higher credits, premium plans, or stricter usage limits | Fashion try-on, motion video, image-to-video and avatars need strong margin and spend controls. |

Pricing should not treat every generation as equivalent once real providers are introduced. A single Premium output may cost far more than multiple Basic script or caption outputs.

## Provider cost mapping

| Tool | Mock status | Future provider category | Provider cost unit | Suggested credit unit | Verification needed |
| --- | --- | --- | --- | --- | --- |
| Affiliate Campaign Generator | Mock + optional OpenAI | Text/script/caption | Tokens | TODO | Verify current model pricing, output size, retries and structured-output overhead. |
| Video Enhancer / Caption Pack | Mock | Text/caption analysis, optional video tools | TODO | TODO | Scope of real enhancement. |
| Product-to-Video Script Pack | Mock | Text/script/caption | TODO | TODO | Script length and localization cost. |
| AI Voiceover | Mock + optional ElevenLabs | Voiceover/TTS | Characters by provider model | TODO | Real v1 tracks estimated cost by character count/model as internal metadata only; pricing is not finalized. |
| Fashion Try-On | Mock | Try-on/image generation | TODO | TODO | Cost per job, failure rate, constraints. |
| Motion AI Video | Mock | Motion/video generation | TODO | TODO | Cost per second/resolution and queue time. |

### Real campaign text cost tracking note

Affiliate Campaign Generator can optionally record OpenAI token usage and estimated provider cost when the real provider is enabled for internal users.

Current assumptions:

- Token usage should be treated as internal cost telemetry only.
- Input/output token rates must be verified from official provider pricing before public launch.
- Structured output may increase output tokens, so schema size and retries should be measured.
- User-facing credits should not change automatically from provider-cost metadata.
- Failed provider jobs should remain failed/refunded rather than charging users for unusable output.
### Real voiceover cost tracking note

AI Voiceover v1 records an estimated provider cost by character count and configured provider model. This is internal metadata for cost monitoring only.

Current assumptions:

- Flash/Turbo-style model estimate is configurable per 1,000 characters.
- Higher-quality multilingual model estimate is configurable per 1,000 characters.
- Estimates must be verified against current provider pricing before public launch.
- Do not automatically change user-facing pricing or credit charges from these estimates yet.
- Keep failed-job refunds/no-charge behavior intact before expanding real provider access.

## Gross margin target

Set targets after provider research.

| Tool category | Gross margin target | Risk buffer | Notes |
| --- | --- | --- | --- |
| Text tools | TODO% | TODO% | Usually easier to price with comfortable margins. |
| Voiceover/TTS | TODO% | TODO% | Candidate for first real provider. |
| Image/try-on | TODO% | TODO% | Account for failed generations and retries. |
| Motion video | TODO% | TODO% | Needs strongest cost controls. |

## Suggested formulas

Use variables until exact costs are verified.

```text
provider_cost_per_generation = provider_unit_cost * expected_units_per_generation
infrastructure_cost_per_generation = storage_cost + queue_cost + bandwidth_cost
risk_buffer = provider_cost_per_generation * risk_buffer_rate
minimum_revenue_per_generation = provider_cost_per_generation + infrastructure_cost_per_generation + risk_buffer
credit_price_floor = minimum_revenue_per_generation / credits_charged
margin = (revenue_per_generation - total_cost_per_generation) / revenue_per_generation
```

Credit charge suggestion:

```text
credits_charged = ceil((total_expected_cost / target_value_per_credit) * risk_multiplier)
```

## Example scenarios

| Scenario | Tool | Expected provider units | Provider cost | Credits charged | Revenue estimate | Gross margin | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Low-cost text campaign | Affiliate Campaign Generator | TODO | TODO | TODO | TODO | TODO | Verify with real text provider. |
| Standard voiceover | AI Voiceover | TODO | TODO | TODO | TODO | TODO | Good first paid-cost test. |
| Fashion try-on output | Fashion Try-On | TODO | TODO | TODO | TODO | TODO | Include failure/refund assumptions. |
| Short motion video | Motion AI Video | TODO | TODO | TODO | TODO | TODO | Include queue and retry assumptions. |

## Open questions

- What should one credit be worth in IDR and USD?
- Should different markets have different credit pack prices?
- Which tools should be available on free plans?
- How many failed generations should be absorbed before stricter limits?
- Should unused monthly credits roll over, expire, or remain only for prepaid packs?
