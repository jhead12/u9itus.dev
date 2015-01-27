@extends('layouts.default')


@section('content')


@if(count(Marketer::all()) >0)





<div class="row">
    <li><p>Call us: <a style="color: red" href="tel:3472308438">3472308438 ext#1</a></p></li>

    @foreach(Marketer::all() as $marketer)

    <div class="list-group">
        <!-- Input a status indicator  -->

        <div id='status'>

            <a href="#" class="list-group-item" id="{{$marketer->_id}}" data-reveal-id="myModal" data-reveal-ajax="{{URL::route('addials.show', $marketer->id) }}">
                <h4 class="list-group-item-heading">{{$marketer->title}}</h4>
                <p class="list-group-item-text">{{$marketer->state}}</p>
                <p class="list-group-item-text">{{$marketer->company_name}}</p>
                <p class="list-group-item-text">listing ID: {{$marketer->id}}</p>
            </a>
        </div>
        <div id="myModal" class="reveal-modal" data-reveal>
            <h2></h2>
            <p class="lead"></p>
            <p></p>
            <a class="close-reveal-modal">&#215;</a>
        </div>


    </div>




    @endforeach
    {{$marketers->links()}}




    @else
    <div class="row">
        <div class="list-group">

            <h1>There are no AdDials Available</h1>
        </div>

    </div>






    <!--    </table> -->

</div>
@endif

        @include('pages.partials.footer')

<!-- Create a script that will get mongodb info and color the list by the completed/opened status. Send the data realtime via the firebase. -->
