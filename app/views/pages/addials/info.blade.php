

            <div class="row">




            <section>
            <h3>{{$marketer->title}}</h3>
            <article>
            {{$marketer->content}}

            </article>
            </section>
            <br/>

            <address>
                Web Site <a href="{{$marketer->purchase_url}}">{{$marketer->purchase_url}}</a>.<br>
                Business Contact{{$marketer->telephone}}<br>
                You may also want to visit us:<br>

                {{$marketer->address}}<br>
                {{$marketer->state}}<br>
                {{$marketer->zip}}<br>
                USA
              </address>




            </div>
