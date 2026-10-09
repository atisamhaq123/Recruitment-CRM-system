<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('recruiter');
            $table->foreignId('team_id')->nullable()->constrained('teams')->restrictOnDelete();
            $table->boolean('active')->default(true);
        });
        foreach (config('recruitment.entities') as $entity => $definition) {
            Schema::create($definition['table'], function (Blueprint $table) use ($entity, $definition) {
                $table->id();
                $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
                $table->string('name');
                $table->unsignedInteger('version')->default(1);
                foreach ($definition['fields'] as $field => $metadata) {
                    if ($field === 'name') {
                        continue;
                    }
                    if ($metadata['type'] === 'reference') {
                        $table->foreignId($field)->nullable(! str_starts_with($metadata['rules'], 'required'))->constrained(config("recruitment.entities.{$metadata['entity']}.table"))->restrictOnDelete();
                    } elseif ($metadata['type'] === 'textarea') {
                        $table->text($field)->nullable();
                    } elseif ($metadata['type'] === 'number') {
                        $table->decimal($field, 12, 2)->nullable();
                    } elseif ($metadata['type'] === 'date') {
                        $table->date($field)->nullable();
                    } elseif ($metadata['type'] === 'datetime-local') {
                        $table->dateTime($field);
                    } else {
                        $table->string($field)->nullable();
                    }
                }
                $table->timestamps();
                $table->index(['owner_id', 'status']);
                if ($entity === 'applications') {
                    $table->unique(['candidate_id', 'job_id']);
                }
                if ($entity === 'placements') {
                    $table->unique('application_id');
                }
            });
        }
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('entity');
            $table->unsignedBigInteger('record_id');
            $table->string('action');
            $table->text('body');
            $table->timestamps();
            $table->index(['entity', 'record_id']);
        });
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('entity');
            $table->unsignedBigInteger('record_id');
            $table->foreignId('user_id')->constrained('users');
            $table->string('name');
            $table->string('path');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->index(['entity', 'record_id']);
        });
        Schema::create('agency_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('TalentFlow');
            $table->string('email');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['agency_settings', 'documents', 'activities', 'placements', 'tasks', 'interviews', 'applications', 'vacancies', 'candidates', 'clients'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn(['role', 'active']);
        });
        Schema::dropIfExists('teams');
    }
};
