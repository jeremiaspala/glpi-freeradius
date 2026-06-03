<?php
/**
 * FreeRADIUS sync engine
 * Maneja la comunicación SSH con el servidor FreeRADIUS
 */
class PluginFreeradiusRadiusSync {

    private array $cfg;

    public function __construct() {
        global $DB;
        $rows = $DB->request(['FROM' => 'glpi_plugin_freeradius_config', 'LIMIT' => 1]);
        $this->cfg = count($rows) ? $rows->current() : [];
    }

    private function ssh(string $cmd): array {
        $host    = $this->cfg['radius_host']    ?? '192.168.13.36';
        $user    = $this->cfg['radius_ssh_user'] ?? 'operador';
        $key     = $this->cfg['radius_ssh_key']  ?? '/var/www/.ssh/id_rsa';
        $khfile  = dirname($key) . '/known_hosts';

        $safe_cmd = escapeshellarg($cmd);
        // Usar /usr/bin/ssh con paths absolutos y HOME explícito para funcionar desde Apache (www-data sin HOME)
        $ssh_cmd  = "/usr/bin/ssh -i $key"
                  . " -o StrictHostKeyChecking=no"
                  . " -o BatchMode=yes"
                  . " -o ConnectTimeout=10"
                  . " -o UserKnownHostsFile=$khfile"
                  . " {$user}@{$host} $safe_cmd 2>&1";

        $output = [];
        $rc     = 0;
        exec($ssh_cmd, $output, $rc);

        return ['output' => implode("\n", $output), 'rc' => $rc];
    }

    private function sshSu(string $cmd): array {
        $su_pass = addslashes($this->cfg['radius_su_pass'] ?? '');
        $full    = "echo '$su_pass' | su root -c " . escapeshellarg($cmd) . " 2>&1; echo __EXIT__\$?";
        return $this->ssh($full);
    }

    // Lee el archivo authorize vía SSH+su
    public function readAuthorizeFile(): string {
        $file = $this->cfg['authorize_file'] ?? '/etc/freeradius/3.0/mods-config/files/authorize';
        $r    = $this->sshSu("cat $file");
        // Quitar la línea "Contraseña:"
        $lines = array_filter(explode("\n", $r['output']), fn($l) => !str_starts_with(trim($l), 'Contraseña:'));
        return implode("\n", $lines);
    }

    // Escribe el archivo authorize vía SSH+su
    public function writeAuthorizeFile(string $content): bool {
        $file    = $this->cfg['authorize_file'] ?? '/etc/freeradius/3.0/mods-config/files/authorize';
        $escaped = base64_encode($content);
        $cmd     = "echo '$escaped' | base64 -d | tee $file > /dev/null && chmod 644 $file";
        $r       = $this->sshSu($cmd);
        return $r['rc'] === 0 || str_contains($r['output'], '__EXIT__0');
    }

    // Recarga FreeRADIUS y retorna ['ok' => bool, 'msg' => string]
    public function reloadRadius(): array {
        // Usa wrapper con sudo NOPASSWD para evitar problemas de TTY desde PHP/Apache
        $r      = $this->ssh('sudo -n /usr/local/bin/freeradius-reload 2>&1');
        $output = trim($r['output']);
        $ok     = str_starts_with($output, 'OK:');
        return ['ok' => $ok, 'msg' => $output ?: 'Sin respuesta del servidor'];
    }

    // Parsea el authorize file y retorna array de dispositivos
    public function parseAuthorizeFile(string $content): array {
        $devices = [];
        $lines   = explode("\n", $content);
        $name    = $user = $mac = '';
        $vlan    = 0;

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^#\s+(\S+)\s*-\s*(.*)/', $line, $m)) {
                $name = $m[1];
                $user = trim($m[2]);
                $mac  = '';
                $vlan = 0;
                continue;
            }

            if (preg_match('/^([0-9a-fA-F]{2}(?::[0-9a-fA-F]{2}){5})\s+Cleartext-Password/i', $line, $m)) {
                $mac = strtolower($m[1]);
                continue;
            }

