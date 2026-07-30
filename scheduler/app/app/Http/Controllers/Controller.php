<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Policies are 2.0's authorization mechanism, so every controller needs
    // $this->authorize() rather than importing the trait one file at a time.
    use AuthorizesRequests;
}
