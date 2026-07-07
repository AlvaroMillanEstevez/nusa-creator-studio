<?php

namespace App\Services\Generation;

use App\Contracts\MotionAiVideoProvider;
use Illuminate\Support\Str;

class MockMotionAiVideoProvider implements MotionAiVideoProvider
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generate(array $input): array
    {
        $title = $this->value($input, 'title', 'Motion AI Video concept', 160);
        $product = $this->value($input, 'product_name', 'the featured product', 160);
        $description = $this->value($input, 'product_description', 'a product shown clearly and honestly', 280);
        $goal = $this->label($this->value($input, 'video_goal', 'product_showcase', 80));
        $motionStyle = $this->label($this->value($input, 'motion_style', 'smooth_camera', 80));
        $visualStyle = $this->label($this->value($input, 'visual_style', 'realistic', 80));
        $duration = $this->intValue($input, 'duration_seconds', 15);
        $aspectRatio = $this->label($this->value($input, 'aspect_ratio', 'vertical_9_16', 80));
        $platform = $this->label($this->value($input, 'platform', 'tiktok', 80));
        $audience = $this->value($input, 'target_audience', 'short-form shoppers', 160);
        $brandNotes = $this->value($input, 'brand_notes', 'keep the brand presentation clean and simple', 240);
        $sceneNotes = $this->value($input, 'scene_notes', 'show the product early and keep each scene easy to understand', 280);
        $audioNotes = $this->value($input, 'audio_notes', 'use upbeat creator-friendly pacing if audio is added later', 240);
        $notes = $this->value($input, 'notes', '', 300);
        $visualReferences = $this->assetList($input, 'visual_references');
        $audioTracks = $this->assetList($input, 'audio_tracks');
        $referenceNames = $this->assetNames($visualReferences, 'uploaded visual reference');
        $audioNames = $this->assetNames($audioTracks, 'no audio track selected');

        return match (strtolower($this->value($input, 'language', 'english', 40))) {
            'indonesian' => $this->localized(
                $title,
                "Rancang konsep video motion {$duration} detik untuk {$product}. Tujuannya {$goal}, gaya gerak {$motionStyle}, gaya visual {$visualStyle}, dan format {$aspectRatio} untuk {$platform}.",
                [
                    ['timestamp' => '0-2s', 'scene_goal' => 'Hook cepat', 'visual_direction' => "Mulai dengan referensi {$referenceNames} dan tampilkan produk sejak awal.", 'motion_direction' => "Gunakan gerakan {$motionStyle} yang halus dan jelas.", 'on_screen_text' => "Kenali {$product}"],
                    ['timestamp' => '2-7s', 'scene_goal' => 'Konteks produk', 'visual_direction' => $description, 'motion_direction' => 'Berikan transisi pendek antar detail produk.', 'on_screen_text' => 'Detail yang mudah dilihat'],
                    ['timestamp' => '7-'.$duration.'s', 'scene_goal' => 'Arah CTA', 'visual_direction' => "Tutup dengan tampilan {$platform} yang aman untuk {$audience}.", 'motion_direction' => 'Akhiri dengan frame stabil agar teks CTA terbaca.', 'on_screen_text' => 'Cek detail produk'],
                ],
                "Video {$aspectRatio}, {$goal}, {$product}, gaya {$visualStyle}, motion {$motionStyle}, referensi visual {$referenceNames}, brand notes: {$brandNotes}, scene notes: {$sceneNotes}. Buat hasil aman, realistis, dan tidak berlebihan.",
                'klaim berlebihan, hasil tidak realistis, logo tambahan, teks rusak, produk berubah bentuk, gerakan kamera memusingkan',
                ["Gunakan {$motionStyle} sebagai pola kamera utama.", 'Prioritaskan produk tetap jelas di 2 detik pertama.', 'Sisakan ruang aman untuk caption dan badge affiliate.'],
                ['Potongan pembuka sangat cepat, lalu ritme lebih stabil.', 'Gunakan 3-5 shot pendek agar placeholder terasa siap untuk provider video nanti.', 'Jaga kontras teks agar mudah dibaca di layar kecil.'],
                $audioTracks === [] ? ['Belum ada audio track; gunakan catatan audio sebagai arahan future sync.', $audioNotes] : ["Sinkronkan gerakan utama dengan {$audioNames}.", $audioNotes],
                ["Gunakan untuk konsep {$platform}; render video nyata belum dibuat.", 'Tambahkan disclosure afiliasi dan detail produk nyata sebelum publikasi.'],
                ['Referensi visual aman dan terhubung ke project.', 'Produk terlihat sejak awal.', 'Tidak ada klaim hasil yang dijamin.', 'Prompt siap dipakai sebagai preview integrasi provider nanti.'],
                ['Unggah image produk dengan background bersih.', 'Tambahkan source video pendek jika ingin motion yang lebih realistis.', 'Tambahkan brand reference untuk warna, font, atau packaging.'],
                array_values(array_filter(['Output ini hanya placeholder terstruktur; belum ada video nyata dibuat.', 'Gunakan hanya asset yang dimiliki atau punya izin penggunaan.', 'Hindari klaim performa, kesehatan, finansial, atau hasil produk yang tidak dapat diverifikasi.', $notes !== '' ? "Catatan kreator: {$notes}" : null])),
                $this->references($visualReferences, $audioTracks),
                $duration,
                $aspectRatio,
            ),
            'spanish' => $this->localized(
                $title,
                "Prepara un concepto de vídeo motion de {$duration} segundos para {$product}. El objetivo es {$goal}, con movimiento {$motionStyle}, estilo visual {$visualStyle} y formato {$aspectRatio} para {$platform}.",
                [
                    ['timestamp' => '0-2s', 'scene_goal' => 'Gancho rápido', 'visual_direction' => "Empieza con la referencia {$referenceNames} y muestra el producto desde el inicio.", 'motion_direction' => "Usa un movimiento {$motionStyle} claro y cómodo de ver.", 'on_screen_text' => "Conoce {$product}"],
                    ['timestamp' => '2-7s', 'scene_goal' => 'Contexto del producto', 'visual_direction' => $description, 'motion_direction' => 'Haz transiciones breves entre detalles visibles.', 'on_screen_text' => 'Detalles fáciles de ver'],
                    ['timestamp' => '7-'.$duration.'s', 'scene_goal' => 'Cierre con CTA', 'visual_direction' => "Cierra con una composición segura para {$platform} y {$audience}.", 'motion_direction' => 'Termina en un encuadre estable para que el CTA sea legible.', 'on_screen_text' => 'Mira los detalles'],
                ],
                "Vídeo {$aspectRatio}, {$goal}, {$product}, estilo {$visualStyle}, movimiento {$motionStyle}, referencias visuales {$referenceNames}, notas de marca: {$brandNotes}, notas de escena: {$sceneNotes}. Mantén el resultado seguro, realista y sin promesas exageradas.",
                'promesas exageradas, resultado irreal, logos extra, texto corrupto, producto deformado, cámara mareante',
                ["Usa {$motionStyle} como movimiento principal de cámara.", 'Mantén el producto claro durante los primeros 2 segundos.', 'Deja zona segura para subtítulos y etiqueta de afiliado.'],
                ['Abre con un corte muy directo y luego estabiliza el ritmo.', 'Usa 3-5 planos cortos para que el placeholder pueda migrar a un proveedor real más adelante.', 'Mantén contraste suficiente para texto en móvil.'],
                $audioTracks === [] ? ['No hay audio track; usa las notas de audio para una futura sincronización.', $audioNotes] : ["Sincroniza los acentos visuales con {$audioNames}.", $audioNotes],
                ["Úsalo como concepto para {$platform}; todavía no se renderiza vídeo real.", 'Añade divulgación de afiliación y datos reales del producto antes de publicar.'],
                ['Las referencias visuales pertenecen al proyecto.', 'El producto aparece desde el inicio.', 'No se prometen resultados garantizados.', 'El prompt está listo como preview para una integración futura.'],
                ['Sube una imagen de producto con fondo limpio.', 'Añade un source video corto si quieres movimiento más realista.', 'Añade una referencia de marca para color, tipografía o packaging.'],
                array_values(array_filter(['Este output es un placeholder estructurado; aún no se genera un vídeo real.', 'Usa únicamente assets propios o con permiso de uso.', 'Evita promesas de rendimiento, salud, finanzas o resultados no verificables.', $notes !== '' ? "Nota del creador: {$notes}" : null])),
                $this->references($visualReferences, $audioTracks),
                $duration,
                $aspectRatio,
            ),
            default => $this->localized(
                $title,
                "Prepare a {$duration}-second motion video concept for {$product}. The goal is {$goal}, with {$motionStyle} movement, a {$visualStyle} visual style and {$aspectRatio} framing for {$platform}.",
                [
                    ['timestamp' => '0-2s', 'scene_goal' => 'Fast hook', 'visual_direction' => "Open with {$referenceNames} and show the product immediately.", 'motion_direction' => "Use clear {$motionStyle} movement without distracting from the product.", 'on_screen_text' => "Meet {$product}"],
                    ['timestamp' => '2-7s', 'scene_goal' => 'Product context', 'visual_direction' => $description, 'motion_direction' => 'Use short transitions between visible product details.', 'on_screen_text' => 'Easy-to-see details'],
                    ['timestamp' => '7-'.$duration.'s', 'scene_goal' => 'CTA close', 'visual_direction' => "Close with a {$platform}-ready frame for {$audience}.", 'motion_direction' => 'Finish on a stable frame so CTA text is readable.', 'on_screen_text' => 'Check the details'],
                ],
                "{$aspectRatio} video, {$goal}, {$product}, {$visualStyle} look, {$motionStyle} motion, visual references {$referenceNames}, brand notes: {$brandNotes}, scene notes: {$sceneNotes}. Keep the result safe, realistic and non-exaggerated.",
                'exaggerated claims, unrealistic results, extra logos, corrupted text, warped product shape, dizzy camera motion',
                ["Use {$motionStyle} as the primary camera movement.", 'Keep the product readable in the first 2 seconds.', 'Leave safe space for captions and affiliate labels.'],
                ['Start with a direct hook cut, then settle into a clearer rhythm.', 'Use 3-5 compact shots so the placeholder can map to a real video provider later.', 'Keep text contrast high for mobile viewing.'],
                $audioTracks === [] ? ['No audio track is attached; use the audio notes for future sync direction.', $audioNotes] : ["Sync visual accents to {$audioNames}.", $audioNotes],
                ["Use this as a {$platform} planning concept; no real video is rendered yet.", 'Add affiliate disclosure and verified product details before publishing.'],
                ['Visual references are attached to the project.', 'The product appears early.', 'No guaranteed product outcomes are claimed.', 'Prompt preview is ready for a future provider integration.'],
                ['Upload a clean product image.', 'Add a short source video if you want more realistic motion direction.', 'Add a brand reference for color, typography or packaging cues.'],
                array_values(array_filter(['This mock output is a structured placeholder; no real video is generated yet.', 'Use only assets you own or have permission to use.', 'Avoid performance, health, financial or product-result claims that cannot be verified.', $notes !== '' ? "Creator note: {$notes}" : null])),
                $this->references($visualReferences, $audioTracks),
                $duration,
                $aspectRatio,
            ),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $scenePlan
     * @param  array<int, string>  $cameraMotionNotes
     * @param  array<int, string>  $editingPlan
     * @param  array<int, string>  $audioSyncNotes
     * @param  array<int, string>  $platformUsageNotes
     * @param  array<int, string>  $qualityChecklist
     * @param  array<int, string>  $recommendedAssetImprovements
     * @param  array<int, string>  $complianceNotes
     * @param  array<string, mixed>  $references
     * @return array<string, mixed>
     */
    private function localized(string $title, string $summary, array $scenePlan, string $motionPrompt, string $negativePrompt, array $cameraMotionNotes, array $editingPlan, array $audioSyncNotes, array $platformUsageNotes, array $qualityChecklist, array $recommendedAssetImprovements, array $complianceNotes, array $references, int $duration, string $aspectRatio): array
    {
        return [
            'video_title' => $title,
            'concept_summary' => $summary,
            'selected_references' => $references,
            'generated_video' => [
                'status' => 'placeholder',
                'message' => 'Real AI video generation will be connected later.',
                'provider' => 'mock',
            ],
            'scene_plan' => $scenePlan,
            'motion_prompt' => $motionPrompt,
            'negative_prompt' => $negativePrompt,
            'camera_motion_notes' => $cameraMotionNotes,
            'editing_plan' => $editingPlan,
            'audio_sync_notes' => $audioSyncNotes,
            'platform_usage_notes' => $platformUsageNotes,
            'quality_checklist' => $qualityChecklist,
            'recommended_asset_improvements' => $recommendedAssetImprovements,
            'compliance_notes' => $complianceNotes,
            'api_ready_payload_preview' => [
                'provider' => 'mock',
                'duration_seconds' => $duration,
                'aspect_ratio' => $aspectRatio,
                'prompt' => $motionPrompt,
                'negative_prompt' => $negativePrompt,
                'references' => $references,
            ],
        ];
    }

    /** @param array<string, mixed> $input @return array<int, array<string, mixed>> */
    private function assetList(array $input, string $key): array
    {
        $assets = $input['assets'] ?? [];
        $items = is_array($assets) ? ($assets[$key] ?? []) : [];

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /** @param array<int, array<string, mixed>> $assets */
    private function assetNames(array $assets, string $fallback): string
    {
        $names = array_map(fn (array $asset): string => $this->assetName($asset), $assets);
        $names = array_values(array_filter($names, fn (string $name): bool => $name !== ''));

        return $names === [] ? $fallback : implode(', ', array_slice($names, 0, 4));
    }

    /** @param array<string, mixed> $asset */
    private function assetName(array $asset): string
    {
        $name = $asset['original_name'] ?? null;

        return is_string($name) && trim($name) !== '' ? trim($name) : 'uploaded asset';
    }

    /**
     * @param  array<int, array<string, mixed>>  $visualReferences
     * @param  array<int, array<string, mixed>>  $audioTracks
     * @return array<string, mixed>
     */
    private function references(array $visualReferences, array $audioTracks): array
    {
        return [
            'visual_references' => $visualReferences,
            'audio_tracks' => $audioTracks,
        ];
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

    /** @param array<string, mixed> $input */
    private function intValue(array $input, string $key, int $fallback): int
    {
        $value = $input[$key] ?? null;

        return is_numeric($value) ? (int) $value : $fallback;
    }

    private function label(string $value): string
    {
        return str_replace('_', ' ', $value);
    }
}