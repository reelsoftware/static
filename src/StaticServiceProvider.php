<?php

namespace Phpreel\StaticPHP;

use Illuminate\Support\ServiceProvider;
use StaticPHP\Console\Commands\GenerateStatic;

class StaticServiceProvider extends ServiceProvider
{
	public function boot()
	{
		if ($this->app->runningInConsole()) 
		{
	        $this->commands([
	            GenerateStatic::class,
	        ]);
    	}
	}

	public function register()
	{
		//
	}
}