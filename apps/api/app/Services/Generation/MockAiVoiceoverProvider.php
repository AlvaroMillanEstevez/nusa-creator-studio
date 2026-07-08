<?php

namespace App\Services\Generation;

use App\Contracts\AiVoiceoverProvider;
use App\Data\Voiceover\AiVoiceoverRequestData;
use App\Data\Voiceover\AiVoiceoverResultData;
use Illuminate\Support\Str;

class MockAiVoiceoverProvider implements AiVoiceoverProvider
{
    public function generate(AiVoiceoverRequestData $data): AiVoiceoverResultData
    {
        $input = $data->input;
        $title = $this->value($input, 'title', 'Creator voiceover plan', 160);
        $sourceText = $this->value($input, 'source_text', '', 800);
        $product = $this->value($input, 'product_name', 'the featured product', 160);
        $description = $this->value(
            $input,
            'product_description',
            'a practical product that should be described clearly and honestly',
            260,
        );
        $audience = $this->value($input, 'target_audience', 'short-form video viewers', 220);
        $language = strtolower($this->value($input, 'language', 'english', 40));
        $voiceStyle = strtolower($this->value($input, 'voice_style', 'natural_creator', 80));
        $tone = strtolower($this->value($input, 'tone', 'natural', 80));
        $pace = strtolower($this->value($input, 'pace', 'medium', 40));
        $duration = $this->duration($input['duration_seconds'] ?? 30);
        $platform = strtolower($this->value($input, 'platform', 'tiktok', 80));
        $notes = $this->value($input, 'notes', '', 500);
        $timestamps = $this->timestamps($duration);

        $payload = match ($language) {
            'indonesian', 'id', 'bahasa indonesia' => $this->indonesian(
                $title,
                $sourceText,
                $product,
                $description,
                $audience,
                $voiceStyle,
                $tone,
                $pace,
                $duration,
                $platform,
                $notes,
                $timestamps,
            ),
            'spanish', 'es', 'español' => $this->spanish(
                $title,
                $sourceText,
                $product,
                $description,
                $audience,
                $voiceStyle,
                $tone,
                $pace,
                $duration,
                $platform,
                $notes,
                $timestamps,
            ),
            default => $this->english(
                $title,
                $sourceText,
                $product,
                $description,
                $audience,
                $voiceStyle,
                $tone,
                $pace,
                $duration,
                $platform,
                $notes,
                $timestamps,
            ),
        };

        return AiVoiceoverResultData::mock($payload, mb_strlen($data->text));
    }

    /**
     * @param  array<int, string>  $timestamps
     * @return array<string, mixed>
     */
    private function english(
        string $title,
        string $sourceText,
        string $product,
        string $description,
        string $audience,
        string $voiceStyle,
        string $tone,
        string $pace,
        int $duration,
        string $platform,
        string $notes,
        array $timestamps,
    ): array {
        $styleLabel = $this->label($voiceStyle);
        $toneLabel = $this->label($tone);
        $platformLabel = $this->label($platform);
        $scriptSource = $sourceText !== ''
            ? "Base the narration on this creator script: {$sourceText}"
            : "Introduce {$product} for {$audience}, describe it as {$description}, and keep the claims easy to verify.";

        return $this->payload(
            title: "{$title}: {$duration}-second voiceover plan",
            narration: "Before you scroll, here is a clear look at {$product}. {$scriptSource} Keep the delivery {$toneLabel}, helpful, and grounded. End by inviting viewers to review the product details before deciding if it fits their routine.",
            timing: [
                $this->timing($timestamps[0], 'Hook', "Open with {$product} and name who it helps.", 'Warm, direct opening.'),
                $this->timing($timestamps[1], 'Useful context', "Explain the creator-provided detail without exaggerating: {$description}.", 'Slow slightly around product details.'),
                $this->timing($timestamps[2], 'CTA', 'Invite viewers to check details and decide for themselves.', 'Confident but not pushy.'),
            ],
            direction: "Use a {$styleLabel} voice with a {$toneLabel} tone and {$pace} pacing for {$platformLabel}.",
            pronunciation: [
                "Say {$product} clearly on the first mention.",
                'Pause briefly before any product detail that appears as on-screen text.',
            ],
            pacing: [
                "Target about {$duration} seconds with {$pace} pacing.",
                'Leave half-second gaps for cuts or text overlays.',
            ],
            platform: [
                "Mix the narration under vertical-video footage for {$platformLabel}.",
                'Keep the first sentence short enough to fit the opening three seconds.',
            ],
            duration: $duration,
            usage: array_values(array_filter([
                'This mock result is a narration plan only; no audio file is generated yet.',
                'Record or synthesize audio later using only claims you can verify.',
                $notes !== '' ? "Creator note to consider: {$notes}" : null,
            ])),
            compliance: [
                'Avoid medical, financial or guaranteed outcome claims unless they are verified and allowed.',
                'Add required affiliate disclosure before publishing.',
            ],
        );
    }

