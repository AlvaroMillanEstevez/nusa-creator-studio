#!/usr/bin/env node
import { readFile } from 'node:fs/promises';
import { runProbe, type ProbeOptions } from './lib.js';

const args = parseArgs(process.argv.slice(2));

try {
  const prompt = args['prompt-file']
    ? await readFile(required(args, 'prompt-file'), 'utf8')
    : required(args, 'prompt');
  const options: ProbeOptions = {
    imageFile: required(args, 'image-file'),
    mimeType: required(args, 'mime-type'),
    prompt,
    model: required(args, 'model'),
    durationSeconds: numberArg(args, 'duration-seconds', 8),
    aspectRatio: args['aspect-ratio'] ?? '9:16',
    outputFile: required(args, 'output-file'),
    dryRun: args['dry-run'] === 'true',
    pollIntervalMs: numberArg(args, 'poll-interval-ms', 10_000),
    maxPollAttempts: numberArg(args, 'max-poll-attempts', 90),
  };
  const result = await runProbe(options);

  process.stdout.write(`${JSON.stringify(result)}\n`);
  process.exitCode = result.ok ? 0 : 1;
} catch (error) {
  process.stdout.write(`${JSON.stringify({
    ok: false,
    provider: 'google_veo',
    transport: 'google_genai_sdk',
    sdkPackageVersion: 'unknown',
    errorCategory: 'cli_error',
    safeMessage: error instanceof Error ? error.message : 'CLI probe failed safely.',
  })}\n`);
  process.exitCode = 1;
}

function parseArgs(argv: string[]): Record<string, string> {
  const parsed: Record<string, string> = {};

  for (let index = 0; index < argv.length; index += 1) {
    const arg = argv[index];

    if (!arg.startsWith('--')) {
      continue;
    }

    const [key, inlineValue] = arg.slice(2).split('=', 2);

    if (inlineValue !== undefined) {
      parsed[key] = inlineValue;
    } else if (argv[index + 1] && !argv[index + 1].startsWith('--')) {
      parsed[key] = argv[index + 1];
      index += 1;
    } else {
      parsed[key] = 'true';
    }
  }

  return parsed;
}

function required(args: Record<string, string>, key: string): string {
  const value = args[key];

  if (!value) {
    throw new Error(`Missing required --${key} option.`);
  }

  return value;
}

function numberArg(args: Record<string, string>, key: string, fallback: number): number {
  const value = args[key];

  if (!value) {
    return fallback;
  }

  const number = Number(value);

  return Number.isFinite(number) ? number : fallback;
}