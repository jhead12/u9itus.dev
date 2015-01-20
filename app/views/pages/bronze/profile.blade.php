@extends('layout.bnav')

@section('content')

 <p>{{ e($user->username)}} ({{ e($user->email) }})</p>

<!--Get Info about USER from Pap ==All Data.-->
@stop