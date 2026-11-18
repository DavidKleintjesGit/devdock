<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('card_archives', function (Blueprint $table): void {
			$table->id();
			$table->string('card')->unique();
			$table->string('project')->nullable();
			$table->string('branch')->nullable();
			$table->string('agent_session_uuid')->nullable();
			$table->timestamp('cleaned_up_at')->nullable();
			$table->timestamp('archived_at')->nullable();
			$table->string('resurface_signature')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('card_archives');
	}
};
