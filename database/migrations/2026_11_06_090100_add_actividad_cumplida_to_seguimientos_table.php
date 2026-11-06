<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimientos', function (Blueprint $table) {
            $table->boolean('actividad_cumplida')->default(false)->after('inventario');
            $table->timestamp('actividad_cumplida_en')->nullable()->after('actividad_cumplida');
            $table->foreignId('actividad_cumplida_por')->nullable()->after('actividad_cumplida_en')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('seguimientos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actividad_cumplida_por');
            $table->dropColumn(['actividad_cumplida', 'actividad_cumplida_en']);
        });
    }
};
