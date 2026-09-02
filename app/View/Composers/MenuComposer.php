<?php

namespace App\View\Composers;

use App\Models\Menu;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class MenuComposer
{
    public function getMenus()
    {
        $user = Auth::user();

        if (!$user) {
            return collect();
        }

        $isSuperAdmin = $user->hasRole('SuperAdmin');

        $menus = Menu::with([
            'links' => function ($query) {
                $query->orderBy('order');
            },
            'links.permission',
        ])
        ->orderBy('order')
        ->get();

        return $menus
            ->map(function ($menu) use ($user, $isSuperAdmin) {

                /*
                |--------------------------------------------------------------------------
                | SUBMENÚ
                |--------------------------------------------------------------------------
                */
                if ($menu->is_submenu) {

                    if (!$isSuperAdmin) {
                        $menu->links = $menu->links
                            ->filter(function ($link) use ($user) {

                                // Sin permiso = visible
                                if (!$link->permission_id) {
                                    return true;
                                }

                                // Con permiso = verificar Spatie
                                return $link->permission
                                    && $user->can($link->permission->name);
                            })
                            ->values();
                    }

                    // Si después del filtro no tiene links, ocultar menú
                    if ($menu->links->isEmpty()) {
                        return null;
                    }

                    // Generar URLs
                    $menu->links->each(function ($link) {

                        if (
                            $link->route &&
                            Route::has($link->route)
                        ) {
                            $link->url = route($link->route);
                        } else {
                            $link->url = '#';
                        }
                    });
                }

                /*
                |--------------------------------------------------------------------------
                | MENÚ SIMPLE
                |--------------------------------------------------------------------------
                */
                if (!$menu->is_submenu) {

                    if (
                        $menu->route &&
                        Route::has($menu->route)
                    ) {
                        $menu->url = route($menu->route);
                    } else {
                        $menu->url = '#';
                    }
                }

                return $menu;
            })
            ->filter()
            ->values();
    }

    public function compose(View $view)
    {
        $view->with(
            'menus',
            $this->getMenus()
        );
    }
}