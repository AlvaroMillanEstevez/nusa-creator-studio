# Service Scope

Nusa Creator Studio is structured into three service levels so creators can understand what a workflow produces, how expensive it may become, and how honestly it should be labeled while the product moves from mock outputs to real providers.

## Beta core stack

The beta should focus on the workflows that create a practical social commerce production chain:

- Affiliate Campaign Generator: Basic campaign/script intelligence with mock default and optional internal text provider.
- Video-to-Campaign: Advanced video analysis with Gemini available for internal beta users and mock fallback for everyone else.
- AI Voiceover: Advanced audio generation with ElevenLabs available for eligible/internal users and mock fallback.
- Auto Video Editor: Premium composite workflow with mock default and Shotstack real rendering available for internal beta users only.

Future premium workflows such as Fashion Try-On, Motion AI Video, avatar presenters and custom avatars should remain clearly labeled as coming soon/preview until real providers, safety rules and cost controls are ready.

## Basic

Low-cost content intelligence.

Typical workflows:

- Campaign generation.
- Scripts, hooks, captions and hashtags.
- Content insights.
- Product/content analysis.
- Localization.
- Compliance and checklist support.

Main output:

- Text.
- Insights.
- Scripts.
- Prompts.
- Structured plans.

## Advanced

Assisted media workflows.

Typical workflows:

- AI voiceover.
- Video-to-Campaign analysis from uploaded product footage.
- Video upload analysis.
- Subtitles/transcript.
- Cut/edit plans.
- Real recuts or assisted clips in the future.
- Audio insertion in the future.
- Product image to ad concept.
- Product-to-video assisted workflow.

Main output:

- Audio.
- Subtitles.
- Edit decision plans.
- Assisted media assets.
- Lightweight generated or processed assets.

## Premium

Expensive generative media.

Typical workflows:

- Fashion Try-On real provider output.
- Auto Video Editor composite planning and future rendering.
- Motion AI Video real provider output.
- Product image-to-video real provider output.
- AI avatar presenter.
- Custom avatar from a photo, person image, photo set or description.
- Avatar + voice product ads.
- Multi-scene AI ad generation.

Main output:

- Generated images.
- Generated videos.
- Avatars.
- Premium media assets.


## Subscription plans vs service tiers

Basic, Advanced and Premium are service/tool tiers. They describe workflow complexity, likely provider cost and access rules. They are not subscription plans.

Subscription plans should be presented separately. A subscription plan gives a user:

- A monthly internal platform credit allowance.
- Access to one or more service tiers.
- Limits or benefits appropriate to the plan.

Pricing pages should focus on actual subscription plans, credits and access. The Tools page should explain which workflows are Basic, Advanced or Premium.

Suggested copy pattern:

- “X monthly credits.”
- “Access to Basic workflows.”
- “Access to Advanced workflows.”
- “Premium workflows included.”
- “Upgrade when you need higher-volume or premium workflows.”

Avoid presenting Basic, Advanced and Premium as standalone pricing cards unless they become real subscription products later.

## Projects vs tools

Nusa Creator Studio should use tools as workflows/actions, not isolated destinations that force creators to manually copy/paste outputs between pages.

- A project can start from a tool route, such as Affiliate Campaign Generator, AI Voiceover, Fashion Try-On or Motion AI Video.
- After creation, the project should act as the creative workspace for product context, generated outputs and reusable media assets.
- Project types can still identify how the project started, but the long-term workspace model should let multiple tools run against the same project.
- Basic tools create structured context that Advanced and Premium workflows can reuse.
- Advanced and Premium workflows should inspect project context and assets before asking the user for more input.

Example:

```text
Affiliate Campaign Generator (Basic)
→ creates campaign hooks/script/caption context
→ AI Voiceover (Advanced) can reuse the selected script
→ Motion AI Video / Avatar Presenter (Premium, later) can reuse script, voiceover and media assets
```

## Workspace action access model

Project pages should present Basic, Advanced and Premium services as workspace actions with clear access and cost metadata.

| Tier | Access behavior | Workspace action behavior |
| --- | --- | --- |
| Basic | Available to authenticated users by default. | Can run directly when the project has enough brief context, or open a simple configure flow. |
| Advanced | Available to Starter, Creator, Pro and internal/unlimited users. | Can reuse prior project generations, direct source text, media context or launcher options. |
| Premium | Available to Creator, Pro and internal/unlimited users. | Usually opens a configure flow for media requirements before expensive provider work starts. |

Admin, internal creator, unlimited and `internal_unlimited` accounts can access implemented actions regardless of public plan gating. Free users should still see Advanced and Premium actions, but locked actions should use a positive “Upgrade subscription” CTA instead of hiding the workflow.

Action cards should show service tier and credit cost before generation. Detailed missing inputs, such as model reference or garment reference images, belong in launcher forms so the card remains encouraging and compact on mobile.

## Tool-by-tool mapping

