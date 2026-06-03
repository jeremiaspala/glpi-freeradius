<?php
/**
 * FreeRADIUS Device model
 */
class PluginFreeradiusDevice extends CommonDBTM {

    static $rightname = 'plugin_freeradius';

    public static function getTypeName($nb = 0) {
        return 'Dispositivo FreeRADIUS';
    }

    public static function getIcon() {
        return 'ti ti-device-laptop';
    }

    public static function getStatusOptions(): array {
        return [
            'authorized' => ['label' => 'Autorizado',  'color' => 'success', 'icon' => 'ti ti-circle-check'],
            'blocked'    => ['label' => 'Bloqueado',   'color' => 'danger',  'icon' => 'ti ti-ban'],
            'pending'    => ['label' => 'Pendiente',   'color' => 'warning', 'icon' => 'ti ti-clock'],
        ];
    }

    public static function getVlanColor(int $vlan): string {
        global $DB;
        $rows = $DB->request(['FROM' => 'glpi_plugin_freeradius_vlans', 'WHERE' => ['vlan_id' => $vlan], 'LIMIT' => 1]);
        return count($rows) ? ($rows->current()['color'] ?? '#6c757d') : '#6c757d';
    }

    public static function getVlanName(int $vlan): string {
        global $DB;
        $rows = $DB->request(['FROM' => 'glpi_plugin_freeradius_vlans', 'WHERE' => ['vlan_id' => $vlan], 'LIMIT' => 1]);
        return count($rows) ? ($rows->current()['name'] ?? "VLAN $vlan") : "VLAN $vlan";
    }

    // Retorna todos los ítems del inventario GLPI que podrían coincidir con la MAC
    public static function searchGlpiInventory(string $mac): array {
        global $DB;

        $results = [];

        // Buscar por MAC exacta en networkports
        $np = $DB->request([
            'FROM'  => 'glpi_networkports',
            'WHERE' => ['mac' => $mac],
        ]);

        foreach ($np as $port) {
            $itemtype = $port['itemtype'];
            $items_id = $port['items_id'];
            $table    = getTableForItemType($itemtype);

            if (!$table || !$DB->tableExists($table)) continue;

            $item_rows = $DB->request(['FROM' => $table, 'WHERE' => ['id' => $items_id], 'LIMIT' => 1]);
            if (count($item_rows) === 0) continue;

            $item = $item_rows->current();
            $results[] = [
                'itemtype'   => $itemtype,
                'items_id'   => $items_id,
                'name'       => $item['name'] ?? "ID $items_id",
                'match_type' => 'mac_exact',
            ];
        }

        return $results;
    }

    // Retorna los VLANs únicos actualmente usados en devices
    public static function getUsedVlans(): array {
        global $DB;
        $vlans = [];
        foreach ($DB->request(['FROM' => 'glpi_plugin_freeradius_devices', 'FIELDS' => ['vlan'], 'GROUPBY' => ['vlan'], 'ORDER' => ['vlan ASC']]) as $row) {
            $vlans[] = (int)$row['vlan'];
        }
        return $vlans;
    }

    public static function getStats(): array {
        global $DB;

        $total = countElementsInTable('glpi_plugin_freeradius_devices', ['is_deleted' => 0]);
        $authorized = countElementsInTable('glpi_plugin_freeradius_devices', ['status' => 'authorized', 'is_deleted' => 0]);
        $blocked    = countElementsInTable('glpi_plugin_freeradius_devices', ['status' => 'blocked', 'is_deleted' => 0]);
        $linked     = $DB->request([
            'COUNT' => 'id',
            'FROM'  => 'glpi_plugin_freeradius_devices',
            'WHERE' => ['NOT' => ['itemtype' => null], 'is_deleted' => 0],
        ])->current()['cpt'] ?? 0;

        // Por VLAN
        $by_vlan = [];
        foreach ($DB->request([
            'FROM'    => 'glpi_plugin_freeradius_devices',
            'FIELDS'  => ['vlan'],
            'WHERE'   => ['is_deleted' => 0],
            'GROUPBY' => ['vlan'],
            'ORDER'   => ['vlan ASC'],
        ]) as $row) {
            $vlan = (int)$row['vlan'];
            $count = countElementsInTable('glpi_plugin_freeradius_devices', ['vlan' => $vlan, 'is_deleted' => 0]);
            $by_vlan[] = [
                'vlan'  => $vlan,
                'name'  => self::getVlanName($vlan),
                'color' => self::getVlanColor($vlan),
                'count' => $count,
            ];
        }

        // Por tipo
        $by_type = [];
        foreach ($DB->request([
            'FROM'    => 'glpi_plugin_freeradius_devices',
            'FIELDS'  => ['device_type'],
            'WHERE'   => ['is_deleted' => 0],
            'GROUPBY' => ['device_type'],
        ]) as $row) {
            $t = $row['device_type'] ?: 'Otro';
            $count = countElementsInTable('glpi_plugin_freeradius_devices', ['device_type' => $row['device_type'], 'is_deleted' => 0]);
            $by_type[] = ['type' => $t, 'count' => $count, 'label' => PluginFreeradiusOui::getDeviceTypeLabel($t)];
        }

        return compact('total', 'authorized', 'blocked', 'linked', 'by_vlan', 'by_type');
    }
}
