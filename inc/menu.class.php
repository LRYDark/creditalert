<?php

class PluginCreditalertMenu extends CommonGLPI
{
    public static $rightname = PluginCreditalertProfile::RIGHTNAME;

    public static function getMenuName()
    {
        return PluginCreditalertCreditItem::getTypeName(2);
    }

    public static function getMenuContent()
    {
        $menu = [];
        
        if (Session::haveRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_CONFIG)) {
            $menu['title'] = self::getMenuName();
            $menu['page'] = '/plugins/creditalert/front/creditlist.php';
            $menu['links']['search'] = '/plugins/creditalert/front/creditlist.php';
            $menu['links']['config'] = '/plugins/creditalert/front/config.form.php';
            $menu['icon'] = self::getIcon();
        }

        return $menu;
    }

    static function getIcon() {
        return "fa-solid fa-coins";
    }
}
