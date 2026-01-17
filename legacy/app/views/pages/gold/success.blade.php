
<div id="panel">

<h1>Congratulations!</h1>

<p>You are now a Gold Member in the Dial4dough program.</p>

<p>This is your pin <strong>{{Auth::user()->pin}}</strong></p>
<p>Please use this pin number to access each Addial.</p>
<p>Click on the <a href="{{URL::route('dialpad')}}">Dialpad</a> to get started.</p>




<p>Below are the benefits that you receive for being a Gold Member:</p>
<ul>

<li>$1.00 for each addial reviewed;</li>
<li>A $20.00 bonus for sponsoring a new Platinum Dial4dough member;</li>

</li>

</ul>

<p>
** Remember that Gold members pay a $10.00 monthly subscription fee.<br>
** All Dial4dough members that review addials, are obligated to make purchases from advertisers in the Dial4dough program.  Once a Dial4dough member earns at least $100 through the Dial4dough system, a minimum 20 % ($20) purchase is required to continue receiving Addials.
Please click on the "Dash Board" link above to review Addials. <br>

<p><a href="{{URL::route('addial-info')}}"> Click link </a>for Instructions</p>
Thank You,
<br/>
<strong> Admin Dial4dough.com</strong>

<div class="large-6">
<a id="off" href="{{URL::route('select-info-off')}}">I get it, please do not show this message again.</a>

</div>

</p>

<a class="close-reveal-modal">&#215;</a>

</div>

