@extends('layouts.inner')
@section('style')

    <style>

        body{

            padding: 0px;
            margin: 0px;
        }

        #gradient
        {
            width: 100%;
            height: 800px;
            padding: 0px;
            margin: 0px;
        }
    </style>
    <link rel="stylesheet" href="{{asset('/css/bootflat.min.css"')}}/>

@endsection

@section('content')


    <div class="container" >
        <div class="row">


                <div class="col-md-12 ">

                        <div class="fb-like" data-href="https://www.dial4dough.com" data-layout="standard" data-action="like" data-show-faces="false" data-share="true"></div>
                        <div class="panel-heading">Upgrade today.</div>





                        <div class="row">

                            <div class="col-md-12">
                                <div class="pricing">
                                    <ul>
                                        <li class="unit price-primary">


                                            <div class="price-title">
                                                {{--<img src="{{asset('images/d__0002_Bronze.jpg')}}" alt="platinum"/>--}}

                                                <h3>$20</h3>
                                                <p>Monthly</p>

                                            </div>
                                            <div class="price-body">
                                                <h4>Platinum Membership</h4>

                                                <ul>
                                                    <p>Earn $1.75 per dial</p>
                                                    <li>Quarterly bonuses for sponsoring paid members</li>
                                                    <li>Earn 15% from Addial buyers</li>


                                                </ul>
                                            </div>
                                            <div class="price-foot">

                                                <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
                                                    <input type="hidden" name="cmd" value="_s-xclick">
                                                    <input type="hidden" name="hosted_button_id" value="VC3HF2W8KA2YN">
                                                    <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_subscribeCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
                                                    <img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
                                                    <input  class="hidden" name="notify_url" value="https://www.dial4dough.info/paypal/pay" id="pap_ab78y5t4a" />

                                                </form>
                                            </div>
                                        </li>
                                        <li class="unit price-success">
                                            <div class="price-title">

                                                {{--<img src="{{asset('images/d__0002_Bronze.jpg')}}" alt="gold"/>--}}


                                                <h3>$15</h3>
                                                <p>Monthly</p>
                                            </div>
                                            <div class="price-body">
                                                <h4>Gold Membership</h4>

                                                <ul>
                                                    <li>Earn $1.00 per dial</li>
                                                    <li>Quarterly bonuses for sponsoring paid members</li>
                                                    <li>Earn 15% from Addial buyers</li>




                                                </ul>
                                            </div>
                                            <div class="price-foot">
                                                <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
                                                    <input type="hidden" name="cmd" value="_s-xclick">
                                                    <input type="hidden" name="hosted_button_id" value="7H2UESMQX82F8">
                                                    <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_subscribeCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
                                                    <img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
                                                    <input  class="hidden" name="notify_url" value="https://www.dial4dough.info/paypal/pay" id="pap_ab78y5t4a" />
                                                </form>
                                            </div>
                                        </li>
                                        <li class="unit price-warning">


                                            <div class="price-title">
                                                {{--<img src="" alt="silver"/>--}}

                                                <h3>$10</h3>
                                                <p>Monthly</p>
                                            </div>
                                            <div class="price-body">
                                                <h4>Silver Membership</h4>
                                                <ul>
                                                    <li>Earn $.75 per dial</li>
                                                    <li>Quarterly bonuses for sponsoring paid members</li>
                                                    <li>Earn 15% from Addial buyers</li>




                                                </ul>
                                            </div>
                                            <div class="price-foot">
                                                <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
                                                    <input type="hidden" name="cmd" value="_s-xclick">
                                                    <input type="hidden" name="hosted_button_id" value="6QAFFXPFLTBVY">
                                                    <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_subscribeCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
                                                    <img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
                                                    <input class="hidden" name="notify_url" value="https:://www.dial4dough.info/paypal/pay" id="pap_ab78y5t4a" />
                                                </form>
                                            </div>
                                        </li>
                                        <li class="unit price-success">


                                            <div class="price-title">
                                                <h3>$5</h3>
                                                <p>Monthly</p>

                                            </div>
                                            <div class="price-body">
                                                <h4>Bronze Qualified</h4>
                                                <small>** free 7 Day Trial **</small>

                                                <ul>
                                                    <li>*Earn $.25 per dial</li>
                                                    <li>Quarterly bonuses for sponsoring paid members</li>
                                                    <li>Earn 15% from Addial buyers</li>


                                                </ul>
                                            </div>
                                            <div class="price-foot">
                                                <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
                                                    <input type="hidden" name="cmd" value="_s-xclick">
                                                    <input type="hidden" name="hosted_button_id" value="PYQREKFF58YP4">
                                                    <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_subscribeCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
                                                    <img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
                                                    <input  class='hidden'name="notify_url" value="https://www.dial4dough.info/paypal/pay" id="pap_ab78y5t4a" />
                                                </form>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>


                        </div>



                    </div>
                </div>






    <!--Add the following script at the bottom of the web page (immediately before the </body> tag)-->
@endsection





