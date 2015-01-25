<nav class="navbar navbar-inverse navbar-fixed-top">
    <div class="container">


        <!-- Brand and toggle get grouped for better mobile display -->
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="{{URL::to('/')}}">Dial4dough</a>
        </div>
        <ul class="nav navbar-nav navbar-right">
            <li><a href="https://dialer.dial4dough.com/affiliates/signup.php#ContactUs"><i class="fa fa-phone-square"></i>347-230-8438</a></li>
            <li><a href="{{URL::to('about')}}">About</a></li>
            <li><a href="{{URL::to('polidream')}}">Politicians Dream</a></li>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">Login <span class="caret"></span></a>
                <ul class="dropdown-menu" role="menu">


                    <li><a href="https://dialer.dial4dough.com/affiliates/signup.php#SignupForm">Registration</a></li>
                    <li class="divider"></li>
                    <li><a href="https://dialer.dial4dough.com/affiliates/login.php#login">Member Login</a></li>
                </ul>
            </li>
        </ul>
</nav>