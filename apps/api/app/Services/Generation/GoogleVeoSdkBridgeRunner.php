<?php

namespace App\Services\Generation;

use App\Exceptions\MotionAiVideo\MotionAiVideoProviderException;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class GoogleVeoSdkBridgeRunner
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{result: array<string, mixed>, video_binary: string, output_size: int}
     */
    public function renderImageFirstFrame(
        string $imageBinary,
        string $prompt,
        string $mimeType,
        string $model,
        int $durationSeconds,
        string $aspectRatio,
        array $context = [],
        ?int $pollIntervalMs = null,
        ?int $maxPollAttempts = null,
    ): array {
        if ($imageBinary === '') {
            throw MotionAiVideoProviderException::invalidResponse('google_veo_sdk_bridge_invalid_response', array_merge($context, [
                'stage' => 'sdk_bridge_prepare',
                'provider_transport' => 'google_genai_sdk',
                'response_format' => 'empty_source_image',
            ]));
        }

        $tempDirectory = $this->makeTempDirectory();
        $imageFile = $tempDirectory.DIRECTORY_SEPARATOR.'input'.$this->extensionForMimeType($mimeType);
        $promptFile = $tempDirectory.DIRECTORY_SEPARATOR.'prompt.txt';
        $outputFile = $tempDirectory.DIRECTORY_SEPARATOR.'output.mp4';

        try {
            file_put_contents($imageFile, $imageBinary);
            file_put_contents($promptFile, $prompt);

            $result = $this->run(
                imageFile: $imageFile,
                promptFile: $promptFile,
                outputFile: $outputFile,
                mimeType: $mimeType,
                model: $model,
                durationSeconds: $durationSeconds,
                aspectRatio: $aspectRatio,
                pollIntervalMs: $pollIntervalMs ?? $this->sdkPollIntervalMs(),
                maxPollAttempts: $maxPollAttempts ?? $this->sdkMaxPollAttempts(),
            );

            if (($result['ok'] ?? false) !== true) {
                throw MotionAiVideoProviderException::requestFailed('google_veo_sdk_bridge_failed', array_merge($context, [
                    'stage' => 'sdk_bridge_generate',
                    'provider_transport' => 'google_genai_sdk',
                    'safe_error_category' => is_string($result['errorCategory'] ?? null) ? $result['errorCategory'] : 'provider_error',
                    'http_status' => is_numeric($result['httpStatus'] ?? null) ? (int) $result['httpStatus'] : null,
                    'safe_provider_message' => is_string($result['safeMessage'] ?? null)
                        ? Str::limit($this->safeMessage($result['safeMessage']), 500, '')
                        : null,
                ]));
            }

            if (! is_file($outputFile) || filesize($outputFile) <= 0) {
                throw MotionAiVideoProviderException::invalidResponse('google_veo_sdk_bridge_invalid_response', array_merge($context, [
                    'stage' => 'sdk_bridge_download',
                    'provider_transport' => 'google_genai_sdk',
                    'response_format' => 'missing_output_file',
                ]));
            }

            $videoBinary = file_get_contents($outputFile);

            if (! is_string($videoBinary) || $videoBinary === '') {
                throw MotionAiVideoProviderException::invalidResponse('google_veo_sdk_bridge_invalid_response', array_merge($context, [
                    'stage' => 'sdk_bridge_download',
                    'provider_transport' => 'google_genai_sdk',
                    'response_format' => 'empty_output_file',
                ]));
            }

            return [
                'result' => $result,
                'video_binary' => $videoBinary,
                'output_size' => strlen($videoBinary),
            ];
        } catch (MotionAiVideoProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw MotionAiVideoProviderException::requestFailed(
                'google_veo_sdk_bridge_failed',
                array_merge($context, [
                    'stage' => 'sdk_bridge_generate',
                    'provider_transport' => 'google_genai_sdk',
                    'safe_exception_message' => $this->safeMessage($exception->getMessage()),
                ]),
                $exception,
            );
        } finally {
            foreach ([$imageFile, $promptFile, $outputFile] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            if (is_dir($tempDirectory)) {
                @rmdir($tempDirectory);
            }
        }
    }

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
            throw new RuntimeException('Google Veo SDK bridge is not built. Run npm ci and npm run build in tools/google-veo-sdk-probe first.');
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
        ], $this->processTimeoutSeconds());
        $decoded = json_decode(trim($result['stdout']), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Google Veo SDK bridge returned malformed JSON.');
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

    private function processTimeoutSeconds(): int
    {
        return max(60, (int) config('motion_ai_video.google_veo.sdk_process_timeout_seconds', 900));
    }

    private function sdkPollIntervalMs(): int
    {
        return max(1, (int) config('motion_ai_video.google_veo.sdk_poll_interval_seconds', 10)) * 1000;
    }

    private function sdkMaxPollAttempts(): int
    {
        return max(1, (int) config('motion_ai_video.google_veo.max_poll_attempts', 120));
    }

    private function makeTempDirectory(): string
    {
        $base = storage_path('app/nusa-debug/tmp');

        if (! is_dir($base)) {
            mkdir($base, 0700, true);
        }

        $directory = $base.DIRECTORY_SEPARATOR.'veo-sdk-'.Str::uuid();
        mkdir($directory, 0700, true);

        return $directory;
    }

    private function extensionForMimeType(string $mimeType): string
    {
        return match ($mimeType) {
            'image/png' => '.png',
            'image/webp' => '.webp',
            default => '.jpg',
        };
    }

    private function safeMessage(string $message): string
    {
        return preg_replace([
            '/https?:\/\/\S+/i',
            '/[A-Za-z0-9+\/]{80,}={0,2}/',
            '/AIza[\w-]+/',
            '/storage\/app\/nusa-debug\/tmp\/[^\s]+/i',
        ], [
            '[redacted-url]',
            '[redacted-long-token]',
            '[redacted-api-key]',
            '[redacted-temp-path]',
        ], $message) ?? 'Google Veo SDK bridge failed safely.';
    }
}