    /**
     * @param  array<int, string>  $timestamps
     * @return array<string, mixed>
     */
    private function indonesian(
        string $title,
        string $sourceText,
        string $product,
        string $description,
        string $audience,
        string $voiceStyle,
        string $tone,
        string $pace,
        int $duration,
        string $platform,
        string $notes,
        array $timestamps,
    ): array {
        $styleLabel = $this->label($voiceStyle);
        $toneLabel = $this->label($tone);
        $platformLabel = $this->label($platform);
        $scriptSource = $sourceText !== ''
            ? "Gunakan skrip kreator ini sebagai dasar narasi: {$sourceText}"
            : "Perkenalkan {$product} untuk {$audience}, jelaskan sebagai {$description}, dan gunakan klaim yang mudah diverifikasi.";

        return $this->payload(
            title: "{$title}: rencana voiceover {$duration} detik",
            narration: "Sebelum lanjut scroll, lihat dulu {$product}. {$scriptSource} Sampaikan dengan gaya {$toneLabel}, membantu, dan tidak berlebihan. Tutup dengan ajakan untuk mengecek detail produk sebelum memutuskan.",
            timing: [
                $this->timing($timestamps[0], 'Pembuka', "Mulai dengan {$product} dan sebutkan siapa yang terbantu.", 'Pembukaan hangat dan langsung.'),
                $this->timing($timestamps[1], 'Konteks berguna', "Jelaskan detail dari kreator tanpa melebih-lebihkan: {$description}.", 'Perlambat sedikit saat menyebut detail produk.'),
                $this->timing($timestamps[2], 'CTA', 'Ajak penonton mengecek detail dan menilai sendiri.', 'Percaya diri, tetapi tidak memaksa.'),
            ],
            direction: "Gunakan suara {$styleLabel} dengan tone {$toneLabel} dan pace {$pace} untuk {$platformLabel}.",
            pronunciation: [
                "Ucapkan {$product} dengan jelas pada penyebutan pertama.",
                'Beri jeda singkat sebelum detail produk yang muncul sebagai teks layar.',
            ],
            pacing: [
                "Target sekitar {$duration} detik dengan pace {$pace}.",
                'Sisakan jeda setengah detik untuk cut atau overlay teks.',
            ],
            platform: [
                "Campurkan narasi di bawah footage video vertikal untuk {$platformLabel}.",
                'Buat kalimat pertama cukup singkat untuk tiga detik pembuka.',
            ],
            duration: $duration,
            usage: array_values(array_filter([
                'Hasil mock ini hanya rencana narasi; belum ada file audio yang dibuat.',
                'Rekam atau hubungkan audio nanti hanya dengan klaim yang dapat diverifikasi.',
                $notes !== '' ? "Catatan kreator: {$notes}" : null,
            ])),
            compliance: [
                'Hindari klaim medis, finansial, atau hasil pasti kecuali sudah terverifikasi dan diperbolehkan.',
                'Tambahkan disclosure afiliasi sebelum publikasi jika diwajibkan.',
            ],
        );
    }

