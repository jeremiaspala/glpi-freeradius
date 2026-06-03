<?php
include("../../../inc/includes.php");
Session::checkLoginUser();
global $CFG_GLPI;

global $DB;

// ── Acciones POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Vincular un dispositivo RADIUS con un ítem GLPI
    if ($action === 'link') {
        $fr_id    = (int)$_POST['fr_id'];
        $itemtype = trim($_POST['itemtype'] ?? '');
        $items_id = (int)$_POST['items_id'];
        if ($fr_id && $itemtype && $items_id) {
            $DB->update('glpi_plugin_freeradius_devices',
                ['itemtype' => $itemtype, 'items_id' => $items_id],
                ['id' => $fr_id]);
        }
        Html::redirect($CFG_GLPI['root_doc'] . '/plugins/freeradius/front/merge.php?linked=1');
        exit;
    }

    // Agregar ítem GLPI a FreeRADIUS con su MAC
    if ($action === 'add_to_radius') {
        $itemtype = trim($_POST['itemtype'] ?? '');
        $items_id = (int)$_POST['items_id'];
        $mac      = strtolower(trim($_POST['mac'] ?? ''));
        $name     = trim($_POST['name'] ?? '');
        $vlan     = (int)($_POST['vlan'] ?? 45);

        if ($mac && $name) {
            $oui = PluginFreeradiusOui::lookup($mac, $name);
            $DB->insert('glpi_plugin_freeradius_devices', [
                'name'          => $name,
                'mac_address'   => $mac,
                'vlan'          => $vlan,
                'device_type'   => $oui['device_type'],
                'oui_vendor'    => $oui['vendor'],
                'status'        => 'authorized',
                'itemtype'      => $itemtype ?: null,
                'items_id'      => $items_id ?: null,
                'is_deleted'    => 0,
                'date_creation' => date('Y-m-d H:i:s'),
                'date_mod'      => date('Y-m-d H:i:s'),
            ]);
        }
        Html::redirect($CFG_GLPI['root_doc'] . '/plugins/freeradius/front/merge.php?added=1');
        exit;
    }

    // Ignorar (marcar como revisado — simplemente no hacemos nada, solo redirigimos)
    Html::redirect($CFG_GLPI['root_doc'] . '/plugins/freeradius/front/merge.php');
    exit;
}

// ── Datos para el merge ────────────────────────────────────────────────────

// 1) FreeRADIUS sin vínculo GLPI
$fr_unlinked = iterator_to_array($DB->request([
    'FROM'  => 'glpi_plugin_freeradius_devices',
    'WHERE' => ['is_deleted' => 0, 'itemtype' => null],
    'ORDER' => ['name ASC'],
]));

// 2) GLPI computers con MACs que NO están en FreeRADIUS
// Subconsulta: MACs ya en radius
$radius_macs_q = $DB->request([
    'SELECT' => ['mac_address'],
    'FROM'   => 'glpi_plugin_freeradius_devices',
    'WHERE'  => ['is_deleted' => 0],
]);
$radius_macs = [];
foreach ($radius_macs_q as $r) $radius_macs[] = $r['mac_address'];

// Buscar networkports con MACs no en FreeRADIUS
// Filtramos en PHP para evitar NOT IN con cientos de valores (causa query gigante)
$glpi_not_in_radius = [];
$radius_macs_index  = array_flip($radius_macs); // para O(1) lookup

$np_rows = $DB->request([
    'FROM'  => 'glpi_networkports',
    'WHERE' => ['NOT' => ['mac' => ['', '00:00:00:00:00:00']]],
    'LIMIT' => 2000,
]);

