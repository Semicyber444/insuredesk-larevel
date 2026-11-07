<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Ported from the Next.js app's db/migrations/001-004. Column names are kept so the services read the same. */
return new class extends Migration
{
    public function up(): void
    {
        $authors = function (Blueprint $table) {
            $table->foreignUuid('created_by')->nullable()->constrained('users');
            $table->foreignUuid('updated_by')->nullable()->constrained('users');
        };

        Schema::create('insurers', function (Blueprint $table) use ($authors) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('contact_channels')->nullable();
            $table->string('website', 2048)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $authors($table);
        });

        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('brand');
            $table->string('model');
            $table->string('variant')->nullable();
            $table->enum('powertrain', ['ICE', 'HEV', 'PHEV', 'BEV', 'other'])->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) use ($authors) {
            $table->uuid('id')->primary();
            $table->foreignUuid('insurer_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->enum('insurance_type', ['ชั้น 1', 'ชั้น 2+', 'ชั้น 3+', 'ชั้น 2', 'ชั้น 3']);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $authors($table);
        });

        Schema::create('plan_versions', function (Blueprint $table) use ($authors) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained()->cascadeOnDelete();
            $table->integer('version_no');
            $table->enum('workflow_status', ['draft', 'in_review', 'published', 'archived'])->default('draft');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->foreignUuid('verified_by')->nullable()->constrained('users');
            $table->date('review_due_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $authors($table);
            $table->unique(['plan_id', 'version_no']);
            $table->index(['workflow_status', 'effective_from', 'effective_to']);
        });

        Schema::create('eligibility_rules', function (Blueprint $table) use ($authors) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_version_id')->constrained()->cascadeOnDelete();
            $table->enum('scope_type', ['specific_model', 'all_models', 'unknown'])->default('specific_model');
            $table->foreignUuid('vehicle_model_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('min_age')->nullable();
            $table->integer('max_age')->nullable();
            $table->enum('age_basis', ['registration_year', 'manufacture_year'])->nullable();
            $table->enum('usage_type', ['private', 'commercial', 'all'])->nullable();
            $table->enum('registration_zone', ['all', 'bangkok_only', 'bangkok_metro', 'upcountry_only'])->nullable()->default('all');
            $table->boolean('inspection_required')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $authors($table);
        });

        Schema::create('price_options', function (Blueprint $table) use ($authors) {
            $table->uuid('id')->primary();
            $table->foreignUuid('eligibility_rule_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->integer('sort_order')->default(0);
            $table->enum('repair_type', ['dealer', 'garage']);
            $table->enum('price_type', ['starting', 'indicative', 'fixed_for_conditions', 'unknown'])->default('fixed_for_conditions');
            $table->decimal('net_premium', 12, 2)->nullable();
            $table->decimal('tax_stamp', 12, 2)->nullable();
            $table->decimal('premium_total', 12, 2)->nullable()->index();
            $table->enum('compulsory_included', ['included', 'excluded', 'unspecified'])->default('unspecified');
            $table->decimal('sum_insured_min', 12, 2)->nullable();
            $table->decimal('sum_insured_max', 12, 2)->nullable();
            $table->decimal('deductible_amount', 12, 2)->default(0);
            $table->text('deductible_terms')->nullable();
            $table->text('driver_conditions')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $authors($table);
            $table->index(['sum_insured_min', 'sum_insured_max']);
        });

        Schema::create('coverage_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('category');
            $table->integer('sort_order')->default(0);
        });

        Schema::create('version_coverages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_version_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('coverage_type_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['covered', 'not_covered', 'optional', 'unknown'])->default('unknown');
            $table->decimal('limit_amount', 14, 2)->nullable();
            $table->string('limit_basis')->nullable();
            $table->decimal('deductible_amount', 12, 2)->nullable();
            $table->text('terms')->nullable();
            $table->text('exclusions')->nullable();
            $table->timestamps();
            $table->unique(['plan_version_id', 'coverage_type_id']);
        });

        Schema::create('source_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_version_id')->constrained()->cascadeOnDelete();
            $table->enum('source_type', ['policy_schedule', 'rate_sheet', 'brochure', 'endorsement', 'manual_note', 'other'])->default('rate_sheet');
            $table->string('storage_path', 1024);
            $table->string('source_url', 2048)->nullable();
            $table->string('title');
            $table->integer('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('page_reference')->nullable();
            $table->date('received_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('action', 64);
            $table->string('entity_type', 64);
            $table->uuid('entity_id');
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at', 3)->nullable()->index();
            $table->index(['entity_type', 'entity_id']);
        });

        // Integrity rules from Section 8.4, kept in the database so no code path can skip them.
        foreach ([
            'plan_versions' => [
                'chk_version_no' => 'version_no > 0',
                'chk_version_dates' => 'effective_to IS NULL OR effective_from IS NULL OR effective_to >= effective_from',
            ],
            'eligibility_rules' => [
                'chk_eligibility_min_age' => 'min_age IS NULL OR min_age >= 0',
                'chk_eligibility_age' => 'min_age IS NULL OR max_age IS NULL OR max_age >= min_age',
            ],
            'price_options' => [
                'chk_price_amounts' => '(net_premium IS NULL OR net_premium >= 0) AND (tax_stamp IS NULL OR tax_stamp >= 0)
                    AND (premium_total IS NULL OR premium_total >= 0) AND (sum_insured_min IS NULL OR sum_insured_min >= 0)
                    AND (sum_insured_max IS NULL OR sum_insured_max >= 0) AND deductible_amount >= 0',
                'chk_sum_insured_range' => 'sum_insured_min IS NULL OR sum_insured_max IS NULL OR sum_insured_max >= sum_insured_min',
                'chk_price_dates' => 'valid_to IS NULL OR valid_from IS NULL OR valid_to >= valid_from',
            ],
            'version_coverages' => [
                'chk_version_coverage_amounts' => '(limit_amount IS NULL OR limit_amount >= 0) AND (deductible_amount IS NULL OR deductible_amount >= 0)',
            ],
        ] as $table => $checks) {
            foreach ($checks as $name => $expr) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expr})");
            }
        }
    }

    public function down(): void
    {
        foreach (['audit_logs', 'source_documents', 'version_coverages', 'coverage_types', 'price_options',
            'eligibility_rules', 'plan_versions', 'plans', 'vehicle_models', 'insurers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
