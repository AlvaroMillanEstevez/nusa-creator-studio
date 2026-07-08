# Voiceover Provider Research

Nusa Creator Studio is using AI Voiceover as the first real provider-backed workflow because it is simpler, cheaper to test, and more reusable than generated images, try-on, motion video or avatar video.

## Why ElevenLabs is selected first

ElevenLabs is the first quality provider candidate for v1 script-to-audio because:

- It has a focused Text to Speech API suitable for short creator scripts.
- It returns audio directly, which fits the existing async generation + MediaAsset storage architecture.
- It supports a default voice configuration, so Nusa can ship a safe v1 before building a full voice selector.
- It can become a reusable asset in the chain: campaign/script → voiceover → subtitles/video/avatar later.

Current implementation note:

- Real provider use is optional and disabled by default.
- Local/internal testing can enable ElevenLabs through backend-only environment variables.
- API keys must never be exposed to the frontend, logs, docs, or API responses.

## Alternative providers to benchmark later

| Provider | Category | Benchmark questions | Status |
| --- | --- | --- | --- |
| AWS Polly | Cloud TTS | Cost, Indonesian/Spanish quality, commercial rights, latency. | TODO verify. |
| Google Cloud Text-to-Speech | Cloud TTS | Voice quality, SSML support, local language quality, pricing. | TODO verify. |
| Azure Speech | Cloud TTS | Voice catalog, neural voices, regional support, pricing. | TODO verify. |
| OpenAI TTS | AI TTS | Voice quality, latency, API fit, cost, rights. | TODO verify. |
| ElevenLabs | AI TTS | Quality, voice selection, Indonesian/Spanish output, API reliability. | First real integration candidate. |

## Cost model notes

Current v1 stores an estimated provider cost as internal metadata only.

| Model category | Estimated unit | Placeholder estimate | Notes |
| --- | --- | --- | --- |
| Flash/Turbo-style model | Characters | Configurable per 1,000 characters | TODO verify against current provider pricing before launch. |
| Higher-quality multilingual model | Characters | Configurable per 1,000 characters | TODO verify against current provider pricing before launch. |

Do not use this estimate for billing yet. It is for internal cost visibility while the credit model is tuned.

## Language support notes

Nusa should test real voiceover quality for:

- English.
- Indonesian.
- Spanish, including accents, ñ, ¿ and ¡.

Quality checks should include pronunciation, pacing, product-name handling, and whether the model preserves the intended tone without creating exaggerated claims.

## Quality testing checklist

- Audio is understandable on mobile speakers.
- Product names are pronounced acceptably.
- Script meaning is preserved.
- Spanish and Indonesian text remains valid UTF-8 end-to-end.
- No unrealistic product claims are introduced.
- Output length is appropriate for 15, 30, 45 and 60 second briefs.
- Audio is saved as a private MediaAsset.
- No storage path or provider secret is exposed to the frontend.
- Failed jobs refund credits safely.

## Safety and rights questions

- What voices are safe for commercial creator content?
- Should cloned/personal voices be disabled until consent flows exist?
- Should preview URLs be exposed, proxied or hidden?
- How should affiliate disclosure be included when generated audio is used in ads?
- What labels are needed for AI-generated voice content in target markets?

## Future workflow phases

| Phase | Scope | Notes |
| --- | --- | --- |
| v1 script to audio | Generate voiceover from source text/script and save as MediaAsset. | Current real-provider direction. |
| v2 audio into uploaded video | Let users combine saved voiceover with uploaded clips. | Requires video overlay/editing scope. |
| v3 audio + subtitles | Generate captions/subtitles aligned to voiceover. | Requires subtitle/timing pipeline. |
| v4 avatar voice ads | Combine avatar, voiceover, campaign script and product assets. | Requires identity-safety and premium cost controls. |
