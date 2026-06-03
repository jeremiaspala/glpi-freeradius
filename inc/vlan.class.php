<?php
class PluginFreeradiusVlan extends CommonDBTM {
    static $rightname = 'plugin_freeradius';

    public static function getTypeName($nb = 0) {
        return 'VLAN';
    }

    public static function getAll(): array {
        global $DB;
        $vlans = [];
        foreach ($DB->request(['FROM' => 'glpi_plugin_freeradius_vlans', 'ORDER' => ['vlan_id ASC']]) as $row) {
            $vlans[$row['vlan_id']] = $row;
        }
        return $vlans;
    }
}
