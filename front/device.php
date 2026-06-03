<?php
include("../../../inc/includes.php");
Session::checkLoginUser();
global $CFG_GLPI;

global $DB;

// ── Eliminación masiva (POST) ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    if ($ids) {
        $DB->update('glpi_plugin_freeradius_devices', ['is_deleted' => 1], ['id' => $ids]);
    }
    Html::redirect($CFG_GLPI['root_doc'] . '/plugins/freeradius/front/device.php');
    exit;
}

Html::header('FreeRADIUS - Dispositivos', $_SERVER['PHP_SELF'], 'plugins', 'freeradius');

$vlan_filter   = isset($_GET['vlan'])   ? (int)$_GET['vlan']   : 0;
$type_filter   = $_GET['type']   ?? '';
$status_filter = $_GET['status'] ?? '';
$search        = $_GET['search'] ?? '';
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 50;

$where = ['is_deleted' => 0];
if ($vlan_filter)   $where['vlan']        = $vlan_filter;
if ($type_filter)   $where['device_type'] = $type_filter;
if ($status_filter) $where['status']      = $status_filter;
if ($search !== '') {
    $where[] = ['OR' => [
        ['name'        => ['LIKE', '%' . $DB->escape($search) . '%']],
        ['mac_address' => ['LIKE', '%' . $DB->escape($search) . '%']],
        ['username'    => ['LIKE', '%' . $DB->escape($search) . '%']],
        ['oui_vendor'  => ['LIKE', '%' . $DB->escape($search) . '%']],
    ]];
}

$total = (int) $DB->request([
    'COUNT' => 'cpt',
    'FROM'  => 'glpi_plugin_freeradius_devices',
    'WHERE' => $where,
])->current()['cpt'];

$total_pages = max(1, ceil($total / $per_page));
$offset      = ($page - 1) * $per_page;

$rows = $DB->request([
    'FROM'  => 'glpi_plugin_freeradius_devices',
    'WHERE' => $where,
    'ORDER' => ['vlan ASC', 'name ASC'],
    'START' => $offset,
    'LIMIT' => $per_page,
]);

$vlans       = PluginFreeradiusVlan::getAll();
$status_opts = PluginFreeradiusDevice::getStatusOptions();

