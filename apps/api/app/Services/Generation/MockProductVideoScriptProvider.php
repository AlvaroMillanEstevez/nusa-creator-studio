<?php

namespace App\Services\Generation;

use App\Contracts\ProductVideoScriptProvider;
use Illuminate\Support\Str;

class MockProductVideoScriptProvider implements ProductVideoScriptProvider
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
            'A practical product that should be demonstrated honestly.',
        );
        $benefits = $this->value(
            $input,
            'key_benefits',
            'Useful product details supplied by the creator.',
        );
        $audience = $this->value($input, 'target_audience', 'people comparing useful products');
        $goal = strtolower($this->value($input, 'video_goal', 'product_demo'));
        $style = strtolower($this->value($input, 'video_style', 'natural'));
        $language = strtolower($this->value($input, 'language', 'english'));
        $duration = $this->duration($input['duration_seconds'] ?? 30);
        $timestamps = $this->timestamps($duration);

        return match ($language) {
            'indonesian', 'id', 'bahasa indonesia' => $this->indonesian(
                $product,
                $description,
                $benefits,
                $audience,
                $goal,
                $style,
                $duration,
                $timestamps,
            ),
            'spanish', 'es', 'español' => $this->spanish(
                $product,
                $description,
                $benefits,
                $audience,
                $goal,
                $style,
                $duration,
                $timestamps,
            ),
            default => $this->english(
                $product,
                $description,
                $benefits,
                $audience,
                $goal,
                $style,
                $duration,
                $timestamps,
            ),
        };
    }

    /**
     * @param  array<int, string>  $timestamps
     * @return array<string, mixed>
     */
    private function english(
        string $product,
        string $description,
        string $benefits,
        string $audience,
        string $goal,
        string $style,
        int $duration,
        array $timestamps,
    ): array {
        $goalLabel = $this->label($goal);
        $styleLabel = $this->label($style);

        return [
            'concept_title' => "{$product}: a {$duration}-second {$styleLabel} production plan",
            'video_angle' => "Use a {$goalLabel} angle that introduces {$product}, demonstrates the creator-provided details, and gives {$audience} a realistic next step.",
            'storyboard' => [
                $this->scene(1, $timestamps[0], 'Stop the scroll', "Open with the clearest hero shot of {$product}.", "Here is a quick look at {$product}.", "Meet {$product}"),
                $this->scene(2, $timestamps[1], 'Set the context', "Show the product in the setting described by the creator: {$description}", "This plan is designed for {$audience}.", 'Who it is for'),
                $this->scene(3, $timestamps[2], 'Demonstrate useful details', "Film two or three close shots that support these supplied benefits: {$benefits}", 'Show only benefits you can verify in the real product.', 'See the details'),
                $this->scene(4, $timestamps[3], 'Close with a clear action', 'End on a stable product shot with room for CTA text.', "Review the product details and decide whether {$product} fits your needs.", 'Review before choosing'),
            ],
            'shot_list' => [
                'Vertical hero shot of the product',
                'Close-up of setup or everyday use',
                'Detail shot supporting a creator-provided benefit',
                'Final product shot with clean space for CTA text',
            ],
            'asset_checklist' => [
                'Product and any accessories shown in the brief',
                'Clean vertical filming background',
                'Readable brand or product detail reference',
                'Royalty-cleared music or original audio',
            ],
            'caption' => "A {$styleLabel} {$goalLabel} plan for {$product}, created for {$audience}. Verify the product details, film the real experience, and avoid exaggerated claims.",
            'hashtags' => $this->hashtags($product, 'english'),
            'cta' => 'Review the product details and see whether it fits your needs.',
            'editing_notes' => [
                "Keep the final cut close to {$duration} seconds and trim pauses between scenes.",
                'Use consistent subtitle placement and leave safe space around interface controls.',
                "Keep transitions {$styleLabel} and let the product remain easy to see.",
            ],
            'compliance_notes' => [
                'This is a production plan and script pack, not a rendered or analyzed video.',
                'Use only claims and benefits you can verify from the real product.',
                'Add any required affiliate disclosure before publishing.',
            ],
        ];
    }

    /**
     * @param  array<int, string>  $timestamps
     * @return array<string, mixed>
     */
    private function indonesian(
        string $product,
        string $description,
        string $benefits,
        string $audience,
        string $goal,
        string $style,
        int $duration,
        array $timestamps,
    ): array {
        $goalLabel = $this->label($goal);
        $styleLabel = $this->label($style);

        return [
            'concept_title' => "{$product}: rencana produksi {$styleLabel} {$duration} detik",
            'video_angle' => "Gunakan sudut {$goalLabel} untuk memperkenalkan {$product}, menunjukkan detail dari kreator, dan memberi langkah berikutnya yang realistis bagi {$audience}.",
            'storyboard' => [
                $this->scene(1, $timestamps[0], 'Tarik perhatian', "Mulai dengan gambar utama {$product} yang paling jelas.", "Ini tampilan singkat {$product}.", "Kenalan dengan {$product}"),
                $this->scene(2, $timestamps[1], 'Berikan konteks', "Tampilkan produk sesuai konteks kreator: {$description}", "Rencana ini dibuat untuk {$audience}.", 'Cocok untuk siapa'),
                $this->scene(3, $timestamps[2], 'Tunjukkan detail berguna', "Rekam dua atau tiga close-up yang mendukung manfaat berikut: {$benefits}", 'Tampilkan hanya manfaat yang bisa dibuktikan.', 'Lihat detailnya'),
                $this->scene(4, $timestamps[3], 'Tutup dengan arahan jelas', 'Akhiri dengan gambar produk stabil dan ruang untuk teks CTA.', "Cek detail produk dan tentukan apakah {$product} sesuai kebutuhanmu.", 'Cek sebelum memilih'),
            ],
            'shot_list' => [
                'Hero shot vertikal produk',
                'Close-up persiapan atau penggunaan sehari-hari',
                'Detail shot yang mendukung manfaat dari kreator',
                'Gambar akhir produk dengan ruang untuk CTA',
            ],
            'asset_checklist' => [
                'Produk dan aksesori yang disebutkan dalam brief',
                'Latar vertikal yang bersih',
                'Referensi detail merek atau produk',
                'Musik bebas lisensi atau audio asli',
            ],
            'caption' => "Rencana {$styleLabel} dengan tujuan {$goalLabel} untuk {$product}, dibuat bagi {$audience}. Periksa detail produk dan hindari klaim berlebihan.",
            'hashtags' => $this->hashtags($product, 'indonesian'),
            'cta' => 'Cek detail produk dan lihat apakah sesuai kebutuhanmu.',
            'editing_notes' => [
                "Jaga durasi akhir mendekati {$duration} detik dan potong jeda antarscene.",
                'Gunakan posisi subtitle yang konsisten dan sisakan area aman.',
                "Gunakan transisi {$styleLabel} agar produk tetap mudah dilihat.",
            ],
            'compliance_notes' => [
                'Ini adalah rencana produksi dan paket skrip, bukan video final.',
                'Gunakan hanya klaim dan manfaat yang dapat diverifikasi.',
                'Tambahkan disclosure afiliasi jika diwajibkan.',
            ],
        ];
    }

    /**
     * @param  array<int, string>  $timestamps
     * @return array<string, mixed>
     */
    private function spanish(
        string $product,
        string $description,
        string $benefits,
        string $audience,
        string $goal,
        string $style,
        int $duration,
        array $timestamps,
    ): array {
        $goalLabel = $this->label($goal);
        $styleLabel = $this->label($style);

        return [
            'concept_title' => "{$product}: plan de producción {$styleLabel} de {$duration} segundos",
            'video_angle' => "Usa un ángulo de {$goalLabel} para presentar {$product}, mostrar los detalles aportados y ofrecer a {$audience} un siguiente paso realista.",
            'storyboard' => [
                $this->scene(1, $timestamps[0], 'Captar la atención', "Empieza con la toma más clara de {$product}.", "Aquí tienes una mirada rápida a {$product}.", "Conoce {$product}"),
                $this->scene(2, $timestamps[1], 'Dar contexto', "Muestra el producto según el contexto del creador: {$description}", "Este plan está pensado para {$audience}.", 'Para quién es'),
                $this->scene(3, $timestamps[2], 'Mostrar detalles útiles', "Graba dos o tres primeros planos que apoyen estos beneficios: {$benefits}", 'Muestra solamente beneficios que puedas verificar.', 'Mira los detalles'),
                $this->scene(4, $timestamps[3], 'Cerrar con una acción clara', 'Termina con una toma estable y espacio para el CTA.', "Revisa los detalles y decide si {$product} encaja con tus necesidades.", 'Revisa antes de elegir'),
            ],
            'shot_list' => [
                'Toma principal vertical del producto',
                'Primer plano de la preparación o uso cotidiano',
                'Toma de detalle que apoye un beneficio aportado',
                'Toma final con espacio limpio para el CTA',
            ],
            'asset_checklist' => [
                'Producto y accesorios incluidos en el brief',
                'Fondo vertical limpio',
                'Referencia legible de la marca o producto',
                'Música autorizada o audio original',
            ],
            'caption' => "Plan {$styleLabel} de {$goalLabel} para {$product}, creado para {$audience}. Comprueba los detalles y evita afirmaciones exageradas.",
            'hashtags' => $this->hashtags($product, 'spanish'),
            'cta' => 'Revisa los detalles del producto y comprueba si encaja contigo.',
            'editing_notes' => [
                "Mantén el corte final cerca de {$duration} segundos y elimina pausas.",
                'Usa subtítulos consistentes y deja espacio seguro para la interfaz.',
                "Mantén transiciones {$styleLabel} para que el producto siga visible.",
            ],
            'compliance_notes' => [
                'Este resultado es un plan de producción y guion, no un vídeo renderizado.',
                'Utiliza únicamente afirmaciones y beneficios que puedas verificar.',
                'Añade la divulgación de afiliación requerida antes de publicar.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scene(
        int $number,
        string $timestamp,
        string $goal,
        string $visual,
        string $voiceover,
        string $onScreenText,
    ): array {
        return [
            'scene_number' => $number,
            'timestamp' => $timestamp,
            'scene_goal' => $goal,
            'visual_direction' => $visual,
            'voiceover' => $voiceover,
            'on_screen_text' => $onScreenText,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function timestamps(int $duration): array
    {
        $secondEnd = (int) round($duration * 0.4);
        $thirdEnd = $duration - 5;

        return [
            '0-3s',
            "3-{$secondEnd}s",
            "{$secondEnd}-{$thirdEnd}s",
            "{$thirdEnd}-{$duration}s",
        ];
    }

    /**
     * @return array<int, string>
     */
    private function hashtags(string $product, string $language): array
    {
        $productTag = preg_replace('/[^\p{L}\p{N}]/u', '', $product) ?: 'ProductVideo';

        $localized = match ($language) {
            'indonesian' => ['#VideoProduk', '#Storyboard', '#RencanaKonten'],
            'spanish' => ['#VideoDeProducto', '#GuionDeVideo', '#PlanDeContenido'],
            default => ['#ProductVideo', '#VideoScript', '#ContentPlan'],
        };

        return [
            '#'.$productTag,
            ...$localized,
            '#TikTokShop',
            '#AffiliateCreator',
            '#ShotList',
            '#ShortFormVideo',
            '#CreatorWorkflow',
            '#ForYou',
        ];
    }

    private function duration(mixed $value): int
    {
        $duration = is_numeric($value) ? (int) $value : 30;

        return in_array($duration, [15, 30, 45], true) ? $duration : 30;
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

    private function label(string $value): string
    {
        return str_replace('_', ' ', $value);
    }
}
