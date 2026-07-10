# Project Workflow Architecture

Nusa Creator Studio should feel like a creator workspace, not a catalog of disconnected tools.

## Core model

### Project as workspace/context

A `Project` is the durable workspace for a product, campaign, asset set or creative idea. It stores the creator's brief, settings, attached assets and generated history.

A project can begin from a specific tool route, such as Affiliate Campaign Generator or AI Voiceover, but the long-term product direction is that the project becomes the context hub for future actions.

### Tool as action/workflow

A `Tool` is a workflow that can run against a project. Tools may start a new project or continue an existing project with prior generations and media assets.

Examples:

- Affiliate Campaign Generator creates campaign context.
- AI Voiceover can use project source text or a completed campaign generation.
- Fashion Try-On can use model and garment media assets.
- Motion AI Video can use product images, source videos or campaign context.

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
→ Affiliate Campaign
→ AI Voiceover
→ Video Ad / Motion Video / Avatar later
```

The current v1 implementation makes the Campaign → Voiceover step work in the same project. The user should not need to manually copy/paste campaign copy into a separate isolated voiceover tool.

## Video-to-Campaign and Auto Video Editor chain

The next workspace chain is video-first:

```text
Project workspace
→ upload raw product video
→ Video-to-Campaign analysis/campaign
→ optional AI Voiceover
→ optional Auto Video Editor
```

The real beta chain is:

```text
raw video
→ Gemini video analysis + campaign/edit-plan JSON
→ ElevenLabs voiceover from Gemini narration text
→ Shotstack Auto Video Editor render for internal beta users
```

Video-to-Campaign is an Advanced workflow. It analyzes a selected project video asset and saves structured campaign context, including `video_summary`, observed product features, detected product moments, recommended cuts, hooks, captions, an edit plan and `narration_voice_script`.

In default mode it uses the deterministic mock provider. In internal beta mode it can use Gemini by reading the private media asset server-side, uploading it to the provider file-input flow, and storing only safe metadata in the generation result. Private storage paths and raw provider file URIs should not be exposed to the frontend.

Video-to-Campaign produces narration text, not audio. The narration script is reusable text for the separate AI Voiceover action, so voiceover remains an intentional additional step unless another workflow explicitly includes it.

Auto Video Editor is a Premium composite workflow. It creates an edit-ready vertical video plan by default and can render a real beta MP4 with Shotstack for eligible internal users when enabled by configuration. It can start from either:

- an existing `generate_video_to_campaign` generation, or
- a raw/source video asset that needs video analysis inside the composite flow.

It can also reuse an existing AI Voiceover generation or audio asset, or proceed with no voiceover. In mock mode it may still plan a new voiceover component. In real Shotstack mode, automatic new ElevenLabs generation is intentionally deferred: creators should generate a voiceover first, then select that existing audio in Auto Video Editor.

Shotstack renders require temporary provider-accessible URLs for private source video and optional voiceover audio. These URLs use opaque expiring tokens and do not expose raw storage paths. In local development, a public HTTPS tunnel such as ngrok or cloudflared is required because Shotstack cannot fetch private `localhost` URLs.

### Composite credit reuse rules

Auto Video Editor should make prior work visible in its credit breakdown:

| Existing project context | Auto editor behavior |
| --- | --- |
| Completed Video-to-Campaign result selected | Video analysis is reused, so no additional video-analysis credit is added. |
| Raw video selected without saved analysis | The composite cost includes the Video-to-Campaign analysis component. |
| Existing voiceover generation or audio asset selected | Voiceover is reused, so no additional voiceover credit is added. |
| Include voiceover with no existing voiceover selected | The composite cost includes the AI Voiceover component. |
| No voiceover selected | Voiceover cost stays zero. |

The charged effective credits should match the composite line items for normal users. Internal/unlimited users follow the existing zero-charge behavior while still seeing the normal estimated cost breakdown.


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

Basic workflows often create context for later Advanced or Premium workflows.

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
- `configure`: “Generate campaign from video”, “Open Auto Video Editor”, “Setup Try-On”, “Configure Motion Video”, “Create voiceover”.
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

## Current v1 scope

Implemented now:

- Project actions endpoint returns contextual action metadata.
- Project detail page can show a Project Action Hub.
- Campaign/Product-to-video generations with usable scripts can become the source for a same-project AI Voiceover generation.
- Video-to-Campaign can analyze project video assets with mock output by default or Gemini for internal beta users, then save reusable narration text plus an edit plan.
- Auto Video Editor can reuse Video-to-Campaign analysis and existing voiceover/audio to avoid double-charging those components.
- Generated voiceover audio remains attached to the same project as a private `MediaAsset` when real ElevenLabs output is used.

Shown but not deeply refactored yet:

- Fashion Try-On is visible as a future/coming-soon Premium workflow. Legacy mock routes may remain for internal testing, but the active workspace flow should not present it as a core beta action.
- Motion AI Video is visible as a future/coming-soon Premium workflow. Legacy mock routes may remain for internal testing, but the active workspace flow should not present it as a core beta action.
- Video ad assembly and Avatar Presenter remain coming-soon contextual actions.
- Auto Video Editor defaults to mock edit plans, but Shotstack rendering can be enabled for eligible internal beta users. Completed renders are stored as private `MediaAsset` records with `usage_role=edited_video_result`.

Out of scope for v1:

- Full workflow graph engine.
- Drag/drop workflow builder.
- Video overlay or subtitles.
- Avatar generation.
- Payment/billing changes.
- Additional real provider integrations beyond the current Gemini/ElevenLabs beta chain.