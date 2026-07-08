<?php

namespace App\Services\Generation;

use App\Contracts\CampaignGenerationProvider;
use App\Data\Campaign\CampaignGenerationRequestData;
use App\Data\Campaign\CampaignGenerationResultData;
use Illuminate\Support\Str;

class MockAffiliateCampaignProvider implements CampaignGenerationProvider
{
    public function generate(CampaignGenerationRequestData $data): CampaignGenerationResultData
    {
        $product = Str::limit($data->productName, 240, '');
        $description = Str::limit($data->productDescription, 240, '');
        $audience = Str::limit($data->targetAudience, 240, '');
        $language = strtolower($data->language);
        $tone = strtolower($data->tone);

        $legacy = match ($language) {
            'indonesian', 'id', 'bahasa indonesia' => $this->indonesian(
                $product,
                $description,
                $audience,
                $tone,
            ),
            'spanish', 'es', 'español' => $this->spanish(
                $product,
                $description,
                $audience,
                $tone,
            ),
            default => $this->english($product, $description, $audience, $tone),
        };

        return $this->resultFromLegacy($legacy);
    }

    /**
     * @return array<string, mixed>
     */
    private function english(
        string $product,
        string $description,
        string $audience,
        string $tone,
    ): array {
        $toneLabel = $this->toneLabel($tone, 'english');

        return [
            'hooks' => [
                "Before you scroll, take a closer look at {$product}.",
                "If you are {$audience}, this product may be worth comparing.",
                "Here is a simple, honest look at what {$product} offers.",
                'POV: you found a product that fits naturally into your routine.',
                "Three quick details to check before deciding if {$product} is for you.",
            ],
            'script' => [
                'title' => "{$product}: a {$toneLabel} TikTok product story",
                'duration_seconds' => 30,
                'scenes' => [
                    [
                        'timestamp' => '0-3s',
                        'voiceover' => "Before you scroll, meet {$product}.",
                        'visual_direction' => 'Open with a clean close-up of the product in use.',
                        'on_screen_text' => "Meet {$product}",
                    ],
                    [
                        'timestamp' => '3-10s',
                        'voiceover' => "It is designed for {$audience}.",
                        'visual_direction' => "Demonstrate this creator-provided product detail honestly: {$description}.",
                        'on_screen_text' => 'Who it is for',
                    ],
                    [
                        'timestamp' => '10-22s',
                        'voiceover' => 'Show the main feature, the setup, and the real experience without exaggeration.',
                        'visual_direction' => 'Use two or three quick shots showing setup and everyday use.',
                        'on_screen_text' => 'See how it works',
                    ],
                    [
                        'timestamp' => '22-30s',
                        'voiceover' => "Check the product details and decide whether {$product} fits your needs.",
                        'visual_direction' => 'Finish on the product with a simple tap or point toward the product link.',
                        'on_screen_text' => 'See details',
                    ],
                ],
            ],
            'caption' => "A quick, honest look at {$product} for {$audience}. Review the details and decide if it fits your routine.",
            'hashtags' => $this->hashtags($product, 'english'),
            'cta' => 'Check the product details and see if it is a good fit for you.',
            'notes' => [
                'Use real product footage and demonstrate only features you can verify.',
                "Keep the delivery {$toneLabel} and conversational rather than overly sales-focused.",
                'Add clear on-screen text so the story still works without sound.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function indonesian(
        string $product,
        string $description,
        string $audience,
        string $tone,
    ): array {
        $toneLabel = $this->toneLabel($tone, 'indonesian');

        return [
            'hooks' => [
                "Sebelum scroll, lihat lebih dekat {$product}.",
                "Kalau kamu termasuk {$audience}, produk ini bisa masuk daftar pertimbangan.",
                "Ini ulasan singkat dan jujur tentang {$product}.",
                'POV: kamu menemukan produk yang cocok untuk rutinitas sehari-hari.',
                "Tiga hal yang perlu dilihat sebelum memilih {$product}.",
            ],
            'script' => [
                'title' => "{$product}: cerita produk TikTok dengan gaya {$toneLabel}",
                'duration_seconds' => 30,
                'scenes' => [
                    [
                        'timestamp' => '0-3s',
                        'voiceover' => "Sebelum scroll, kenalan dulu dengan {$product}.",
                        'visual_direction' => 'Buka dengan close-up produk saat digunakan.',
                        'on_screen_text' => "Kenalan dengan {$product}",
                    ],
                    [
                        'timestamp' => '3-10s',
                        'voiceover' => "Produk ini ditujukan untuk {$audience}.",
                        'visual_direction' => "Tunjukkan detail dari kreator ini secara jujur: {$description}.",
                        'on_screen_text' => 'Cocok untuk siapa?',
                    ],
                    [
                        'timestamp' => '10-22s',
                        'voiceover' => 'Perlihatkan fitur utama, cara menyiapkan, dan pengalaman penggunaan tanpa melebih-lebihkan.',
                        'visual_direction' => 'Gunakan dua atau tiga klip singkat tentang persiapan dan penggunaan.',
                        'on_screen_text' => 'Lihat cara kerjanya',
                    ],
                    [
                        'timestamp' => '22-30s',
                        'voiceover' => "Cek detailnya dan tentukan apakah {$product} sesuai kebutuhanmu.",
                        'visual_direction' => 'Akhiri dengan tampilan produk dan arahkan ke tautan produk.',
                        'on_screen_text' => 'Cek detail produk',
                    ],
                ],
            ],
            'caption' => "Ulasan singkat dan jujur tentang {$product} untuk {$audience}. Cek detailnya dan sesuaikan dengan kebutuhanmu.",
            'hashtags' => $this->hashtags($product, 'indonesian'),
            'cta' => 'Cek detail produk dan lihat apakah cocok untuk kebutuhanmu.',
            'notes' => [
                'Gunakan rekaman produk asli dan hanya tampilkan fitur yang bisa dibuktikan.',
                "Sampaikan dengan gaya {$toneLabel} dan tetap terdengar natural.",
                'Tambahkan teks di layar agar pesan tetap jelas tanpa suara.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function spanish(
        string $product,
        string $description,
        string $audience,
        string $tone,
    ): array {
        $toneLabel = $this->toneLabel($tone, 'spanish');

        return [
            'hooks' => [
                "¿Quieres crear contenido rápido? Mira de cerca {$product}.",
                "Si eres {$audience}, vale la pena comparar este producto.",
                "Aquí tienes una mirada breve y honesta a {$product}.",
                '¡Una idea sencilla para mostrar un producto que encaja en tu rutina diaria!',
                "Tres detalles que conviene revisar antes de elegir {$product}.",
            ],
            'script' => [
                'title' => "{$product}: una historia de producto {$toneLabel} para TikTok",
                'duration_seconds' => 30,
                'scenes' => [
                    [
                        'timestamp' => '0-3s',
                        'voiceover' => "Antes de seguir, conoce {$product}.",
                        'visual_direction' => 'Empieza con un primer plano limpio del producto en uso.',
                        'on_screen_text' => "Conoce {$product}",
                    ],
                    [
                        'timestamp' => '3-10s',
                        'voiceover' => "Está pensado para {$audience}.",
                        'visual_direction' => "Muestra honestamente este detalle proporcionado por el creador: {$description}.",
                        'on_screen_text' => '¿Para quién es?',
                    ],
                    [
                        'timestamp' => '10-22s',
                        'voiceover' => 'Enseña la función principal, la preparación y la experiencia real sin exagerar.',
                        'visual_direction' => 'Usa dos o tres tomas rápidas de la preparación y el uso cotidiano.',
                        'on_screen_text' => 'Mira cómo funciona',
                    ],
                    [
                        'timestamp' => '22-30s',
                        'voiceover' => "Revisa los detalles y decide si {$product} encaja con tus necesidades.",
                        'visual_direction' => 'Termina mostrando el producto y señala el enlace del producto.',
                        'on_screen_text' => 'Ver detalles',
                    ],
                ],
            ],
            'caption' => "Una mirada rápida y honesta a {$product} para {$audience}. Revisa los detalles y decide si encaja en tu rutina.",
            'hashtags' => $this->hashtags($product, 'spanish'),
            'cta' => 'Revisa los detalles del producto y comprueba si es adecuado para ti.',
            'notes' => [
                'Usa imágenes reales y muestra solamente funciones que puedas verificar.',
                "Mantén un tono {$toneLabel}, cercano y sin presión de venta.",
                'Añade texto en pantalla para que el mensaje funcione también sin sonido.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function hashtags(string $product, string $language): array
    {
        $productTag = preg_replace('/[^\p{L}\p{N}]/u', '', $product) ?: 'ProductFind';

        $localized = match ($language) {
            'indonesian' => ['#RacunTikTok', '#ProdukPilihan', '#ReviewProduk'],
            'spanish' => ['#TikTokMeHizoComprarlo', '#ReseñaDeProducto', '#Creadores'],
            default => ['#TikTokMadeMeBuyIt', '#ProductReview', '#CreatorTips'],
        };

        return [
            '#'.$productTag,
            ...$localized,
            '#TikTokShop',
            '#AffiliateCreator',
            '#ProductDemo',
            '#ShortFormVideo',
            '#TikTokFinds',
            '#ForYou',
        ];
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function resultFromLegacy(array $legacy): CampaignGenerationResultData
    {
        /** @var array<string, mixed> $script */
        $script = is_array($legacy['script'] ?? null) ? $legacy['script'] : [];
        /** @var array<int, array<string, mixed>> $legacyScenes */
        $legacyScenes = is_array($script['scenes'] ?? null) ? $script['scenes'] : [];
        $scenes = array_values(array_map(fn (array $scene): array => [
            'time_range' => (string) ($scene['timestamp'] ?? ''),
            'visual_direction' => (string) ($scene['visual_direction'] ?? ''),
            'voiceover' => (string) ($scene['voiceover'] ?? ''),
            'on_screen_text' => (string) ($scene['on_screen_text'] ?? ''),
        ], $legacyScenes));
        $voiceoverLines = array_values(array_filter(array_map(
            fn (array $scene): string => trim((string) ($scene['voiceover'] ?? '')),
            $legacyScenes,
        )));
        $legacyHooks = array_values(is_array($legacy['hooks'] ?? null) ? $legacy['hooks'] : []);
        $hooks = array_values(array_map(
            fn (mixed $hook, int $index): array => [
                'label' => 'Hook '.($index + 1),
                'text' => (string) $hook,
                'intent' => 'Open with a clear, honest reason to keep watching.',
            ],
            $legacyHooks,
            array_keys($legacyHooks),
        ));

        return new CampaignGenerationResultData(
            provider: 'mock',
            providerModel: null,
            campaignTitle: (string) ($script['title'] ?? 'Mock affiliate campaign'),
            productAngle: 'A practical, honest short-form product story.',
            targetAudienceSummary: 'Use the creator-provided target audience and verify product fit before publishing.',
            hooks: $hooks,
            mainScript: [
                'duration_seconds' => (int) ($script['duration_seconds'] ?? 30),
                'voiceover_script' => implode("\n", $voiceoverLines),
                'on_screen_text' => array_values(array_map(
                    fn (array $scene): string => (string) ($scene['on_screen_text'] ?? ''),
                    $legacyScenes,
                )),
                'scenes' => $scenes,
            ],
            voiceoverReadyScript: implode("\n", $voiceoverLines),
            sceneBreakdown: $scenes,
            captions: [(string) ($legacy['caption'] ?? '')],
            hashtags: array_values(is_array($legacy['hashtags'] ?? null) ? $legacy['hashtags'] : []),
            ctaOptions: [(string) ($legacy['cta'] ?? '')],
            complianceNotes: [
                'Verify product details and affiliate disclosure requirements before publishing.',
                'Avoid claims that are not supported by real product evidence.',
            ],
            usageNotes: array_values(is_array($legacy['notes'] ?? null) ? $legacy['notes'] : []),
            metadata: [
                'mode' => 'deterministic_mock',
            ],
        );
    }

    private function toneLabel(string $tone, string $language): string
    {
        $labels = [
            'english' => [
                'natural' => 'natural',
                'funny' => 'light and funny',
                'premium' => 'premium',
                'problem_solution' => 'problem-solution',
                'review' => 'review-style',
            ],
            'indonesian' => [
                'natural' => 'natural',
                'funny' => 'ringan dan lucu',
                'premium' => 'premium',
                'problem_solution' => 'masalah-solusi',
                'review' => 'ulasan',
            ],
            'spanish' => [
                'natural' => 'natural',
                'funny' => 'ligero y divertido',
                'premium' => 'premium',
                'problem_solution' => 'problema-solución',
                'review' => 'reseña',
            ],
        ];

        return $labels[$language][$tone] ?? $labels[$language]['natural'];
    }
}
