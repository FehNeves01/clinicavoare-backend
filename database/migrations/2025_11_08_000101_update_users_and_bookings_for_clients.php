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
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'phone')) {
                $table->dropColumn('phone');
            }
            if (Schema::hasColumn('users', 'birth_date')) {
                $table->dropColumn('birth_date');
            }
            if (Schema::hasColumn('users', 'credit_balance')) {
                $table->dropColumn('credit_balance');
            }
            if (Schema::hasColumn('users', 'credit_expires_at')) {
                $table->dropColumn('credit_expires_at');
            }
        });

        if (Schema::hasColumn('bookings', 'user_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropIndex(['user_id']);
                $table->dropColumn('user_id');
            });
        }

        if (!Schema::hasColumn('bookings', 'client_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->foreignId('client_id')
                    ->after('id')
                    ->constrained()
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'client_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropForeign(['client_id']);
                $table->dropColumn('client_id');
            });
        }

        if (!Schema::hasColumn('bookings', 'user_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->foreignId('user_id')
                    ->after('id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->index('user_id');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'credit_balance')) {
                $table->decimal('credit_balance', 8, 2)->default(0)->after('birth_date');
            }
            if (!Schema::hasColumn('users', 'credit_expires_at')) {
                $table->date('credit_expires_at')->nullable()->after('credit_balance');
            }
        });
    }
};

