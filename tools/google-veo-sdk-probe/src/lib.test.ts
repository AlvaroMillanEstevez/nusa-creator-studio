import assert from 'node:assert/strict';
import { mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import test from 'node:test';
import { buildHardenedPrompt, buildSdkRequest, errorCategory, runProbe, safeMessage } from './lib.js';

test('buildSdkRequest uses official Google GenAI video image fields', () => {
  const request = buildSdkRequest({
    imageFile: '/tmp/input.jpg',
    mimeType: 'image/jpeg',
    prompt: 'Animate this product.',
    model: 'veo-3.1-generate-preview',
    durationSeconds: 8,
    aspectRatio: '9:16',
    outputFile: '/tmp/output.mp4',
  }, 'base64-image');

  assert.equal(request.model, 'veo-3.1-generate-preview');
  assert.equal(request.image?.imageBytes, 'base64-image');
  assert.equal(request.image?.mimeType, 'image/jpeg');
  assert.equal(request.config?.aspectRatio, '9:16');
  assert.equal(request.config?.durationSeconds, 8);
  assert.match(request.prompt ?? '', /clean full-frame product footage/i);
  assert.match(request.prompt ?? '', /Do not recreate TikTok/i);
});

test('dry run reads image bytes but never creates SDK client', async () => {
  const dir = await mkdtemp(join(tmpdir(), 'veo-sdk-probe-'));

  try {
    const imageFile = join(dir, 'input.jpg');
    await writeFile(imageFile, 'fake-image');

    const result = await runProbe({
      imageFile,
      mimeType: 'image/jpeg',
      prompt: 'Animate this product.',
      model: 'veo-3.1-generate-preview',
      durationSeconds: 8,
      aspectRatio: '9:16',
      outputFile: join(dir, 'output.mp4'),
      dryRun: true,
    }, {
      packageVersion: '2.12.0',
      createClient: () => {
        throw new Error('SDK client should not be created during dry-run.');
      },
    });

    assert.equal(result.ok, true);
    assert.equal(result.dryRun, true);
    assert.equal(result.imageBytesIncluded, true);
    assert.equal(result.outputFileCreated, false);
  } finally {
    await rm(dir, { recursive: true, force: true });
  }
});

test('successful SDK operation writes generated video bytes', async () => {
  const dir = await mkdtemp(join(tmpdir(), 'veo-sdk-probe-'));

  try {
    const imageFile = join(dir, 'input.jpg');
    const outputFile = join(dir, 'output.mp4');
    await writeFile(imageFile, 'fake-image');
    process.env.GOOGLE_VEO_API_KEY = 'test-api-key';

    const result = await runProbe({
      imageFile,
      mimeType: 'image/jpeg',
      prompt: 'Animate this product.',
      model: 'veo-3.1-generate-preview',
      durationSeconds: 8,
      aspectRatio: '9:16',
      outputFile,
      pollIntervalMs: 1,
      maxPollAttempts: 1,
    }, {
      packageVersion: '2.12.0',
      sleep: async () => undefined,
      createClient: () => ({
        models: {
          generateVideos: async () => ({ name: 'operations/test', done: false }),
        },
        operations: {
          getVideosOperation: async () => ({
            done: true,
            response: {
              generatedVideos: [{
                video: {
                  videoBytes: Buffer.from('mp4-bytes').toString('base64'),
                  mimeType: 'video/mp4',
                },
              }],
            },
          }),
        },
      }),
    });

    assert.equal(result.ok, true);
    assert.equal(result.outputFileCreated, true);
    assert.equal(await readFile(outputFile, 'utf8'), 'mp4-bytes');
  } finally {
    delete process.env.GOOGLE_VEO_API_KEY;
    await rm(dir, { recursive: true, force: true });
  }
});

test('SDK operation errors become safe structured failures', async () => {
  const dir = await mkdtemp(join(tmpdir(), 'veo-sdk-probe-'));

  try {
    const imageFile = join(dir, 'input.jpg');
    await writeFile(imageFile, 'fake-image');
    process.env.GOOGLE_VEO_API_KEY = 'test-api-key';

    const result = await runProbe({
      imageFile,
      mimeType: 'image/jpeg',
      prompt: 'Animate this product.',
      model: 'veo-3.1-generate-preview',
      durationSeconds: 8,
      aspectRatio: '9:16',
      outputFile: join(dir, 'output.mp4'),
    }, {
      packageVersion: '2.12.0',
      createClient: () => ({
        models: {
          generateVideos: async () => ({
            done: true,
            error: {
              code: 400,
              message: 'INVALID_ARGUMENT: inlineData failed for https://provider.example/private?key=AIzaSECRET',
            },
          }),
        },
        operations: {
          getVideosOperation: async () => ({ done: true }),
        },
      }),
    });

    assert.equal(result.ok, false);
    assert.equal(result.errorCategory, 'invalid_argument');
    assert.equal(result.httpStatus, 400);
    assert.doesNotMatch(result.safeMessage ?? '', /AIzaSECRET/);
    assert.doesNotMatch(result.safeMessage ?? '', /provider\.example/);
  } finally {
    delete process.env.GOOGLE_VEO_API_KEY;
    await rm(dir, { recursive: true, force: true });
  }
});

test('safe helpers redact credentials URLs and long byte-like values', () => {
  const message = safeMessage('Failure at https://provider.example/file?key=AIzaSECRET with AAAABBBBCCCCDDDDEEEEFFFFGGGGHHHHIIIIJJJJKKKKLLLLMMMMNNNNOOOOPPPPQQQQRRRRSSSSTTTT');

  assert.doesNotMatch(message, /provider\.example/);
  assert.doesNotMatch(message, /AIzaSECRET/);
  assert.match(message, /\[redacted-url\]/);
  assert.match(message, /\[redacted-long-token\]/);
  assert.equal(errorCategory('INVALID_ARGUMENT bad request'), 'invalid_argument');
});

test('hardened prompt forbids social UI and burned-in overlays', () => {
  const prompt = buildHardenedPrompt('Animate my product image.');

  assert.match(prompt, /Do not recreate TikTok/);
  assert.match(prompt, /Do not add usernames/);
  assert.match(prompt, /Do not render burned-in text/);
});