# Project Workflow Architecture

Nusa Creator Studio should feel like a creator workspace, not a catalog of disconnected tools.

## Closed beta workflow architecture

The closed beta should be organized around four main user-facing workflows inside one Project Workspace:

1. **Product Content Kit** — creates campaign angles, hooks, scripts, captions, hashtags, CTA options and publishing notes from a brief, product image or product video analysis.
2. **Auto Video Editor** — turns real/raw product footage into an organic creator/social video using AI video analysis or manual edit setup.
3. **Auto Video Ads** — available real-beta-capable workflow for paid ad creative with hook, benefits, promo overlays, CTA/end card and optional Shotstack MP4 render. It reuses Product Content Kit output, project assets and optional voiceover context. Mock mode remains the safe default.
4. **Motion AI Video** — available real-beta-capable workflow for visual-first image-to-video motion clips from product image, optional scenario/background image and campaign/script context. Mock mode remains the safe default; Google Veo can render a private MP4 for eligible internal users when enabled.

The Project Workspace contains:

- Product brief and strategy context.
- Media assets with usage roles.
- Saved generations and result history.
- Credit-aware workflow actions.
- Supporting tools that feed the main workflows.

Supporting tools are intentionally not positioned as equal fifth/sixth main workflows:

- **AI Voiceover** creates reusable narration audio for Auto Video Editor, Auto Video Ads and future Motion AI Video.
- **Video analysis** is the Video-to-Campaign path. It can create Product Content Kit from video and feed Auto Video Editor with recommended cuts, detected product moments, captions and narration text.
- **Media Library** manages uploaded and generated assets used across workflows.

Fashion Studio and Product Photo Studio remain later modules, not core closed-beta workflows.

## Core model

### Project as workspace/context

A `Project` is the durable workspace for a product, campaign, asset set or creative idea. It stores the creator's brief, settings, attached assets and generated history.

A project can begin from a specific workflow route, such as Product Content Kit or AI Voiceover, but the long-term product direction is that the project becomes the context hub for future actions.

### Tool as action/workflow

A `Tool` is a workflow that can run against a project. Tools may start a new project or continue an existing project with prior generations and media assets.

Examples:

- Product Content Kit creates reusable campaign/content context.
- AI Voiceover can use project source text or a completed campaign generation.
- Fashion Try-On can use model and garment media assets.
- Motion AI Video can use a product image, optional scenario/background image, campaign context, voiceover context, manual script or creative notes to create a structured image-to-video plan in mock mode or a visual-first Google Veo motion clip when real mode is enabled.

### Generation as output

A `Generation` is a saved output produced by a tool. It should keep structured request/source context and result payloads so later actions can reuse it safely.

Important source fields:

- `source_generation_id` links a generation to a previous generation in the same project/user workspace.
- `source_text` stores the source text or excerpt used for text-to-audio/text-to-media workflows.
- Future `source_media_asset_ids` can link generated work to specific media inputs.

### MediaAsset as input/output

A `MediaAsset` can be uploaded by users or created by provider workflows. It should be reusable as project context.

Useful roles include:

- `product_image`
- `source_video`
- `raw_product_video`
- `campaign_source_video`
- `video_reference`
- `audio_track`
- `voiceover_audio`
- `edited_video_result`
- `model_reference`
- `garment_reference`
- `background_reference`
- `brand_reference`

## Source relationships

The v1 workspace model should support simple source relationships before a full workflow graph exists:

| Source | Purpose | Current scope |
| --- | --- | --- |
| `source_generation_id` | Reuse completed structured output from the same project/user | Campaign/Product-to-video → Voiceover |
| `source_text` | Provide direct text for voiceover or future script-driven actions | AI Voiceover and same-project voiceover action |
| `source_media_asset_ids` | Future explicit media inputs | Planned |
| `usage_role` | Identify what each project asset can be used for | Media library, Video-to-Campaign, Auto Video Editor, Fashion Try-On, Motion AI Video |

## Action chaining example

```text
Product brief
→ Product Content Kit
→ AI Voiceove
→ Auto Video Ads / Auto Video Editor / Motion AI Video plan or visual motion clip
```

The current v1 implementation makes the Campaign → Voiceover step work in the same project. The user should not need to manually copy/paste campaign copy into a separate isolated voiceover tool.

## Product Content Kit input modes

Product Content Kit is the primary text/content workflow. It can start from:

- `brief_only`: creator-provided product/project brief only.
- `image_assisted`: creator brief plus one product image attached to the project as safe metadata/context.

Brief-only and image-assisted kits are useful for hooks, scripts, captions, CTA options, voiceover-ready narration and publishing prep. They are not primary sources for Auto Video Editor because they do not contain source video ranges, detected video moments or recommended cuts.

