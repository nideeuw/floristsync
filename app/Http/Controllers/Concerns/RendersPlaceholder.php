<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\View\View;

trait RendersPlaceholder
{
    protected function placeholder(Request $request): View
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
