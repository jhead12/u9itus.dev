@extends('standalone.layouts.dashboard')

@section('title', 'Interactive Map')
@section('page-title', 'Interactive Map')

@push('styles')
<style>
    /* Remove padding so the iframe fills the content area edge-to-edge */
    #politician-map-wrapper { display: flex; flex-direction: column; height: calc(100vh - 4rem); }
    #politician-map-iframe  { flex: 1; width: 100%; border: none; display: block; }
</style>
@endpush

@section('content')
<div id="politician-map-wrapper">
    <iframe
        id="politician-map-iframe"
        src="{{ url('/map') }}"
        title="U.S. Interactive Map"
        allowfullscreen
        loading="eager"
    ></iframe>
</div>
@endsection
