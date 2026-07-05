@extends('layouts.voter')

@section('title', 'Interactive Map')

@push('styles')
<style>
    /* Remove padding so the iframe fills the content area edge-to-edge */
    #voter-map-wrapper { display: flex; flex-direction: column; height: calc(100vh - 4rem); }
    #voter-map-iframe  { flex: 1; width: 100%; border: none; display: block; }
</style>
@endpush

@section('content')
<div id="voter-map-wrapper">
    <iframe
        id="voter-map-iframe"
        src="{{ url('/map') }}"
        title="U.S. Interactive Map"
        allowfullscreen
        loading="eager"
    ></iframe>
</div>
@endsection