If the creator wants raw product footage analyzed for edit decisions, the correct path is **Analyze footage** / video analysis. Internally this still uses the Video-to-Campaign job, but user-facing UX should frame it as Product Content Kit from video and as preparation for Auto Video Editor. Video-analysis outputs use `campaign_input_mode=video_analysis`, include a source video asset id, and become Auto Video Editor eligible only when usable `recommended_cuts` or `edit_plan.timeline` data exists.

## Video analysis and Auto Video Editor chain

The next workspace chain is video-first:

```text
Project workspace
→ upload raw product video
→ Product Content Kit from video / video analysis
→ optional AI Voiceove
→ optional Auto Video Edito
```

The real beta chain is:

```text
raw video
→ Gemini video analysis + campaign/edit-plan JSON
→ ElevenLabs voiceover from Gemini narration text
→ Shotstack Auto Video Editor render for internal beta users
```

Video-to-Campaign is now treated as the internal video-analysis path, not a fifth main beta workflow. It analyzes a selected project video asset and saves structured campaign context, including `video_summary`, observed product features, detected product moments, recommended cuts, hooks, captions, an edit plan and `narration_voice_script`.

In default mode it uses the deterministic mock provider. In internal beta mode it can use Gemini by reading the private media asset server-side, uploading it to the provider file-input flow, and storing only safe metadata in the generation result. Private storage paths and raw provider file URIs should not be exposed to the frontend.

Video analysis produces narration text, not audio. The narration script is reusable text for the separate AI Voiceover action, so voiceover remains an intentional additional step unless another workflow explicitly includes it.

Auto Video Editor is a Premium composite workflow. It creates an edit-ready vertical video plan by default and can render a real beta MP4 with Shotstack for eligible internal users when enabled by configuration. It has two source modes:

- `ai_video_analysis`: reuse an eligible video-analysis generation with source video id plus usable recommended cuts or edit-plan timeline data.
- `manual_edit`: use an uploaded/selected source video plus creator-selected settings to build a fallback timeline.

Text-only and image-assisted Product Content Kit generations should not appear as AI-analyzed campaign sources because they do not contain source video cuts. Manual edit setup remains available when the creator has a source video but has not run Analyze footage yet.

Auto Video Editor can also reuse an existing AI Voiceover generation or audio asset, or proceed with no voiceover. In mock mode it may still plan a new voiceover component. In real Shotstack mode, automatic new ElevenLabs generation is intentionally deferred: creators should generate a voiceover first, then select that existing audio in Auto Video Editor.

## Auto Video Ads beta workflow

Auto Video Ads is now an available Premium beta workflow. It creates paid social ad creative from one project workspace instead of a disconnected ad tool. By default it uses the deterministic mock provider and returns a structured plan; when `AUTO_VIDEO_AD_PROVIDER=shotstack`, `AUTO_VIDEO_AD_REAL_ENABLED=true` and the user is eligible, it renders a real MP4 with Shotstack.

Input sources:

- A product image, a source video, or both. At least one visual asset is required.
- Optional Product Content Kit, video-analysis or product-to-video generation context.
- Optional AI Voiceover generation context.
- Ad setup options such as platform, format, goal, target duration, tone, visual style, hook style, primary CTA and verified offer text.

The backend endpoint is `POST /api/v1/projects/{project}/generate-auto-video-ad`. It charges 12 internal platform credits at generation start, creates a queued `Generation` with job type `generate_auto_video_ad`, and completes asynchronously through the selected provider. Mock mode remains available; Shotstack mode downloads the rendered MP4 server-side and stores it as a private generated video `MediaAsset`.

The shared output schema keeps the ad plan sections in both mock and real modes. It includes:

- `ad_summary`
- `hook_options`
- `ad_script`
- `storyboard`
- `overlay_plan`
- `caption_variants`
- `hashtags`
- `cta_options`
- `compliance_notes`
- `render_plan`
- `metadata`

Mock mode must not create a fake MP4, fake render URL or generated video `MediaAsset`; the result UI should clearly say that final MP4 rendering is disabled/not available. Shotstack mode adds secure final-video fields such as `rendered_video_asset`, `final_video_asset_id`, `final_video_stream_url`, `final_video_download_url`, `provider_metadata.provider_render_status=done` and `metadata.render_available=true`. Provider URLs and private storage paths must remain hidden from the frontend.

Shotstack Auto Video Ads renders support image-only, video-only and hybrid inputs. They require temporary provider-accessible URLs for private product images, source videos and optional voiceover audio. These URLs use opaque expiring tokens and do not expose raw storage paths. In local development, a public HTTPS tunnel such as ngrok or cloudflared is required because Shotstack cannot fetch private `localhost` URLs.

### Composite credit reuse rules

Auto Video Editor should make prior work visible in its credit breakdown:

