import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { GoogleGenAI, type GenerateVideosParameters } from '@google/genai';

export type ProbeOptions = {
  imageFile: string;
  mimeType: string;
  prompt: string;
  model: string;
  durationSeconds: number;
  aspectRatio: string;
  outputFile: string;
  dryRun?: boolean;
  pollIntervalMs?: number;
  maxPollAttempts?: number;
};

type VideoPayload = {
  uri?: string;
  videoBytes?: string;
  mimeType?: string;
};

type GenerateVideosOperationLike = {
  name?: string;
  done?: boolean;
  error?: Record<string, unknown>;
  response?: {
    generatedVideos?: Array<{
      video?: VideoPayload;
    }>;
  };
};

type GoogleGenAiClientLike = {
  models: {
    generateVideos(params: GenerateVideosParameters): Promise<GenerateVideosOperationLike>;
  };
  operations: {
    getVideosOperation(params: { operation: GenerateVideosOperationLike }): Promise<GenerateVideosOperationLike>;
  };
};

type ProbeDeps = {
  createClient?: (apiKey: string) => GoogleGenAiClientLike;
  fetchImpl?: typeof fetch;
  sleep?: (ms: number) => Promise<void>;
  packageVersion?: string;
};

export type ProbeResult = {
  ok: boolean;
  provider: 'google_veo';
  transport: 'google_genai_sdk';
  sdkPackageVersion: string;
  outputFileCreated?: boolean;
  dryRun?: boolean;
  model?: string;
  imageBytesIncluded?: boolean;
  mimeType?: string;
  promptCharacterCount?: number;
  promptHash?: string;
  durationSeconds?: number;
  aspectRatio?: string;
  errorCategory?: string;
  httpStatus?: number;
  safeMessage?: string;
};

export function buildHardenedPrompt(prompt: string): string {
  const trimmedPrompt = prompt.trim();
  const visualIntegrity = [
    'Generate clean full-frame product footage only.',
    'Do not recreate TikTok, Instagram, YouTube, marketplace, or other social-media interfaces.',
    'Do not add usernames, account handles, shop names, store names, watermarks, app controls, platform chrome, prices, discounts, URLs, captions, CTA text, or promotional labels.',
    'Do not invent brand, seller, account, price, offer, performance, health, revenue, or product claims.',
    'Do not render burned-in text unless exact visual text was explicitly requested; this probe defers all overlays to post-production.',
    'Leave clean safe space for captions and CTA overlays to be added later.',
  ].join(' ');

  return `User visual prompt: ${trimmedPrompt}\n\nVisual integrity requirements: ${visualIntegrity}`;
}

export function buildSdkRequest(options: ProbeOptions, imageBytes: string): GenerateVideosParameters {
  return {
    model: options.model,
    prompt: buildHardenedPrompt(options.prompt),
    image: {
      imageBytes,
      mimeType: options.mimeType,
    },
    config: {
      aspectRatio: options.aspectRatio,
      durationSeconds: options.durationSeconds,
    },
  };
}

export async function runProbe(options: ProbeOptions, deps: ProbeDeps = {}): Promise<ProbeResult> {
  const packageVersion = deps.packageVersion ?? await sdkPackageVersion();
  const imageBuffer = await readFile(options.imageFile);
  const imageBytes = imageBuffer.toString('base64');
  const prompt = buildHardenedPrompt(options.prompt);
  const promptHash = createHash('sha256').update(prompt).digest('hex');

  if (options.dryRun) {
    return {
      ok: true,
      provider: 'google_veo',
      transport: 'google_genai_sdk',
      sdkPackageVersion: packageVersion,
      dryRun: true,
      model: options.model,
      imageBytesIncluded: imageBytes.length > 0,
      mimeType: options.mimeType,
      promptCharacterCount: prompt.length,
      promptHash,
      durationSeconds: options.durationSeconds,
      aspectRatio: options.aspectRatio,
      outputFileCreated: false,
    };
  }

  const apiKey = process.env.GOOGLE_VEO_API_KEY;

  if (!apiKey) {
    return failure(packageVersion, 'missing_api_key', undefined, 'GOOGLE_VEO_API_KEY is not configured.');
  }

  const client = deps.createClient?.(apiKey) ?? new GoogleGenAI({ apiKey }) as GoogleGenAiClientLike;
  const request = buildSdkRequest(options, imageBytes);

  try {
    let operation = await client.models.generateVideos(request);
    const pollIntervalMs = options.pollIntervalMs ?? 10_000;
    const maxPollAttempts = options.maxPollAttempts ?? 90;

    for (let attempt = 0; !operation.done && attempt < maxPollAttempts; attempt += 1) {
      process.stderr.write(`google_veo_sdk_probe: polling operation ${attempt + 1}/${maxPollAttempts}\n`);
      await (deps.sleep ?? sleep)(pollIntervalMs);
      operation = await client.operations.getVideosOperation({ operation });
    }

    if (!operation.done) {
      return failure(packageVersion, 'timeout', undefined, 'Timed out waiting for Google Veo operation to complete.');
    }

    if (operation.error) {
      const status = httpStatusFromUnknown(operation.error);

      return failure(packageVersion, errorCategory(operation.error), status, safeMessage(operation.error));
    }

    const video = operation.response?.generatedVideos?.[0]?.video;

    if (!video) {
      return failure(packageVersion, 'invalid_response', undefined, 'Google Veo operation completed without a generated video.');
    }

    await writeVideoOutput(video, options.outputFile, apiKey, deps.fetchImpl ?? fetch);

    return {
      ok: true,
      provider: 'google_veo',
      transport: 'google_genai_sdk',
      sdkPackageVersion: packageVersion,
      outputFileCreated: true,
    };
  } catch (error) {
    return failure(packageVersion, errorCategory(error), httpStatusFromUnknown(error), safeMessage(error));
  }
}

