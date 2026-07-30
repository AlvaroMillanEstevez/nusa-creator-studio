<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONSTRAINT_NAME = 'creator_tools_output_type_valid';

    private const HISTORICAL_OUTPUT_TYPES = [
        'text_insight',
        'script_pack',
        'assisted_media',
        'audio',
        'image',
        'video',
        'avatar',
        'mixed_media',
    ];

    private const CURRENT_OUTPUT_TYPES = [
        'text_insight',
        'script_pack',
        'assisted_media',
        'audio',
        'image',
        'video',
        'avatar',
        'mixed_media',
        'ad_plan',
    ];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $this->replaceConstraint(self::CURRENT_OUTPUT_TYPES);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $newOnlyOutputTypes = array_values(array_diff(self::CURRENT_OUTPUT_TYPES, self::HISTORICAL_OUTPUT_TYPES));

        $unsafeRowsExist = DB::table('creator_tools')
            ->whereIn('output_type', $newOnlyOutputTypes)
            ->exists();

        if ($unsafeRowsExist) {
            throw new RuntimeException(
                'Cannot restore the historical creator_tools output_type constraint while rows use newer output types: '
                .implode(', ', $newOnlyOutputTypes)
                .'. Update or remove those rows before rolling back this migration.',
            );
        }

        $this->replaceConstraint(self::HISTORICAL_OUTPUT_TYPES);
    }

    /**
     * @param  array<int, string>  $allowedOutputTypes
     */
    private function replaceConstraint(array $allowedOutputTypes): void
    {
        $quotedValues = collect($allowedOutputTypes)
            ->map(fn (string $outputType): string => DB::connection()->getPdo()->quote($outputType))
            ->implode(', ');

        DB::statement(sprintf(
            'ALTER TABLE creator_tools DROP CONSTRAINT IF EXISTS %s',
            self::CONSTRAINT_NAME,
        ));

        DB::statement(sprintf(
            'ALTER TABLE creator_tools ADD CONSTRAINT %s CHECK (output_type IS NULL OR output_type IN (%s))',
            self::CONSTRAINT_NAME,
            $quotedValues,
        ));
    }
};