    /**
     * @param  array<int, string>  $timestamps
     * @return array<string, mixed>
     */
    private function spanish(
        string $title,
        string $sourceText,
        string $product,
        string $description,
        string $audience,
        string $voiceStyle,
        string $tone,
        string $pace,
        int $duration,
        string $platform,
        string $notes,
        array $timestamps,
    ): array {
        $styleLabel = $this->label($voiceStyle);
        $toneLabel = $this->label($tone);
        $platformLabel = $this->label($platform);
        $scriptSource = $sourceText !== ''
            ? "Usa este guion del creador como base de la narración: {$sourceText}"
            : "Presenta {$product} para {$audience}, descríbelo como {$description} y mantén afirmaciones fáciles de comprobar.";

        return $this->payload(
            title: "{$title}: plan de voz en off de {$duration} segundos",
            narration: "Antes de seguir, mira de cerca {$product}. {$scriptSource} Mantén una entrega {$toneLabel}, útil y sin exageraciones. Cierra con una pregunta simple: ¿encaja con su rutina? ¡Sin presión ni promesas exageradas!",
            timing: [
                $this->timing($timestamps[0], 'Gancho', "Abre con {$product} y menciona a quién puede ayudar.", 'Entrada cálida y directa.'),
                $this->timing($timestamps[1], 'Contexto útil', "Explica el detalle aportado sin exagerar: {$description}.", 'Baja un poco la velocidad en los detalles.'),
                $this->timing($timestamps[2], 'CTA', 'Invita a revisar los detalles y decidir con criterio propio.', 'Seguro, pero sin presión.'),
            ],
            direction: "Usa una voz {$styleLabel} con tono {$toneLabel} y ritmo {$pace} para {$platformLabel}.",
            pronunciation: [
                "Pronuncia {$product} con claridad en la primera mención.",
                'Haz una pausa breve antes de los detalles que aparezcan como texto en pantalla.',
            ],
            pacing: [
                "Apunta a unos {$duration} segundos con ritmo {$pace}.",
                'Deja pausas de medio segundo para cortes o textos superpuestos.',
            ],
            platform: [
                "Mezcla la narración debajo del vídeo vertical para {$platformLabel}.",
                'Mantén la primera frase lo bastante corta para los primeros tres segundos.',
            ],
            duration: $duration,
            usage: array_values(array_filter([
                'Este resultado mock es solo un plan de narración; todavía no se genera audio real.',
                'Graba o conecta audio más adelante usando solo afirmaciones verificables.',
                $notes !== '' ? "Nota del creador: {$notes}" : null,
            ])),
            compliance: [
                'Evita afirmaciones médicas, financieras o resultados garantizados salvo que estén verificados y permitidos.',
                'Añade la divulgación de afiliación requerida antes de publicar.',
            ],
        );
    }

    /**
     * @param  array<int, array<string, string>>  $timing
     * @param  array<int, string>  $pronunciation
     * @param  array<int, string>  $pacing
     * @param  array<int, string>  $platform
     * @param  array<int, string>  $usage
     * @param  array<int, string>  $compliance
     * @return array<string, mixed>
     */
    private function payload(
        string $title,
        string $narration,
        array $timing,
        string $direction,
        array $pronunciation,
        array $pacing,
        array $platform,
        int $duration,
        array $usage,
        array $compliance,
    ): array {
        return [
            'voiceover_title' => $title,
            'narration_script' => $narration,
            'scene_timing' => $timing,
            'voice_direction' => $direction,
            'pronunciation_notes' => $pronunciation,
            'pacing_notes' => $pacing,
            'platform_notes' => $platform,
            'estimated_duration_seconds' => $duration,
            'suggested_audio_format' => 'MP3 or WAV, 44.1 kHz, normalized for vertical-video editing.',
            'usage_notes' => $usage,
            'mock_audio' => [
                'status' => 'placeholder',
                'message' => 'Real audio generation will be connected later.',
                'provider' => 'mock',
            ],
            'compliance_notes' => $compliance,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function timing(string $timestamp, string $section, string $narrationFocus, string $deliveryNote): array
    {
        return [
            'timestamp' => $timestamp,
            'section' => $section,
            'narration_focus' => $narrationFocus,
            'delivery_note' => $deliveryNote,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function timestamps(int $duration): array
    {
        $middle = max(6, (int) round($duration * 0.55));
        $endStart = max($middle + 1, $duration - 6);

        return [
            '0-3s',
            "3-{$middle}s",
            "{$endStart}-{$duration}s",
        ];
    }

    private function duration(mixed $value): int
    {
        $duration = is_numeric($value) ? (int) $value : 30;

        return in_array($duration, [15, 30, 45, 60], true) ? $duration : 30;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function value(array $input, string $key, string $fallback, int $limit): string
    {
        $value = $input[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }

        return Str::limit(trim($value), $limit, '');
    }

    private function label(string $value): string
    {
        return str_replace('_', ' ', $value);
    }
}
