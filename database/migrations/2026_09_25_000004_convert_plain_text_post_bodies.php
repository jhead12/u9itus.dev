<?php

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Re-run existing plain-text post bodies through the Post body mutator so
     * their newlines become <p>/<br> instead of rendering as one run-on block.
     */
    public function up(): void
    {
        DB::table('posts')
            ->whereNotNull('body')
            ->where('body', 'not like', '%<p%')
            ->where('body', 'like', "%\n%")
            ->orderBy('id')
            ->select(['id', 'body'])
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $post = new Post;
                    $post->body = $row->body;
                    $body = $post->getAttributes()['body'] ?? null;

                    if ($body !== $row->body) {
                        DB::table('posts')->where('id', $row->id)->update(['body' => $body]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Irreversible data cleanup; the converted HTML is the intended form.
    }
};
