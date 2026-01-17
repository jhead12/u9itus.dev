@extends('layout.main')

@section('content')


<h1>This page is for Platinum Users.</h1>



<a href="#" data-reveal-id="platinum"></a>

<div id="platinum" class="reveal-modal" data-reveal>
 @include('account.platinum.success')
</div>
@stop


@section('scripts')



<script>

$('a[data-reveal-id="platinum"]').trigger('click');



</script>

@stop
