<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /* The status column was seeded with Spanish values; the enum backing them
       is English now, so rows written before the rename must follow. */
    private const RENAMES = [
        'pendiente'  => 'pending',
        'en_curso'   => 'in_progress',
        'conseguido' => 'obtained',
        'descartado' => 'dropped',
    ];

    public function up(): void
    {
        Schema::table('book_requests', function(Blueprint $table): void {
            $table->string('status')->default('pending')->change();
        });

        foreach (self::RENAMES as $spanish => $english) {
            DB::table('book_requests')->where('status', $spanish)->update(['status' => $english]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $spanish => $english) {
            DB::table('book_requests')->where('status', $english)->update(['status' => $spanish]);
        }

        Schema::table('book_requests', function(Blueprint $table): void {
            $table->string('status')->default('pendiente')->change();
        });
    }
};
