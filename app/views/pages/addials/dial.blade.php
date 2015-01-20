<div class="panel" xmlns="http://www.w3.org/1999/html">


<p>This is the ad Dial portal. Once the call has completed you will receive your payment.{{----}} </p>
<div class="phone">



<form action="#" method="post" id='callback'>
    <span>Your Number:
    <input type="text" value="{{Auth::user()->telephone}}" name="called"  disabled/></span>
    <input type="hidden" value="{{$marketer->telephone}}" name="telephone">

    <input type="submit" class='button' id="connect" value="Connect"/>

    {{Form::token()}}
</form>

</div>

</div>

@section('scripts')
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

