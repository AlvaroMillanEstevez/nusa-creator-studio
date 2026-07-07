# AI Provider Research

This document tracks candidate AI providers for future real integrations. Keep prices, rate limits, availability, and API claims as TODO until verified from official documentation or vendor contact.

## Research goals

- Identify providers that can replace mock tools one by one.
- Compare cost, latency, output quality, API quality, reliability, and commercial terms.
- Keep provider abstraction simple and avoid early lock-in.
- Choose integrations that are useful, affordable, and safe for a credit-based SaaS.

## Provider categories

| Category | Nusa workflow | Evaluation criteria |
| --- | --- | --- |
| Text/script/caption | Affiliate campaigns, scripts, captions | JSON reliability, multilingual quality, safety controls, cost. |
| Voiceover/TTS | AI Voiceover, dubbing | Voice quality, language support, commercial rights, cost per unit. |
| Fashion try-on | Fashion Try-On | Input constraints, realism, latency, failure rate, cost. |
| Motion video | Motion AI Video | Prompt control, duration, resolution, queue time, cost. |
| Image generation/editing | Product visuals and variants | Editing APIs, prompt adherence, moderation, rights. |

## Text/script/caption providers

| Provider | Candidate use | Strengths to verify | Risks to verify | Source/date verified |
| --- | --- | --- | --- | --- |
| OpenAI | Scripts, captions, campaign JSON, localization | Structured output, multilingual quality, safety tooling | Cost, rate limits, data policy, model choice | TODO |
| Replicate | Hosted open-source models | Model variety, simple API | Reliability, model maintenance, pricing variance | TODO |
| fal.ai | Possible multimodal workflows | Fast inference ecosystem | Exact text-model fit unclear | TODO |

## Voiceover/TTS providers

| Provider | Candidate use | Languages to verify | Cost unit | API notes | Source/date verified |
| --- | --- | --- | --- | --- | --- |
| ElevenLabs | First real AI Voiceover candidate | English, Indonesian, Spanish, others TODO | TODO | Verify voices, commercial terms, async/streaming options. | TODO |
| Google Cloud Text-to-Speech | Reliable TTS fallback | TODO | TODO | Verify SSML, voices, quotas, regional support. | TODO |
| Azure Speech | Enterprise-grade TTS option | TODO | TODO | Verify neural voices, custom voices, compliance. | TODO |
| AWS Polly | Cost-effective TTS fallback | TODO | TODO | Verify creator-quality voices and Indonesian support. | TODO |

## Fashion try-on providers

| Provider | Candidate use | Required inputs | Cost unit | API readiness notes | Source/date verified |
| --- | --- | --- | --- | --- | --- |
| FASHN | Fashion Try-On candidate | TODO | TODO | Verify docs, async flow, image constraints, terms. | TODO |
| fal.ai | Hosted try-on models if available | TODO | TODO | Verify model quality, ownership, uptime, policy. | TODO |
| Replicate | Hosted try-on models if suitable | TODO | TODO | Verify model freshness, queue time, reliability. | TODO |

## Motion video providers

| Provider | Candidate use | Duration/resolution to verify | Cost unit | API readiness notes | Source/date verified |
| --- | --- | --- | --- | --- | --- |
| Runway API | Motion AI Video candidate | TODO | TODO | Verify API access, queue behavior, webhooks, terms. | TODO |
| Kling/Seedance providers | Future motion generation | TODO | TODO | Verify official/API partner access and licensing. | TODO |
| Luma/Pika if API access is viable | Future video generation | TODO | TODO | Verify stable public API access first. | TODO |
| fal.ai | Hosted video models | TODO | TODO | Verify model selection and production constraints. | TODO |
| Replicate | Hosted video models | TODO | TODO | Verify queue times, stability, and pricing. | TODO |

## Image generation/editing providers

| Provider | Candidate use | Cost unit | API notes | Source/date verified |
| --- | --- | --- | --- | --- |
| OpenAI | Product image variants, backgrounds, edits | TODO | Verify image generation/editing and moderation. | TODO |
| fal.ai | Fast image generation/editing | TODO | Verify model choice, rights, and uptime. | TODO |
| Replicate | Model marketplace for image tasks | TODO | Verify production model stability. | TODO |

## Cost comparison table

| Provider | Category | Unit to price | Official price | Expected Nusa credit mapping | Notes | Source/date verified |
| --- | --- | --- | --- | --- | --- | --- |
| OpenAI | Text/image | TODO | TODO | TODO | Do not fill without official docs. | TODO |
| ElevenLabs | Voiceover/TTS | TODO | TODO | TODO | Do not fill without official docs. | TODO |
| Google Cloud Text-to-Speech | Voiceover/TTS | TODO | TODO | TODO | Do not fill without official docs. | TODO |
| Azure Speech | Voiceover/TTS | TODO | TODO | TODO | Do not fill without official docs. | TODO |
| AWS Polly | Voiceover/TTS | TODO | TODO | TODO | Do not fill without official docs. | TODO |
| FASHN | Fashion try-on | TODO | TODO | TODO | Do not fill without official docs. | TODO |
| fal.ai | Multimodal | TODO | TODO | TODO | Do not fill without official docs. | TODO |
| Runway API | Motion video | TODO | TODO | TODO | Do not fill without official docs. | TODO |
| Replicate | Multimodal | TODO | TODO | TODO | Do not fill without official docs. | TODO |
| Kling/Seedance providers | Motion video | TODO | TODO | TODO | Verify official access path. | TODO |
| Luma/Pika if API access is viable | Motion video | TODO | TODO | TODO | Verify API availability first. | TODO |

## API readiness table

| Provider | Auth method | Sync/async | Webhooks | SDKs | Sandbox | Rate limits | Data retention | Production readiness | Source/date verified |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| OpenAI | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| ElevenLabs | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| Google Cloud Text-to-Speech | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| Azure Speech | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| AWS Polly | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| FASHN | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| fal.ai | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| Runway API | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |
| Replicate | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO | TODO |

## Recommended integration order

1. AI Voiceover real provider integration.
   - Reason: cheaper and simpler to validate than try-on or motion video, pending provider verification.
   - Candidates: ElevenLabs, Google Cloud Text-to-Speech, Azure Speech, AWS Polly.
2. Text/script/caption provider integration.
   - Reason: upgrades campaign and caption tools while keeping structured output.
   - Candidate: OpenAI.
3. Image/fashion try-on integration.
   - Reason: high value, but needs stricter input validation and cost controls.
   - Candidates: FASHN, fal.ai, Replicate.
4. Motion video integration.
   - Reason: high perceived value, but likely higher cost, latency, and failure risk.
   - Candidates: Runway API, fal.ai, Replicate, official Kling/Seedance access if available.

## Open questions

- Which providers support Indonesian and Spanish at creator-quality levels?
- Which providers allow commercial use for generated assets?
- Which APIs provide reliable async job status and webhook callbacks?
- How should failed provider jobs refund or preserve user credits?
- What per-tool credit prices protect margin under provider cost spikes?
