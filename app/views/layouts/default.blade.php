<!docType html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="google-site-verification" content="OzhSWDz72APDh5zTIpCvWki0bnXbTUh3SroT0_2UQ-s" />
    <meta property="og:description"
          content="Small businesses are humans, and people buy from humans. Dial4dough gives that person to make money online to support themselves as well as make a profit while building their company. Any one can make money online with dial4dough.com" />
    <meta property="og:determiner" content="the" />
    <meta property="og:locale" content="en_GB" />
    <meta name="description" content="earn money online by viewing ads community ads brick and mortar ads making an side income retirement aid ">
    <meta property="og:locale:alternate" content="fr_FR" />
    <meta property="og:locale:alternate" content="es_ES" />
    <meta property="og:site_name" content="dial4dough" />
    <meta property="article:section" content="Money">
    <title>The Dial4dough Community income system</title>
    <link rel="stylesheet" href="{{asset('css/style.css')}}">


    {{--<link rel="stylesheet" type="text/css" href="{{asset('css/flashblock.css')}}" />--}}



	<!-- special IE-only canvas fix -->
	<!--[if IE]><script type="text/javascript" src="js/excanvas.js"></script><![endif]-->
</head>

<body class="landing" >
<script>(function(d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) return;
        js = d.createElement(s); js.id = id;
        js.src = "//connect.facebook.net/en_US/sdk.js#xfbml=1&version=v2.3&appId=296623837209769";
        fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));</script>


<!-- Google Tag Manager -->


<noscript><iframe src="//www.googletagmanager.com/ns.html?id=GTM-NL8VZM"
                  height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            '//www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-NL8VZM');</script>
<!-- End Google Tag Manager -->




<div id="fb-root"></div>


	@include('layouts.partials.nav')
	@include('notifications')


	@yield('content')


<div id="ouibounce-modal">
    <div class="underlay"></div>
    <div class="modal">
        <div class="modal-title">
            <h3>Trying to sell products using video ads?</h3>
        </div>

        <div class="modal-body">
            <p>Getting Little or NO results?.</p>
            <h2>We have good news!</h2>
            <p style="margin: 0">Get started with a new concept!</p>
            <p style="font-size: large;color: red;margin: 0">FREE No Obligation!</p>
            <p style="font-size: larger;margin: 0">Sell Products! Make Money!</p>
            <p style="margin:0">(No credit card required) </p>
            <a class="button button-primary" href="http://adddough-com.3dcartstores.com/Addials-Free-Starter-Kit--limited-offer_p_26.html" target="_blank">Start here!</a>

            <br>
        </div>

        <div class="modal-footer">
            <p>no thanks</p>
        </div>
    </div>
</div>

@include('layouts.partials.footer')


<script src="{{asset('js/ouibounce.min.js')}}"></script>

<script>

    var _ouibounce = ouibounce(document.getElementById('ouibounce-modal'), {
        aggressive: true,
        timer: 0,
        callback: function() { console.log('ouibounce fired!'); }
    });
    $('body').on('click', function() {
        $('#ouibounce-modal').hide();
    });
    $('#ouibounce-modal .modal-footer').on('click', function() {
        $('#ouibounce-modal').hide();
    });
    $('#ouibounce-modal .modal').on('click', function(e) {
        e.stopPropagation();
    });
</script>

<!-- Start of StatCounter Code for Default Guide -->
<script type="text/javascript">
    var sc_project=10428515;
    var sc_invisible=1;
    var sc_security="98419ed9";
    var scJsHost = (("https:" == document.location.protocol) ?
            "https://secure." : "http://www.");
    document.write("<sc"+"ript type='text/javascript' src='" +
    scJsHost+
    "statcounter.com/counter/counter.js'></"+"script>");
</script>


<noscript><div class="statcounter"><a title="web analytics"
                                      href="http://statcounter.com/" target="_blank"><img
                    class="statcounter"
                    src="http://c.statcounter.com/10428515/0/98419ed9/1/"
                    alt="web analytics"></a></div></noscript>
<!-- End of StatCounter Code for Default Guide -->


<script>
    var cb = function() {
        var l = document.createElement('link'); l.rel = 'stylesheet';
        l.href = '//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css';
        var h = document.getElementsByTagName('head')[0]; h.parentNode.insertBefore(l, h);
    };
    var raf = requestAnimationFrame || mozRequestAnimationFrame ||
            webkitRequestAnimationFrame || msRequestAnimationFrame;
    if (raf) raf(cb);
    else window.addEventListener('load', cb);
</script>




<a href="http://www.sonicrun.com"></a>

</body>
</html>