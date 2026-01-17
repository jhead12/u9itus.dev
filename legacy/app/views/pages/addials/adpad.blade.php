
@if(count(Marketer::all()) >0)





<div class="large-10 columns">
 <li><p>Call us: <a style="color: red" href="tel:3472308438">3472308438 ext#1</a></p></li>
    <!-- <table class="table table-striped">
        <thead>
        <tr>
            <th width="100">Click ID</th>
            <th width="200">Company Name</th>
            <th>City/State</th>
            <th>Post Title</th>

            <th width="50">Ratings</th>
        </tr>
        </thead>

        <tbody> -->
		
		
			
          @foreach($marketers as $marketer)
		  
		  <div class="list-group">
            <!-- Input a status indicator  -->
            <div id='status'>
		    <a href="#" class="list-group-item" id="{{$marketer->id}}" data-reveal-id="myModal" data-reveal-ajax="{{URL::route('ad',$marketer->id) }}">
  		      <h4 class="list-group-item-heading">{{$marketer->title}}</h4>
  		      <p class="list-group-item-text">{{$marketer->state}}</p>
  			  <p class="list-group-item-text">{{$marketer->company_name}}</p>
			   <p class="list-group-item-text">listing ID: {{$marketer->id}}</p>
			    </a>
				</div>
	            <div id="myModal" class="reveal-modal" data-reveal>
	                <h2>Dial4dough is all you need.</h2>
	                <p class="lead"></p>
	                <p></p>
	                <a class="close-reveal-modal">&#215;</a>
	            </div>
		  
		  
		  </div>

    


        @endforeach
        {{$marketers->links()}}

@else


<div class="list-group">

<p><img src="{{asset('images/empty_addials.jpg')}}"> </p>
</div>




<!--    </table> -->

</div>
@endif

<!-- Create a script that will get mongodb info and color the list by the completed/opened status. Send the data realtime via the firebase. -->

