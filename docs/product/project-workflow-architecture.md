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
- `audio_track`
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
| `usage_role` | Identify what each project asset can be used for | Media library, Fashion Try-On, Motion AI Video |

## Action chaining example

```text
Product brief
→ Affiliate Campaign
→ AI Voiceover
→ Video Ad / Motion Video / Avatar later
```

The current v1 implementation makes the Campaign → Voiceover step work in the same project. The user should not need to manually copy/paste campaign copy into a separate isolated voiceover tool.

## Honest separation of generated content and UI actions

Generated campaign content should stay useful to the creator. It should not advertise Nusa services or include next-service instructions such as “copy this into voiceover next.”

Workflow guidance belongs in the application UI, for example:

- Continue with this project.
- Continue with selected result.
- Create voiceover from this script.
- Use this script for a future video ad or avatar workflow.

This keeps provider output portable and prevents generated content from becoming product upsell copy.

## Basic, Advanced and Premium relationship

Basic workflows often create context for later Advanced or Premium workflows.

| Tier | Workspace role |
| --- | --- |
| Basic | Create low-cost structured context: hooks, scripts, captions, insights and voiceover-ready text. |
| Advanced | Reuse text/media context for assisted workflows such as voiceover, captions, video analysis and product-to-video planning. |
| Premium | Reuse project context and media assets for expensive generative media such as try-on, motion video and avatar workflows. |

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
- `configure`: “Setup Try-On”, “Configure Motion Video”, “Create voiceover”.
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
- Generated voiceover audio remains attached to the same project as a private `MediaAsset` when real ElevenLabs output is used.

Shown but not deeply refactored yet:

- Fashion Try-On is a configurable Premium action. Its card stays positive while launcher requirements ask for model and garment reference images.
- Motion AI Video is a configurable Premium action. Its card stays positive while launcher requirements ask for visual references such as product image, source video, background or brand assets.
- Video ad assembly and Avatar Presenter remain coming-soon contextual actions.

Out of scope for v1:

- Full workflow graph engine.
- Drag/drop workflow builder.
- Video overlay or subtitles.
- Avatar generation.
- Payment/billing changes.
- New real provider integrations.