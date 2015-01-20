 
<div class="panel " id="addials">
    <p>Please enter your <strong>pin</strong> to access addial</p>
<form method="post" action="{{URL::action('addial-confirm')}}" id="market-addial">
 
    <div class="row">
        <div class="large-4">
        <label>User ID
            <input type="text" name="userid" id="userid" disabled value="{{Auth::user()->userid}}">
            <input type="hidden" name="merchid" id='merchid' value="{{$marketer->_id}}">
            </label>
        </div>
    <div class="large-4">
        <label>Pin <small id="error" hidden> Please Enter Pin or <a href="{{URL::to('forgot-pin')}}">Reset</a>. </small>
            <input type="password" autocomplete="off" name="password" id="pin" required>

        </label>
        
        </div>
    <input class="button" name="submit" id="submit" type="submit"value="confirm">
    <br/>
    <small>You agree to payout  <a href="#">agreement</a></small>
    <br/>
    <small><a href="{{URL::to('forgot-pin')}}">Reset pin</a></small>


    {{Form::token()}}
</div>
</form>
    <a class="close-reveal-modal">&#215;</a>
</div>



@section('scripts')

 <script src="//code.jquery.com/jquery-1.11.0.min.js"></script>



<script>
$(document).ready(function(){
    $('#market-addial').submit(function(event){
        event.preventDefault();

        var userid      = $('[name=userid]').val();
        var pin    =    $('#pin').val();
        var token       = $('input[name="_token"]').val();
        var currentId   = $('[name="merchid"]').val();
        var dataString  = 'userid='+userid+'&token='+token+'&currentId='+currentId + '&pin=' + pin ;




       $.ajax({
       headers: {'X-CSRF-Token' : token},
            url: "{{URL::to('/addial/confirm')}}",
            type: 'post',
            data: dataString,
            
                     
            error: function(errorThrown){
               console.log(errorThrown.responseText);
           },

            success: function(data){

                        console.log(data);
                    if(data === 'error'){
                       
                        $('#error').show();


                    }else{

                        $('#addials').empty();
                        $('#addials').append(data);

                    }

                    //console.log(data);
//                This Ajax Call will allow the




            },
            complete: function(success){
                    //console.log(success);
            }
            


       });

    });
});

</script>




