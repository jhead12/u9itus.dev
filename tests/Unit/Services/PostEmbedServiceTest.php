<?php

use App\Models\Post;
use App\Services\PostEmbedService;
use Illuminate\Support\Facades\Http;

it('builds a direct YouTube embed from a watch url with no network call', function (): void {
    Http::fake();

    $html = (new PostEmbedService)->resolve('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    expect($html)->toContain('src="https://www.youtube.com/embed/dQw4w9WgXcQ"');
    Http::assertNothingSent();
});

it('builds a direct YouTube embed from a youtu.be short url', function (): void {
    $html = (new PostEmbedService)->resolve('https://youtu.be/dQw4w9WgXcQ');

    expect($html)->toContain('src="https://www.youtube.com/embed/dQw4w9WgXcQ"');
});

it('builds a direct Instagram embed', function (): void {
    $html = (new PostEmbedService)->resolve('https://www.instagram.com/reel/CxL8g5nMx0y/');

    expect($html)->toContain('src="https://www.instagram.com/reel/CxL8g5nMx0y/embed"');
});

it('builds a direct SoundCloud embed carrying the original url', function (): void {
    $html = (new PostEmbedService)->resolve('https://soundcloud.com/forss/flickermood');

    expect($html)->toContain('w.soundcloud.com/player')
        ->and($html)->toContain(urlencode('https://soundcloud.com/forss/flickermood'));
});

it('calls the X oEmbed API and keeps only the blockquote, dropping the script tag', function (): void {
    Http::fake([
        'publish.twitter.com/oembed*' => Http::response([
            'html' => '<blockquote class="twitter-tweet"><p lang="en" dir="ltr">Hello</p>&mdash; jack (@jack) <a href="https://x.com/jack/status/20">March 21, 2006</a></blockquote>'
                .'<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>',
        ], 200),
    ]);

    $html = (new PostEmbedService)->resolve('https://x.com/jack/status/20');

    expect($html)->toContain('twitter-tweet')
        ->and($html)->not->toContain('<script');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'publish.twitter.com/oembed')
        && $request['omit_script'] === 'true');
});

it('calls the TikTok oEmbed API, unwraps <section>, and drops the script tag', function (): void {
    Http::fake([
        'www.tiktok.com/oembed*' => Http::response([
            'html' => '<blockquote class="tiktok-embed" cite="https://www.tiktok.com/@scout2015/video/123">'
                .'<section><a href="https://www.tiktok.com/@scout2015">@scout2015</a><p>hi</p></section>'
                .'</blockquote><script async src="https://www.tiktok.com/embed.js"></script>',
        ], 200),
    ]);

    $html = (new PostEmbedService)->resolve('https://www.tiktok.com/@scout2015/video/123');

    expect($html)->toContain('tiktok-embed')
        ->and($html)->not->toContain('<section>')
        ->and($html)->not->toContain('<script');
});

it('returns null for an unsupported platform without making any request', function (): void {
    Http::fake();

    $html = (new PostEmbedService)->resolve('https://example.com/not-a-supported-platform');

    expect($html)->toBeNull();
    Http::assertNothingSent();
});

it('returns null when the oEmbed API call fails', function (): void {
    Http::fake([
        'publish.twitter.com/oembed*' => Http::response(null, 404),
    ]);

    $html = (new PostEmbedService)->resolve('https://x.com/jack/status/20');

    expect($html)->toBeNull();
});

it('rejects a spoofed platform-lookalike domain', function (): void {
    $html = (new PostEmbedService)->resolve('https://www.youtube.com.evil.example/watch?v=dQw4w9WgXcQ');

    expect($html)->toBeNull();
});

it('survives the post sanitizer intact — a real end-to-end check, not just isolated regex matching', function (): void {
    $html = (new PostEmbedService)->resolve('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    $post = new Post;
    $post->body = $html;

    expect($post->body)->toContain('src="https://www.youtube.com/embed/dQw4w9WgXcQ"')
        ->and($post->body)->toContain('<iframe');
});

it('sanitizer strips an iframe pointed at an arbitrary domain even if somehow injected directly', function (): void {
    $post = new Post;
    $post->body = '<iframe src="https://evil.example/phish"></iframe><p>text</p>';

    // The src attribute is what gets validated and stripped; the now-empty
    // <iframe></iframe> shell it leaves behind is separately cleaned up by
    // sanitizeBody() (see Post.php) rather than shipped as dead markup.
    expect($post->body)->not->toContain('evil.example')
        ->and($post->body)->not->toContain('src=')
        ->and($post->body)->not->toContain('<iframe')
        ->and($post->body)->toContain('<p>text</p>');
});
