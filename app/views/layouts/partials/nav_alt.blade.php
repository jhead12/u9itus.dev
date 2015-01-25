<!-- Navbar -->
<div class="navbar navbar-inverse navbar-fixed-top">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-ex1-collapse">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="{{ url('/') }}">{{ Config::get('site.name') }}</a>
        </div>
        <div class="collapse navbar-collapse navbar-ex1-collapse">
            <ul class="nav navbar-nav">
                <li {{ (Request::is('/') ? ' class="active"' : '') }}><a href="{{{ URL::to('') }}}">Dial4dough</a></li>
            </ul>

            <ul class="nav navbar-nav pull-right">
                @if (Auth::check())
                @if (Auth::user()->hasRole('admin'))
                <li><a href="{{{ URL::to('admin') }}}">Admin Panel</a></li>
                @endif
                <li><a href="{{{ URL::to('users') }}}">Logged in as {{{ Auth::user()->username }}}</a></li>


                <li><a href="{{{ URL::to('users/logout') }}}">Logout</a></li>
                @else
                <li><a href="https://dialer.dial4dough.com/affiliates/signup.php#ContactUs"><i class="fa fa-phone-square"></i>347-230-8438</a></li>
                <li><a href="{{URL::to('/pricing')}}">Pricing</a></li>
                <li><a href="{{URL::to('/about')}}">About</a></li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">Login <span class="caret"></span></a>
                    <ul class="dropdown-menu" role="menu">

                        <li><a href="{{URL::to('users/login')}}">Login</a></li>
                        <li class="divider"></li>
                        <li><a href="https://dialer.dial4dough.com/affiliates/login.php#login">Member Login</a></li>
                    </ul>
                </li>


                @endif
            </ul>
            <!-- ./ nav-collapse -->
        </div>
    </div>
</div>
<!-- ./ navbar -->