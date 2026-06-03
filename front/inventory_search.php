<?php
/**
 * AJAX endpoint: busca ítems en el inventario GLPI y retorna sus MACs.
 * Usado por el formulario de dispositivo para seleccionar ítem + MAC.
 */
include("../../../inc/includes.php");
Session::checkLoginUser();
header('Content-Type: application/json; charset=utf-8');

global $DB;

$action = $_GET['action'] ?? '';

// ── Buscar ítems por nombre ────────────────────────────────────────────────
if ($action === 'search') {
    $q    = trim($_GET['q'] ?? '');
    $type = $_GET['type'] ?? ''; // Computer, Phone, NetworkEquipment, Unmanaged, ''=todos

    if (strlen($q) < 2) {
        echo json_encode([]);
        exit;
    }

    $safe = $DB->escape($q);
    $results = [];

    $item_types = $type ? [$type] : ['Computer', 'NetworkEquipment', 'Phone', 'Unmanaged'];

    foreach ($item_types as $itype) {
        $table = getTableForItemType($itype);
        if (!$table || !$DB->tableExists($table)) continue;

        $rows = $DB->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => $table,
            'WHERE'  => [
                'is_deleted' => 0,
                ['name' => ['LIKE', "%$safe%"]],
            ],
            'ORDER'  => ['name ASC'],
            'LIMIT'  => 15,
        ]);

        foreach ($rows as $row) {
            $results[] = [
                'id'       => $row['id'],
                'name'     => $row['name'],
                'itemtype' => $itype,
                'label'    => "[{$itype}] {$row['name']}",
            ];
        }
    }

    // Limitar total a 30
    usort($results, fn($a,$b) => strcmp($a['name'], $b['name']));
    echo json_encode(array_slice($results, 0, 30));
    exit;
}

// ── Obtener MACs de un ítem ────────────────────────────────────────────────
if ($action === 'macs') {
    $itemtype = $_GET['itemtype'] ?? '';
    $items_id = (int)($_GET['items_id'] ?? 0);

    if (!$itemtype || !$items_id) {
        echo json_encode([]);
        exit;
    }

    $macs = [];
    $np_rows = $DB->request([
        'SELECT'  => ['id', 'name', 'mac', 'instantiation_type'],
        'FROM'    => 'glpi_networkports',
        'WHERE'   => [
            'itemtype' => $itemtype,
            'items_id' => $items_id,
            ['NOT' => ['mac' => '']],
            ['NOT' => ['mac' => '00:00:00:00:00:00']],
        ],
        'ORDER'   => ['name ASC'],
    ]);

    foreach ($np_rows as $np) {
        // Check if this MAC is already in FreeRADIUS
        $in_radius = $DB->request([
            'COUNT' => 'id',
            'FROM'  => 'glpi_plugin_freeradius_devices',
            'WHERE' => ['mac_address' => strtolower($np['mac']), 'is_deleted' => 0],
        ])->current()['cpt'] ?? 0;

        $macs[] = [
            'port_id'   => $np['id'],
            'port_name' => $np['name'],
            'mac'       => strtolower($np['mac']),
            'in_radius' => (bool)$in_radius,
        ];
    }

    echo json_encode($macs);
    exit;
}

// ── Buscar usuarios ────────────────────────────────────────────────────────
if ($action === 'users') {
    $q    = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) { echo json_encode([]); exit; }

    $safe = $DB->escape($q);
    $rows = $DB->request([
        'SELECT' => ['id', 'name', 'firstname', 'realname'],
        'FROM'   => 'glpi_users',
        'WHERE'  => [
            'is_deleted' => 0,
            'is_active'  => 1,
            ['OR' => [
                ['name'     => ['LIKE', "%$safe%"]],
                ['realname' => ['LIKE', "%$safe%"]],
                ['firstname'=> ['LIKE', "%$safe%"]],
            ]],
        ],
        'ORDER'  => ['realname ASC', 'name ASC'],
        'LIMIT'  => 20,
    ]);

    $results = [];
    foreach ($rows as $row) {
        $full = trim(($row['firstname'] ?? '') . ' ' . ($row['realname'] ?? ''));
        $results[] = [
            'id'    => $row['id'],
            'name'  => $row['name'],
            'full'  => $full ?: $row['name'],
            'label' => $full ? "$full ({$row['name']})" : $row['name'],
        ];
    }
    echo json_encode($results);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
