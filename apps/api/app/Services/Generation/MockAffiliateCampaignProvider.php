<?php

namespace App\Services\Generation;

use App\Contracts\AffiliateCampaignProvider;
use Illuminate\Support\Str;

class MockAffiliateCampaignProvider implements AffiliateCampaignProvider
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generate(array $input): array
    {
        $product = $this->value($input, 'product_name', 'Featured product');
        $description = $this->value(
            $input,
            'product_description',
            'a practical product worth demonstrating clearly',
        );
        $audience = $this->value($input, 'target_audience', 'people comparing useful products');
        $language = strtolower($this->value($input, 'language', 'english'));
        $tone = strtolower($this->value($input, 'tone', 'natural'));

        return match ($language) {
            'indonesian', 'id', 'bahasa indonesia' => $this->indonesian(
                $product,
                $description,
                $audience,
                $tone,
            ),
            'spanish', 'es', 'espa?ol' => $this->spanish(
                $product,
                $description,
                $audience,
                $tone,
            ),
            default => $this->english($product, $description, $audience, $tone),
        };
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
                "Antes de seguir deslizando, mira de cerca {$product}.",
                "Si eres {$audience}, vale la pena comparar este producto.",
                "Aqu? tienes una mirada breve y honesta a {$product}.",
                'POV: encuentras un producto que encaja en tu rutina diaria.',
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
                        'voiceover' => "Est? pensado para {$audience}.",
                        'visual_direction' => "Muestra honestamente este detalle proporcionado por el creador: {$description}.",
                        'on_screen_text' => '?Para qui?n es?',
                    ],
                    [
                        'timestamp' => '10-22s',
                        'voiceover' => 'Ense?a la funci?n principal, la preparaci?n y la experiencia real sin exagerar.',
                        'visual_direction' => 'Usa dos o tres tomas r?pidas de la preparaci?n y el uso cotidiano.',
                        'on_screen_text' => 'Mira c?mo funciona',
                    ],
                    [
                        'timestamp' => '22-30s',
                        'voiceover' => "Revisa los detalles y decide si {$product} encaja con tus necesidades.",
                        'visual_direction' => 'Termina mostrando el producto y se?ala el enlace del producto.',
                        'on_screen_text' => 'Ver detalles',
                    ],
                ],
            ],
            'caption' => "Una mirada r?pida y honesta a {$product} para {$audience}. Revisa los detalles y decide si encaja en tu rutina.",
            'hashtags' => $this->hashtags($product, 'spanish'),
            'cta' => 'Revisa los detalles del producto y comprueba si es adecuado para ti.',
            'notes' => [
                'Usa im?genes reales y muestra solamente funciones que puedas verificar.',
                "Mant?n un tono {$toneLabel}, cercano y sin presi?n de venta.",
                'A?ade texto en pantalla para que el mensaje funcione tambi?n sin sonido.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function hashtags(string $product, string $language): array
    {
        $productTag = preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($product)) ?: 'ProductFind';

        $localized = match ($language) {
            'indonesian' => ['#RacunTikTok', '#ProdukPilihan', '#ReviewProduk'],
            'spanish' => ['#TikTokMeHizoComprarlo', '#Rese?aDeProducto', '#Creadores'],
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
                'problem_solution' => 'problema-soluci?n',
                'review' => 'rese?a',
            ],
        ];

        return $labels[$language][$tone] ?? $labels[$language]['natural'];
    }
}
