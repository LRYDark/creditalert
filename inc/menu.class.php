<?php

class PluginCreditalertMenu extends CommonGLPI
{
    public static function getMenuName()
    {
        return PluginCreditalertCreditItem::getTypeName(2);
    }

    public static function getMenuContent()
    {
        $menu = [];
        $menu['title'] = self::getMenuName();
        $menu['page'] = '/plugins/creditalert/front/creditlist.php';
        $menu['links']['search'] = '/plugins/creditalert/front/creditlist.php';

        if (Session::haveRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_CONFIG)) {
            $menu['links']['config'] = '/plugins/creditalert/front/config.form.php';
        }

        return $menu;
    }
}
