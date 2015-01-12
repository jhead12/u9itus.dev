<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UsersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('users', function($table){
            $table-> increments('id')->unique();
            $table->string('userid');
            $table->string('email')->unique();
            $table->string('username')->unique();
            $table->text('firstName');
            $table->string('ip_address');
            $table->string('telephone')->unique();
            $table->boolean('ftlogin');
            $table->text('lastName');
            $table->text('password');
            $table->text('password_temp');
            $table->text('street_address');
            $table->text('city');
            $table->text('state');
            $table->text('zip');
            $table->integer('terms');
            $table->string('campaignid');
            $table->boolean('info');
            $table->integer('invited_by')->nullable();
            $table->string('pic');
            $table->string('birthday');
            $table->string('sex');
            $table->string('total_earned');
            $table->string('refid');
            $table->string('pin');
            $table->string('country');


            $table->string('Paypal_email')->unique()->nullable();


            $table->text('comments');
            $table->text('ratings');
            $table->text('favorites');



			$table->text('code');
			$table->string('remember_token');

            $table-> integer('inDial');
			$table-> integer('active');
            $table-> integer('oldUser')->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('users');
	}

}