foreach ($np_rows as $np) {
    $mac = strtolower($np['mac']);
    // Saltar si ya está en FreeRADIUS
    if (isset($radius_macs_index[$mac])) continue;

    $itype = $np['itemtype'];
    $table = getTableForItemType($itype);
    if (!$table || !$DB->tableExists($table)) continue;

    $item = $DB->request([
        'FROM'  => $table,
        'WHERE' => ['id' => $np['items_id'], 'is_deleted' => 0],
        'LIMIT' => 1,
    ]);
    if (!count($item)) continue;
    $item = $item->current();

    $glpi_not_in_radius[] = [
        'itemtype'  => $itype,
        'items_id'  => $np['items_id'],
        'name'      => $item['name'],
        'mac'       => $mac,
        'port_name' => $np['name'],
    ];

    // Limitar resultados para no saturar la UI
    if (count($glpi_not_in_radius) >= 300) break;
}

// 3) Sugerencias automáticas de match por nombre similar
$suggestions = [];
foreach ($fr_unlinked as $fr) {
    $fr_name_clean = strtolower(preg_replace('/[^a-z0-9]/i', '', $fr['name']));
    foreach (['Computer', 'NetworkEquipment', 'Phone', 'Unmanaged'] as $itype) {
        $table = getTableForItemType($itype);
        if (!$table || !$DB->tableExists($table)) continue;
        $safe = $DB->escape(substr($fr['name'], 0, 20));
        $candidates = $DB->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => $table,
            'WHERE'  => ['is_deleted' => 0, ['name' => ['LIKE', "%$safe%"]]],
            'LIMIT'  => 3,
        ]);
        foreach ($candidates as $c) {
            $c_clean = strtolower(preg_replace('/[^a-z0-9]/i', '', $c['name']));
            similar_text($fr_name_clean, $c_clean, $pct);
            if ($pct >= 70) {
                $suggestions[] = [
                    'fr'       => $fr,
                    'itemtype' => $itype,
                    'items_id' => $c['id'],
                    'name'     => $c['name'],
                    'score'    => round($pct),
                ];
            }
        }
    }
}
usort($suggestions, fn($a,$b) => $b['score'] - $a['score']);
// Deduplicar: un fr_id solo una sugerencia (la mejor)
$seen_fr = [];
$suggestions = array_filter($suggestions, function($s) use (&$seen_fr) {
    $key = $s['fr']['id'];
    if (isset($seen_fr[$key])) return false;
    $seen_fr[$key] = true;
    return true;
});

$vlans = PluginFreeradiusVlan::getAll();

