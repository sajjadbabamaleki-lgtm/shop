<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * The storefront's front page.
     *
     * The sections it renders are still the ported markup with the copy and
     * photographs baked in; nothing is read from the catalogue yet. Wiring
     * those to Eloquent is what comes after the port.
     */
    public function __invoke(): View
    {
        return view('home');
    }
}
