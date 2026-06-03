<?php
/**
 * FreeRADIUS Plugin for GLPI 11
 * hook.php - Install / Uninstall
 */

function plugin_freeradius_install() {
    global $DB;

    // Tabla principal de dispositivos
    if (!$DB->tableExists('glpi_plugin_freeradius_devices')) {
        $DB->queryOrDie("CREATE TABLE `glpi_plugin_freeradius_devices` (
          `id`            int NOT NULL AUTO_INCREMENT,
          `name`          varchar(255) NOT NULL DEFAULT '',
          `mac_address`   varchar(17)  NOT NULL DEFAULT '',
          `vlan`          int NOT NULL DEFAULT 0,
          `username`      varchar(255) DEFAULT NULL,
          `description`   text,
          `oui_vendor`    varchar(255) DEFAULT NULL,
          `device_type`   varchar(100) DEFAULT 'Computer',
          `status`        varchar(20)  NOT NULL DEFAULT 'authorized',
          `itemtype`      varchar(100) DEFAULT NULL,
          `items_id`      int DEFAULT NULL,
          `is_deleted`    tinyint(1)  NOT NULL DEFAULT 0,
          `users_id`      int DEFAULT NULL,
          `date_creation` datetime DEFAULT NULL,
          `date_mod`      datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `mac_address` (`mac_address`),
          KEY `vlan` (`vlan`),
          KEY `status` (`status`),
          KEY `itemtype_items` (`itemtype`, `items_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "Error creating freeradius devices table");
    }

    // Tabla de VLANs
    if (!$DB->tableExists('glpi_plugin_freeradius_vlans')) {
        $DB->queryOrDie("CREATE TABLE `glpi_plugin_freeradius_vlans` (
          `id`          int NOT NULL AUTO_INCREMENT,
          `vlan_id`     int NOT NULL,
          `name`        varchar(255) NOT NULL DEFAULT '',
          `description` text,
          `color`       varchar(10) DEFAULT '#6c757d',
          PRIMARY KEY (`id`),
          UNIQUE KEY `vlan_id` (`vlan_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "Error creating freeradius vlans table");

        // VLANs por defecto (basado en los datos reales del ambiente)
        $default_vlans = [
            [5,    'VLAN-5',       '#dc3545'],
            [10,   'VLAN-10',      '#fd7e14'],
            [20,   'VLAN-20',      '#ffc107'],
            [45,   'VLAN-45',      '#198754'],
            [50,   'VLAN-50',      '#0dcaf0'],
            [55,   'VLAN-55',      '#0d6efd'],
            [100,  'VLAN-100',     '#6610f2'],
            [110,  'VLAN-110',     '#d63384'],
            [120,  'VLAN-120',     '#20c997'],
            [300,  'VLAN-300',     '#adb5bd'],
            [1010, 'VLAN-1010',    '#212529'],
        ];
        foreach ($default_vlans as $v) {
            $DB->insert('glpi_plugin_freeradius_vlans', [
                'vlan_id'     => $v[0],
                'name'        => $v[1],
                'description' => '',
                'color'       => $v[2],
            ]);
        }
    }

    // Tabla de configuración
    if (!$DB->tableExists('glpi_plugin_freeradius_config')) {
        $DB->queryOrDie("CREATE TABLE `glpi_plugin_freeradius_config` (
          `id`                    int NOT NULL AUTO_INCREMENT,
          `radius_host`           varchar(255) DEFAULT '192.168.13.36',
          `radius_ssh_user`       varchar(100) DEFAULT 'operador',
          `radius_ssh_key`        varchar(500) DEFAULT '/var/www/.ssh/id_rsa',
          `radius_su_pass`        varchar(255) DEFAULT '',
          `radius_db_host`        varchar(255) DEFAULT '127.0.0.1',
          `radius_db_user`        varchar(100) DEFAULT 'freeradius',
          `radius_db_pass`        varchar(255) DEFAULT '',
          `radius_db_name`        varchar(100) DEFAULT 'freeradius',
          `authorize_file`        varchar(500) DEFAULT '/etc/freeradius/3.0/mods-config/files/authorize',
          `last_sync`             timestamp NULL DEFAULT NULL,
          `auto_reload_radius`    tinyint(1) DEFAULT 1,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "Error creating freeradius config table");

        $DB->insert('glpi_plugin_freeradius_config', [
            'id'                 => 1,
            'radius_host'        => '192.168.13.36',
            'radius_ssh_user'    => 'operador',
            'radius_ssh_key'     => '/var/www/.ssh/id_rsa',
            'radius_su_pass'     => '30ochocero',
            'radius_db_host'     => '127.0.0.1',
            'radius_db_user'     => 'freeradius',
            'radius_db_pass'     => 'freeGuy28',
            'radius_db_name'     => 'freeradius',
            'authorize_file'     => '/etc/freeradius/3.0/mods-config/files/authorize',
            'auto_reload_radius' => 1,
        ]);
    }

    return true;
}

function plugin_freeradius_uninstall() {
    global $DB;
    foreach (['glpi_plugin_freeradius_devices', 'glpi_plugin_freeradius_vlans', 'glpi_plugin_freeradius_config'] as $table) {
        if ($DB->tableExists($table)) {
            $DB->queryOrDie("DROP TABLE `$table`");
        }
    }
    return true;
}
