@extends('layouts.default')

@section('content')


<div class="container">





    <h1>Dial Pad</h1>




    <p><strong>DialPad User Instructions</strong></p>

    <p ><span ><strong><span >Please adhere to the following instructions to enable receiving credits for addials.</span>&nbsp;</strong></span></span></p>

    <p ><strong>Adequate&nbsp;information must be present in Dial4dough members&#39; profiles to receive addials. &nbsp;Login to the dial4dough<span class="Apple-converted-space">&nbsp;</span>members&#39; area.&nbsp;Click on the &quot;My Profile&quot; link on the left side of the site. &nbsp;Make sure that the &quot;Additional Information&quot;section<span class="Apple-converted-space">&nbsp;</span>&nbsp;at the bottom of page&nbsp; is completed and accurate.&nbsp; If this is not done, our tracking system will not provide you with addials to enable your earning income.</strong></p>


    <form method="post" style="border: 1px dotted rgb(255, 0, 0); padding: 2px; color: rgb(0, 0, 0); font-family: 'Times New Roman'; font-size: medium; font-style: normal; font-variant: normal; font-weight: normal; letter-spacing: normal; line-height: normal; orphans: auto; text-align: start; text-indent: 0px; text-transform: none; white-space: normal; widows: auto; word-spacing: 0px; -webkit-text-stroke-width: 0px;">
        <p><strong>Web&nbsp;audio and video addials:</strong></p>

        <p ><strong><span class="auto-style9" style="color: rgb(190, 24, 153);">Web and some audio addials may be clicked.  Member will be sent to a Website or audio file to view a timed video and, or audio file.  Product I.D., your username, phone number and email address will be required to receive credit for your listening or viewing. Your presence will be identified once you follow the instructions within the specific addials promotion.</span></strong></p>

        <p><strong>Payment credits for addials will be alloted in accordance to membership selection.</strong></p>
    </form>



    <table class="auto-style17" style="width: 100%">

        <tbody>

        <tr>
            <td style="height: 23px; width: 131px"><strong>Company Name</strong></td>
            <td style="height: 23px; width: 103px"><strong>Product I.D.</strong></td>
            <td style="height: 23px; width: 154px"><strong>Phone Number or Link</strong></td>
            <td style="height: 23px; width: 101px"><strong>Product Name</strong></td>
            <td style="height: 23px; width: 245px"><strong>Type advertisement</strong></td>
        </tr>
        @foreach($marketers as $marketer)

            @if($marketer->active ===true)


            <tr class="{{$marketer->id}}">
                <td style="width: 131px">{{$marketer->company_name}}</td>
                <td style="width: 103px">{{$marketer->id}}</td>
                {{--<td style="width: 154px">  <a href="#" class="btn btn-primary" data-toggle="modal" data-target=".bs-{{$marketer->id}}">Click here</a></td>--}}
                <td style="width: 154px"><a href="{{URL::route('addials.show', $marketer->id) }}" class="btn btn-primary">Click here</a></td>

                <td class="auto-style18" style="width: 101px">{{$marketer->title}}</td>
                <td style="width: 245px">{{$marketer->type}}</td>

                <!-- Large modal -->
                <div id="myModal" class="reveal-modal" data-reveal>
                    <h2>Awesome. I have it.</h2>
                    <p class="lead">Your couch.  It is mine.</p>
                    <p>I'm a cool paragraph that lives inside of an even cooler modal. Wins!</p>
                    <a class="close-reveal-modal">&#215;</a>
                </div>

                {{--<div class="modal fade bs-{{$marketer->id}}" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">--}}
                    {{--<div class="modal-dialog modal-lg">--}}
                        {{--<div class="modal-content">--}}
                            {{--<h1>{{$marketer->title}}</h1>--}}

                            {{--@include('pages.addials.business')--}}
                            {{--@include('pages.addials.business', array($marketer))--}}

                        {{--</div>--}}
                    {{--</div>--}}
                {{--</div>--}}


            </tr>
            @endif

        @endforeach
        </tbody>


    </table>





    <h3 ><strong>Telephone Dials:</strong></h3>

    <ol >
        <li>
            <p ><strong><span ><span ><span class="auto-style7">If telephone number is not clickable, simply dial the number</span><span class="Apple-converted-space">&nbsp;</span><span style="background-color: rgb(255, 240, 245);">with&nbsp;the telephone number that you have inserted&nbsp;into</span><span class="auto-style7">&nbsp;your Dial4dough.com profile.</span></span></span></strong></p>
        </li>
        <li>
            <p ><strong><span class="auto-style3">Write down the addial phone number and Product I.D.;</span></strong></p>
        </li>
        <li>
            <p><span class="auto-style3"><strong>State your referral I.D., and the addial product I.D</strong></span>&nbsp;<span class="auto-style3"><strong>when requested.</strong>&nbsp; </span>(Your Referral I.D. can be located at the top of the page within your members area).</p>
        </li>
        <li>
            <p class="auto-style4"><strong>Do not call telephone number or attempt to view addial more than once <span class="auto-style8">unless number is busy.</span> You will <span class="auto-style8">not</span> receive additional credits by calling numbers or clicking links multiple times.</strong></p>
        </li>
        <li>
            <p class="auto-style3"><strong>Please be courteous to Advertisers;</strong></p>
        </li>
        <li>
            <p class="auto-style3"><strong>Remain on the telephone line<span class="Apple-converted-space">&nbsp;</span>for the entire presentation&nbsp;to receive credit. (Presentations will not exceed three minutes)</strong></p>
        </li>
    </ol>


</div>


    @include('layouts.partials.footer')



@stop