| Existing project context | Auto editor behavior |
| --- | --- |
| Completed Video-to-Campaign result selected | Video analysis is reused, so no additional video-analysis credit is added. |
| Manual edit selected with raw/source video | Uses fallback timeline logic; no Video-to-Campaign analysis credit is added because no new analysis is run. |
| Existing voiceover generation or audio asset selected | Voiceover is reused, so no additional voiceover credit is added. |
| Include voiceover with no existing voiceover selected | The composite cost includes the AI Voiceover component. |
| No voiceover selected | Voiceover cost stays zero. |

The charged effective credits should match the composite line items for normal users. Internal/unlimited users follow the existing zero-charge behavior while still seeing the normal estimated cost breakdown.

### Auto Video Editor beta quality pass

The real beta chain now carries more context from each step into the next render step:

- Gemini Video-to-Campaign prompts ask for render-friendly edit plans with 3-5 practical cuts, short caption-safe copy, narration duration guidance, CTA options and concrete visual reasons.
- Auto Video Editor builds a normalized creative brief from user settings, selected Video-to-Campaign output, selected voiceover/audio metadata and project context.
- Auto Video Editor render settings include source audio mode, voiceover mix, transition style, motion polish, clip diversity and end screen behavior.
- Source audio controls are volume/mix controls only: mute source, keep source low, keep source, or product-sound focus. Advanced AI denoise is deferred.
- Shotstack timeline generation is voiceover-aware: when voiceover duration is known or estimated, the final timeline should be at least as long as the narration plus a small ending buffer.
- Short source footage can be extended with repeated product moments and a CTA/end-card beat instead of ending on black or cutting off audio.
- Shotstack timeline metadata should store source audio volume, voiceover volume, audio cleanup strategy and whether original audio was muted.
- Captions should stay in a conservative vertical safe area, use 1-2 short lines and avoid oversized text blocks.
- The Auto Video Editor form can reuse prior campaign CTA/options and voiceover duration hints, while avoiding overwriting fields the creator has already edited.

This is still a beta clean-cut renderer, not a full nonlinear editor. Advanced effects, licensed music selection, TikTok music selection, beat syncing, object-aware reframing and multi-scene creative direction remain later work.


## Honest separation of generated content and UI actions

Generated campaign content should stay useful to the creator. It should not advertise Nusa services or include next-service instructions such as “copy this into voiceover next.”

Workflow guidance belongs in the application UI, for example:

- Continue with this project.
- Continue with selected result.
- Create voiceover from this script.
- Use this script for a future video ad or avatar workflow.

This keeps provider output portable and prevents generated content from becoming product upsell copy.


## Workspace cleanliness and focused result views

The main project workspace should stay clean and action-oriented:

1. Project header and context.
2. Continue/action hub.
3. Generation history.
4. Optional compact status or summary cards only when they help navigation.

Full generation outputs should open in focused result views such as `/projects/{project}/generations/{generation}`. This keeps long campaign scripts, captions, hashtags and provider metadata from overwhelming the main workspace.

Campaign result pages can include contextual actions. In particular, the campaign detail view may expose an editable **Narration voice** section that lets the creator adjust the voiceover text before launching AI Voiceover in the same project. The edited narration text should be sent as `source_text` while keeping `source_generation_id` linked for traceability.

Project assets should ideally be attached through action setup flows with clear roles, such as `product_image`, `model_reference`, `garment_reference`, `source_video` or `audio_track`. The generic workspace should avoid showing a broad upload area before the creator knows what an asset means in the workflow.

The Project Action Hub can appear in two contexts:

- In the main workspace with no selected generation, where it starts or continues from project context.
- In a focused generation result view with a selected generation, where actions can reuse that result as source context.

## Basic, Advanced and Premium relationship

Lightweight content workflows often create context for later media workflows.

| Tier | Workspace role |
| --- | --- |
| Basic | Create low-cost structured context: hooks, scripts, captions, insights and voiceover-ready text. |
| Advanced | Reuse text/media context for assisted workflows such as voiceover, captions, Video-to-Campaign analysis and product-to-video planning. |
| Premium | Reuse project context, media assets and prior generations for expensive or composite workflows such as Auto Video Editor, try-on, motion video and avatar workflows. |

## Project action card metadata

Project action cards should be lightweight invitations, not validation error panels. The card-level API metadata should expose enough information for the UI to decide whether the action can be started, configured, upgraded or shown as coming soon.

Core fields:

| Field | Purpose |
| --- | --- |
| `service_tier` | Shows whether the action is Basic, Advanced or Premium. |
| `credit_cost` | Shows the expected internal platform credits before the user starts generation. |
| `user_has_access` | Indicates whether the current user can access the service tier. |
| `upgrade_required` | Marks actions that should route users toward pricing or account plan management. |
| `access_label` | Short positive explanation of why the user has access. |
| `locked_reason` | Safe explanation for locked actions without leaking internal rules. |
| `action_mode` | One of `direct`, `configure`, `upgrade` or `coming_soon`. |
| `primary_cta` | Card CTA label plus route or frontend action key. |
| `requirements` | Structured launcher-form requirements, not negative card copy. |
| `recommended_sources` | Prior same-project generations that can be reused. |
| `recommended_assets` | Project media assets that match useful roles. |
| `missing_requirements_count` | Count for launcher readiness indicators. |

