<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_news_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 500);
            $table->text('summary')->nullable();
            $table->string('source', 120);
            $table->string('category', 80)->default('World');
            $table->text('article_url');
            $table->text('image_url')->nullable();
            $table->char('url_hash', 64)->unique();
            $table->char('title_hash', 64)->unique();
            $table->dateTime('published_at')->index();
            $table->dateTime('synced_at');
            $table->timestamps();

            $table->index(['published_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_news_articles');
    }
};
