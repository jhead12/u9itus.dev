@extends('layouts.default')


<div class="container">



    <h1>Thank You</h1>

    <ul class="pager">
        <li type="btn btn-primary " class="previous"><a href="{{URL::to('dialpad')}}">&larr; back</a></li>
    </ul>

    <p><strong>Thank you for viewing our ads.</strong>  Credit for your viewing efforts will be posted within a few minutes.  You may view your earned credits in your members area at the top of the page on all pages.</p>

    <p>Payments are made on the 15th and 30th of each month through PayPal for all credits paying $1.00 and above.  You will probably make this amount in just a few hours of viewing our advertisements.</p>

    <p>Remember ads are made possible by merchants that purchase addials at <a href="http://www.addials.net">AdDials</a>.  Please tell all of your friends and neighbors about the dial4dough.com program, and our Addials marketing system.</p>

    <p>Earn a 15% commission on all merchants that purchase addials from our addials.net site (see admin for details).</p>

    <p>Thank You for allowing us to serve you.</p>

    <p><strong>Sincerely</strong></p>

    <p>Admin@dial4dough.com</p>





</div>

@include('pages/scripts/create')



<script type="text/javascript">
    document.write(unescape("%3Cscript id=%27pap_x2s6df8d%27 src=%27" + (("https:" == document.location.protocol) ? "https://" : "http://") + "dialer.dial4dough.com/scripts/trackjs.js%27 type=%27text/javascript%27%3E%3C/script%3E"));
</script> <script type="text/javascript">
    PostAffTracker.setAccountId('default1');
    var sale = PostAffTracker.createSale();
    sale.setVisitorId(id.user);
    sale.setTotalCost('1.75');
    sale.setOrderID(id.id);
    sale.setProductID(id.name);

    PostAffTracker.register();
</script>