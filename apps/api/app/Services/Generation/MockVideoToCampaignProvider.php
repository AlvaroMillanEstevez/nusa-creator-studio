<?php

namespace App\Services\Generation;

use App\Contracts\VideoToCampaignProvider;
use App\Support\CampaignWorkflowMetadata;
use Illuminate\Support\Str;

class MockVideoToCampaignProvider implements VideoToCampaignProvider
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generate(array $input): array
    {
        $product = $this->value($input, 'product_name', 'the featured product', 160);
        $description = $this->value($input, 'product_description', 'a practical product shown in creator footage', 280);
        $audience = $this->value($input, 'target_audience', 'social commerce viewers', 180);
        $context = $this->value($input, 'campaign_context', 'raw product footage from the creator workspace', 260);
        $tone = $this->label($this->value($input, 'tone', 'natural', 80));
        $platform = $this->label($this->value($input, 'platform', 'tiktok', 80));
        $sourceVideoAssetId = $this->intValue($input, 'source_video_asset_id');
        $sourceVideoName = $this->sourceVideoName($input);

        return match (strtolower($this->value($input, 'language', 'english', 40))) {
            'indonesian' => $this->localized(
                product: $product,
                sourceVideoName: $sourceVideoName,
                videoSummary: "Rekaman {$sourceVideoName} diperlakukan sebagai video produk mentah untuk {$product}. Mock ini tidak menganalisis frame asli; ia membuat rencana kampanye berdasarkan metadata, brief, dan konteks kreator.",
                moments: [
                    ['timestamp' => '0-3s', 'moment' => 'Tampilkan produk sejak awal', 'campaign_use' => 'Gunakan sebagai hook visual pertama.'],
                    ['timestamp' => '3-8s', 'moment' => "Jelaskan konteks {$description}", 'campaign_use' => 'Pakai sebagai bagian problem-solution atau review singkat.'],
                    ['timestamp' => '8-15s', 'moment' => "Hubungkan manfaat ke {$audience}", 'campaign_use' => 'Jaga klaim tetap faktual dan bisa diverifikasi.'],
                ],
                observations: [
                    ['scene' => 'Opening', 'observation' => 'Mulai dengan shot produk paling jelas.', 'confidence' => 'mock'],
                    ['scene' => 'Middle', 'observation' => "Tambahkan konteks kreator: {$context}.", 'confidence' => 'mock'],
                    ['scene' => 'Close', 'observation' => 'Tutup dengan CTA sederhana tanpa klaim berlebihan.', 'confidence' => 'mock'],
                ],
                cuts: [
                    ['timestamp' => '0-2s', 'cut' => 'Potong jeda awal dan buka dengan produk.', 'reason' => 'Penonton perlu tahu produk apa dalam 2 detik.'],
                    ['timestamp' => '5-9s', 'cut' => 'Gunakan close-up atau detail yang paling informatif.', 'reason' => 'Detail produk membantu script terasa konkret.'],
                    ['timestamp' => '12-18s', 'cut' => 'Sisakan frame bersih untuk teks CTA.', 'reason' => 'CTA lebih mudah dibaca di layar kecil.'],
                ],
                angle: "Video {$tone} untuk {$platform} yang menunjukkan {$product} secara jujur untuk {$audience}.",
                hooks: [
                    "Punya rekaman produk? Ubah jadi campaign singkat untuk {$product}.",
                    "Tunjukkan {$product} dari shot paling jelas dulu.",
                    "Satu video mentah, tiga pesan utama untuk {$audience}.",
                    "Bikin penonton paham {$product} tanpa klaim berlebihan.",
                    'Gunakan footage ini untuk hook, narasi, caption dan CTA.',
                ],
                narration: "Mulai dari shot paling jelas: ini {$product}. Berdasarkan brief, produk ini dijelaskan sebagai {$description}. Tunjukkan konteks penggunaan secara nyata, lalu ajak penonton memeriksa detail produk sebelum membeli.",
                captions: [
                    "Footage mentah → campaign {$platform} untuk {$product}.",
                    'Hook, narasi, caption dan edit plan dari satu video produk.',
                    'Cek detail produk dan pastikan sesuai kebutuhanmu.',
                ],
                hashtags: ['#VideoProduk', '#TikTokShop', '#KreatorAfiliasi', '#KontenProduk', '#RencanaEdit', '#CaptionPack', '#BelanjaOnline', '#CreatorTools', '#UntukKamu', '#NusaCreator'],
                ctaOptions: ['Cek detail produknya.', 'Bandingkan dengan kebutuhanmu.', 'Simpan ide campaign ini.'],
                editPlanTitle: "Rencana edit vertikal untuk {$product}",
                complianceNotes: ['Pastikan semua klaim sesuai detail produk asli.', 'Tambahkan disclosure afiliasi jika konten digunakan untuk promosi.', 'Jangan menyiratkan hasil yang tidak terlihat di footage.'],
                usageNotes: ['Output ini mock dan belum menganalisis frame video asli.', 'Gunakan sebagai dasar script, voiceover, caption dan Auto Video Editor.', 'Pilih ulang footage jika nama file atau konteks tidak sesuai.'],
                sourceVideoAssetId: $sourceVideoAssetId,
            ),
            'spanish' => $this->localized(
                product: $product,
                sourceVideoName: $sourceVideoName,
                videoSummary: "El vídeo {$sourceVideoName} se trata como material bruto del producto {$product}. Este mock no analiza los frames reales; crea un plan de campaña desde los metadatos, el brief y el contexto del creador.",
                moments: [
                    ['timestamp' => '0-3s', 'moment' => 'Mostrar el producto desde el inicio', 'campaign_use' => 'Usarlo como primer gancho visual.'],
                    ['timestamp' => '3-8s', 'moment' => "Explicar el contexto: {$description}", 'campaign_use' => 'Convertirlo en una sección de problema-solución o reseña breve.'],
                    ['timestamp' => '8-15s', 'moment' => "Conectar el valor con {$audience}", 'campaign_use' => 'Mantener las afirmaciones factuales y verificables.'],
                ],
                observations: [
                    ['scene' => 'Apertura', 'observation' => 'Empieza con la toma más clara del producto.', 'confidence' => 'mock'],
                    ['scene' => 'Desarrollo', 'observation' => "Añade contexto del creador: {$context}.", 'confidence' => 'mock'],
                    ['scene' => 'Cierre', 'observation' => 'Termina con un CTA sencillo sin promesas exageradas.', 'confidence' => 'mock'],
                ],
                cuts: [
                    ['timestamp' => '0-2s', 'cut' => 'Recorta la pausa inicial y abre con el producto.', 'reason' => 'La audiencia debe entender el producto en los primeros 2 segundos.'],
                    ['timestamp' => '5-9s', 'cut' => 'Usa un close-up o el detalle más informativo.', 'reason' => 'El detalle visual hace que el guion sea concreto.'],
                    ['timestamp' => '12-18s', 'cut' => 'Deja un encuadre limpio para el CTA.', 'reason' => 'El CTA debe leerse bien en móvil.'],
                ],
                angle: "Vídeo {$tone} para {$platform} que presenta {$product} de forma honesta para {$audience}.",
                hooks: [
                    "¿Tienes footage de producto? Conviértelo en una campaña breve para {$product}.",
                    "Muestra {$product} desde la toma más clara primero.",
                    "Un vídeo bruto, tres mensajes clave para {$audience}.",
                    "Haz que la audiencia entienda {$product} sin promesas exageradas.",
                    'Usa este footage para gancho, narración, caption y CTA.',
                ],
                narration: "Empieza con la toma más clara: esto es {$product}. Según el brief, se describe como {$description}. Muestra el contexto de uso de forma realista y después invita a revisar los detalles del producto antes de comprar.",
                captions: [
                    "Footage bruto → campaña {$platform} para {$product}.",
                    'Gancho, narración, caption y plan de edición desde un vídeo de producto.',
                    'Revisa los detalles y comprueba si encaja contigo.',
                ],
                hashtags: ['#VideoDeProducto', '#TikTokShop', '#CreadorAfiliado', '#ContenidoProducto', '#PlanDeEdición', '#IdeasDeCaption', '#CompraOnline', '#CreatorTools', '#ParaTi', '#NusaCreator'],
                ctaOptions: ['Revisa los detalles del producto.', 'Compáralo con tus necesidades.', 'Guarda esta idea de campaña.'],
                editPlanTitle: "Plan de edición vertical para {$product}",
                complianceNotes: ['Verifica que todas las afirmaciones coincidan con los detalles reales del producto.', 'Añade disclosure de afiliación si se usa como promoción.', 'No sugieras resultados que no aparezcan en el footage.'],
                usageNotes: ['Este output es mock y todavía no analiza los frames reales del vídeo.', 'Úsalo como base para guion, voiceover, caption y Auto Video Editor.', 'Elige otro footage si el nombre del archivo o el contexto no encajan.'],
                sourceVideoAssetId: $sourceVideoAssetId,
            ),
            default => $this->localized(
                product: $product,
                sourceVideoName: $sourceVideoName,
                videoSummary: "The video {$sourceVideoName} is treated as raw product footage for {$product}. This mock does not inspect actual frames; it creates a campaign plan from metadata, the project brief and creator context.",
                moments: [
                    ['timestamp' => '0-3s', 'moment' => 'Show the product immediately', 'campaign_use' => 'Use it as the opening visual hook.'],
                    ['timestamp' => '3-8s', 'moment' => "Explain the context: {$description}", 'campaign_use' => 'Turn it into a short problem-solution or review beat.'],
                    ['timestamp' => '8-15s', 'moment' => "Connect the value to {$audience}", 'campaign_use' => 'Keep claims factual and verifiable.'],
                ],
                observations: [
                    ['scene' => 'Opening', 'observation' => 'Start with the clearest product shot.', 'confidence' => 'mock'],
                    ['scene' => 'Middle', 'observation' => "Add creator context: {$context}.", 'confidence' => 'mock'],
                    ['scene' => 'Close', 'observation' => 'End with a simple CTA without exaggerated claims.', 'confidence' => 'mock'],
                ],
                cuts: [
                    ['timestamp' => '0-2s', 'cut' => 'Trim any opening pause and start on the product.', 'reason' => 'Viewers should understand the product within 2 seconds.'],
                    ['timestamp' => '5-9s', 'cut' => 'Use the closest or most informative detail shot.', 'reason' => 'Specific detail makes the script feel concrete.'],
                    ['timestamp' => '12-18s', 'cut' => 'Leave a clean frame for the CTA.', 'reason' => 'CTA text needs readable mobile safe space.'],
                ],
                angle: "A {$tone} {$platform} video that presents {$product} honestly for {$audience}.",
                hooks: [
                    "Have raw product footage? Turn it into a short campaign for {$product}.",
                    "Show {$product} from the clearest shot first.",
                    "One raw video, three key messages for {$audience}.",
                    "Help viewers understand {$product} without exaggerated claims.",
                    'Use this footage for a hook, narration, caption and CTA.',
                ],
                narration: "Start with the clearest shot: this is {$product}. Based on the brief, it is described as {$description}. Show the usage context realistically, then invite viewers to review the product details before buying.",
                captions: [
                    "Raw footage → {$platform} campaign for {$product}.",
                    'Hook, narration, caption and edit plan from one product video.',
                    'Review the details and see if it fits your needs.',
                ],
                hashtags: ['#ProductVideo', '#TikTokShop', '#AffiliateCreator', '#ProductContent', '#EditPlan', '#CaptionIdeas', '#OnlineShopping', '#CreatorTools', '#ForYou', '#NusaCreator'],
                ctaOptions: ['Review the product details.', 'Compare it with your needs.', 'Save this campaign idea.'],
                editPlanTitle: "Vertical edit plan for {$product}",
                complianceNotes: ['Verify all claims against the real product details.', 'Add affiliate disclosure if this becomes promotional content.', 'Do not imply results that are not visible in the footage.'],
                usageNotes: ['This mock output has not analyzed real video frames yet.', 'Use it as a base for script, voiceover, captions and Auto Video Editor.', 'Choose a different source video if the filename or context does not match.'],
                sourceVideoAssetId: $sourceVideoAssetId,
            ),
        };
    }

    /**
     * @param  array<int, array<string, string>>  $moments
     * @param  array<int, array<string, string>>  $observations
     * @param  array<int, array<string, string>>  $cuts
     * @param  array<int, string>  $hooks
     * @param  array<int, string>  $captions
     * @param  array<int, string>  $hashtags
     * @param  array<int, string>  $ctaOptions
     * @param  array<int, string>  $complianceNotes
     * @param  array<int, string>  $usageNotes
     * @return array<string, mixed>
     */
    private function localized(
        string $product,
        string $sourceVideoName,
        string $videoSummary,
        array $moments,
        array $observations,
        array $cuts,
        string $angle,
        array $hooks,
        string $narration,
        array $captions,
        array $hashtags,
        array $ctaOptions,
        string $editPlanTitle,
        array $complianceNotes,
        array $usageNotes,
        ?int $sourceVideoAssetId,
    ): array {
        $workflowMetadata = CampaignWorkflowMetadata::forVideoAnalysis([
            'source_video_asset_id' => $sourceVideoAssetId,
            'recommended_cuts' => $cuts,
            'edit_plan' => ['timeline' => [[
                'timestamp' => '0-3s',
                'action' => 'Open with the strongest product visual and hook text.',
            ]]],
        ], $sourceVideoAssetId);

        return array_merge([
            'video_summary' => $videoSummary,
            'detected_product_moments' => $moments,
            'scene_observations' => $observations,
            'recommended_cuts' => $cuts,
            'campaign_angle' => $angle,
            'hooks' => $hooks,
            'narration_voice_script' => $narration,
            'captions' => $captions,
            'hashtags' => $hashtags,
            'cta_options' => $ctaOptions,
            'edit_plan' => [
                'title' => $editPlanTitle,
                'format' => 'vertical_9_16',
                'platform' => 'tiktok',
                'timeline' => [
                    ['timestamp' => '0-3s', 'action' => 'Open with the strongest product visual and hook text.'],
                    ['timestamp' => '3-10s', 'action' => 'Show proof/context shots while narration explains the product.'],
                    ['timestamp' => '10-20s', 'action' => 'Add benefit-oriented captions and keep claims factual.'],
                    ['timestamp' => '20-30s', 'action' => 'End with CTA and clean product frame.'],
                ],
                'source_video_name' => $sourceVideoName,
            ],
            'compliance_notes' => $complianceNotes,
            'usage_notes' => $usageNotes,
            'source_video_asset_id' => $sourceVideoAssetId,
            'provider_metadata' => [
                'provider' => 'mock',
                ...$workflowMetadata,
                'mode' => 'deterministic_video_to_campaign_plan',
                'real_video_analysis' => false,
                'source_video_name' => $sourceVideoName,
                'product' => $product,
            ],
        ], $workflowMetadata);
    }

    /** @param array<string, mixed> $input */
    private function sourceVideoName(array $input): string
    {
        $asset = $input['source_video_asset'] ?? [];

        if (is_array($asset) && is_string($asset['original_name'] ?? null) && trim($asset['original_name']) !== '') {
            return trim($asset['original_name']);
        }

        return 'uploaded source video';
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
    private function intValue(array $input, string $key): ?int
    {
        $value = $input[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private function label(string $value): string
    {
        return str_replace('_', ' ', $value);
    }
}
