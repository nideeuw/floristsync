<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingRequest;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function index(): View
    {
        return view('admin.settings.index', ['form' => $this->settings->form()]);
    }

    public function update(SettingRequest $request): RedirectResponse
    {
        $this->settings->save($request->payload());

        return redirect()->route('admin.settings')->with('success', 'Store settings saved.');
    }
}
