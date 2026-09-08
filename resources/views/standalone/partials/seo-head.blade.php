{{--
    Shared SEO / favicon head partial.
    Include once after <title>; pages must not also emit their own SEO tags.

    Optional variables (set before @include):
      $seoTitle       — page-specific title (default: app name)
      $seoDescription — meta description
      $seoCanonical   — canonical URL
      $ogType         — og:type (default: website)
      $ogImage        — absolute URL to social share image
      $ogUrl          — legacy canonical URL input; og:url always matches canonical
      $twitterCard    — twitter:card type (default: summary_large_image)
      $seoRobots      — index policy (default: route-aware index/noindex)
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
    $seoTitle       = $seoTitle       ?? $ogTitle ?? config('app.name', 'U9itus');
    $seoDescription = trim(strip_tags($seoDescription ?? $ogDescription ?? '')) ?: 'U9itus — civic transparency and political advertising platform. Research politicians, watch campaign ads, and earn rewards.';
    $seoCanonical   = \App\Support\Seo::canonical($seoCanonical ?? $ogUrl ?? null);
    $seoRobots      = $seoRobots ?? \App\Support\Seo::robots();
    $ogType         = $ogType         ?? 'website';
    $ogImage        = ($ogImage ?? null) ?: asset('images/og-default.png');
    $twitterCard    = $twitterCard    ?? 'summary_large_image';
@endphp

<meta name="description" content="{{ $seoDescription }}">
<link rel="canonical" href="{{ $seoCanonical }}">
<meta name="robots" content="{{ $seoRobots }}">

{{-- ── Open Graph ───────────────────────────────────────────────────────── --}}
<meta property="og:site_name"   content="{{ config('app.name', 'U9itus') }}">
<meta property="og:type"        content="{{ $ogType }}">
<meta property="og:url"         content="{{ $seoCanonical }}">
<meta property="og:title"       content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:image"       content="{{ $ogImage }}">

{{-- ── Twitter / X Card ─────────────────────────────────────────────────── --}}
<meta name="twitter:card"        content="{{ $twitterCard }}">
<meta name="twitter:title"       content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image"       content="{{ $ogImage }}">
