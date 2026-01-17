
@foreach($marketer as $data)
            <div class="container">


            <div class="row">

            <section>
            <h3>{{$data->title}}</h3>
                <p>Product I.D: {{$data->id}}</p>
            <article>
            {{$data->content}}

            </article>
            </section>
            <br/>

            <address>
                Web Site <a href="{{$data->purchase_url}}">{{$data->purchase_url}}</a>.<br>
                Business Contact{{$data->telephone}}<br>
                You may also want to visit us:<br>

                {{$data->address}}<br>
                {{$data->state}}<br>
                {{$data->zip}}<br>
                USA
              </address>
            </div>



            </div>
@endforeach