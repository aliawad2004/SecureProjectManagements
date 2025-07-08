<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // للـ authorize()
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController; // للـ middleware() (موروثة من BaseController)

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