| Tool | Service tier | Output type | Current implementation stage | Notes |
| --- | --- | --- | --- | --- |
| Affiliate Campaign Generator / Product Content Kit | Basic | Script pack | Mock + optional real provider | Low-cost campaign intelligence for scripts, hooks, captions, voiceover-ready copy and filming notes. Supports brief-only and image-assisted context, but does not create video cuts/edit-plan data. OpenAI can be enabled for internal users only. |
| Video Enhancer / Caption Pack | Advanced | Assisted media | Mock | Legacy-compatible mock edit/caption plan; future work should migrate toward the project workspace video workflows. |
| Video-to-Campaign | Advanced | Campaign + edit plan | Beta real provider + mock fallback | Gemini can analyze selected project video assets for internal beta users; mock remains default. Saves reusable hooks, narration text, captions, recommended cuts and edit plan. It does not generate audio. |
| Product-to-Video Script Pack | Advanced | Assisted media | Mock | Current script/storyboard; future real v1 can move from product image or URL to storyboard/prompt; future real v2 can move toward image-to-video. |
| AI Voiceover | Advanced | Audio | Real provider + mock fallback | v1 can generate ElevenLabs audio for internal/eligible users and save it as a private MediaAsset; mock remains the default fallback. |
| Fashion Try-On | Premium | Image | Future / coming soon | Future real provider should generate try-on images after safety, cost and UX controls are ready. |
| Auto Video Editor | Premium | Video edit plan / rendered video | Beta real provider + mock fallback | Composite workflow with AI video analysis mode for eligible Video-to-Campaign results and manual edit setup for source-video fallback timelines. Mock remains default; Shotstack can render a real private MP4 for eligible internal users when enabled. |
| Motion AI Video | Premium | Video | Future / coming soon | Future real provider should generate video assets after cost controls and provider selection are ready. |
| AI Avatar Presenter | Premium | Avatar | Future | Future avatar-led product presenter videos with voice. |
| Custom AI Avatar | Premium | Avatar | Future | Future reusable avatar identity from person images, photo sets or descriptions. |
| Avatar Voice Ads | Premium | Mixed media | Future | Future avatar-led product ads combining presenter, voiceover and scripts. |



### Product Content Kit and video editing compatibility

Product Content Kit can be brief-only or image-assisted. Both modes create useful text assets: hooks, scripts, captions, CTA options, publishing notes and voiceover-ready narration. They should not be treated as primary Auto Video Editor sources because they do not include source video ranges or recommended cuts.

For the best Auto Video Editor result, users should first generate a Video-to-Campaign Insight from raw product footage. That creates `video_analysis` metadata with a source video and edit-plan/recommended-cut data.

Manual edit setup is still valid when the creator has a source video but no Video-to-Campaign Insight yet. Manual setup uses fallback timeline logic based on the selected video, target duration, pacing, clip diversity, CTA, captions, voiceover duration and edit-polish settings. It should be labeled honestly as a fallback timeline, not as AI video analysis.
## Auto Video Editor beta scope and limitations

Auto Video Editor is currently a beta clean-cut workflow. The goal is to make the real render useful and honest without implying it is a finished professional editor.

Current beta behavior:

