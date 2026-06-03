<?php
/**
 * OUI lookup — identifica fabricante y tipo de dispositivo por los primeros 3 octetos de la MAC.
 * Fuente: base IEEE + registros del ambiente real.
 */
class PluginFreeradiusOui {

    // [vendor, device_type]  —  device_type: Computer | Phone | NetworkEquipment | Printer | Peripheral
    private static array $oui_table = [

        // ── LENOVO (laptops / desktops / workstations) ──────────────────────
        '00:45:E2' => ['Lenovo', 'Computer'],
        '04:EA:56' => ['Lenovo', 'Computer'],
        '14:B5:CD' => ['Lenovo', 'Computer'],
        '1C:64:F0' => ['Lenovo', 'Computer'],
        '28:0C:50' => ['Lenovo', 'Computer'],
        '2C:6D:C1' => ['Lenovo', 'Computer'],
        '2C:6E:85' => ['Lenovo', 'Computer'],
        '3C:21:9C' => ['Lenovo', 'Computer'],
        '40:45:DA' => ['Lenovo', 'Computer'],
        '44:38:E8' => ['Lenovo', 'Computer'],
        '48:45:E6' => ['Lenovo', 'Computer'],
        '48:5F:99' => ['Lenovo', 'Computer'],
        '54:13:79' => ['Lenovo', 'Computer'],
        '54:EE:75' => ['Lenovo', 'Computer'],
        '70:32:17' => ['Lenovo', 'Computer'],
        '7C:B2:7D' => ['Lenovo', 'Computer'],
        'A8:E2:91' => ['Lenovo', 'Computer'],
        'D0:39:57' => ['Lenovo', 'Computer'],
        'DC:1B:A1' => ['Lenovo', 'Computer'],
        'E4:AA:EA' => ['Lenovo', 'Computer'],
        'F8:9E:94' => ['Lenovo', 'Computer'],

        // ── MOTOROLA / LENOVO MOBILE (teléfonos Moto G, Moto E, etc.) ───────
        '00:B8:B6' => ['Motorola', 'Phone'],
        '04:D6:AA' => ['Motorola', 'Phone'],
        '0C:EC:8D' => ['Motorola', 'Phone'],
        '18:CE:94' => ['Motorola', 'Phone'],
        '18:FA:B7' => ['Motorola', 'Phone'],
        '28:12:D0' => ['Motorola', 'Phone'],
        '28:C2:1F' => ['Motorola', 'Phone'],
        '40:83:DE' => ['Motorola', 'Phone'],
        '44:1C:7F' => ['Motorola', 'Phone'],
        '48:61:EE' => ['Motorola', 'Phone'],
        '4C:3C:16' => ['Motorola', 'Phone'],
        '5C:AF:06' => ['Motorola', 'Phone'],
        '64:BC:0C' => ['Motorola', 'Phone'],
        '64:B5:F2' => ['Motorola', 'Phone'],
        '6C:55:63' => ['Motorola', 'Phone'],
        '70:AE:D5' => ['Motorola', 'Phone'],
        '70:5F:A3' => ['Motorola', 'Phone'],
        '80:6C:1B' => ['Motorola', 'Phone'],
        '80:9F:F5' => ['Motorola', 'Phone'],
        '84:5F:04' => ['Motorola', 'Phone'],
        '90:2C:09' => ['Motorola', 'Phone'],
        '90:4C:C5' => ['Motorola', 'Phone'],
        '94:E1:29' => ['Motorola', 'Phone'],
        '94:FB:29' => ['Motorola', 'Phone'],
        '98:FB:27' => ['Motorola', 'Phone'],
        'A0:64:8F' => ['Motorola', 'Phone'],
        'B0:E0:3C' => ['Motorola', 'Phone'],
        'B4:0B:1D' => ['Motorola', 'Phone'],
        'B8:A2:5D' => ['Motorola', 'Phone'],
        'B8:CF:BF' => ['Motorola', 'Phone'],
        'BC:FF:EB' => ['Motorola', 'Phone'],
        'C4:42:02' => ['Motorola', 'Phone'],
        'C8:C7:50' => ['Motorola', 'Phone'],
        'D4:38:9C' => ['Motorola', 'Phone'],
        'D4:5B:51' => ['Motorola', 'Phone'],
        'D8:CF:BF' => ['Motorola', 'Phone'],
        'DC:B5:4F' => ['Motorola', 'Phone'],
        'DC:BF:E9' => ['Motorola', 'Phone'],
        'E4:26:D5' => ['Motorola', 'Phone'],
        'E8:7F:6B' => ['Motorola', 'Phone'],
        'EC:ED:73' => ['Motorola', 'Phone'],
        'F0:CD:31' => ['Motorola', 'Phone'],
        'F8:CF:C5' => ['Motorola', 'Phone'],
        'FC:D4:36' => ['Motorola', 'Phone'],
        '00:E9:3A' => ['Motorola', 'Phone'],
        '8C:F1:12' => ['Motorola', 'Phone'],

        // ── SAMSUNG (teléfonos) ──────────────────────────────────────────────
        '00:17:C9' => ['Samsung', 'Phone'],
        '04:18:D6' => ['Samsung', 'Phone'],
        '1C:56:FE' => ['Samsung', 'Phone'],
        '28:11:A8' => ['Samsung', 'Phone'],
        '28:9F:04' => ['Samsung', 'Phone'],
        '2C:CF:67' => ['Samsung', 'Phone'],
        '34:23:87' => ['Samsung', 'Phone'],
        '54:09:10' => ['Samsung', 'Phone'],
        '64:11:A4' => ['Samsung', 'Phone'],
        '7C:C2:25' => ['Samsung', 'Phone'],
        '7C:C2:C6' => ['Samsung', 'Phone'],
        '9C:14:63' => ['Samsung', 'Phone'],
        '9C:5A:44' => ['Samsung', 'Phone'],
        'A8:6D:AA' => ['Samsung', 'Phone'],
        'BC:1D:89' => ['Samsung', 'Phone'],
        'BC:52:74' => ['Samsung', 'Phone'],
        'C0:D5:E2' => ['Samsung', 'Phone'],
        'FC:AA:81' => ['Samsung', 'Phone'],
        'FC:B9:DF' => ['Samsung', 'Phone'],
        '48:68:4A' => ['Samsung', 'Phone'],

        // ── APPLE (iPhones / iPads) ──────────────────────────────────────────
        'A8:66:7F' => ['Apple', 'Phone'],
        '04:F7:E4' => ['Apple', 'Phone'],
        '28:37:37' => ['Apple', 'Phone'],
        '3C:15:C2' => ['Apple', 'Phone'],
        '70:EC:E4' => ['Apple', 'Phone'],
        '78:7B:8A' => ['Apple', 'Phone'],
        'A4:B1:97' => ['Apple', 'Phone'],
        'F4:F1:5A' => ['Apple', 'Phone'],
        'AC:BC:32' => ['Apple', 'Computer'],

        // ── HUAWEI (teléfonos) ───────────────────────────────────────────────
        '04:BF:D5' => ['Huawei', 'Phone'],
        '18:47:3D' => ['Huawei', 'Phone'],
        '24:C8:6E' => ['Huawei', 'Phone'],
        '34:CD:BE' => ['Huawei', 'Phone'],
        '4C:1B:86' => ['Huawei', 'Phone'],
        '60:DE:44' => ['Huawei', 'Phone'],
        '78:1D:BA' => ['Huawei', 'Phone'],
        'A4:BD:C8' => ['Huawei', 'Phone'],

        // ── XIAOMI ───────────────────────────────────────────────────────────
        '04:CF:8C' => ['Xiaomi', 'Phone'],
        '28:6C:07' => ['Xiaomi', 'Phone'],
        '34:80:B3' => ['Xiaomi', 'Phone'],
        '58:44:98' => ['Xiaomi', 'Phone'],
        '64:09:80' => ['Xiaomi', 'Phone'],
        '74:51:BA' => ['Xiaomi', 'Phone'],
        'A8:9C:ED' => ['Xiaomi', 'Phone'],
        'B0:E2:35' => ['Xiaomi', 'Phone'],
        'D4:97:0B' => ['Xiaomi', 'Phone'],
        'F4:8B:32' => ['Xiaomi', 'Phone'],

        // ── ZTE / BLACKVIEW / OTROS ANDROID ─────────────────────────────────
        'E0:CA:94' => ['ZTE', 'Phone'],
        '5C:C9:D3' => ['ZTE', 'Phone'],
        '00:17:23' => ['Symbol/Zebra', 'Phone'],   // HH handheld scanners
        '40:9F:38' => ['Zebra', 'Phone'],
        '5C:3A:45' => ['Android', 'Phone'],
        '1C:EE:C9' => ['Blackview', 'Phone'],
        '08:31:A4' => ['Blackview', 'Phone'],

        // ── HP (laptops/impresoras) ──────────────────────────────────────────
        '3C:95:09' => ['HP', 'Computer'],
        '74:C6:3B' => ['HP', 'Computer'],
        'D8:C0:A6' => ['HP', 'Computer'],
        '9C:B6:54' => ['HP', 'Computer'],
        '38:63:BB' => ['HP', 'Computer'],
        '40:B0:34' => ['HP', 'Computer'],
        'B0:5A:DA' => ['HP', 'Computer'],
        '00:E8:01' => ['HP', 'Computer'],
        '10:60:4B' => ['HP', 'Printer'],
        '38:EA:A7' => ['HP', 'Printer'],
        'A0:D3:C1' => ['HP', 'Printer'],

        // ── DELL ─────────────────────────────────────────────────────────────
        '28:CD:C4' => ['Dell', 'Computer'],
        'A4:97:B1' => ['Dell', 'Computer'],
        'F8:3D:C6' => ['Dell', 'Computer'],
        '14:35:B7' => ['Dell', 'Computer'],
        '18:66:DA' => ['Dell', 'Computer'],
        '5C:BA:EF' => ['Dell', 'Computer'],

        // ── ASUS ─────────────────────────────────────────────────────────────
        '2C:56:DC' => ['Asus', 'Computer'],
        '74:D4:35' => ['Asus', 'Computer'],
        'AC:22:0B' => ['Asus', 'Computer'],
        'D0:17:C2' => ['Asus', 'Computer'],

        // ── TP-LINK / MERCUSYS ────────────────────────────────────────────────
        '48:A4:72' => ['TP-Link', 'NetworkEquipment'],
        '7C:01:3E' => ['TP-Link', 'NetworkEquipment'],
        'AC:07:75' => ['TP-Link', 'NetworkEquipment'],
        '50:3E:AA' => ['TP-Link', 'NetworkEquipment'],

        // ── CISCO ─────────────────────────────────────────────────────────────
        '00:1B:54' => ['Cisco', 'NetworkEquipment'],
        '00:40:9D' => ['Cisco', 'NetworkEquipment'],

        // ── VIRTUALES ─────────────────────────────────────────────────────────
        '00:50:56' => ['VMware', 'Computer'],
        '08:00:27' => ['VirtualBox', 'Computer'],
        '00:E0:4C' => ['Realtek', 'Computer'],
    ];

