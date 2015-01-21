@extends('layouts.default')


@section('content')
<div class="create" >

		{{Form::open(['route'=>'register_path'])}}
			
		
        <div class="jumbotron"><h1>Sign Up</h1> <p>If you have any questions or concerns feel free to contact us: 347-230-8438</p></div>
        <div class="large-6"><small>* Fields required</small></div>
        <br/>

        @if($errors->any() )

            <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <li>{{$error}}</li>
            @endforeach
                
            </div>
        @endif



            <div class="form-group">
                <label>First name *
                    <input class="form-control" type="text" id="firstName" name="firstName" placeholder="First Name" {{ (Input::old('firstName')) ? 'value="' . e(Input::old('firstName')) . '"': '' }} />

                </label>



            </div>
            @if($errors->has('firstName'))
            <small id="alert" class="large-4 columns left [tiny small large]">

                <div data-alert class="alert alert-danger">
                    <small class="error">{{$errors->first('firstName')}}</small>

                    <a href="#" class="close">&times;</a>
                </div>

            </small>
            @endif

             <div class="row">

                        <input type="hidden" name="refid"/>
                    </div>



            <div class="form-group">
                <label>Last Name *
                    <input type="text" name="lastName"class="form-control" id="lastName" placeholder="Last Name" {{ (Input::old('lastName')) ? 'value="' . e(Input::old('lastName')) . '"': '' }} />
                </label>

            </div>
            @if($errors->has('lastName'))
            <div id="alert" class="large-4 columns left">

                <div data-alert class="alert-box">
                    <small class="error">{{$errors->first('lastName')}}</small>

                    <a href="#" class="close">&times;</a>
                </div>

            </div>
            @endif


          <div class="form-group">
            
                 <label> Username *
                     <input type="text" class="form-control" id="username" name="username"placeholder="User Name" {{ (Input::old('username')) ? 'value="' . e(Input::old('username')) . '"': '' }}>
                 </label>


             @if($errors->has('username'))
             <div id="alert" class="large-5 columns left">

                 <div data-alert class="alert-box">
                     <small class="error">{{$errors->first('username')}}</small>

                     <a href="#" class="close">&times;</a>
                 </div>

             </div>
             @endif
         </div> 

             <div class="form-group">
                
                     <label> Email * <small>This will also be your username.</small>
                         <input type="email" id="email" class="form-control" name="email"placeholder="email" {{ (Input::old('email')) ? 'value="' . e(Input::old('email')) . '"': '' }}>

                     </label>


                
                 @if($errors->has('email'))
                 <div id="alert" class="large-5 columns left">

                     <div data-alert class="alert-box">
                         <small class="error">{{$errors->first('email')}}</small>

                         <a href="#" class="close">&times;</a>
                     </div>

                 </div>
                 @endif
                 </div>
    <div class="form-group">
        {{Form::label('country2', ' Available Countries', array('class' => 'large-5','columns','left'))}}
        {{Form::select('country2', array(
        'Available countries' => array("USA","United Kingdom","Virgin Islands","Canada","Guam","Domican Republic","Puerto Rico")

        ))}}


        </label>



        @if($errors->has('email'))
        <div id="alert" class="large-5 columns left">

            <div data-alert class="alert-box">
                <small class="error">{{$errors->first('telephone')}}</small>

                <a href="#" class="close">&times;</a>
            </div>

        </div>
        @endif
    </div>

    <div class="form-group">

        <label> Phone * <small>Enter a valid telephone number.</small>
            <input type="tel" id="email" class="form-control" name="telephone"placeholder="email" {{ (Input::old('telephone')) ? 'value="' . e(Input::old('telephone')) . '"': '' }}>

        </label>



        @if($errors->has('email'))
        <div id="alert" class="large-5 columns left">

            <div data-alert class="alert-box">
                <small class="error">{{$errors->first('telephone')}}</small>

                <a href="#" class="close">&times;</a>
            </div>

        </div>
        @endif
    </div>

                 
                 <div class="form-group">
                    
                         <label> Phone * <small>please enter a valid number </small> 

                          <!--  -->
              			<input type="text" class="form-control" name="telephone" id="telephone" placeholder="telephone" {{ (Input::old('telephone')) ? 'value="' . e(Input::old('telephone')) . '"': '' }}>

                         </label>

                   
                     @if($errors->has('telephone'))
                     <div id="alert" class="large-5 columns left">

                         <div data-alert class="alert-box">
                             <small class="error">{{$errors->first('telephone')}}</small>

                             <a href="#" class="close">&times;</a>
                         </div>

                     </div>
                     @endif
                     </div>


                   
                      <div class="form-group">
                        {{Form::label('country2', ' Available Countries', array('class' => 'large-5','columns','left'))}}
						</div>
							 <div class="form-group">
                       
                        {{ Form::select('country2', array('USA' => 'USA', 'United Kingdom' => 'United Kingdom', 'Virgin Islands' => 'Virgin Islands', 'Canada' => 'Canada', 'Guam' => 'Guam', 'Domican Republic' => 'Domican Republic', 'Puerto Rico' => 'Puerto Rico'), 'USA')}}
                                               

                     
                   </div>

                               
                     
                      <div class="row">

                                          @if($errors->has('telephone'))
                                          <div id="alert" class="large-5 columns left">
                     
                                              <div data-alert class="alert-box">
                                                  <small class="error">{{$errors->first('telephone')}}</small>
                     
                                                  <a href="#" class="close">&times;</a>
                                              </div>
                     
                                          </div>
                                          @endif
                       </div>

                       <div class="form-group">
                       	<label for="gender">Gender</label>
                       	
                     <div class="radio">
					<label>
					<input type="radio" name="sex" id="gender" value="male" checked>
					Male
					</label>
					</div>
					<div class="radio">
					<label>
					<input type="radio" name="sex" id="gender" value="female">
					female
					</label>
					</div>

                       </div>
                  



                                                @if (Session::has('name'))
                          
                              <label for="referredby">You were referred by:</label>
                              <div class="large-4 columns">
                                 <input type="text" class="form-control" disabled value="{{Session::get('name')}}">

                              </div>
                            
                            
                          
                          @endif
                                   


                 
                        <div class="form-group">
                         <label> Password <small>Use a secure password. </small>
                            <!-- Angular Js Ocapacity CSS Data gather system. This will allow for the character inputs to be recogined instantally. -->
                             <input type="password" class="form-control" id="password" name="password"placeholder="password" {{ (Input::old('password')) ? 'value="' . e(Input::old('password')) . '"': '' }}>

                         </label>


                        </div>
                    

                     @if($errors->has('password'))
                     <div id="alert" class="large-5 columns left">

                         <div data-alert class="alert-box">
                             <small class="error">{{$errors->first('password')}}</small>

                             <a href="#" class="close">&times;</a>
                         </div>

                     </div>
                     @endif
                    
                
                                      
                         <div class="form-group">

                             <label> Confirm Password
                                 <input type="password" class="form-control" id="password_again" name="password_again" placeholder="confirm Password" {{ (Input::old('password_again')) ? 'value="' . e(Input::old('password_again')) . ' " ': '' }}>

                             </label>


                         </div>
                         @if($errors->has('password_again'))
                         <div id="alert" class="large-5 columns left">

                             <div data-alert class="alert-box">
                                 <small class="error">{{$errors->first('password_again')}}</small>

                                 <a href="#" class="close">&times;</a>
                             </div>

                         </div>
                         @endif
                   

                        
                             <div id="recaptcha" hidden>
                             	
                             	<script type="text/javascript"
                                     src="https://www.google.com/recaptcha/api/challenge?k=6Ld5gPASAAAAAJEMqnE1_T-UWot7W3nh6jIhw6zz">
                             </script>
                             </div>
                             
                          
							

                             <div class="form-group">

                          

                                 <div class="checkbox">
   				 	<label>
      					<input type="checkbox" id="checkbox" name="terms">  I agree to the <a href="{{URL::route('terms')}}"> Head Enterprises Terms of Use</a> and <a href="{{URL::route('privatepolicy')}}"> Private policy</a>
   					 </label>
  						</div>
                                
                                 
                                 <div class="row">
                                 	
                                 	<input type='submit'  class="btn btn-primary" value="Create account">
                                 </div>

                                 {{Form::close()}}
                             
                         </div>


                         

                    




@include('pages.partials.footer')
@include('pages.scripts.create')

    @stop

