<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\AuthorizesPortal;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use AuthorizesPortal;

    public function show(Request $request)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);

        return view('registrar.profile.show', ['user' => $request->user()]);
    }
}
