# Google Veo SDK probe

Diagnostic-only CLI probe for Google Veo image-first-frame SDK requests.

This package is intentionally isolated from the Laravel production provider and frontend. It does not create Nusa generations, does not charge credits, and should only be invoked through the local Artisan wrapper.

## Local checks

```bash
npm ci
npm run type-check
npm test
npm run build
```

## Transport

The probe uses `@google/genai` and calls:

```ts
ai.models.generateVideos({
  model,
  prompt,
  image: {
    imageBytes,
    mimeType,
  },
  config: {
    aspectRatio,
    durationSeconds,
  },
});
```

stdout is reserved for one structured JSON result. Progress goes to stderr.