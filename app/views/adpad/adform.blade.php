@extends('layouts.default')

@section('content')

    <!--
      Collect data about Audio Files. Does the user want to record or upload an Audio File. With a time limit of :30 seconds
    -->


    <div class="container">
        <div class="jumbotron"><h1>Addials.</h1>
            <h3>The AdDials Creation System</h3>
        </div>

        <p>Here is a table of the current Orders(The parameters are subject to change)</p>
        <table summary="This table will show information about the currently purchased Addials">

            <tr>

                <th>id</th>
                <th>Purchased</th>
                <th>order id</th>
                <th>Price Paid</th>
                <th> Phone Contact</th>
            </tr>
            <tr>


            </tr> <!-- ... -->

        </table>




        @if (Session::has('errors'))

            <ul class="alert alert-danger" role="alert" >
                @foreach ($errors->all() as $error)
                    <li>{{$error}}</li>
                @endforeach
            </ul>
        @endif




        {{Form::open(['route'=> 'addials.store', 'files'=>true])}}





        <!-- Get the shopify orderid -->
        <div class="form-group">
            <!-- Title -->
            {{Form::label('title','Message Title')}}


            {{Form::text('title',null,['class'=>'form-group'])}}
        </div>
        <div class="form-group">

            <!-- Shopify order -->

            {{Form::label('orderid','Choose the order to use to create an addial. ')}}
            <select name="orderid" multiple>

            </select>

            <!-- This user Id will be generated from the User id of the Marketer. -->

        </div>





        <div class="form-group">



            {{Form::label('Type')}}
            <!-- Discover if there is a created company name -->
            {{Form::select('type',array('type' =>['video'=>'video','audio'=>'audio']))}}
        </div>




        <div class="form-group">



            {{Form::label('AdDial Thumbnail')}}
            <!-- Needs to Specifiy an image amount. -->
            {{Form::file('banner')}}
        </div>


        <div class="form-group">



            {{Form::label('id')}}


            <input type="text" name="userid" value="" placeholder="use Id from Table.">



        </div>


        <div class="form-group">

            <!-- Company Name -->

            {{Form::label('Company Name')}}
            <!-- Discover if there is a created company name -->
            {{Form::text('company_name',null,['placeholder'=>'company name'])}}
        </div>

        <div class="form-group">

            <!-- Company Name -->

            {{Form::label('Company Address')}}
            <!-- Discover if there is a created company name -->
            {{Form::text('company_address',null,['placeholder'=>'company address'])}}
        </div>

        <div class="form-group">

            <h1>Tiwilo Entries </h1>
            {{Form::text('accountSid',null,['placeholder'=>'public sid from twilio'])}}

            <div class="form-group">
                <label for="private sid">
                    Auth Token

                </label>

                {{Form::password('authToken',null,['name'=>'private sid', 'placeholder'=>'private sid'])}}

            </div>
            {{Form::text('telephone',null,['placeholder'=>'Twilio Phone Number'])}}
        </div>

        <div class="form-group">

            {{Form::label('video_src','Video Source:')}}
            {{Form::text('video_src',null,['class'=>'form-control'])}}
        </div>

        <div class="form-group">

            {{Form::label('url','Product Url: eg. clickback')}}
            {{Form::text('purchase_url',null,['class'=>'form-control'])}}
        </div>
        <div class="form-group">

            {{Form::label('amount','How many Addials')}}

            {{Form::selectRange('amount', 1, 1200)}}



        </div>

        <div class="form-group">



            {{ Form::macro('fooField', function()
            {
            return '<input type="custom"/>';
            })   }}
        </div>



        <!-- The fileinput-button span is used to style the file input field as button -->

        <div class="form-group">

            {{Form::label('Upload Audio Dial')}}

            <span class="btn btn-success fileinput-button">
		    <i class="glyphicon glyphicon-plus"></i>
		    <span>Add files...</span>
		    <!-- The file input field used as target for the file upload widget -->

                {{Form::file('audio_file', array('class' => 'fileuplod'))}}
		</span>
        </div>

        <br>
        <div class="form-group">

            <blockquote>Shopify Order ID list -- Depending on the order ID the the name of the ad Advertiser will show here</blockquote>
        </div>

        <div class="form-group">
            {{Form::label('locations', ' Available Locations', array('class' => 'large-5','columns','left'))}}
            {{Form::select('locations', array(
                'Available Locations' => array("All",'USA',"United Kingdom","Virgin Islands","Canada","Guam","Domican Republic","Puerto Rico")

            ))}}


        </div>
        <div class="form-group">
            {{Form::label('catagory', 'Catagories', array('class' => 'large-5','columns','left'))}}
            {{Form::select('catagory', array(
                'catagories' => $list

            ))}}


        </div>




        <div class="form-group">

            {{Form::label('postingbody','Message:')}}
            {{Form::textarea('postingbody',null,['class'=>'form-control']) }}
        </div>



        <div class="form-group">

            {{Form::submit('Create AdDial',['class'=>'btn btn-primary'])}}

        </div>

        {{Form::close()}}




    </div>


@stop

