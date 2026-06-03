<?php
/**
 * FreeRADIUS Plugin for GLPI 11
 * Gestión de dispositivos autenticados por MAC en FreeRADIUS (MAB/VLAN)
 */

define('PLUGIN_FREERADIUS_VERSION', '1.0.0');
define('PLUGIN_FREERADIUS_MIN_GLPI', '11.0.0');
define('PLUGIN_FREERADIUS_MAX_GLPI', '12.0.0');

function plugin_version_freeradius() {
    return [
        'name'           => 'FreeRADIUS',
        'version'        => PLUGIN_FREERADIUS_VERSION,
        'author'         => 'Jeremías Palazzesi',
        'license'        => 'GPL v2+',
        'homepage'       => 'https://www.nerdadas.com',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_FREERADIUS_MIN_GLPI,
                'max' => PLUGIN_FREERADIUS_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_freeradius_check_prerequisites() {
    if (version_compare(GLPI_VERSION, PLUGIN_FREERADIUS_MIN_GLPI, 'lt')
        || version_compare(GLPI_VERSION, PLUGIN_FREERADIUS_MAX_GLPI, 'ge')) {
        echo "Este plugin requiere GLPI >= " . PLUGIN_FREERADIUS_MIN_GLPI . " y < " . PLUGIN_FREERADIUS_MAX_GLPI;
        return false;
    }
    return true;
}

function plugin_freeradius_check_config() {
    return true;
}

function plugin_freeradius_init() {
    global $PLUGIN_HOOKS;

    Plugin::registerClass('PluginFreeradiusDevice');
    Plugin::registerClass('PluginFreeradiusVlan');
    Plugin::registerClass('PluginFreeradiusConfig');

    $PLUGIN_HOOKS['redefine_menus']['freeradius'] = function($menu) {
        $menu['freeradius'] = [
            'title'   => 'FreeRADIUS',
            'icon'    => 'ti ti-wifi',
            'default' => '/plugins/freeradius/front/dashboard.php',
            'content' => [
                'dashboard' => ['title' => 'Dashboard',    'page' => '/plugins/freeradius/front/dashboard.php',  'icon' => 'ti ti-chart-bar'],
                'devices'   => ['title' => 'Dispositivos', 'page' => '/plugins/freeradius/front/device.php',     'icon' => 'ti ti-device-laptop'],
                'vlans'     => ['title' => 'VLANs',        'page' => '/plugins/freeradius/front/vlan.php',       'icon' => 'ti ti-network'],
                'sync'      => ['title' => 'Sincronizar',  'page' => '/plugins/freeradius/front/sync.php',       'icon' => 'ti ti-refresh'],
                'merge'     => ['title' => 'Merge',           'page' => '/plugins/freeradius/front/merge.php',      'icon' => 'ti ti-git-merge'],
                'config'    => ['title' => 'Configuración','page' => '/plugins/freeradius/front/config.php',     'icon' => 'ti ti-settings'],
            ],
        ];
        return $menu;
    };
}

function plugin_init_freeradius() {
    plugin_freeradius_init();
}

function plugin_freeradius_getMenuContent() {
    $menu = [];
    $menu['title'] = 'FreeRADIUS';
    $menu['page']  = '/plugins/freeradius/front/dashboard.php';
    $menu['icon']  = 'ti ti-wifi';
    return $menu;
}
