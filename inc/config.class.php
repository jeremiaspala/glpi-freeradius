<?php
class PluginFreeradiusConfig extends CommonDBTM {
    static $rightname = 'config';

    public static function getTypeName($nb = 0) {
        return 'Configuración FreeRADIUS';
    }

    public static function get(): array {
        global $DB;
        $rows = $DB->request(['FROM' => 'glpi_plugin_freeradius_config', 'LIMIT' => 1]);
        return count($rows) ? $rows->current() : [];
    }

    public static function save(array $data): void {
        global $DB;
        $rows = $DB->request(['FROM' => 'glpi_plugin_freeradius_config', 'LIMIT' => 1]);
        if (count($rows) === 0) {
            $DB->insert('glpi_plugin_freeradius_config', array_merge(['id' => 1], $data));
        } else {
            $DB->update('glpi_plugin_freeradius_config', $data, ['id' => 1]);
        }
    }
}
