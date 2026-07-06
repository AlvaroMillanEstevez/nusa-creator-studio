<?php

namespace App\Services\Generation;

use App\Contracts\VideoEnhancerProvider;
use Illuminate\Support\Str;

class MockVideoEnhancerProvider implements VideoEnhancerProvider
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generate(array $input): array
    {
        $title = $this->value($input, 'video_title', 'Recorded product video');
        $product = $this->value($input, 'product_name', 'Featured product');
        $context = $this->value(
            $input,
            'video_context',
            'The creator demonstrates the product in an everyday setting.',
        );
        $spoken = $this->value(
            $input,
            'spoken_summary',
            'A short, honest explanation of the product and how it fits the creator brief.',
        );
        $audience = $this->value($input, 'target_audience', 'people comparing useful products');
        $language = strtolower($this->value($input, 'language', 'english'));
        $style = strtolower($this->value($input, 'style', 'natural'));

        return match ($language) {
            'indonesian', 'id', 'bahasa indonesia' => $this->indonesian(
                $title,
                $product,
                $context,
                $spoken,
                $audience,
                $style,
            ),
            'spanish', 'es', 'español' => $this->spanish(
                $title,
                $product,
                $context,
                $spoken,
                $audience,
                $style,
            ),
            default => $this->english(
                $title,
                $product,
                $context,
                $spoken,
                $audience,
                $style,
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function english(
        string $title,
        string $product,
        string $context,
        string $spoken,
        string $audience,
        string $style,
    ): array {
        $styleLabel = $this->styleLabel($style, 'english');

        return [
            'improved_caption' => "A {$styleLabel}, honest look at {$product} for {$audience}. Use the recorded footage to show the real experience, then invite viewers to review the product details.",
            'subtitle_plan' => [
                ['timestamp' => '0-3s', 'text' => "{$title}: meet {$product}"],
                ['timestamp' => '3-10s', 'text' => $spoken],
                ['timestamp' => '10-20s', 'text' => "Brief context: {$context}"],
                ['timestamp' => '20-30s', 'text' => 'Check the details and decide if it fits your needs.'],
            ],
            'cut_plan' => [
                [
                    'timestamp' => '0-3s',
                    'edit_direction' => 'Open on the clearest product shot and trim any pause before the first spoken line.',
                    'reason' => 'A direct opening gives viewers immediate context.',
                ],
                [
                    'timestamp' => '3-18s',
                    'edit_direction' => 'Alternate the strongest demonstration angles described in the brief.',
                    'reason' => 'Visual variety supports the spoken explanation without implying unseen analysis.',
                ],
                [
                    'timestamp' => '18-30s',
                    'edit_direction' => 'Hold on the product result, then finish with a clean CTA frame.',
                    'reason' => 'The final beat gives viewers time to understand the next step.',
                ],
            ],
            'on_screen_text' => [
                "Meet {$product}",
                'What the creator noticed',
                'Everyday product demo',
                'Review the details',
            ],
            'posting_text' => "{$title} — a brief-based edit plan for {$product}. Created for {$audience}; verify every detail against the recorded footage before posting.",
            'hashtags' => $this->hashtags($product, 'english'),
            'cta' => 'Review the product details and see whether it fits your routine.',
            'notes' => [
                'This enhancement pack is based only on your written brief, not an analysis of the video file.',
                'Check subtitle timing against the real spoken audio and adjust each cue before posting.',
                "Keep the edit {$styleLabel} and use only product details you can verify.",
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function indonesian(
        string $title,
        string $product,
        string $context,
        string $spoken,
        string $audience,
        string $style,
    ): array {
        $styleLabel = $this->styleLabel($style, 'indonesian');

        return [
            'improved_caption' => "Ulasan {$styleLabel} dan jujur tentang {$product} untuk {$audience}. Gunakan rekaman asli untuk menunjukkan pengalaman nyata, lalu ajak penonton memeriksa detail produk.",
            'subtitle_plan' => [
                ['timestamp' => '0-3s', 'text' => "{$title}: kenalan dengan {$product}"],
                ['timestamp' => '3-10s', 'text' => $spoken],
                ['timestamp' => '10-20s', 'text' => "Konteks dari brief: {$context}"],
                ['timestamp' => '20-30s', 'text' => 'Cek detailnya dan tentukan apakah sesuai kebutuhanmu.'],
            ],
            'cut_plan' => [
                [
                    'timestamp' => '0-3s',
                    'edit_direction' => 'Mulai dengan gambar produk yang paling jelas dan potong jeda sebelum kalimat pertama.',
                    'reason' => 'Pembukaan langsung membantu penonton memahami konteks.',
                ],
                [
                    'timestamp' => '3-18s',
                    'edit_direction' => 'Ganti sudut demonstrasi terbaik yang disebutkan dalam brief.',
                    'reason' => 'Variasi visual mendukung penjelasan tanpa mengaku menganalisis video.',
                ],
                [
                    'timestamp' => '18-30s',
                    'edit_direction' => 'Tahan gambar hasil penggunaan, lalu tutup dengan frame CTA yang bersih.',
                    'reason' => 'Bagian akhir memberi waktu untuk memahami langkah berikutnya.',
                ],
            ],
            'on_screen_text' => [
                "Kenalan dengan {$product}",
                'Yang diperhatikan kreator',
                'Demo penggunaan sehari-hari',
                'Cek detail produk',
            ],
            'posting_text' => "{$title} — rencana edit berdasarkan brief untuk {$product}. Dibuat untuk {$audience}; periksa semua detail dengan rekaman asli sebelum posting.",
            'hashtags' => $this->hashtags($product, 'indonesian'),
            'cta' => 'Cek detail produk dan lihat apakah cocok untuk rutinitasmu.',
            'notes' => [
                'Paket ini hanya berdasarkan brief tertulis, bukan analisis file video.',
                'Cocokkan waktu subtitle dengan audio asli sebelum posting.',
                "Pertahankan gaya {$styleLabel} dan gunakan hanya detail produk yang bisa diverifikasi.",
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function spanish(
        string $title,
        string $product,
        string $context,
        string $spoken,
        string $audience,
        string $style,
    ): array {
        $styleLabel = $this->styleLabel($style, 'spanish');

        return [
            'improved_caption' => "Una mirada {$styleLabel} y honesta a {$product} para {$audience}. Usa la grabación real para mostrar la experiencia y después invita a revisar los detalles del producto.",
            'subtitle_plan' => [
                ['timestamp' => '0-3s', 'text' => "{$title}: conoce {$product}"],
                ['timestamp' => '3-10s', 'text' => $spoken],
                ['timestamp' => '10-20s', 'text' => "Contexto del brief: {$context}"],
                ['timestamp' => '20-30s', 'text' => 'Revisa los detalles y decide si encaja con tus necesidades.'],
            ],
            'cut_plan' => [
                [
                    'timestamp' => '0-3s',
                    'edit_direction' => 'Empieza con la toma más clara del producto y elimina la pausa inicial.',
                    'reason' => 'Una apertura directa da contexto de inmediato.',
                ],
                [
                    'timestamp' => '3-18s',
                    'edit_direction' => 'Alterna los mejores ángulos de demostración descritos en el brief.',
                    'reason' => 'La variedad visual apoya la explicación sin fingir un análisis del vídeo.',
                ],
                [
                    'timestamp' => '18-30s',
                    'edit_direction' => 'Mantén el resultado en pantalla y termina con un CTA limpio.',
                    'reason' => 'El cierre da tiempo para entender el siguiente paso.',
                ],
            ],
            'on_screen_text' => [
                "Conoce {$product}",
                'Lo que observó el creador',
                'Demostración cotidiana',
                'Revisa los detalles',
            ],
            'posting_text' => "{$title} — un plan de edición basado en el brief para {$product}. Creado para {$audience}; comprueba cada detalle con la grabación antes de publicar.",
            'hashtags' => $this->hashtags($product, 'spanish'),
            'cta' => 'Revisa los detalles del producto y comprueba si encaja en tu rutina.',
            'notes' => [
                'Este paquete se basa únicamente en tu brief escrito, no en un análisis del archivo de vídeo.',
                'Ajusta cada subtítulo al audio real antes de publicar.',
                "Mantén una edición {$styleLabel} y usa solamente detalles que puedas verificar.",
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function hashtags(string $product, string $language): array
    {
        $productTag = preg_replace('/[^\p{L}\p{N}]/u', '', $product) ?: 'ProductVideo';

        $localized = match ($language) {
            'indonesian' => [
                '#PeningkatVideo',
                '#TipsCaption',
                '#KreatorTikTok',
                '#VideoProduk',
                '#RencanaEdit',
                '#TeksDiLayar',
                '#TikTokShop',
                '#KreatorAfiliasi',
                '#UntukKamu',
            ],
            'spanish' => [
                '#MejoraDeVideo',
                '#IdeasDeCaption',
                '#CreadoresTikTok',
                '#VideoDeProducto',
                '#PlanDeEdición',
                '#TextoEnPantalla',
                '#TikTokShop',
                '#CreadorAfiliado',
                '#ParaTi',
            ],
            default => [
                '#VideoEnhancer',
                '#CaptionTips',
                '#TikTokCreator',
                '#ProductVideo',
                '#EditPlan',
                '#OnScreenText',
                '#TikTokShop',
                '#AffiliateCreator',
                '#ForYou',
            ],
        };

        return ['#'.$productTag, ...$localized];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function value(array $input, string $key, string $fallback): string
    {
        $value = $input[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }

        return Str::limit(trim($value), 240, '');
    }

    private function styleLabel(string $style, string $language): string
    {
        $labels = [
            'english' => [
                'natural' => 'natural',
                'energetic' => 'energetic',
                'premium' => 'premium',
                'educational' => 'educational',
                'review' => 'review-style',
            ],
            'indonesian' => [
                'natural' => 'natural',
                'energetic' => 'energik',
                'premium' => 'premium',
                'educational' => 'edukatif',
                'review' => 'ulasan',
            ],
            'spanish' => [
                'natural' => 'natural',
                'energetic' => 'enérgica',
                'premium' => 'premium',
                'educational' => 'educativa',
                'review' => 'de reseña',
            ],
        ];

        return $labels[$language][$style] ?? $labels[$language]['natural'];
    }
}
