<?php

/**
 * Plugin install process.
 *
 * @return bool
 */
function plugin_creditalert_install()
{
    include_once __DIR__ . '/sql/install.php';
    return PluginCreditalertInstall::install();
}

/**
 * Plugin uninstall process.
 *
 * @return bool
 */
function plugin_creditalert_uninstall()
{
    include_once __DIR__ . '/sql/uninstall.php';
    return PluginCreditalertInstall::uninstall();
}
