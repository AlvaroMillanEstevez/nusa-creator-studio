<?php

namespace App\Services\Generation;

use App\Contracts\FashionTryOnProvider;
use App\Support\FashionTryOnCapabilities;
use Illuminate\Support\Str;

class MockFashionTryOnProvider implements FashionTryOnProvider
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generate(array $input): array
    {
        $title = $this->value($input, 'title', 'Fashion Try-On concept', 160);
        $product = $this->value($input, 'product_name', 'the featured garment', 160);
        $description = $this->value($input, 'product_description', 'a fashion product shown clearly and honestly', 260);
        $garmentType = $this->label($this->value($input, 'garment_type', 'garment', 80));
        $audience = $this->value($input, 'target_audience', 'style-focused shoppers', 160);
        $styleGoal = $this->label($this->value($input, 'style_goal', 'product_showcase', 80));
        $outputStyle = $this->label($this->value($input, 'output_style', 'realistic', 80));
        $background = $this->value($input, 'background_preference', 'a clean neutral background', 180);
        $platform = $this->label($this->value($input, 'platform', 'tiktok', 80));
        $modelNotes = $this->value($input, 'model_notes', 'keep the model pose natural and respectful', 220);
        $garmentNotes = $this->value($input, 'garment_notes', 'show the garment shape without altering design details', 220);
        $notes = $this->value($input, 'notes', '', 300);
        $modelAsset = $this->asset($input, 'model_reference');
        $garmentAsset = $this->asset($input, 'garment_reference');

        $tier = FashionTryOnCapabilities::normalizeTier(is_string($input['tier'] ?? null) ? $input['tier'] : null);
        $packageKey = FashionTryOnCapabilities::normalizePackage(is_string($input['package_key'] ?? null) ? $input['package_key'] : null);
        $personSource = FashionTryOnCapabilities::normalizePersonSource(is_string($input['person_source'] ?? null) ? $input['person_source'] : null);
        $category = is_string($input['category'] ?? null) ? $input['category'] : 'auto';
        $requestedOutputCount = is_int($input['requested_output_count'] ?? null)
            ? (int) $input['requested_output_count']
            : FashionTryOnCapabilities::defaultOutputCount($packageKey);
        $configuredNusaCreditCost = FashionTryOnCapabilities::configuredCreditCost($input['configured_nusa_credit_cost'] ?? $input['nusa_credit_cost'] ?? null);
        $perOutputCreditCost = FashionTryOnCapabilities::configuredCreditCost($input['per_output_credit_cost'] ?? $configuredNusaCreditCost);
        $premiumPreset = $tier === FashionTryOnCapabilities::TIER_PREMIUM
            ? FashionTryOnCapabilities::premiumPreset(FashionTryOnCapabilities::premiumPresetKeyFromInput($input))
            : null;

        $localized = match (strtolower($this->value($input, 'language', 'english', 40))) {
            'indonesian' => $this->localized(
                $title,
                "Buat konsep try-on {$outputStyle} untuk {$product}, tipe {$garmentType}, ditujukan untuk {$audience}. Gunakan referensi yang diunggah dan jangan mengubah desain utama produk.",
                "Gunakan {$this->assetName($modelAsset)} sebagai referensi model/orang. Catatan: {$modelNotes}.",
                "Gunakan {$this->assetName($garmentAsset)} sebagai referensi garment/produk. Konteks produk: {$description}. Catatan: {$garmentNotes}.",
                ["Komposisi vertikal untuk {$platform}.", "Tempatkan {$garmentType} secara natural sambil menjaga warna, siluet, dan detail utama.", "Gunakan {$background} agar produk tetap menjadi fokus."],
                ['Gunakan pose natural dari depan atau tiga perempat.', 'Sisakan ruang untuk caption pendek dan label produk.'],
                ['Jaga fit tetap masuk akal; jangan menjanjikan ukuran pasti atau perubahan bentuk tubuh.', 'Pertahankan panjang, potongan, pola, material, dan aksen dari gambar produk.'],
                ["Lighting lembut dan rata, sesuai gaya {$outputStyle}.", 'Hindari logo atau elemen brand yang tidak diberikan oleh kreator.'],
                ["Gunakan sebagai placeholder rencana konten {$styleGoal} untuk {$platform}.", 'Tambahkan disclosure afiliasi dan detail produk sebelum publikasi.'],
                ['Referensi model adalah asset gambar.', 'Referensi garment adalah asset gambar.', 'Warna dan siluet produk tetap mudah dikenali.', 'Tidak ada klaim fit, tubuh, atau hasil yang berlebihan.'],
                "Mockup fashion try-on vertikal, {$product}, {$garmentType}, {$outputStyle}, {$styleGoal}, referensi model {$this->assetName($modelAsset)}, referensi garment {$this->assetName($garmentAsset)}, background: {$background}, tampilan realistis dan aman.",
                'bentuk tubuh terdistorsi, fit tidak realistis, desain produk berubah, logo tambahan, artefak teks, klaim berlebihan',
                ['Satu gambar model/orang full-body yang jelas.', 'Satu gambar garment/produk resolusi baik dengan item terlihat penuh.', 'Referensi brand atau background opsional jika perlu arah kampanye khusus.'],
                array_values(array_filter(['Hasil mock ini hanya rencana; belum ada gambar try-on nyata yang dibuat.', 'Gunakan hanya asset yang dimiliki atau diizinkan untuk digunakan.', 'Hindari klaim ukuran pasti, perubahan tubuh, atau hasil produk yang dijamin.', $notes !== '' ? "Catatan kreator: {$notes}" : null])),
            ),
            'spanish' => $this->localized(
                $title,
                "Crea un concepto de probador virtual {$outputStyle} para {$product}, una prenda tipo {$garmentType}, pensado para {$audience}. Mantén la referencia del producto sin cambiar su diseño principal.",
                "Usa {$this->assetName($modelAsset)} como referencia de modelo/persona. Notas: {$modelNotes}.",
                "Usa {$this->assetName($garmentAsset)} como referencia de prenda/producto. Contexto: {$description}. Notas: {$garmentNotes}.",
                ["Encuadra en formato vertical para {$platform}.", "Coloca la prenda {$garmentType} de forma natural, conservando color, silueta y detalles visibles.", "Usa {$background} para que el producto sea el foco."],
                ['Usa una pose relajada de frente o tres cuartos, sin tapar detalles importantes.', 'Deja espacio para subtítulos cortos y etiquetas de producto.'],
                ['Mantén un ajuste creíble; no prometas talla exacta, cambios corporales ni resultados garantizados.', 'Conserva largo, cuello, mangas, patrón, material y accesorios de la imagen original.'],
                ["Iluminación suave y uniforme, coherente con el estilo {$outputStyle}.", 'Evita logos o elementos de marca que no hayan sido aportados por el creador.'],
                ["Úsalo como placeholder para contenido {$styleGoal} en {$platform}.", 'Añade divulgación de afiliación y detalles reales del producto antes de publicar.'],
                ['La referencia del modelo es un asset de imagen.', 'La referencia de la prenda es un asset de imagen.', 'El color y la silueta del producto siguen reconocibles.', 'No se hacen promesas de ajuste, cuerpo o resultados garantizados.'],
                "Mockup vertical de fashion try-on, {$product}, {$garmentType}, {$outputStyle}, {$styleGoal}, referencia modelo {$this->assetName($modelAsset)}, referencia prenda {$this->assetName($garmentAsset)}, fondo: {$background}, presentación realista y segura.",
                'cuerpo distorsionado, ajuste irreal, diseño de prenda cambiado, logos extra, texto corrupto, promesas exageradas',
                ['Una imagen clara de modelo/persona de cuerpo completo.', 'Una imagen de prenda/producto en buena resolución con el artículo completo visible.', 'Referencia opcional de marca o fondo si se necesita una estética concreta.'],
                array_values(array_filter(['Este resultado mock es solo un plan; todavía no se genera una imagen try-on real.', 'Usa únicamente assets propios o con permiso de uso.', 'Evita afirmar tallas exactas, cambios corporales o resultados garantizados.', $notes !== '' ? "Nota del creador: {$notes}" : null])),
            ),
            default => $this->localized(
                $title,
                "Create a {$outputStyle} try-on concept for {$product}, a {$garmentType}, aimed at {$audience}. Keep the presentation grounded in the uploaded references and avoid changing the product's core design.",
                "Use {$this->assetName($modelAsset)} as the model/person reference. Notes: {$modelNotes}.",
                "Use {$this->assetName($garmentAsset)} as the garment/product reference. Product context: {$description}. Notes: {$garmentNotes}.",
                ["Frame the model in a vertical composition for {$platform}.", "Place the {$garmentType} naturally on the body while preserving visible seams, color, and silhouette.", "Use {$background} so the product remains the focal point."],
                ['Use a relaxed front three-quarter pose with hands clear of important garment details.', 'Leave room at the top and bottom for short-form captions and product labels.'],
                ['Keep fit believable; do not imply custom tailoring, body transformation, or guaranteed sizing accuracy.', 'Preserve the garment length, neckline, sleeve shape, hardware, pattern and material cues from the source image.'],
                ["Lighting should be soft and even, matching the {$outputStyle} output style.", 'Avoid clutter, logos, or brand elements that were not supplied by the creator.'],
                ["Use this as a planning placeholder for {$styleGoal} content on {$platform}.", 'Add affiliate disclosure and product details before publishing.'],
                ['Model reference is an image asset.', 'Garment reference is an image asset.', 'Product color and silhouette remain recognizable.', 'No body-shaming, exact-fit, or guaranteed outcome claims are made.'],
                "Vertical fashion try-on mockup, {$product}, {$garmentType}, {$outputStyle}, {$styleGoal}, model reference {$this->assetName($modelAsset)}, garment reference {$this->assetName($garmentAsset)}, background: {$background}, safe realistic presentation.",
                'distorted body, unrealistic fit, changed garment design, extra logos, text artifacts, unsafe claims, exaggerated results',
                ['One clear full-body model/person image.', 'One high-resolution garment/product image with the full item visible.', 'Optional brand or background reference if a specific campaign look is needed.'],
                array_values(array_filter(['This mock result is a planning placeholder; no real try-on image is generated yet.', 'Use only assets you own or have permission to use.', 'Avoid implying exact sizing, body changes, or guaranteed product outcomes.', $notes !== '' ? "Creator note to consider: {$notes}" : null])),
            ),
        };

        return array_merge([
            'package_key' => $packageKey,
            'person_source' => $personSource,
            'tier' => $tier,
            'category' => in_array($category, FashionTryOnCapabilities::STANDARD_CATEGORIES, true) ? $category : 'auto',
            'premium_preset' => $premiumPreset['key'] ?? null,
            'nusa_credit_cost' => $configuredNusaCreditCost,
            'configured_nusa_credit_cost' => $configuredNusaCreditCost,
            'per_output_credit_cost' => $perOutputCreditCost,
            'requested_output_count' => $requestedOutputCount,
            'output_count' => 0,
            'delivered_output_count' => 0,
            'missing_output_count' => $packageKey === FashionTryOnCapabilities::PACKAGE_VARIATIONS ? $requestedOutputCount : 0,
            'partial_output' => false,
            'selected_person_image' => $modelAsset,
            'selected_product_image' => $garmentAsset,
            'input_readiness' => [
                'person_image_selected' => $modelAsset !== [],
                'product_image_selected' => $garmentAsset !== [],
                'permission_confirmed' => (bool) ($input['person_image_permission_confirmed'] ?? false),
            ],
            'provider_request_summary' => [
                'provider' => 'mock',
                'package_key' => $packageKey,
                'person_source' => $personSource,
                'tier' => $tier,
                'premium_preset' => $premiumPreset['key'] ?? null,
                'requested_output_count' => $requestedOutputCount,
                'output_count' => 0,
                'real_provider_call_made' => false,
            ],
            'generation_summary' => [
                'quality' => ucfirst($tier),
                'preset' => $tier === FashionTryOnCapabilities::TIER_PREMIUM ? ($premiumPreset['label'] ?? null) : null,
                'premium_preset' => $premiumPreset['key'] ?? null,
                'provider_resolution' => $premiumPreset['resolution_label'] ?? null,
                'provider_generation_mode' => $premiumPreset['quality_label'] ?? null,
                'category' => in_array($category, FashionTryOnCapabilities::STANDARD_CATEGORIES, true) ? $category : 'auto',
                'package_key' => $packageKey,
                'person_source' => $personSource,
                'requested_output_count' => $requestedOutputCount,
                'output_count' => 0,
                'nusa_credit_cost' => $configuredNusaCreditCost,
                'configured_nusa_credit_cost' => $configuredNusaCreditCost,
                'per_output_credit_cost' => $perOutputCreditCost,
            ],
            'image_quality_recommendations' => [
                'Use a clear person photo where the body and outfit area are visible.',
                'Use one clear clothing or wearable product photo with minimal background clutter.',
                'Avoid screenshots, collages, low light, heavy filters or tiny product crops.',
            ],
            'expected_output_description' => $packageKey === FashionTryOnCapabilities::PACKAGE_VARIATIONS
                ? 'A future real provider call will create the requested try-on variations as private image assets.'
                : 'A future real provider call will combine the person photo with the clothing/product photo into one private try-on image.',
            'missing_or_risky_input_notes' => [],
        ], $localized);
    }

    /**
     * @param  array<int, string>  $composition
     * @param  array<int, string>  $pose
     * @param  array<int, string>  $fit
     * @param  array<int, string>  $backgroundNotes
     * @param  array<int, string>  $platformNotes
     * @param  array<int, string>  $checklist
     * @param  array<int, string>  $recommended
     * @param  array<int, string>  $compliance
     * @return array<string, mixed>
     */
    private function localized(string $title, string $summary, string $modelSummary, string $garmentSummary, array $composition, array $pose, array $fit, array $backgroundNotes, array $platformNotes, array $checklist, string $prompt, string $negative, array $recommended, array $compliance): array
    {
        return [
            'try_on_title' => $title,
            'concept_summary' => $summary,
            'model_reference_summary' => $modelSummary,
            'garment_reference_summary' => $garmentSummary,
            'generated_image' => [
                'status' => 'not_generated',
                'message' => 'Preview plan only — no try-on image was generated.',
                'provider' => 'mock',
            ],
            'composition_plan' => $composition,
            'pose_and_framing_notes' => $pose,
            'garment_fit_notes' => $fit,
            'background_and_lighting_notes' => $backgroundNotes,
            'platform_usage_notes' => $platformNotes,
            'quality_checklist' => $checklist,
            'api_ready_prompt' => $prompt,
            'negative_prompt' => $negative,
            'recommended_assets' => $recommended,
            'compliance_notes' => $compliance,
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function asset(array $input, string $key): array
    {
        $assets = $input['assets'] ?? [];
        $asset = is_array($assets) ? ($assets[$key] ?? []) : [];

        return is_array($asset) ? $asset : [];
    }

    /** @param array<string, mixed> $asset */
    private function assetName(array $asset): string
    {
        $name = $asset['original_name'] ?? null;

        return is_string($name) && trim($name) !== '' ? trim($name) : 'uploaded reference image';
    }

    /** @param array<string, mixed> $input */
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
