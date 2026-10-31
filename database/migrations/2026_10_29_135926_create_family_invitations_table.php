
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_invitations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('family_id')
                ->constrained('families')
                ->cascadeOnDelete();

            $table->foreignId('invited_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('email');

            $table->enum('role', ['parent', 'child']);

            $table->string('token_hash', 64)->unique();

            $table->timestamp('expires_at');

            $table->timestamp('accepted_at')->nullable();

            $table->timestamps();

            $table->index(['family_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_invitations');
    }
};
