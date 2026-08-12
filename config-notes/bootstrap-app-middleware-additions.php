<?php

/*
|--------------------------------------------------------------------------
| Middleware Aliases — ADD this into your existing bootstrap/app.php
|--------------------------------------------------------------------------
|
| This is NOT a real config file Laravel loads — it's instructions for the
| two additions needed in bootstrap/app.php to register the 'subscribed'
| and 'admin' route middleware aliases used throughout routes/web.php and
| routes/admin.php.
|
| A fresh Laravel 11/12 skeleton's bootstrap/app.php looks like:
|
|   return Application::configure(basePath: dirname(__DIR__))
|       ->withRouting(...)
|       ->withMiddleware(function (Middleware $middleware) {
|           //
|       })
|       ->withExceptions(function (Exceptions $exceptions) {
|           //
|       })->create();
|
| Change the (currently empty) withMiddleware() closure to:
|
|   ->withMiddleware(function (Middleware $middleware) {
|       $middleware->alias([
|           'subscribed' => \App\Http\Middleware\EnsureUserHasAccess::class,
|           'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
|       ]);
|   })
|
| If you already have other aliases registered there, just add the
| 'subscribed' and 'admin' lines into the existing
| $middleware->alias([...]) array rather than replacing the whole closure.
|
*/
