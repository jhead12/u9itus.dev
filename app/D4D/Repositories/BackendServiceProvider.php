<?php namespace D4D\Repositories;

use Illuminate\Support\ServiceProvider;

class BackendServiceProvider extends ServiceProvider {

 public function register()
    {
        $this->app->bind(
            'D4D\Repositories\OrderRepositoryInterface',
            'D4D\Repositories\Forum\DbAdpadRepository'
        );
    }




}



	

