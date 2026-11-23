<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use \Illuminate\Routing\Controller as BaseController;


/**
 * Extends Laravel's BaseController with Authorization, Dispatching, and Validation
 * capabilities.
 *
 * The AuthorizesRequests trait provides authorization and gate checks for the
 * controller actions.
 *
 * The DispatchesJobs trait provides methods for dispatching jobs and handling
 * the results of those jobs.
 *
 * The ValidatesRequests trait provides a convenient method for validating
 * incoming HTTP requests.
 *
 * @see https://laravel.com/docs/authorization Laravel Authorization Docs
 * @see https://laravel.com/docs/queues Laravel Queues Docs
 * @see https://laravel.com/docs/validation Laravel Validation Docs
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
