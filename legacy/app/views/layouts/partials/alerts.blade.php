
@if(Session::has('global-success'))

    <div id="global-success" class="alert-box success ">

        <div class="panel-success">
            <strong>Success:</strong>{{Session::get('global-success')}}
        </div>
        <button class="close"><a class="close-reveal-modal">&#215;</a></button>
    </div>


@endif




@if(Session::has('global-warning'))
    <div id="global-warning" class="alert alert-warning alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
        <strong>Warning: </strong> {{ Session::get('global-warning')}}
    </div>
@endif

@if(Session::has('global-info'))
    <div id="global-info" class="alert alert-info alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
        <strong>Please note: </strong> {{ Session::get('global-info')}}
    </div>
@endif

@if(Session::has('global-danger'))

    <div id="global-danger" class="alert alert-danger " role="alert">

        <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
        <strong>Please note: </strong> {{ Session::get('global-danger')}}

    </div>

@endif
