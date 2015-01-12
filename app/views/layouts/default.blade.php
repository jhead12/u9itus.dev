<!docType html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<title>Dial4dough</title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css">
<link href="//maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" rel="stylesheet">
<link rel="stylesheet" href="{{asset('css/main.css')}}">
</head>
<body>
	@include('layouts.partials.nav')
	
	<div class="container">
	   @yield('content')

	   
	</div>

<script src="{{asset('js/jquery.min.js')}}"></script>
<script src="{{asset('js/script.js')}}"></script>
</body>
</html>
