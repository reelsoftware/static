<?php

namespace Phpreel\StaticPHP;

use Illuminate\Support\ServiceProvider;

class StaticServiceProvider extends ServiceProvider
{
	public function boot()
	{
		dd("Works");
	}

	public function register()
	{

	}
}