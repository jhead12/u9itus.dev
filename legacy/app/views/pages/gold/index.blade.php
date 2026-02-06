@extends('layout.main')

@section('content')

@if( Auth::user()->info == 1)

<a href="#" data-reveal-id="myModal"></a>

<div id="myModal" class="reveal-modal" data-reveal>
 @include('account.gold.success')
</div>

@else

@endif


@stop

@section('scripts')



<script>
$('a[data-reveal-id="myModal"]').trigger('click');

</script>

@stop