    /**
     * Prefijos de nombre → tipo de dispositivo.
     * Se aplica si el OUI no se encuentra en la tabla.
     */
    private static array $name_type_hints = [
        'cel'         => ['Celular', 'Phone'],
        'hh'          => ['Handheld', 'Phone'],
        'phone'       => ['Teléfono', 'Phone'],
        'iphone'      => ['iPhone', 'Phone'],
        'android'     => ['Android', 'Phone'],
        'tablet'      => ['Tablet', 'Phone'],
        'printer'     => ['Impresora', 'Printer'],
        'switch'      => ['Switch', 'NetworkEquipment'],
        'router'      => ['Router', 'NetworkEquipment'],
        'ap-'         => ['Access Point', 'NetworkEquipment'],
        'nb-'         => ['Notebook', 'Computer'],
        'nbtpr'       => ['Notebook', 'Computer'],
        'pc-'         => ['PC', 'Computer'],
        'laptop'      => ['Laptop', 'Computer'],
        'desktop'     => ['Desktop', 'Computer'],
        'lenovo'      => ['Lenovo', 'Computer'],
        'thinkpad'    => ['Lenovo ThinkPad', 'Computer'],
        'dell'        => ['Dell', 'Computer'],
        'hp'          => ['HP', 'Computer'],
        'asus'        => ['Asus', 'Computer'],
        'supgraneles' => ['Celular', 'Phone'],
        'inspeccion'  => ['Celular', 'Phone'],
        'black'       => ['Blackview', 'Phone'],
    ];

