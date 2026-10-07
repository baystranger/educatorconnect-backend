<?php

namespace App\Http\Controllers;

use App\Http\Concerns\RespondsWithJson;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests, RespondsWithJson;
}
