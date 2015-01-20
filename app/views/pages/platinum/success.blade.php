
<div id="panel">

<h1>Congratulations!</h1>

<p>You are now a Platinum Member in the Dial4dough program.</p>
<p>This is your pin <strong>{{Auth::user()->pin}}</strong></p>
<p>Please use this pin number to access each Addial.</p>

<p>Click on the <a href="{{URL::route('dialpad')}}">Dialpad</a> to get started.</p>


<p>Below are the benefits that you receive for being a Platinum Member:</p>
<ul>

<li>$1.75 for each AdDial reviewed;</li>
<li>A $30.00 bonus for sponsoring a new Platinum Dial4dough member;</li>
<li>A $1.00 monthly residual income payment for sponsoring a new Gold Member.  Residual monthly income will be paid each time one of your sponsored Gold members re-subscribes as a Gold member.  If a sponsored Gold member upgrades to Platinum, then the sponsoring Platinum member will receive a $20.00 bonus payment (yearly residual income).
</li>
<li>You will also receive 10% of the accumulated monthly earnings of your sponsored Platinum members only.
</li>

</ul>

<p>
** Remember that platinum members pay a $100.00 Yearly subscription fee.<br>
** All Dial4dough members that review Ad-Dials, are obligated to make purchases from advertisers in the Dial4dough program.  Once a Dial4dough member earns at least $100 through the Dial4dough system, a minimum 20 % ($20) purchase is required to continue receiving Addials.
Please click on the "Dash Board" link above to review Ad-Dials. <br>

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