    public static function lookup(string $mac, string $device_name = ''): array {
        $mac_upper = strtoupper(str_replace(['-', '.'], ':', $mac));
        $parts     = explode(':', $mac_upper);

        if (count($parts) >= 3) {
            $oui = sprintf('%s:%s:%s', $parts[0], $parts[1], $parts[2]);
            if (isset(self::$oui_table[$oui])) {
                return [
                    'vendor'      => self::$oui_table[$oui][0],
                    'device_type' => self::$oui_table[$oui][1],
                ];
            }
        }

        // Fallback por nombre de dispositivo
        if ($device_name !== '') {
            $name_lower = strtolower($device_name);
            foreach (self::$name_type_hints as $prefix => $info) {
                if (str_starts_with($name_lower, $prefix)) {
                    return ['vendor' => $info[0], 'device_type' => $info[1]];
                }
            }
        }

        return ['vendor' => 'Desconocido', 'device_type' => 'Computer'];
    }

    public static function getDeviceTypeIcon(string $type): string {
        return match($type) {
            'Computer'         => 'ti ti-device-laptop',
            'Phone'            => 'ti ti-device-mobile',
            'NetworkEquipment' => 'ti ti-router',
            'Printer'          => 'ti ti-printer',
            'Peripheral'       => 'ti ti-device-usb',
            default            => 'ti ti-device-desktop',
        };
    }

    public static function getDeviceTypeLabel(string $type): string {
        return match($type) {
            'Computer'         => 'Computadora',
            'Phone'            => 'Teléfono/Celular',
            'NetworkEquipment' => 'Equipo de Red',
            'Printer'          => 'Impresora',
            'Peripheral'       => 'Periférico',
            default            => 'Otro',
        };
    }
}
