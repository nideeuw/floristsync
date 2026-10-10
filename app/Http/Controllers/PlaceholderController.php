<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlaceholderController extends Controller
{
    public function __invoke(Request $request): View
    {
        $menu = Menu::query()
            ->where('url_link', $request->route()?->getName())
            ->where('is_active', true)
            ->firstOrFail();

        return view('placeholder', [
            'title'  => $menu->menu_name,
            'layout' => $menu->module_group === 'POS' ? 'pos-layout' : 'staff-layout',
        ]);
    }
}