Html::header('FreeRADIUS - Merge/Reconciliación', $_SERVER['PHP_SELF'], 'plugins', 'freeradius');
?>
<div class="container-fluid px-4 py-3">
  <div class="d-flex align-items-center gap-2 mb-4">
    <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i>Dashboard</a>
    <h2 class="mb-0 ms-2"><i class="ti ti-git-merge me-2 text-primary"></i>Merge / Reconciliación</h2>
    <span class="ms-auto text-muted small">Compara FreeRADIUS con el inventario de GLPI</span>
  </div>

  <?php if (isset($_GET['linked'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="ti ti-check me-1"></i>Dispositivo vinculado correctamente.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>
  <?php if (isset($_GET['added'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="ti ti-check me-1"></i>Dispositivo agregado a FreeRADIUS.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <!-- KPIs -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card border-0 shadow-sm text-center py-3">
        <div class="fs-1 fw-bold text-warning"><?= count($fr_unlinked) ?></div>
        <div class="text-muted small">En RADIUS, sin vínculo GLPI</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm text-center py-3">
        <div class="fs-1 fw-bold text-info"><?= count($glpi_not_in_radius) ?></div>
        <div class="text-muted small">En inventario GLPI, no están en RADIUS</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm text-center py-3">
        <div class="fs-1 fw-bold text-success"><?= count($suggestions) ?></div>
        <div class="text-muted small">Coincidencias sugeridas por nombre</div>
      </div>
    </div>
  </div>

  <!-- TABS -->
  <ul class="nav nav-tabs mb-0" id="mergeTabs">
    <li class="nav-item">
      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab_suggestions">
        <i class="ti ti-wand me-1"></i>Sugerencias automáticas
        <?php if (count($suggestions)): ?><span class="badge bg-success ms-1"><?= count($suggestions) ?></span><?php endif; ?>
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab_fr_unlinked">
        <i class="ti ti-wifi me-1"></i>RADIUS sin GLPI
        <span class="badge bg-warning text-dark ms-1"><?= count($fr_unlinked) ?></span>
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab_glpi_missing">
        <i class="ti ti-device-laptop me-1"></i>GLPI sin RADIUS
        <span class="badge bg-info ms-1"><?= count($glpi_not_in_radius) ?></span>
      </button>
    </li>
  </ul>

  <div class="tab-content border border-top-0 rounded-bottom shadow-sm bg-white">

    <!-- ── Tab 1: Sugerencias ── -->
    <div class="tab-pane fade show active p-3" id="tab_suggestions">
      <?php if (empty($suggestions)): ?>
      <p class="text-muted text-center py-4"><i class="ti ti-mood-happy d-block fs-1 mb-2"></i>Sin sugerencias — los datos están en buen estado.</p>
      <?php else: ?>
      <p class="text-muted small mb-3">
        Dispositivos en FreeRADIUS que tienen un ítem GLPI con nombre similar (≥70% de coincidencia).
        Confirmá si son el mismo equipo.
      </p>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Dispositivo FreeRADIUS</th>
              <th>MAC</th>
              <th>Coincidencia</th>
              <th>Ítem GLPI sugerido</th>
              <th class="text-end">Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($suggestions as $s): ?>
            <tr>
              <td>
                <a href="device.form.php?id=<?= $s['fr']['id'] ?>" class="fw-semibold text-decoration-none">
                  <?= htmlspecialchars($s['fr']['name']) ?>
                </a>
                <div class="text-muted small">VLAN <?= $s['fr']['vlan'] ?></div>
              </td>
              <td><code class="small"><?= $s['fr']['mac_address'] ?></code></td>
              <td>
                <div class="d-flex align-items-center gap-1">
                  <div class="progress flex-grow-1" style="height:8px;min-width:60px">
                    <div class="progress-bar bg-<?= $s['score']>=90?'success':($s['score']>=75?'warning':'danger') ?>"
                         style="width:<?= $s['score'] ?>%"></div>
                  </div>
                  <span class="small fw-bold"><?= $s['score'] ?>%</span>
                </div>
              </td>
              <td>
                <span class="badge bg-secondary me-1"><?= $s['itemtype'] ?></span>
                <strong><?= htmlspecialchars($s['name']) ?></strong>
              </td>
              <td class="text-end">
                <form method="POST" class="d-inline">
                  <input type="hidden" name="_glpi_csrf_token" value="<?= Session::getNewCSRFToken() ?>">
                  <input type="hidden" name="action"   value="link">
                  <input type="hidden" name="fr_id"    value="<?= $s['fr']['id'] ?>">
                  <input type="hidden" name="itemtype" value="<?= $s['itemtype'] ?>">
                  <input type="hidden" name="items_id" value="<?= $s['items_id'] ?>">
                  <button type="submit" class="btn btn-xs btn-success">
                    <i class="ti ti-link me-1"></i>Vincular
                  </button>
                </form>
                <a href="device.form.php?id=<?= $s['fr']['id'] ?>" class="btn btn-xs btn-outline-secondary ms-1">
                  <i class="ti ti-edit"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── Tab 2: RADIUS sin GLPI ── -->
    <div class="tab-pane fade p-3" id="tab_fr_unlinked">
      <p class="text-muted small mb-3">
        Dispositivos autorizados en FreeRADIUS que no tienen ningún ítem de inventario vinculado en GLPI.
        Podés vincularlos manualmente o editarlos.
      </p>
      <?php if (empty($fr_unlinked)): ?>
      <p class="text-muted text-center py-4"><i class="ti ti-circle-check d-block fs-1 text-success mb-2"></i>Todos los dispositivos de RADIUS tienen vínculo en GLPI.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Nombre</th>
              <th>MAC</th>
              <th>Fabricante</th>
              <th>VLAN</th>
              <th>Usuario</th>
              <th class="text-end">Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($fr_unlinked as $fr): ?>
            <tr>
              <td class="fw-semibold">
                <i class="<?= PluginFreeradiusOui::getDeviceTypeIcon($fr['device_type'] ?? 'Computer') ?> text-muted me-1"></i>
                <?= htmlspecialchars($fr['name']) ?>
              </td>
              <td><code class="small"><?= $fr['mac_address'] ?></code></td>
              <td class="text-muted small"><?= htmlspecialchars($fr['oui_vendor'] ?? '—') ?></td>
              <td><span class="badge" style="background-color:<?= PluginFreeradiusDevice::getVlanColor((int)$fr['vlan']) ?>">VLAN <?= $fr['vlan'] ?></span></td>
              <td class="text-muted small"><?= htmlspecialchars($fr['username'] ?? '') ?></td>
              <td class="text-end">
                <a href="device.form.php?id=<?= $fr['id'] ?>" class="btn btn-xs btn-outline-primary">
                  <i class="ti ti-link me-1"></i>Vincular / Editar
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── Tab 3: GLPI sin RADIUS ── -->
    <div class="tab-pane fade p-3" id="tab_glpi_missing">
      <p class="text-muted small mb-3">
        Ítems del inventario GLPI que tienen MACs registradas pero <strong>no están autorizados en FreeRADIUS</strong>.
        Podés agregarlos directamente desde aquí.
      </p>
      <?php if (empty($glpi_not_in_radius)): ?>
      <p class="text-muted text-center py-4"><i class="ti ti-circle-check d-block fs-1 text-success mb-2"></i>Todos los ítems con MAC ya están en FreeRADIUS.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Ítem GLPI</th>
              <th>MAC</th>
              <th>Puerto</th>
              <th>VLAN destino</th>
              <th class="text-end">Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($glpi_not_in_radius as $g): ?>
            <?php global $CFG_GLPI; ?>
            <tr>
              <td>
                <span class="badge bg-secondary me-1"><?= $g['itemtype'] ?></span>
                <a href="<?= PluginFreeradiusRadiusSync::getGlpiItemUrl($g['itemtype'], (int)$g['items_id']) ?>"
                   target="_blank" class="text-decoration-none fw-semibold">
                  <?= htmlspecialchars($g['name']) ?>
                </a>
              </td>
              <td><code class="small"><?= $g['mac'] ?></code></td>
              <td class="text-muted small"><?= htmlspecialchars($g['port_name']) ?></td>
              <td style="min-width:130px">
                <select class="form-select form-select-sm vlan_pick">
                  <?php foreach ($vlans as $v): ?>
                    <option value="<?= $v['vlan_id'] ?>" <?= $v['vlan_id'] == 45 ? 'selected' : '' ?>>
                      VLAN <?= $v['vlan_id'] ?> — <?= htmlspecialchars($v['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="text-end">
                <form method="POST" class="d-inline add_form">
                  <input type="hidden" name="_glpi_csrf_token" value="<?= Session::getNewCSRFToken() ?>">
                  <input type="hidden" name="action"   value="add_to_radius">
                  <input type="hidden" name="itemtype" value="<?= $g['itemtype'] ?>">
                  <input type="hidden" name="items_id" value="<?= $g['items_id'] ?>">
                  <input type="hidden" name="mac"      value="<?= $g['mac'] ?>">
                  <input type="hidden" name="name"     value="<?= htmlspecialchars($g['name']) ?>">
                  <input type="hidden" name="vlan"     class="vlan_value" value="45">
                  <button type="submit" class="btn btn-xs btn-success"
                    onclick="return confirm('¿Agregar <?= htmlspecialchars(addslashes($g['name'])) ?> a FreeRADIUS?')">
                    <i class="ti ti-wifi-plus me-1"></i>Agregar a RADIUS
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// Sync VLAN selector → hidden input en tab 3
document.querySelectorAll('.vlan_pick').forEach(sel => {
    sel.addEventListener('change', () => {
        sel.closest('tr').querySelector('.vlan_value').value = sel.value;
    });
});
</script>
<?php Html::footer(); ?>
