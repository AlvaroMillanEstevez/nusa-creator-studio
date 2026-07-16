<?php

namespace App\Services\Generation;

use RuntimeException;
use Symfony\Component\Process\Process;

class GoogleVeoSdkProbeRunner
{
    /**
     * @return array<string, mixed>
     */
    public function run(
        string $imageFile,
        string $promptFile,
        string $outputFile,
        string $mimeType,
        string $model,
        int $durationSeconds,
        string $aspectRatio,
        int $pollIntervalMs = 10_000,
        int $maxPollAttempts = 90,
    ): array {
        if (! is_file($this->entrypoint())) {
            throw new RuntimeException('Google Veo SDK probe is not built. Run npm ci and npm run build in tools/google-veo-sdk-probe first.');
        }

        $command = [
            'node',
            $this->entrypoint(),
            '--image-file',
            $imageFile,
            '--mime-type',
            $mimeType,
            '--prompt-file',
            $promptFile,
            '--model',
            $model,
            '--duration-seconds',
            (string) $durationSeconds,
            '--aspect-ratio',
            $aspectRatio,
            '--output-file',
            $outputFile,
            '--poll-interval-ms',
            (string) $pollIntervalMs,
            '--max-poll-attempts',
            (string) $maxPollAttempts,
        ];
        $apiKey = (string) config('motion_ai_video.google_veo.api_key');
        $result = $this->executeProcess($command, $this->packageDirectory(), [
            'GOOGLE_VEO_API_KEY' => $apiKey,
        ], 900);
        $decoded = json_decode(trim($result['stdout']), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Google Veo SDK probe returned malformed JSON.');
        }

        $decoded['process_exit_code'] = $result['exit_code'];

        return $decoded;
    }

    public function sdkPackageVersion(): string
    {
        $lockPath = $this->packageDirectory().DIRECTORY_SEPARATOR.'package-lock.json';

        if (is_file($lockPath)) {
            $lock = json_decode((string) file_get_contents($lockPath), true);
            $version = data_get($lock, 'packages.node_modules/@google/genai.version');

            if (is_string($version) && $version !== '') {
                return $version;
            }
        }

        return 'unknown';
    }

    public function packageDirectory(): string
    {
        return base_path('../../tools/google-veo-sdk-probe');
    }

    protected function entrypoint(): string
    {
        return $this->packageDirectory().DIRECTORY_SEPARATOR.'dist'.DIRECTORY_SEPARATOR.'index.js';
    }

    /**
     * @param  array<int, string>  $command
     * @param  array<string, string>  $env
     * @return array{exit_code: int, stdout: string, stderr: string, command: array<int, string>, env: array<string, string>, timeout: int}
     */
    protected function executeProcess(array $command, string $cwd, array $env, int $timeout): array
    {
        $process = new Process($command, $cwd, $env);
        $process->setTimeout($timeout);
        $process->run();

        return [
            'exit_code' => $process->getExitCode() ?? 1,
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
            'command' => $command,
            'env' => $env,
            'timeout' => $timeout,
        ];
    }
}
