<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing blog posts shown on the public site and managed from the admin.
 * `status` is 'draft' or 'published'; `published_at` is set the first time a
 * post goes live; `views` tallies public reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blog_posts')) {
            Schema::create('blog_posts', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('excerpt')->nullable();
                $table->longText('body')->nullable();
                $table->string('cover_image')->nullable();
                $table->string('author')->nullable();
                $table->string('status')->default('draft'); // draft | published
                $table->timestamp('published_at')->nullable();
                $table->integer('views')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
