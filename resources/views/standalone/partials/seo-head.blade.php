{{--
    Shared SEO / favicon head partial.
    Include at the end of every <head> block, after <title>.

    Optional variables (set before @include):
      $seoTitle       — page-specific title (default: app name)
      $seoDescription — meta description
      $seoCanonical   — canonical URL
      $ogType         — og:type (default: website)
      $ogImage        — absolute URL to social share image
      $ogUrl          — og:url (defaults to current URL)
      $twitterCard    — twitter:card type (default: summary)
--}}

{{-- ── Favicons ─────────────────────────────────────────────────────────── --}}
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
<link rel="apple-touch-icon" href="{{ asset('media/apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="theme-color" content="#0f172a">
<meta name="msapplication-TileColor" content="#0f172a">

{{-- ── SEO meta ─────────────────────────────────────────────────────────── --}}
@php
    $seoTitle       = $seoTitle       ?? config('app.name', 'U9itus');
    $seoDescription = $seoDescription ?? 'U9itus — civic transparency and political advertising platform. Research politicians, watch campaign ads, and earn rewards.';
    $seoCanonical   = $seoCanonical   ?? url()->current();
    $ogType         = $ogType         ?? 'website';
    $ogUrl          = $ogUrl          ?? $seoCanonical;
    $ogImage        = $ogImage        ?? asset('media/og-image.png');
    $twitterCard    = $twitterCard    ?? 'summary_large_image';
@endphp

<meta name="description" content="{{ $seoDescription }}">
<link rel="canonical" href="{{ $seoCanonical }}">

{{-- ── Open Graph ───────────────────────────────────────────────────────── --}}
<meta property="og:site_name"   content="{{ config('app.name', 'U9itus') }}">
<meta property="og:type"        content="{{ $ogType }}">
<meta property="og:url"         content="{{ $ogUrl }}">
<meta property="og:title"       content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:image"       content="{{ $ogImage }}">
<meta property="og:image:width"  content="1200">
<meta property="og:image:height" content="630">

{{-- ── Twitter / X Card ─────────────────────────────────────────────────── --}}
<meta name="twitter:card"        content="{{ $twitterCard }}">
<meta name="twitter:title"       content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image"       content="{{ $ogImage }}">
