@extends('layout.main')



@section('content')


<div class='jumbotron'><p>Dial4dough has three membership levels.  Please upgrade to a membership level that best suits your needs.</p></div>

<div class="row">
  
  <ul class="pricing-table large-4 columns">
           <li class="title">Platinum</li>
        <li class="price">$100.00 Yearly</li>

        <li class="description">Earn $1.75 per Addial</li>
        <li class="bullet-item">$30.00 referral bonus for referring other Platinum Members</li>
        <li class="bullet-item">$2.00 monthly residual income for recruiting a Gold Member</li>
      <li class="cta-button"><a class="button" href="{{URL::route('platinum-upgrade')}}">Get Started</a></li>
  </ul>


  <ul class="pricing-table large-4 columns">
      <li class="title">Gold Membership</li>
        <li class="price">$10.00 Monthly</li>
        <li class="description">$1.25 for each addial reviewed</li>
        <li class="bullet-item">$30.00 referral bonus for referring  Platinum Members</li>
        <li class="bullet-item">$2.00 monthly residual income for recruiting Gold Members;</li>
      <li class="cta-button"><a class="button" href="{{URL::route('gold-upgrade')}}">Get Started</a></li>
  </ul>


  </div>

 

@stop