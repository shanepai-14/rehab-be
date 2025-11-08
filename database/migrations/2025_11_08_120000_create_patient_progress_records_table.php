<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('patient_progress_records', function (Blueprint $table) {
            $table->id();
            $table->date('session_date');
            $table->foreignId('patient_id')->constrained('users');
            $table->string('therapy_type');
            $table->foreignId('attending_therapist_id')->nullable()->constrained('users');
            $table->text('goals_set')->nullable();
            $table->text('activities_done')->nullable();
            $table->text('evaluation_summary')->nullable();
            $table->text('behavior_mood')->nullable();
            $table->text('recommendation')->nullable();
            $table->date('next_appointment_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_progress_records');
    }
};

