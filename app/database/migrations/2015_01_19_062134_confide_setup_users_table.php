<?php

use Illuminate\Database\Migrations\Migration;

class ConfideSetupUsersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Creates the users table
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('confirmation_code');
            $table->string('remember_token')->nullable();
            $table->boolean('confirmed')->default(false);
            $table->timestamps();

            
            $table->string('userId');
            $table->string('telephone')->unique();
            $table->boolean('ftlogin');
            $table->text('lastName');
            $table->text('zip');
            $table->integer('terms');
            $table->string('campaignid');
              $table->string('birthday');
            $table->string('sex');
            $table->string('total_earned');
            $table->string('refid');
            $table->string('pin');
            $table->string('country');
        });

        // Creates password reminders table
        Schema::create('password_reminders', function ($table) {
            $table->string('email');
            $table->string('token');
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::drop('password_reminders');
        Schema::drop('users');
    }
}
