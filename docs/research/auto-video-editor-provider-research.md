# Auto Video Editor Provider Research

## Research goals

- Identify the simplest reliable provider for beta Auto Video Editor rendering.
- Keep the first real render flow focused on source video + edit plan + optional existing voiceover.
- Avoid advanced template systems, self-hosted rendering, or expensive generative video until the beta chain is stable.
- Preserve private media storage by using short-lived provider asset URLs instead of public buckets.

## Selected beta provider: Shotstack

Shotstack is selected for the first beta render because it accepts JSON timeline edits, can render MP4 output from remote video/audio URLs, and fits the current backend provider-abstraction pattern.

Current beta chain:

```text
raw product video
→ Gemini Video-to-Campaign analysis and edit plan
→ optional ElevenLabs voiceover
→ Shotstack Auto Video Editor render
→ private edited video MediaAsset
```

## Deferred providers/options

| Provider / option | Status | Reason |
| --- | --- | --- |
| Shotstack | Selected for beta | Good fit for JSON timeline rendering and server-side MP4 output. |
| Creatomate | Deferred | Better suited for polished product ad templates after the core workspace flow is proven. |
| Remotion | Deferred | Strong developer control, but needs self-hosted/render infrastructure decisions. |
| FFmpeg self-hosted | Deferred | Powerful and cost-controllable later, but requires queue/runtime scaling, sandboxing and media-processing hardening. |

## Local testing note

Shotstack must fetch input assets from a public HTTPS URL. It cannot fetch private `localhost` or `127.0.0.1` URLs.

For local development:

1. Start the Laravel API locally.
2. Expose it through ngrok or cloudflared.
3. Set `PROVIDER_ASSET_PUBLIC_BASE_URL` to the public HTTPS tunnel base URL.
4. Keep `PROVIDER_ASSET_URL_TTL_MINUTES` short.
5. Never expose raw storage paths or permanent public bucket URLs.

## Current beta limitations

- Uses simple clean cuts only.
- Uses 720x1280 vertical output for cost control.
- Reuses existing voiceover audio only; it does not auto-generate a new ElevenLabs voiceover inside the same render flow yet.
- Does not add advanced transitions, branded templates, licensed music, auto subtitles or motion graphics yet.
- Provider cost estimates are metadata-only unless a verified cost model is configured later.

## Open questions

- What default target duration should beta creators prefer: 15s, 30s or user-selected?
- Should Shotstack render cost become a separate credit line item after real usage data exists?
- When should Creatomate-style reusable ad templates be introduced?
- Should self-hosted FFmpeg become a lower-cost fallback after beta demand is clearer?
