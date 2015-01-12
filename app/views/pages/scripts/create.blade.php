
 <script src="//code.jquery.com/jquery-1.11.0.min.js"></script>
 <script src="{{asset('js/jquery.formatter.js')}}"></script>
 
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
     <script src="{{asset('js/formatter.js')}}">
      </script>
