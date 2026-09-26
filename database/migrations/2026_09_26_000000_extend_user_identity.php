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
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique();
            $table->string('first_name')->default('');
            $table->string('last_name')->default('');
            $table->string('phone')->nullable()->index();
            $table->string('status', 40)->default('active');
            $table->timestamp('last_login_at')->nullable();
        });
        DB::table('users')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $parts = explode(' ', $user->name, 2);
                DB::table('users')->where('id', $user->id)->update([
                    'uuid' => (string) Str::uuid(), 'first_name' => $parts[0], 'last_name' => $parts[1] ?? '',
                ]);
            }
        });
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropIndex(['phone']);
            $table->dropColumn(['uuid', 'first_name', 'last_name', 'phone', 'status', 'last_login_at']);
        });
    }
};
