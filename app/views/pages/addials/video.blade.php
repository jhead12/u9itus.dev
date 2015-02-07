<video id='video'
       controls preload='none'
       mediagroup='myVideoGroup'
       poster="http://media.w3.org/2010/05/sintel/poster.png">
    <!--
          <source id='mp4'
            src="../sintel/trailer.mp4"
            type='video/mp4'>
          <source id='webm'
            src="../sintel/trailer.webm"
            type='video/webm'>
    -->
    <source id='mp4'
            src="http://media.w3.org/2010/05/sintel/trailer.mp4"
            type='video/mp4'>
    <source id='webm'
            src="http://media.w3.org/2010/05/sintel/trailer.webm"
            type='video/webm'>
    <source id='ogv'
            src="http://media.w3.org/2010/05/sintel/trailer.ogv"
            type='video/ogg'>

    <p>Your user agent does not support the HTML5 Video element.</p>
</video>


<script type="text/javascript" src="{{asset('js/swfobject.js')}}"></script>
<div id="ytapiplayer">
    You need Flash player 8+ and JavaScript enabled to view this video.
</div>

<script type="text/javascript">
    var params = { allowScriptAccess: "always" };
    var atts = { id: "myytplayer" };
    swfobject.embedSWF("http://www.youtube.com/v/VIDEO_ID?enablejsapi=1&playerapiid=ytplayer&version=3",
            "ytapiplayer", "425", "356", "8", null, null, params, atts);
</script>


