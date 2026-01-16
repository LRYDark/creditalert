<?php

define('PLUGIN_CREDITALERT_VERSION', '1.0.0');
define('PLUGIN_CREDITALERT_MIN_GLPI', '10.0.0');
define('PLUGIN_CREDITALERT_MAX_GLPI', '11.0.99');

/**
 * Init hooks of the plugin.
 *
 * @return void
 */
function plugin_init_creditalert()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['creditalert'] = true;
    $PLUGIN_HOOKS['change_profile']['creditalert'] = [PluginCreditalertProfile::class, 'initProfile'];

    $plugin = new Plugin();
    if ($plugin->isInstalled('creditalert') && $plugin->isActivated('creditalert')) {
        if (!$plugin->isInstalled('credit')) {
            return;
        }

        Plugin::registerClass(
            PluginCreditalertProfile::class,
            ['addtabon' => Profile::class],
        );

        Plugin::registerClass(
            PluginCreditalertConfig::class,
            ['addtabon' => 'Config'],
        );

        $PLUGIN_HOOKS['config_page']['creditalert'] = '../../front/config.form.php?forcetab=' . urlencode('PluginCreditalertConfig$1');
        $PLUGIN_HOOKS['menu_toadd']['creditalert'] = ['tools' => PluginCreditalertMenu::class];
        $PLUGIN_HOOKS['menu_entries']['creditalert'] = true;
        $PLUGIN_HOOKS['submenu_entry']['creditalert']['search'] = 'front/creditlist.php';
        $PLUGIN_HOOKS['submenu_entry']['creditalert']['config'] = 'front/config.form.php';
    }
}

/**
 * Plugin metadata.
 *
 * @return array
 */
function plugin_version_creditalert()
{
    return [
        'name'         => __('Credit Alert', 'creditalert'),
        'version'      => PLUGIN_CREDITALERT_VERSION,
        'author'       => 'REINERT Joris',
        'license'      => 'GPLv3',
        'homepage'     => 'https://github.com/pluginsGLPI/creditalert',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_CREDITALERT_MIN_GLPI,
                'max' => PLUGIN_CREDITALERT_MAX_GLPI,
            ],
        ],
    ];
}

/**
 * Check prerequisites at installation time.
 *
 * @return bool
 */
function plugin_creditalert_check_prerequisites()
{
    if (version_compare(GLPI_VERSION, PLUGIN_CREDITALERT_MIN_GLPI, '<')) {
        return false;
    }
    if (version_compare(GLPI_VERSION, PLUGIN_CREDITALERT_MAX_GLPI, '>=')) {
        return false;
    }

    $plugin = new Plugin();
    if (!$plugin->isInstalled('credit')) {
        Session::addMessageAfterRedirect(__('Credit plugin must be installed to use CreditAlert', 'creditalert'), true, ERROR);
        return false;
    }
    return true;
}

/**
 * Configuration check callback.
 *
 * @param boolean $verbose
 *
 * @return boolean
 */
function plugin_creditalert_check_config($verbose = false)
{
    return true;
}
