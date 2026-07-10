# Video Analysis Provider Research

## Research goals

Identify the best first real provider for Nusa Creator Studio's Video-to-Campaign beta workflow.

The provider must help turn uploaded social commerce/product footage into structured creator outputs:

- video summary
- observed product features
- detected moments
- recommended cuts
- campaign hooks
- voiceover-ready narration text
- captions, hashtags and CTA options
- edit plan for later Auto Video Editor rendering

## Selected beta provider

Gemini is the selected beta provider for Video-to-Campaign.

Why Gemini first:

- Direct video input support is suitable for short product/social commerce footage.
- Structured output / JSON schema support fits the existing saved `Generation` payload model.
- Creative reasoning is useful for turning observations into hooks, narration and edit direction.
- It keeps implementation complexity lower than adding a specialized video-understanding provider plus a separate text-generation provider for v1.

The integration should remain disabled by default and internal-only until beta output quality, latency and provider cost are measured with real test videos.

## Provider shortlist

| Provider | Category | Potential role | Beta status | Notes / TODO |
| --- | --- | --- | --- | --- |
| Gemini | Multimodal/video + text reasoning | Selected first beta provider for Video-to-Campaign | Selected | TODO: verify exact production API limits, supported video formats, latency and pricing before public rollout. |
| TwelveLabs | Specialized video understanding | Future benchmark for deeper video search/understanding | Future | Strong candidate if Nusa needs timeline search, transcript/visual indexing or deeper video semantics later. |
| OpenAI | Text/multimodal reasoning | Future benchmark, not used for this workflow in this task | Future | OpenAI is already researched for campaign text generation; not integrated for Video-to-Campaign here. |
| Replicate | Provider marketplace | Possible wrapper for video/image models | Future | Useful for experimentation; production reliability and cost controls need verification. |
| fal.ai | Fast media model platform | Possible future image/video generation infrastructure | Future | Better fit for generated media workflows than first video analysis. |
| Runway API | Video generation/editing | Future motion/rendering provider candidate | Future | Not needed for Video-to-Campaign text/edit-plan analysis. |

## Gemini beta integration scope

Included:

- Server-side read of private source video media assets.
- Server-side upload to Gemini Files API / file input flow.
- File processing poll with timeout.
- Structured JSON generation request.
- Safe server-side JSON parse and schema normalization.
- Safe provider metadata capture: provider, model, mode, usage, latency, source media asset id and file size.
- Optional internal cost estimate when token pricing env values are configured.
- Best-effort provider-side temporary file deletion.

Excluded:

- Public signed video URLs.
- Raw private storage paths in API responses.
- Raw Gemini file URI/name in user-visible output.
- Real video rendering.
- Shotstack, TwelveLabs, Creatomate or OpenAI integrations.
- Billing, payments or provider-cost-based credit pricing.

## Structured output target

The result payload should remain compatible with current Video-to-Campaign and downstream workflows:

- `VideoToCampaignGenerationResult`
- `AiVoiceoverSourceResolver`
- Auto Video Editor source reuse

Gemini output may contain richer object shapes than the mock provider, especially for hooks, timestamps, observed product features and edit plan timeline items. The frontend should render both old mock shapes and the Gemini schema safely.

## Safety and honesty guardrails

Video analysis output must not:

- invent product claims
- invent discounts
- invent testimonials
- invent fake revenue, conversion or sales numbers
- guarantee virality or sales
- make unsupported health, financial, legal or medical claims

If information is missing or not visible, it should be returned in:

- `missing_information`
- `unverified_claims`

## Cost and latency research table

Do not hardcode provider prices in application code. Fill this table only after external verification.

| Provider | Model | Input pricing | Output pricing | Video/file pricing | Typical latency | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| Gemini | `gemini-2.5-flash` | TODO verify | TODO verify | TODO verify | TODO measure | Initial beta model. |
| TwelveLabs | TODO | TODO verify | TODO verify | TODO verify | TODO measure | Future benchmark. |
| OpenAI | TODO | TODO verify | TODO verify | TODO verify | TODO measure | Not used for this task. |

## Recommended beta rollout

1. Keep `VIDEO_TO_CAMPAIGN_PROVIDER=mock` by default.
2. Enable Gemini only for admin/internal/unlimited users.
3. Run short, clear product videos under the configured size/duration caps.
4. Review output quality against safe-claims rules.
5. Confirm downstream reuse:
   - Gemini narration → AI Voiceover
   - Gemini edit plan → Auto Video Editor mock flow
6. Measure latency and token usage.
7. Decide whether to open a limited paid beta after real cost data exists.

## Open questions

- Which exact video formats should be allowed beyond MP4?
- Should we generate thumbnails or duration metadata during upload validation?
- How should provider cost estimates surface in admin analytics?
- What quality threshold should promote Gemini from internal beta to public beta?
- When should TwelveLabs be benchmarked against Gemini for deeper video understanding?
