@extends('layouts.voter')

@section('title', 'Interactive Map')

@push('styles')
<style>
    /*
     * Pin the iframe to the exact usable viewport area.
     * Header is h-16 (64px). Sidebar is w-64 (256px) on lg+ and hidden on mobile.
     * Fixed positioning takes it out of the normal scroll flow so the
     * layout's overflow-y-auto on <main> does not clip or scroll the map.
     */
    #voter-map-iframe {
        position: fixed;
        top: 64px;    /* below sticky header */
        left: 0;
        right: 0;
        bottom: 0;
        width: 100%;
        height: calc(100dvh - 64px);
        border: none;
        display: block;
        z-index: 10;  /* below header (z-30) and mobile sidebar overlay (z-20) */
    }

    /* On desktop the sidebar is 256px wide — shift iframe to the right of it */
    @media (min-width: 1024px) {
        #voter-map-iframe {
            left: 256px;
            width: calc(100% - 256px);
        }
    }

    /* Give the placeholder div enough height so the page footer elements
       (payout button, trust score) are not obscured by the fixed iframe */
    #voter-map-placeholder {
        height: calc(100dvh - 64px);
        pointer-events: none;
    }
</style>
@endpush

@section('content')
{{-- Placeholder keeps layout flow intact while iframe is fixed-positioned --}}
<div id="voter-map-placeholder" aria-hidden="true"></div>

<iframe
    id="voter-map-iframe"
    src="{{ url('/map') }}"
    title="U.S. Interactive Map"
    allowfullscreen
    loading="eager"
></iframe>
@endsection