            if (preg_match('/Tunnel-Private-Group-Id\s*=\s*(\d+)/', $line, $m) && $mac !== '') {
                $vlan = (int)$m[1];
                $oui  = PluginFreeradiusOui::lookup($mac);
                $devices[] = [
                    'name'        => $name,
                    'username'    => $user,
                    'mac_address' => $mac,
                    'vlan'        => $vlan,
                    'oui_vendor'  => $oui['vendor'],
                    'device_type' => $oui['device_type'],
                    'status'      => 'authorized',
                ];
                $mac  = '';
                $vlan = 0;
            }
        }

        return $devices;
    }

    // Importa dispositivos del authorize file a la tabla de GLPI
    public function importFromRadius(): array {
        global $DB;

        $content = $this->readAuthorizeFile();
        if (empty(trim($content))) {
            return ['error' => 'No se pudo leer el archivo authorize. Verifique la conexión SSH.', 'count' => 0];
        }

        $devices  = $this->parseAuthorizeFile($content);
        $imported = 0;
        $updated  = 0;
        $errors   = [];

        foreach ($devices as $dev) {
            $existing = $DB->request([
                'FROM'  => 'glpi_plugin_freeradius_devices',
                'WHERE' => ['mac_address' => $dev['mac_address']],
                'LIMIT' => 1,
            ]);

            if (count($existing) === 0) {
                // Buscar en inventario GLPI por MAC
                $glpi_item = self::findGlpiItemByMac($dev['mac_address']);

                $data = [
                    'name'        => $dev['name'],
                    'mac_address' => $dev['mac_address'],
                    'vlan'        => $dev['vlan'],
                    'username'    => $dev['username'],
                    'oui_vendor'  => $dev['oui_vendor'],
                    'device_type' => $dev['device_type'],
                    'status'      => $dev['status'],
                    'date_creation' => date('Y-m-d H:i:s'),
                    'date_mod'      => date('Y-m-d H:i:s'),
                ];

                if ($glpi_item) {
                    $data['itemtype'] = $glpi_item['itemtype'];
                    $data['items_id'] = $glpi_item['items_id'];
                }

                try {
                    $DB->insert('glpi_plugin_freeradius_devices', $data);
                    $imported++;
                } catch (Exception $e) {
                    $errors[] = "Error al insertar {$dev['mac_address']}: " . $e->getMessage();
                }
            } else {
                $row = $existing->current();
                $DB->update('glpi_plugin_freeradius_devices', [
                    'name'       => $dev['name'],
                    'vlan'       => $dev['vlan'],
                    'username'   => $dev['username'],
                    'oui_vendor' => $dev['oui_vendor'],
                    'status'     => $dev['status'],
                    'date_mod'   => date('Y-m-d H:i:s'),
                ], ['id' => $row['id']]);
                $updated++;
            }
        }

        $DB->update('glpi_plugin_freeradius_config', ['last_sync' => date('Y-m-d H:i:s')], ['id' => 1]);

        return [
            'count'    => count($devices),
            'imported' => $imported,
            'updated'  => $updated,
            'errors'   => $errors,
        ];
    }

    // Genera y empuja el authorize file desde la DB de GLPI hacia FreeRADIUS
    public function pushToRadius(): array {
        global $DB;

        $devices = $DB->request([
            'FROM'    => 'glpi_plugin_freeradius_devices',
            'WHERE'   => ['status' => 'authorized', 'is_deleted' => 0],
            'ORDER'   => ['vlan ASC', 'name ASC'],
        ]);

        $content = '';
        foreach ($devices as $dev) {
            $mac  = $dev['mac_address'];
            $vlan = (int)$dev['vlan'];
            $name = $dev['name'];
            $user = $dev['username'] ? ' ' . $dev['username'] : '';
            $content .= "# {$name} -{$user}\n";
            $content .= "{$mac}    Cleartext-Password := \"{$mac}\"\n";
            $content .= "            Tunnel-Type = VLAN,\n";
            $content .= "            Tunnel-Medium-Type = 6,\n";
            $content .= "            Tunnel-Private-Group-Id = {$vlan}\n\n";
        }
        $content .= "DEFAULT    Auth-Type := Reject\n";

        $ok = $this->writeAuthorizeFile($content);
        if (!$ok) {
            return ['success' => false, 'message' => 'Error al escribir el archivo authorize en FreeRADIUS'];
        }

        $reload_msg = '';
        if (($this->cfg['auto_reload_radius'] ?? 1) == 1) {
            $reload = $this->reloadRadius();
            $reload_msg = $reload['msg'];
            if (!$reload['ok']) {
                $DB->update('glpi_plugin_freeradius_config', ['last_sync' => date('Y-m-d H:i:s')], ['id' => 1]);
                return [
                    'success' => false,
                    'message' => 'Archivo authorize escrito pero FreeRADIUS NO se recargó: ' . $reload['msg'],
                ];
            }
        }

        $DB->update('glpi_plugin_freeradius_config', ['last_sync' => date('Y-m-d H:i:s')], ['id' => 1]);

        return [
            'success' => true,
            'message' => 'Archivo authorize actualizado. ' . ($reload_msg ?: 'FreeRADIUS recargado correctamente.'),
        ];
    }

    // Obtiene MACs que aparecen en el log de RADIUS pero NO están en la DB
    public function getUnknownMacs(int $days = 7): array {
        global $DB;

        $logcmd = "grep 'Login' /var/log/freeradius/radius.log | tail -n 50000";
        $r      = $this->ssh($logcmd);

        $seen = [];
        foreach (explode("\n", $r['output']) as $line) {
            if (preg_match('/Login (OK|incorrect): \[([0-9a-f:]+)\//i', $line, $m)) {
                $mac    = strtolower($m[2]);
                $status = strtolower($m[1]) === 'ok' ? 'OK' : 'rejected';
                if (!isset($seen[$mac]) || $status === 'OK') {
                    $seen[$mac] = $status;
                }
            }
        }

        // Filtrar los que ya están en la DB
        if (empty($seen)) {
            return [];
        }

        $existing = [];
        foreach ($DB->request(['FROM' => 'glpi_plugin_freeradius_devices', 'FIELDS' => ['mac_address']]) as $row) {
            $existing[$row['mac_address']] = true;
        }

        $unknown = [];
        foreach ($seen as $mac => $status) {
            if (!isset($existing[$mac])) {
                $oui = PluginFreeradiusOui::lookup($mac);
                $unknown[] = [
                    'mac'         => $mac,
                    'status'      => $status,
                    'oui_vendor'  => $oui['vendor'],
                    'device_type' => $oui['device_type'],
                ];
            }
        }

        return $unknown;
    }

    // Busca en el inventario de GLPI un ítem con la MAC dada
    public static function findGlpiItemByMac(string $mac): ?array {
        global $DB;

        $rows = $DB->request([
            'FROM'   => 'glpi_networkports',
            'WHERE'  => ['mac' => $mac],
            'LIMIT'  => 1,
        ]);

        if (count($rows) === 0) {
            return null;
        }

        $row = $rows->current();
        return [
            'itemtype' => $row['itemtype'],
            'items_id' => $row['items_id'],
        ];
    }

    // Obtiene el nombre del ítem GLPI vinculado a un dispositivo
    public static function getGlpiItemName(string $itemtype, int $items_id): string {
        if (empty($itemtype) || empty($items_id)) {
            return '';
        }
        global $DB;
        $table = getTableForItemType($itemtype);
        if (!$table || !$DB->tableExists($table)) {
            return '';
        }
        $rows = $DB->request(['FROM' => $table, 'WHERE' => ['id' => $items_id], 'LIMIT' => 1]);
        if (count($rows) === 0) {
            return '';
        }
        $row = $rows->current();
        return $row['name'] ?? '';
    }

    public function testConnection(): array {
        $r = $this->ssh('echo connection_ok');
        return [
            'ssh'  => str_contains($r['output'], 'connection_ok'),
            'msg'  => $r['output'],
        ];
    }
}
