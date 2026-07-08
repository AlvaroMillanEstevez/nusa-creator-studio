# Integration Roadmap

This roadmap keeps Nusa Creator Studio focused on mock-first product validation before adding paid or production external APIs.

## Current mock architecture

- The platform has auth, admin, dynamic tools, pricing/plans, media library, async jobs, and generation history.
- The six current tools use mock providers:
  - Affiliate Campaign Generator
  - Video Enhancer / Caption Pack
  - Product-to-Video Script Pack
  - AI Voiceover
  - Fashion Try-On
  - Motion AI Video
- Mock generations create project/generation records, preserve history, and use internal platform credits without calling real external providers.

## Why mocks came first

- Validate product flows before committing to provider costs.
- Keep development safe while auth, credits, admin, media, and async jobs mature.
- Avoid overbuilding provider abstractions before the tool UX is clear.
- Let pricing and credit behavior be tested without real billing or checkout.
- Reduce risk from third-party API limits, failures, and cost surprises.

## Recommended first real API integration

AI Voiceover should be the first real provider integration.

Reasons:

- It is simpler than try-on or motion video because the input/output shape is easier to validate.
- It should be cheaper to test than high-resolution image/video generation, pending provider verification.
- It produces an obvious creator asset that can be saved, replayed, and attached to projects.
- It is a good test bed for provider abstraction, async status, credit charging, and failure refunds.

Candidate providers to research first:

- ElevenLabs
- Google Cloud Text-to-Speech
- Azure Speech
- AWS Polly

## Integration phases

| Phase | Goal | Scope | Exit criteria |
| --- | --- | --- | --- |
| Phase 0: Research | Verify provider pricing, terms, API behavior | Provider docs, test accounts, cost model | Shortlist one primary and one fallback provider. |
| Phase 1: Adapter skeleton | Add provider interface without changing UX | Contracts, config placeholders, tests | Mock and real adapter can share the same app flow. |
| Phase 2: Internal alpha | Enable real provider for admin/internal users only | Feature flag, cost logging, limited credits | Successful jobs, clear failures, no credit leaks. |
| Phase 3: Limited beta | Enable for selected users/plans | Usage caps, monitoring, support flow | Costs and quality match assumptions. |
| Phase 4: Public rollout | Make provider-backed tool generally available | Documentation, pricing updates, support readiness | Stable margin, reliable UX, rollback tested. |

## Output labeling before public launch

Before public launch, define whether generated examples and saved outputs are labeled as:

- `mock` - deterministic mock output created before real provider integration.
- `beta` - beta/internal example output that may not represent final production quality.
- `real provider-backed` - output created by a real external AI provider after that integration is enabled.

Avoid confusing mock outputs with real AI provider outputs. Public examples, galleries, demo workflows, and support docs should make the output source clear.

## Risk controls

- Keep provider calls behind feature flags.
- Enforce credit balance checks before starting real provider work.
- Use idempotency keys for generation requests where practical.
- Store provider request IDs for debugging and reconciliation.
- Add hard daily/monthly spend caps per provider.
- Rate-limit expensive generation endpoints.
- Define refund/no-charge behavior for failed jobs before launch.
- Keep user-facing errors friendly and provider-neutral.

## Feature flags

| Flag | Purpose | Default | Notes |
| --- | --- | --- | --- |
| `voiceover_provider_real_enabled` | Enable real AI Voiceover provider | Off | Admin/internal users first. |
| `text_provider_real_enabled` | Enable real text/script provider | Off | Use after voiceover proves the pattern. |
| `fashion_try_on_real_enabled` | Enable real try-on provider | Off | Requires stronger input validation. |
| `motion_video_real_enabled` | Enable real motion video provider | Off | Requires strict spend and queue controls. |
| `provider_fallback_to_mock_enabled` | Allow fallback to mock in development or controlled failure modes | Off in production TODO | Define safe behavior before use. |

## Cost monitoring

Track at minimum:

- Provider name and operation.
- Provider request/job ID.
- Credits charged.
- Estimated provider cost.
- Final provider cost if available.
- Job status and failure reason.
- User, project, and generation IDs.
- Daily and monthly spend by provider/tool.

## Provider abstraction

Keep the abstraction small at first.

Suggested contract shape:

```text
GenerationProvider
- supports(jobType): bool
- estimateCost(payload): CostEstimate
- generate(payload): GenerationResult or ProviderJobReference
- getStatus(providerJobId): ProviderJobStatus
```

Guidelines:

- Do not build a complex provider marketplace too early.
- Keep provider-specific payload mapping inside adapter classes.
- Store normalized result payloads so the frontend remains stable.
- Keep raw provider diagnostics internal and avoid exposing unnecessary provider data to users.

## Rollback strategy

- Disable the real provider feature flag.
- Continue serving existing saved generation history.
- Fall back to mock only in development or if explicitly allowed by environment/config.
- Stop charging credits for real-provider attempts if the provider is disabled before execution.
- Preserve failed generation records for support/debugging without leaking provider internals.
- Communicate degraded tool status in the UI only when a user-facing feature is affected.