- Reuses Video-to-Campaign analysis when selected, including CTA options, edit plan cuts and campaign angle.`n- Supports manual edit setup with a selected source video and fallback timeline logic when no eligible Video-to-Campaign Insight exists.
- Reuses existing voiceover generation/audio when selected.
- Builds a structured creative brief from user settings such as editing style, pacing, caption style, CTA, music direction and target duration.
- Supports source audio modes for mute, low source audio, keep source audio and product-sound focus.
- Supports voiceover mix controls for balanced, voiceover-dominant and soft-background exports.
- Supports lightweight edit-polish settings for transition style, motion polish, clip diversity and end screen behavior.
- Extends the timeline to fit known or estimated voiceover duration so narration is not cut off.
- Uses simple clean cuts, repeated product shots and CTA/end-card holds as safe fallback techniques.
- Keeps captions short and inside a vertical safe area.
- Stores timeline summary, duration sync strategy, caption count and CTA metadata in the generation output.

Known beta limitations:

- Not a full professional video editor yet.
- No automated beat sync or licensed music selection.
- No music generation or TikTok music selection; TikTok native/commercial music is deferred to a later publishing workflow.
- No advanced AI denoise yet. Beta cleanup controls volume and mixing only.
- No object-aware crop/reframe decisions beyond basic vertical cover fitting.
- No advanced transitions, motion graphics or scene reconstruction.
- Source footage that is too short may still reuse product clips visibly.
- Creators should review product claims, captions and timing before publishing.


## Affiliate Campaign Generator v1 real provider scope

Affiliate Campaign Generator is the first Basic workflow that can use a real text provider. Its scope is intentionally narrow:

- Generate structured campaign copy from creator-provided product inputs.
- Support English, Indonesian and Spanish output.
- Include hooks, script scenes, captions, hashtags, CTA options, compliance notes and a `voiceover_ready_script` field.
- Keep the mock provider as the default and fallback path.
- Restrict real provider usage to admin/internal/unlimited accounts while `CAMPAIGN_REAL_INTERNAL_ONLY=true`.
- Store token usage and estimated provider cost as internal metadata only when configured.
- Fail safely and refund charged credits if provider configuration or response validation fails.

Out of scope for this version:

- Product URL scraping.
- Product image analysis.
- Marketplace listing analysis.
- Real ad performance prediction.
- Billing or provider-cost-based credit pricing.

## AI Voiceover v1 real provider scope

AI Voiceover is the first real provider-backed Advanced workflow. Its v1 scope is intentionally narrow:

- Generate narration audio from a source script or safe product-based fallback text.
- Use the backend-configured default ElevenLabs voice and model.
- Save generated audio as a private `MediaAsset` with `type=audio` and `usage_role=audio_track`.
- Keep the mock provider as the default and fallback path.
- Restrict real provider usage to admin/internal/unlimited accounts while `VOICEOVER_REAL_INTERNAL_ONLY=true`.
- Store provider cost estimates as internal metadata only.

The workflow role is a reusable content chain asset:

```text
campaign/script → voiceover → subtitles/captions → video overlay / avatar / motion video later
```

Out of scope for v1:

- Video overlay.
- Subtitle timing.
- Avatar generation.
- Public audio download/player routes.
- Billing or provider-cost-based credit pricing.

## Future avatar workflows

### AI Avatar Presenter

Create product presenter videos using an AI avatar and voice. This should remain Premium because avatar video generation is likely provider-expensive and needs careful disclosure.

### Custom AI Avatar

Create a reusable avatar from a person image, photo set, or description. This workflow needs consent, identity-safety rules, and clear labeling before real implementation.

### Avatar Voice Ads

Combine AI avatar presenters, voiceover and product scripts into social ads. This should wait until voiceover, script generation, media library, and provider cost controls are stable.

## Composite workflow credit transparency

Composite workflows can include multiple logical steps inside one action. Auto Video Editor is the first example:

| Component | When charged | When reused at zero additional credits |
| --- | --- | --- |
| Base editor planning | Always part of the Auto Video Editor workflow for normal users. | Internal/unlimited accounts follow existing zero-charge behavior. |
| Video campaign analysis | Not charged inside Auto Video Editor unless a future composite analysis step is explicitly implemented. | Reused at zero additional credits when the user selects an eligible completed Video-to-Campaign generation. |
| Voiceover | Charged when the user chooses to include a new AI voiceover. | Reused when the user selects an existing voiceover generation or audio asset; zero when no voiceover is selected. |
| Shotstack render | Zero additional user-facing credits in the current beta. | Real provider cost is tracked as metadata when configured; provider-cost-based pricing is deferred. |

The UI and saved generation metadata should show the estimated normal cost breakdown even when an internal/unlimited account is charged zero credits. This keeps the product honest and prepares the workflow for future real provider cost controls.

## Honest labeling

Public UI, docs and examples should label outputs clearly:

- `mock` - deterministic product output created without real external AI providers.
- `beta` - early real or semi-real workflow being tested with limited users.
- `real provider-backed` - output generated by a real external provider after integration is enabled.
- `future` - catalog item or concept that is not yet implemented.

Mock outputs must not be confused with real provider-backed AI media. Auto Video Editor mock results must clearly say they are edit plans only. Shotstack-rendered Auto Video Editor results should show the private video preview/download and remain labeled as beta real provider output. Future tools must remain visibly future/coming soon until they are truly available.

## Credit strategy by tier

| Tier | Credit strategy | Notes |
| --- | --- | --- |
| Basic | Low credit cost | Text and insight workflows should stay accessible and lightweight. |
| Advanced | Moderate credit cost | Media inputs, voiceover and assisted processing may consume more credits. |
| Premium | Higher credit cost or premium-plan gating | Generative images, videos and avatars need stricter cost controls before real launch. |

Do not let user balances go negative. Expensive tools should check credit balance before starting provider work and define refund/no-charge behavior for failed jobs.

## Recommended real implementation order

Completed / in beta:

1. AI Voiceover real provider with ElevenLabs.
2. Affiliate Campaign Generator real text provider for internal users.
3. Video-to-Campaign real video analysis provider with Gemini for internal beta users.

Recommended next:

4. Auto Video Editor real render provider with Shotstack for internal beta users.
5. Fashion Try-On real provider.
6. Motion AI Video real provider.
7. Product image-to-video real provider.
8. AI Avatar Presenter.
9. Custom AI Avatar.
10. Avatar Voice Ads.

This order keeps the beta chain practical: source video → Gemini analysis/campaign → ElevenLabs voiceover → Shotstack render. Expensive generative media and identity-sensitive avatar workflows should wait until cost monitoring, labeling and safety controls are stronger.
