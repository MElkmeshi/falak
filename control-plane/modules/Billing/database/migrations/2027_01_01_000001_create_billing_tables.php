<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->decimal('price_lyd', 10, 2);
            $table->decimal('price_egp', 10, 2);
            $table->unsignedSmallInteger('period_days')->default(30);
            $table->decimal('cpus', 4, 2);
            $table->unsignedInteger('memory_mb');
            $table->unsignedInteger('disk_gb');
            $table->unsignedSmallInteger('max_apps');
            $table->unsignedSmallInteger('max_databases');
            $table->boolean('featured')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('billing_subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('organization_id')->index();
            $table->ulid('plan_id');
            $table->string('status', 16);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('organization_id')->index();
            $table->ulid('user_id')->nullable();
            $table->ulid('plan_id');
            $table->ulid('subscription_id')->nullable();
            $table->string('provider', 32);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->string('status', 24);
            $table->string('payer', 120)->nullable();
            $table->string('reference', 190)->nullable();
            $table->json('meta')->nullable();
            $table->string('failure', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->ulid('reviewed_by')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_spaces', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('organization_id')->unique();
            $table->ulid('server_id')->nullable();
            $table->string('instance', 64)->unique();
            $table->string('status', 16);
            $table->string('ipv4', 45)->nullable();
            $table->decimal('cpus', 4, 2);
            $table->unsignedInteger('memory_mb');
            $table->unsignedInteger('disk_gb');
            $table->string('error', 1000)->nullable();
            $table->timestamps();
        });

        $now = now();
        $plans = [
            ['starter', 'Starter', 'For course projects and a first app.', 25, 250, 0.5, 1024, 8, 3, 1, false, 10],
            ['pro', 'Pro', 'Room for n8n, a database and a few apps.', 50, 500, 1, 2048, 15, 8, 3, true, 20],
            ['business', 'Business', 'Production workloads with headroom.', 100, 1000, 2, 4096, 30, 20, 6, false, 30],
        ];

        foreach ($plans as [$slug, $name, $description, $lyd, $egp, $cpus, $memory, $disk, $apps, $databases, $featured, $sort]) {
            DB::table('billing_plans')->insert([
                'id' => strtolower((string) Str::ulid()),
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'price_lyd' => $lyd,
                'price_egp' => $egp,
                'period_days' => 30,
                'cpus' => $cpus,
                'memory_mb' => $memory,
                'disk_gb' => $disk,
                'max_apps' => $apps,
                'max_databases' => $databases,
                'featured' => $featured,
                'active' => true,
                'sort' => $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_spaces');
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('billing_subscriptions');
        Schema::dropIfExists('billing_plans');
    }
};