Card copy should stay positive:

- `direct`: “Generate campaign”, “Generate another campaign version”, “Create voiceover”.
- `configure`: “Generate campaign from video”, “Open Auto Video Editor”, “Configure ad plan”, “Configure Motion AI Video”, “Setup Try-On”, “Create voiceover”.
- `upgrade`: “Upgrade subscription”.
- `coming_soon`: “Coming soon”.

Avoid card text such as “missing model image” or “missing garment image”. Those details belong inside the launcher form where the user can choose, upload or replace inputs.

## Launcher requirements

Launcher forms should own detailed requirements and guidance. The action endpoint can return requirement objects such as:

| Requirement type | Example keys | Notes |
| --- | --- | --- |
| `input` | `product_name`, `product_description` | Basic project brief requirements. |
| `source_generation` | `campaign_or_script_generation` | Completed same-project generation source. |
| `source_text` | `source_text` | Direct pasted or written script text. |
| `media_asset` | `model_reference`, `garment_reference`, `product_image`, `source_video`, `background_reference`, `brand_reference` | Media inputs selected or uploaded in the launcher. |

A launcher can use `recommended_sources` and `recommended_assets` to preselect the most likely inputs while still allowing the creator to choose another source. This keeps the workspace-first flow flexible without turning project cards into dense configuration forms.

## Future UX direction

Future project pages can expose action language such as:

- “Continue with this result.”
- “Use this script for…”
- “Create next asset.”
- “Attach source asset.”
- “Review source chain.”

The UI should stay contextual and mobile-first instead of forcing creators to choose from a disconnected tool list every time.


## Auto Video Editor timeline construction

Auto Video Editor builds a Shotstack timeline from several existing project signals rather than a new prompt-based provider step:

1. Project brief and workspace settings provide product context, platform and CTA preferences.
2. Video analysis can provide cuts, detected product moments, captions and CTA options.
3. `edit_mode` chooses between AI video analysis reuse and manual edit fallback.
4. Audio controls decide source audio volume, voiceover mix and voiceover-aware duration extension.
5. Edit polish settings shape pacing: transition style, motion polish and clip diversity.
6. `overlay_style` chooses safe text/shape overlay behavior such as minimal clean, UGC native, product highlights, promo badges or premium brand.
7. The Shotstack timeline builder assembles base video clips, optional voiceover audio, captions, CTA/end screen, decorative overlays and render metadata.
8. A local validation pass checks non-negative timings, positive clip lengths, caption overlap prevention and final-duration bounds before the payload is submitted to Shotstack.

The builder should keep source video ranges separate from final timeline ranges. It must not expose private storage paths or provider URLs in saved result payloads.

## Current v1 scope

Implemented now:

- Project actions endpoint returns contextual action metadata.
- Project detail page can show a Project Action Hub.
- Campaign/Product-to-video generations with usable scripts can become the source for a same-project AI Voiceover generation.
- Video-to-Campaign can analyze project video assets with mock output by default or Gemini for internal beta users, then save reusable narration text plus an edit plan.
- Auto Video Editor can reuse eligible Video-to-Campaign analysis or run manual edit setup with a source video and fallback timeline logic.
- Auto Video Ads can generate mock ad plans by default and Shotstack MP4 renders for eligible real-provider beta users.
- Generated voiceover audio remains attached to the same project as a private `MediaAsset` when real ElevenLabs output is used.
- Motion AI Video is now a real-beta-capable workflow with mock fallback. Mock mode creates structured image-to-video plans; Google Veo mode creates a visual-first private MP4 motion clip from product image, optional scenario/background image and campaign/script context. Provider URLs and private storage paths must not be exposed; final voiceover/music/caption compositing remains a later Motion phase.

Shown but not deeply refactored yet:

- Fashion Studio is a later module. Legacy try-on routes may remain for internal testing, but the active workspace flow should not present it as a core beta action.
- Avatar Presenter remains a coming-soon contextual action.
- Auto Video Editor defaults to mock edit plans, but Shotstack rendering can be enabled for eligible internal beta users. Completed renders are stored as private `MediaAsset` records with `usage_role=edited_video_result`.

Out of scope for v1:

- Full workflow graph engine.
- Drag/drop workflow builder.
- Video overlay or subtitles.
- Avatar generation.
- Payment/billing changes.
- Additional real provider integrations beyond the current Gemini/ElevenLabs beta chain.