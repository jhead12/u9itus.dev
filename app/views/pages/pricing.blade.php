@extends('layouts.default')


@section('content')
<div class="row">

<h1>Membership Options.</h1>
</div>


<div class="row">

 <ul class="pricing-table large-4 columns">
           <li class="title">Platinum</li>
           <li class="price">$100.00 Yearly</li>
       <li class="description">Earn $1.75 per Addial</li>
        <li class="bullet-item">*$30.00 referral bonus for referring other Platinum Members</li>
        <li class="bullet-item">*$1 monthly residual incoming for each recruited Gold Member.</li>
      <li class="cta-button"><a class="button" href="{{URL::to('/register')}}">Get Started</a></li>
  </ul>


  <ul class="pricing-table large-4 columns">
      <li class="title">Gold Membership</li>
        <li class="price">$10.00 Monthly</li>
        <li class="bullet-item">$1.00 for each Addial reviewed</li>
        <li class="bullet-item">$20.00 referral bonus for referring  Platinum Members</li>
        <li class="bullet-item">No monthly residual income for recruiting Gold Members</li>
      <li class="cta-button"><a
 class="button" href="{{URL::to('/register')}}">Get Started</a></li>
  </ul>
       <ul class="pricing-table large-4 columns">
   <li class="title">Bronze</li>
        <li class ="price">Free</li>
        <li class="description">No Referral bonuses</li>
        <li class="bullet-item">25 cents for each Addial reviewed</li>
        <li class="bullet-item">*$15.00 referral bonus for referring  Platinum Members</li>
     <li> <a href="{{URL::to('/register')}}">will upgrade later</a></li>
     </ul>

  </div>
  <div class="pad"></div>

@include('pages.partials.footer')

@stop