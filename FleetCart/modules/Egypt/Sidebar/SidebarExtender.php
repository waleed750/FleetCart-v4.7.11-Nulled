<?php

namespace Modules\Egypt\Sidebar;

use Maatwebsite\Sidebar\Item;
use Maatwebsite\Sidebar\Menu;
use Maatwebsite\Sidebar\Group;
use Modules\Admin\Sidebar\BaseSidebarExtender;

class SidebarExtender extends BaseSidebarExtender
{
    public function extend(Menu $menu)
    {
        $menu->group(trans('admin::sidebar.system'), function (Group $group) {
            $group->item(trans('egypt::sidebar.egypt_store'), function (Item $item) {
                $item->icon('fa fa-map-marker');
                $item->weight(25);
                $item->route('admin.egypt.index');
                $item->authorize(
                    $this->auth->hasAccess('admin.egypt.index')
                );
            });
        });
    }
}
