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
        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();

            // Raw CSV fields
            $table->string('FirstName')->nullable();
            $table->string('LastName')->nullable();
            $table->string('email_address')->nullable();
            $table->string('Password')->nullable(); // consider encrypting (see security section)
            $table->string('UnitPath')->nullable();
            $table->string('personalEmail')->nullable();
            $table->string('studentPhone')->nullable();
            $table->string('Title')->nullable();
            $table->string('studentDepartment')->nullable();
            $table->string('DepartmentName')->nullable();
            $table->string('ChangePassNext')->nullable();

            // Processed fields
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();

            $table->string('generated_email')->nullable();
            $table->unsignedInteger('email_generation_attempts')->default(0);

            $table->string('email_status')->default('pending'); // pending|queued|sent|failed
            $table->text('email_error')->nullable();

            $table->timestamps();

            $table->index(['import_id', 'email_status']);
            $table->unique(['import_id', 'generated_email']); // ensures uniqueness within an import
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
