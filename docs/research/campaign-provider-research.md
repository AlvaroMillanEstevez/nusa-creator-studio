# Campaign Provider Research

Nusa Creator Studio is adding real provider-backed campaign/script generation after the mock MVP. This research file tracks provider options, safety requirements and rollout notes without inventing unverified pricing or performance claims.

## Research goals

- Choose a reliable first provider for Affiliate Campaign Generator real script output.
- Preserve the existing mock provider as the default fallback.
- Keep campaign outputs structured enough for frontend rendering, history, voiceover reuse and future localization.
- Avoid fake traction, fake metrics, fake testimonials or unsupported product claims.
- Track provider cost metadata without changing billing, subscriptions or user-facing pricing yet.

## Current implementation direction

The first real campaign provider integration uses OpenAI behind backend feature flags:

- `CAMPAIGN_PROVIDER=mock` by default.
- `CAMPAIGN_REAL_ENABLED=false` by default.
- `CAMPAIGN_REAL_INTERNAL_ONLY=true` by default.
- Admin/internal/unlimited accounts may use the real provider when enabled.
- Normal users continue receiving mock output while the real provider is internal-only.

## Provider candidates

| Provider | Candidate use | Structured output support | Multilingual support | Cost model to verify | Notes |
| --- | --- | --- | --- | --- | --- |
| OpenAI | Campaign scripts, hooks, captions, compliance notes | TODO: verify current Responses API structured output behavior and limits | English, Indonesian, Spanish to verify | Tokens | First integration candidate because text output is lower risk than media generation. |
| Anthropic | Alternative script/copy provider | TODO | TODO | Tokens | Evaluate later if provider diversity is needed. |
| Google Gemini | Alternative script/copy provider | TODO | TODO | Tokens | Evaluate if Google stack becomes useful for other workflows. |
| Azure OpenAI | Enterprise deployment option | TODO | TODO | Tokens/deployment | Consider later for enterprise/regional requirements. |
| Local/open models | Cost-controlled fallback | TODO | TODO | Infrastructure cost | Not a priority until quality and ops needs are clearer. |

## Output schema requirements

Real campaign output should remain stable and frontend-friendly:

| Field | Purpose | Required |
| --- | --- | --- |
| `campaign_title` | Human-readable result title | Yes |
| `product_angle` | Core creative angle | Yes |
| `target_audience_summary` | Audience framing | Yes |
| `hooks[]` | Five hook objects with label/text/intent | Yes |
| `main_script` | Timed short-form video script and scenes | Yes |
| `voiceover_ready_script` | Clean script for the AI Voiceover workflow | Yes |
| `captions[]` | Caption options | Yes |
| `hashtags[]` | Hashtag set | Yes |
| `cta_options[]` | CTA variants | Yes |
| `compliance_notes[]` | Safety/claim reminders | Yes |
| `usage_notes[]` | Practical production tips | Yes |
| `provider_metadata` | Provider, model, token usage, estimated cost | Internal metadata only |

## Prompt safety requirements

Provider prompts must instruct the model to avoid:

- Fake metrics.
- Fake customer logos.
- Fake testimonials.
- Fake revenue, ad spend, user counts or case studies.
- Guaranteed viral/sales/income claims.
- Unsupported medical, legal or financial claims.
- Unsupported urgency/scarcity claims.

Outputs should encourage creators to verify product details and required affiliate disclosures before publishing.

## Rollout controls

| Control | Initial value | Reason |
| --- | --- | --- |
| Provider flag | `CAMPAIGN_PROVIDER=mock` | Keep mock as safe default. |
| Real enabled flag | `CAMPAIGN_REAL_ENABLED=false` | Avoid accidental provider spend. |
| Internal-only flag | `CAMPAIGN_REAL_INTERNAL_ONLY=true` | Test with admin/internal accounts first. |
| Input limit | `CAMPAIGN_MAX_INPUT_CHARACTERS=4000` | Control prompt size and cost. |
| Output token limit | `CAMPAIGN_MAX_OUTPUT_TOKENS=2500` | Control provider cost and response size. |
| Failure behavior | Mark generation failed and refund credits | Preserve trust and credit safety. |

## Cost tracking notes

- Token usage can be stored in generation `provider_metadata` when the provider returns usage data.
- Estimated cost should be calculated only when verified configurable token rates are available.
- Estimated cost is internal metadata, not billing logic.
- Do not change user-facing credit prices from provider metadata until pricing research is complete.

## Testing checklist

- Mock remains default.
- Real provider is selected only when configured and allowed.
- Normal users fall back to mock while internal-only is enabled.
- Missing API key fails safely and refunds.
- Invalid provider JSON/schema fails safely and refunds.
- Structured output saves `voiceover_ready_script`.
- API keys never appear in responses or stored output.
- Prompt contains honest marketing guardrails.
- Unicode text remains valid for English, Indonesian and Spanish.

## Open questions

- Which OpenAI model should be the first production default after current pricing and quality are verified?
- Should campaign generation support longer product briefs or uploaded research later?
- Should high-volume users get separate provider limits?
- How should output quality be evaluated across English, Indonesian and Spanish?
- What token-cost margin buffer should Basic text workflows use?