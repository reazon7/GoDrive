<?php

namespace REAZON\GoDrive;

use Illuminate\Support\ServiceProvider;

class GoDriveServiceProvider extends ServiceProvider
{
	/**
	 * Bootstrap any application services.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->publishes([
			__DIR__ . '/config/config.php' => config_path('godrive.php'),
		], 'config');
	}

	/**
	 * Register any application services.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->mergeConfigFrom(__DIR__ . '/config/config.php', 'godrive');
	}
}
