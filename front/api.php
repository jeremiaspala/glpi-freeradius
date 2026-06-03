<?php
include("../../../inc/includes.php");
Session::checkLoginUser();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'oui') {
    $mac = $_GET['mac'] ?? '';
    $result = PluginFreeradiusOui::lookup($mac);
    echo json_encode($result);
    exit;
}

if ($action === 'search_glpi') {
    $mac  = $_GET['mac'] ?? '';
    $matches = PluginFreeradiusDevice::searchGlpiInventory($mac);
    echo json_encode($matches);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
