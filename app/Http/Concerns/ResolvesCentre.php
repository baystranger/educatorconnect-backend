<?php

namespace App\Http\Concerns;

use App\Models\ChildcareCentre;
use Illuminate\Http\Request;

trait ResolvesCentre
{
    /** The signed-in owner's centre. Every centre account gets one at sign-up. */
    protected function centre(Request $request): ChildcareCentre
    {
        $centre = $request->user()->centre;
        abort_if(! $centre, 404, 'No centre profile is linked to this account.');

        return $centre;
    }
}
