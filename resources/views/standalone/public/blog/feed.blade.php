{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ config('app.name', 'U9itus') }} Blog</title>
        <link>{{ route('blog.index') }}</link>
        <description>Latest civic and political updates, community stories, and campaign news from U9itus.</description>
        <language>en-us</language>
        <atom:link href="{{ route('blog.feed') }}" rel="self" type="application/rss+xml" />
        @foreach($posts as $post)
        <item>
            <title>{{ $post->title }}</title>
            <link>{{ route('blog.show', $post) }}</link>
            <guid>{{ route('blog.show', $post) }}</guid>
            <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
            <description><![CDATA[{{ $post->excerpt ?? Str::limit(strip_tags($post->body ?? ''), 250) }}]]></description>
        </item>
        @endforeach
    </channel>
</rss>