async function writeVideoOutput(video: VideoPayload, outputFile: string, apiKey: string, fetchImpl: typeof fetch): Promise<void> {
  await mkdir(dirname(outputFile), { recursive: true });

  if (typeof video.videoBytes === 'string' && video.videoBytes !== '') {
    await writeFile(outputFile, Buffer.from(video.videoBytes, 'base64'));

    return;
  }

  if (typeof video.uri !== 'string' || video.uri.trim() === '') {
    throw new Error('Generated video did not include video bytes or a download URI.');
  }

  const response = await fetchImpl(video.uri, {
    headers: {
      'x-goog-api-key': apiKey,
    },
  });

  if (!response.ok) {
    throw new Error(`Video download failed with HTTP ${response.status}.`);
  }

  const body = Buffer.from(await response.arrayBuffer());
  await writeFile(outputFile, body);
}

function failure(packageVersion: string, errorCategoryValue: string, httpStatus: number | undefined, message: string): ProbeResult {
  return {
    ok: false,
    provider: 'google_veo',
    transport: 'google_genai_sdk',
    sdkPackageVersion: packageVersion,
    errorCategory: errorCategoryValue,
    httpStatus,
    safeMessage: message,
  };
}

export function errorCategory(error: unknown): string {
  const message = safeMessage(error).toLowerCase();

  if (message.includes('invalid_argument') || message.includes('invalid argument')) {
    return 'invalid_argument';
  }

  if (message.includes('unauthorized') || message.includes('permission') || message.includes('forbidden')) {
    return 'auth_or_permission';
  }

  if (message.includes('timeout') || message.includes('timed out')) {
    return 'timeout';
  }

  if (message.includes('network') || message.includes('fetch') || message.includes('connection')) {
    return 'network';
  }

  return 'provider_error';
}

export function safeMessage(error: unknown): string {
  let message = 'Google Veo SDK request failed safely.';

  if (error instanceof Error) {
    message = error.message;
  } else if (typeof error === 'string') {
    message = error;
  } else if (typeof error === 'object' && error !== null) {
    const record = error as Record<string, unknown>;
    const nestedMessage = record.message ?? record.error ?? record.status ?? record.code;

    if (typeof nestedMessage === 'string') {
      message = nestedMessage;
    } else {
      message = JSON.stringify(redactObject(record));
    }
  }

  return redactText(message).slice(0, 500);
}

function redactObject(value: Record<string, unknown>): Record<string, unknown> {
  return Object.fromEntries(Object.entries(value).map(([key, entry]) => {
    if (/key|token|secret|bytes|data|uri|url|path|prompt/i.test(key)) {
      return [key, '[redacted]'];
    }

    if (typeof entry === 'string') {
      return [key, redactText(entry)];
    }

    return [key, entry];
  }));
}

function redactText(value: string): string {
  return value
    .replace(/https?:\/\/\S+/gi, '[redacted-url]')
    .replace(/[A-Za-z0-9+/]{80,}={0,2}/g, '[redacted-long-token]')
    .replace(/AIza[\w-]+/g, '[redacted-api-key]');
}

function httpStatusFromUnknown(error: unknown): number | undefined {
  if (typeof error !== 'object' || error === null) {
    return undefined;
  }

  const record = error as Record<string, unknown>;
  const candidates = [record.status, record.statusCode, record.code];

  for (const candidate of candidates) {
    if (typeof candidate === 'number') {
      return candidate;
    }

    if (typeof candidate === 'string' && /^\d+$/.test(candidate)) {
      return Number(candidate);
    }
  }

  return undefined;
}

async function sdkPackageVersion(): Promise<string> {
  const packagePath = fileURLToPath(new URL('../package.json', import.meta.url));
  const packageJson = JSON.parse(await readFile(packagePath, 'utf8')) as { dependencies?: Record<string, string> };

  return packageJson.dependencies?.['@google/genai'] ?? 'unknown';
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}