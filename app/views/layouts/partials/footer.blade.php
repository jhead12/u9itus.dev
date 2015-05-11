<!-- Footer -->
<footer id="footer">
    <ul class="icons">

        <li><a href="https://twitter.com/dial4dough" class="icon fa-twitter"><span class="label">Twitter</span></a></li>
        <li><a href="http://www.facebook.com/dial4dough" class="icon fa-facebook"><span class="label">Facebook</span></a></li>
        {{--<li><a href="#" class="icon fa-instagram"><span class="label">Instagram</span></a></li>--}}
        {{--<li><a href="#" class="icon fa-github"><span class="label">Github</span></a></li>--}}
        {{--<li><a href="#" class="icon fa-dribbble"><span class="label">Dribbble</span></a></li>--}}
        {{--<li><a href="#" class="icon fa-google-plus"><span class="label">Google+</span></a></li>--}}
        {{----}}
        <li>  </li>
    </ul>
    <ul class="copyright">
        <li>&copy; HeadEnterprise. All rights reserved.</li>
        <li><a href="{{URL::route('privatepolicy')}}">Privacy</a></li>
        <li><a href="{{URL::route('terms')}}" target="_blank">Terms of
                Service</a></li>


      </ul>

    @include('pages.scripts.create')
</footer>