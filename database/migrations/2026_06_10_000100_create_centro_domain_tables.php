<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('full_name')->nullable();
            $table->text('avatar_url')->nullable();
            $table->string('job_title')->nullable();
            $table->string('phone')->nullable();
            $table->text('bio')->nullable();
            $table->string('completion_effect')->default('none');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('role')->default('guest');
            $table->unique(['user_id', 'role']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('color')->default('#2563eb');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('legal_form')->nullable();
            $table->string('business_sector')->nullable();
            $table->string('source')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('tax_code')->nullable();
            $table->string('vat_treatment')->nullable();
            $table->string('country')->nullable();
            $table->string('address')->nullable();
            $table->string('street')->nullable();
            $table->string('street_number')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('email')->nullable();
            $table->string('pec')->nullable();
            $table->string('sdi_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('iban')->nullable();
            $table->string('bic_swift')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->nullable();
            $table->boolean('is_pa')->default(false);
            $table->longText('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('client_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('role')->nullable();
            $table->longText('notes')->nullable();
            $table->timestamps();
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });

        Schema::create('client_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('service_id');
            $table->unique(['client_id', 'service_id']);
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->longText('description')->nullable();
            $table->uuid('client_id')->nullable();
            $table->string('status')->default('active');
            $table->string('color')->default('#2563eb');
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('project_followers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('user_id');
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->uuid('project_id')->nullable();
            $table->uuid('client_id')->nullable();
            $table->uuid('service_id')->nullable();
            $table->uuid('parent_task_id')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->time('due_time')->nullable();
            $table->string('location')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('todo');
            $table->string('task_type')->default('task');
            $table->boolean('recurring_enabled')->default(false);
            $table->string('recurring_mode')->nullable();
            $table->unsignedInteger('recurring_interval_value')->nullable();
            $table->string('recurring_interval_unit')->nullable();
            $table->unsignedTinyInteger('recurring_weekday')->nullable();
            $table->unsignedTinyInteger('recurring_month_day')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
            $table->foreign('parent_task_id')->references('id')->on('tasks')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        foreach (['task_assignees', 'task_followers'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('task_id');
                $table->uuid('user_id');
                $table->timestamps();
                $table->unique(['task_id', 'user_id']);
                $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        Schema::create('task_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id');
            $table->uuid('user_id');
            $table->longText('content');
            $table->timestamps();
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('task_activity', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id');
            $table->uuid('user_id')->nullable();
            $table->string('action');
            $table->string('field')->nullable();
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->timestamps();
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('actor_id')->nullable();
            $table->uuid('task_id')->nullable();
            $table->string('type');
            $table->longText('message');
            $table->boolean('read')->default(false);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('task_id')->references('id')->on('tasks')->nullOnDelete();
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('widget_type');
            $table->unsignedInteger('position')->default(0);
            $table->string('size')->default('medium');
            $table->unsignedTinyInteger('col_span')->default(1);
            $table->boolean('visible')->default(true);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('user_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->json('content');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        foreach ([
            'user_notes', 'dashboard_widgets', 'app_settings', 'notifications', 'task_activity',
            'task_comments', 'task_followers', 'task_assignees', 'tasks', 'project_followers',
            'projects', 'client_services', 'client_contacts', 'clients', 'services',
            'user_roles', 'profiles',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
