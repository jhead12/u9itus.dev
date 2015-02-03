
<!-- soundManager.useFlashBlock: related CSS -->
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>


 <script src="//code.jquery.com/jquery-migrate-1.2.1.min.js"></script>
<script src="{{asset('js/foundation.min.js')}}"></script>

 <script src="{{asset('js/script.js')}}"></script>
<script src="{{asset('js/berniecode-animator.js')}}"></script>
 <script src="{{asset('js/audio.js')}}"></script>

<script src="{{asset('js/360player.js')}}"></script>
<script src="{{asset('js/custom.js')}}"></script>
<script src="{{asset('js/type.js')}}"></script>
{{--<script src="{{asset('js/app.js')}}"></script>--}}



<script type="text/javascript">

    $(document).foundation();
</script>



<script type="text/javascript">
    $('#close').click(function(){
        location.reload();

    });
</script>


 <script>
    $(document).ready(function(){
        var token = $('input[name="_hidden"]');
        var form =  $( "#create" );


       form.click(function(event){
           //event.preventDefault();
           form.validate({

               rules: {
                   firstName: "required",
                   lastName: "required",
                   username: "required",
                   sex:"required",
                   country2:"required",
                   telephone: {
                       required:true
                   },
                   email: {
                       headers: {'X-CSRF-Token' : token},
                       required: true,
                       email: true,
                       type:'post',
                       //url:"{{URL::to('/account/create-post')}}",
                       success:function(){console.log('Valid')},
                       data: {
                           username: function() {
                               return $( "#username" ).val();
                           }
                       }

                   },
                   password: "required",
                   checkbox:"required",
                   password_again: {
                       equalTo: "#password"
                   }
               },
               messages:{
                   firstName: "Please enter your first name",
                   lastName: "Please enter your last name",
                   username: "A username is required",
                   telephone:"Please enter a valid Telephone number",
                   email: "Please enter a valid email address",
                   sex:"Please choose a gender"


               }
           });




       });


    });
    </script>



     <script>

         $('#callback').submit(function(event){
             event.preventDefault();
//console.log('test');

             var token  = $('input[name="_token"]').val();
             var called = $('input[name="called"]').val();
             var telephone = $('input[name="telephone"]').val();
             var dataString = "called="+ called +"&telephone=" +telephone +"&token=" + token;

             //console.log('this is the data'+dataString)
             $.ajax({

                 headers: {'X-CSRF-Token' : token},
                 url: "{{URL::to('callback')}}",
                 type: 'post',
                 data: dataString,
                 success: function(data){
                     //console.log(data);

                     $('.phone').empty();



                     var obj = JSON.parse(data);
                     //$('.phone').append('<div id="sid">'+ obj.sid +'</div>');

                     var sid = obj.sid;
                     var dataSid = "sid="+ obj.sid;



                     var promise = $.ajax({
                         url: "{{URL::to('status')}}",
                         data: dataSid,
                         type: 'get',
                         success:function(data){
                             $('.phone').append("<div> Call status:"+data+"</div>");


                         }
                     });


                 }
             });



         });
     </script>


 <script>
     $('#phoneNumber').intlTelInput({
         responsiveDropdown: true,
         autoFormat: true,
         utilsScript: '/js/utils.js'
     });

     $('#contactForm').on('submit', function(e) {
         // Prevent submit event from bubbling and automatically submitting the
         // form
         e.preventDefault();

         // Call our ajax endpoint on the server to initialize the phone call
         $.ajax({
             url: '/call',
             method: 'POST',
             dataType: 'json',
             data: {
                 phoneNumber: $('#phoneNumber').val()
             }
         }).done(function(data) {
             // The JSON sent back from the server will contain a success message
             alert(data.message);
         }).fail(function(error) {
             alert(JSON.stringify(error));
         });
     });
 </script>






 <script>
     (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
         (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
         m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
     })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

     ga('create', 'UA-56093034-1', 'auto');
     ga('send', 'pageview');

 </script>


 <script type="text/javascript"><!--
     document.write(unescape("%3Cscript id='pap_x2s6df8d' src='" + (("https:" == document.location.protocol) ? "https://" : "http://") +
     "dialer.dial4dough.com/scripts/trackjs.js' type='text/javascript'%3E%3C/script%3E"));//-->
 </script>
 <script type="text/javascript"><!--
     PostAffTracker.setAccountId('default1');
     try {
         PostAffTracker.track();
     } catch (err) { }
     //-->
 </script>
 <script type="text/javascript">
     (function(d, src, c) { var t=d.scripts[d.scripts.length - 1],s=d.createElement('script');s.id='la_x2s6df8d';s.async=true;s.src=src;s.onload=s.onreadystatechange=function(){var rs=this.readyState;if(rs&&(rs!='complete')&&(rs!='loaded')){return;}c(this);};t.parentElement.insertBefore(s,t.nextSibling);})(document,
         '//dial4dough.ladesk.com/scripts/track.js',
         function(e){  });
 </script>


 <script type="text/javascript" id="la_x2s6df8d" src="//dial4dough.ladesk.com/scripts/track.js"></script>
 <img src="//dial4dough.ladesk.com/scripts/pix.gif" onLoad="LiveAgentTracker.createButton('button1', this);"/>



@include('pages/scripts/data')

 @yield('scripts')