// Construir query string para mantener filtros en paginación y en el form POST
$qs = http_build_query(array_filter([
    'vlan'   => $vlan_filter ?: null,
    'type'   => $type_filter,
    'status' => $status_filter,
    'search' => $search,
    'page'   => $page > 1 ? $page : null,
]));
?>
<div class="container-fluid px-4 py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0"><i class="ti ti-device-laptop me-2 text-primary"></i>Dispositivos FreeRADIUS</h2>
    <a href="device.form.php" class="btn btn-primary">
      <i class="ti ti-plus me-1"></i>Nuevo dispositivo
    </a>
  </div>

  <!-- Filtros -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
          <input type="text" name="search" class="form-control form-control-sm"
            placeholder="Buscar por nombre, MAC, usuario, fabricante..."
            value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-2">
          <select name="vlan" class="form-select form-select-sm">
            <option value="">Todas las VLANs</option>
            <?php foreach ($vlans as $v): ?>
              <option value="<?= $v['vlan_id'] ?>" <?= $vlan_filter == $v['vlan_id'] ? 'selected' : '' ?>>
                VLAN <?= $v['vlan_id'] ?> — <?= htmlspecialchars($v['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="type" class="form-select form-select-sm">
            <option value="">Todos los tipos</option>
            <?php foreach (['Computer','Phone','NetworkEquipment','Printer','Peripheral'] as $t): ?>
              <option value="<?= $t ?>" <?= $type_filter === $t ? 'selected' : '' ?>>
                <?= PluginFreeradiusOui::getDeviceTypeLabel($t) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="status" class="form-select form-select-sm">
            <option value="">Todos los estados</option>
            <?php foreach ($status_opts as $k => $s): ?>
              <option value="<?= $k ?>" <?= $status_filter === $k ? 'selected' : '' ?>>
                <?= $s['label'] ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
          <button type="submit" class="btn btn-sm btn-outline-primary flex-grow-1">
            <i class="ti ti-filter me-1"></i>Filtrar
          </button>
          <a href="device.php" class="btn btn-sm btn-outline-secondary" title="Limpiar filtros">
            <i class="ti ti-x"></i>
          </a>
        </div>
      </form>
    </div>
  </div>

  <!-- Tabla con form para bulk delete -->
  <form method="POST" action="device.php<?= $qs ? "?$qs" : '' ?>" id="bulk_form">
    <input type="hidden" name="_glpi_csrf_token" value="<?= Session::getNewCSRFToken() ?>">
    <input type="hidden" name="bulk_delete" value="1">

    <div class="card border-0 shadow-sm">
      <!-- Header: contador + barra de acciones masivas -->
      <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-2 gap-2">
        <span class="text-muted small">
          Mostrando <?= $offset + 1 ?>–<?= min($offset + $per_page, $total) ?> de <strong><?= $total ?></strong> dispositivos
        </span>

        <!-- Acciones masivas (oculto hasta tener selección) -->
        <div id="bulk_bar" class="d-none d-flex align-items-center gap-2">
          <span class="text-muted small"><strong id="sel_count">0</strong> seleccionados</span>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAll(true)">
            <i class="ti ti-select-all me-1"></i>Seleccionar página
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAll(false)">
            <i class="ti ti-deselect me-1"></i>Deseleccionar
          </button>
          <button type="submit" class="btn btn-sm btn-danger"
            onclick="return confirm('¿Eliminar los ' + document.getElementById('sel_count').textContent + ' dispositivos seleccionados? Esta acción no se puede deshacer.')">
            <i class="ti ti-trash me-1"></i>Eliminar seleccionados
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-sm mb-0" id="devices_table">
          <thead class="table-light">
            <tr>
              <th class="ps-3" style="width:36px">
                <input type="checkbox" class="form-check-input" id="check_all" title="Seleccionar todos">
              </th>
              <th>Nombre</th>
              <th>MAC Address</th>
              <th>Fabricante / Tipo</th>
              <th>VLAN</th>
              <th>Estado</th>
              <th>Inventario GLPI</th>
              <th>Usuario</th>
              <th class="text-end pe-3">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
            <?php
              $type_icon  = PluginFreeradiusOui::getDeviceTypeIcon($row['device_type'] ?? 'Computer');
              $type_label = PluginFreeradiusOui::getDeviceTypeLabel($row['device_type'] ?? 'Computer');
              $vlan_color = PluginFreeradiusDevice::getVlanColor((int)($row['vlan'] ?? 0));
              $vlan_name  = PluginFreeradiusDevice::getVlanName((int)($row['vlan'] ?? 0));
              $status     = $status_opts[$row['status'] ?? ''] ?? $status_opts['authorized'];
              $glpi_name  = '';
              $glpi_url   = '';
              if (!empty($row['itemtype']) && !empty($row['items_id'])) {
                  $glpi_name = PluginFreeradiusRadiusSync::getGlpiItemName($row['itemtype'], (int)$row['items_id']);
                  $glpi_url  = ($CFG_GLPI['root_doc'] ?? '') . '/' . strtolower($row['itemtype']) . '.form.php?id=' . $row['items_id'];
              }
            ?>
            <tr class="row-item">
              <td class="ps-3">
                <input type="checkbox" class="form-check-input row-check" name="ids[]" value="<?= $row['id'] ?>">
              </td>
              <td>
                <i class="<?= $type_icon ?> text-muted me-1"></i>
                <a href="device.form.php?id=<?= $row['id'] ?>" class="text-decoration-none fw-semibold">
                  <?= htmlspecialchars($row['name']) ?>
                </a>
              </td>
              <td><code class="small"><?= htmlspecialchars($row['mac_address']) ?></code></td>
              <td>
                <span class="text-muted small"><?= htmlspecialchars($row['oui_vendor'] ?? 'Desconocido') ?></span>
                <span class="badge bg-light text-dark border small ms-1"><?= $type_label ?></span>
              </td>
              <td>
                <span class="badge" style="background-color:<?= $vlan_color ?>">
                  VLAN <?= $row['vlan'] ?>
                </span>
                <span class="text-muted small ms-1"><?= htmlspecialchars($vlan_name) ?></span>
              </td>
              <td>
                <span class="badge bg-<?= $status['color'] ?>">
                  <i class="<?= $status['icon'] ?> me-1"></i><?= $status['label'] ?>
                </span>
              </td>
              <td>
                <?php if ($glpi_name): ?>
                  <a href="<?= $glpi_url ?>" class="text-decoration-none small" target="_blank">
                    <i class="ti ti-external-link me-1 text-muted"></i><?= htmlspecialchars($glpi_name) ?>
                  </a>
                <?php else: ?>
                  <span class="text-muted small"><i class="ti ti-unlink me-1"></i>Sin vincular</span>
                <?php endif; ?>
              </td>
              <td class="text-muted small"><?= htmlspecialchars($row['username'] ?? '') ?></td>
              <td class="text-end pe-3">
                <a href="device.form.php?id=<?= $row['id'] ?>" class="btn btn-xs btn-outline-primary me-1" title="Editar">
                  <i class="ti ti-edit"></i>
                </a>
                <a href="device.form.php?delete=1&id=<?= $row['id'] ?>"
                   class="btn btn-xs btn-outline-danger"
                   onclick="return confirm('¿Eliminar este dispositivo?')" title="Eliminar">
                  <i class="ti ti-trash"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if ($total === 0): ?>
            <tr>
              <td colspan="9" class="text-center py-4 text-muted">
                <i class="ti ti-mood-empty fs-2 d-block mb-2"></i>
                No hay dispositivos. <a href="sync.php">Sincronizar con FreeRADIUS</a>
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($total_pages > 1): ?>
      <div class="card-footer bg-transparent border-top d-flex justify-content-center">
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
              <a class="page-link" href="?page=<?= $p ?>&vlan=<?= $vlan_filter ?>&type=<?= urlencode($type_filter) ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>">
                <?= $p ?>
              </a>
            </li>
            <?php endfor; ?>
          </ul>
        </nav>
      </div>
      <?php endif; ?>
    </div>
  </form>
</div>

<script>
const checkAll  = document.getElementById('check_all');
const bulkBar   = document.getElementById('bulk_bar');
const selCount  = document.getElementById('sel_count');

function updateBulkBar() {
    const checked = document.querySelectorAll('.row-check:checked').length;
    selCount.textContent = checked;
    bulkBar.classList.toggle('d-none', checked === 0);
    bulkBar.classList.toggle('d-flex', checked > 0);
    checkAll.indeterminate = checked > 0 && checked < document.querySelectorAll('.row-check').length;
    checkAll.checked = checked > 0 && checked === document.querySelectorAll('.row-check').length;
}

function selectAll(checked) {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = checked);
    updateBulkBar();
}

// Checkbox "seleccionar todos" en el header
checkAll.addEventListener('change', () => selectAll(checkAll.checked));

// Cada checkbox individual
document.querySelectorAll('.row-check').forEach(cb => {
    cb.addEventListener('change', updateBulkBar);
});

// Clic en la fila también activa el checkbox (excepto en links/botones)
document.querySelectorAll('tr.row-item').forEach(tr => {
    tr.addEventListener('click', e => {
        if (e.target.closest('a,button,input')) return;
        const cb = tr.querySelector('.row-check');
        if (cb) { cb.checked = !cb.checked; updateBulkBar(); }
    });
    tr.style.cursor = 'pointer';
});
</script>
<?php
Html::footer();
