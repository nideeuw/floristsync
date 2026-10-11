<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RendersPlaceholder;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    use RendersPlaceholder;

    public function index(Request $request): View
    {
        return $this->placeholder($request);
    }

    public function create(): never
    {
        abort(501, 'Not implemented yet.');
    }

    public function store(Request $request): never
    {
        abort(501, 'Not implemented yet.');
    }

    public function edit(User $staff): never
    {
        abort(501, 'Not implemented yet.');
    }

    public function update(Request $request, User $staff): never
    {
        abort(501, 'Not implemented yet.');
    }

    public function destroy(User $staff): never
    {
        abort(501, 'Not implemented yet.');
    }
}
