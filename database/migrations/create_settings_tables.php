<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\VariableKeys\Enums\MorphType;
use Cline\VariableKeys\Enums\PrimaryKeyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        $primaryKeyType = PrimaryKeyType::tryFrom(config('settings.primary_key_type', 'id')) ?? PrimaryKeyType::ID;
        $ownerMorphType = MorphType::tryFrom(config('settings.owner_morph_type', 'string')) ?? MorphType::String;
        $boundaryMorphType = MorphType::tryFrom(config('settings.boundary_morph_type', 'string')) ?? MorphType::String;
        $subjectMorphType = MorphType::tryFrom(config('settings.subject_morph_type', 'string')) ?? MorphType::String;

        Schema::create(config('settings.table_names.values', 'settings_values'), function (Blueprint $table) use ($boundaryMorphType, $ownerMorphType, $primaryKeyType): void {
            $table->variablePrimaryKey($primaryKeyType);
            $table->string('settings_class');
            $table->string('settings_namespace');
            $table->string('property');
            $table->variableMorphs('owner', $ownerMorphType);
            $table->variableMorphs('boundary', $boundaryMorphType, nullable: true);
            $table->json('value');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(
                [
                    'settings_class',
                    'property',
                    'owner_type',
                    'owner_id',
                    'boundary_type',
                    'boundary_id',
                ],
                'settings_values_scope_unique',
            );
        });

        Schema::create(config('settings.table_names.audits', 'settings_audits'), function (Blueprint $table) use ($boundaryMorphType, $ownerMorphType, $primaryKeyType, $subjectMorphType): void {
            $table->variablePrimaryKey($primaryKeyType);
            $table->string('action');
            $table->string('settings_class');
            $table->string('settings_namespace');
            $table->string('property')->nullable();
            $table->variableMorphs('owner', $ownerMorphType);
            $table->variableMorphs('boundary', $boundaryMorphType, nullable: true);
            $table->variableMorphs('subject', $subjectMorphType, nullable: true);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
        });

        Schema::create(config('settings.table_names.migrations', 'settings_migrations'), function (Blueprint $table) use ($primaryKeyType): void {
            $table->variablePrimaryKey($primaryKeyType);
            $table->string('migration')->unique();
            $table->unsignedInteger('batch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('settings.table_names.migrations', 'settings_migrations'));
        Schema::dropIfExists(config('settings.table_names.audits', 'settings_audits'));
        Schema::dropIfExists(config('settings.table_names.values', 'settings_values'));
    }
};
