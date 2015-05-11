
<!-- Header -->
<header id="header" class="alt">
    <h1><a class="navbar-brand" href="{{URL::to('/')}}">Dial4dough</a></h1>




    <nav id="nav">
        <ul>
            <li><form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
                    <input type="hidden" name="cmd" value="_s-xclick">
                    <input type="hidden" name="hosted_button_id" value="UVDM2WC56KUXA">
                    <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
                    <img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
                </form></li>
            <li><a href="{{URL::to('/')}}">Home</a></li>


            <li>
                <a href="" class="icon fa-angle-down">Philosophy</a>
                <ul>
                    <li><a href="/polidream">Politicians Dream</a></li>

                    <li><a href="{{URL::to('about')}}">About</a></li>

                    <li><a class="fb-share-button" data-href="https://www.dial4dough.com" data-layout="button"></a></li>
                    {{--<li>--}}
                        {{--<a href="">Submenu</a>--}}
                        {{--<ul>--}}
                            {{--<li><a href="#">Option One</a></li>--}}
                            {{--<li><a href="#">Option Two</a></li>--}}
                            {{--<li><a href="#">Option Three</a></li>--}}
                            {{--<li><a href="#">Option Four</a></li>--}}
                        {{--</ul>--}}
                    {{--</li>--}}
                </ul>
            </li>
            <li><a href="https://dialer.dial4dough.com/affiliates/login.php">Member Login</a></li>
            <li><a href="https://dialer.dial4dough.com/affiliates/signup.php#SignupForm" class="button">Sign Up</a></li>
        </ul>
    </nav>
</header>