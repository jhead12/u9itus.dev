
<!--This page will pull from the Marketing Database-->


@if(Marketer::all())

<div class="row">
    <table class="table table-striped">
        <thead>
        <tr>
            <th width="100">Click ID</th>
            <th width="200">Company Name</th>
            <th>City/State</th>
            <th>Post Title</th>

            <th width="50">Ratings</th>
        </tr>
        </thead>

        <tbody>

          @foreach($marketers as $marketer)



        <tr>

            <td><a href="#" data-reveal-id="myModal" data-reveal-ajax="{{URL::route('ad',$marketer->id) }}">{{$marketer->id}}</a></td>
            <div id="myModal" class="reveal-modal" data-reveal>
                <h2>Dial4dough is all you need.</h2>
                <p class="lead"></p>
                <p></p>
                <a class="close-reveal-modal">&#215;</a>
            </div>

            <td>{{$marketer->company_name}}</td>

            <td>{{$marketer->city}}</td>
            <td>{{$marketer}}</td>

            <td>{{$marketer->ratings}}</td>
        </tr>

        </tbody>



        @endforeach
        {{$marketers->links()}}

@else


<p>No users</p>




    </table>

</div>
@endif

@section('scripts')

{{--Trying to get the Maps working proper--}}

     <script type="text/javascript"
                        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDr-HY1dJdKOyuNKwoSSoOFqA64B1x9LIg">
                      </script>
                      <script type="text/javascript">
                        function initialize() {
                          var mapOptions = {
                            center: { lat: -34.397, lng: 150.644},
                            zoom: 8
                          };
                          var map = new google.maps.Map(document.getElementById('map-canvas'),
                              mapOptions);
                        }
                        google.maps.event.addDomListener(window, 'load', initialize);
                      </script>

@stop
