<?php
include("../../../inc/includes.php");
Session::checkLoginUser();
global $CFG_GLPI;

global $DB;

// Guardar VLAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkLoginUser();
    $vlan_id = (int)$_POST['vlan_id'];
    $name    = trim($_POST['name'] ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $color   = trim($_POST['color'] ?? '#6c757d');

    $existing = $DB->request(['FROM' => 'glpi_plugin_freeradius_vlans', 'WHERE' => ['vlan_id' => $vlan_id], 'LIMIT' => 1]);
    if (count($existing)) {
        $DB->update('glpi_plugin_freeradius_vlans', ['name' => $name, 'description' => $desc, 'color' => $color], ['vlan_id' => $vlan_id]);
    } else {
        $DB->insert('glpi_plugin_freeradius_vlans', [
            'vlan_id' => $vlan_id, 'name' => $name, 'description' => $desc, 'color' => $color,
        ]);
    }
    Html::redirect($CFG_GLPI['root_doc'] . '/plugins/freeradius/front/vlan.php?saved=1');
    exit;
}

// Eliminar
if (isset($_GET['delete'])) {
    Session::checkLoginUser();
    $DB->delete('glpi_plugin_freeradius_vlans', ['id' => (int)$_GET['delete']]);
    Html::redirect($CFG_GLPI['root_doc'] . '/plugins/freeradius/front/vlan.php');
    exit;
}

$vlans = $DB->request(['FROM' => 'glpi_plugin_freeradius_vlans', 'ORDER' => ['vlan_id ASC']]);

Html::header('FreeRADIUS - VLANs', $_SERVER['PHP_SELF'], 'plugins', 'freeradius');
?>
<div class="container-fluid px-4 py-3" style="max-width:900px">
  <div class="d-flex align-items-center gap-2 mb-4">
    <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i>Dashboard</a>
    <h2 class="mb-0 ms-2"><i class="ti ti-network me-2 text-primary"></i>Gestión de VLANs</h2>
  </div>

  <?php if (isset($_GET['saved'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="ti ti-check me-1"></i>VLAN guardada.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">VLAN ID</th>
              <th>Nombre</th>
              <th>Descripción</th>
              <th>Color</th>
              <th>Dispositivos</th>
              <th class="text-end pe-3">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($vlans as $v): ?>
            <?php $count = countElementsInTable('glpi_plugin_freeradius_devices', ['vlan' => $v['vlan_id'], 'is_deleted' => 0]); ?>
            <tr>
              <td class="ps-3">
                <span class="badge fs-6" style="background-color:<?= htmlspecialchars($v['color']) ?>">
                  <?= $v['vlan_id'] ?>
                </span>
              </td>
              <td class="fw-semibold"><?= htmlspecialchars($v['name']) ?></td>
              <td class="text-muted small"><?= htmlspecialchars($v['description']) ?></td>
              <td><span class="badge" style="background-color:<?= htmlspecialchars($v['color']) ?>"><?= $v['color'] ?></span></td>
              <td>
                <a href="device.php?vlan=<?= $v['vlan_id'] ?>" class="text-decoration-none">
                  <?= $count ?> dispositivos
                </a>
              </td>
              <td class="text-end pe-3">
                <button type="button" class="btn btn-xs btn-outline-primary"
                  onclick="editVlan(<?= $v['vlan_id'] ?>, '<?= htmlspecialchars(addslashes($v['name'])) ?>', '<?= htmlspecialchars(addslashes($v['description'])) ?>', '<?= $v['color'] ?>')">
                  <i class="ti ti-edit"></i>
                </button>
                <?php if ($count === 0): ?>
                <a href="vlan.php?delete=<?= $v['id'] ?>" class="btn btn-xs btn-outline-danger ms-1"
                   onclick="return confirm('¿Eliminar VLAN <?= $v['vlan_id'] ?>?')">
                  <i class="ti ti-trash"></i>
                </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-bottom">
          <h5 class="mb-0" id="form_title">Nueva VLAN</h5>
        </div>
        <div class="card-body">
          <form method="POST" id="vlan_form">
            <input type="hidden" name="_glpi_csrf_token" value="<?= Session::getNewCSRFToken() ?>">
            <div class="mb-3">
              <label class="form-label fw-semibold">VLAN ID <span class="text-danger">*</span></label>
              <input type="number" name="vlan_id" class="form-control" required
                id="f_vlan_id" placeholder="Ej: 45, 55, 100">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Nombre</label>
              <input type="text" name="name" class="form-control" id="f_name"
                placeholder="Ej: VLAN-Empleados">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Descripción</label>
              <input type="text" name="description" class="form-control" id="f_desc"
                placeholder="Descripción de la VLAN">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Color</label>
              <input type="color" name="color" class="form-control form-control-color" id="f_color" value="#198754">
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary flex-grow-1">
                <i class="ti ti-device-floppy me-1"></i>Guardar
              </button>
              <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                <i class="ti ti-x"></i>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function editVlan(vlan_id, name, desc, color) {
    document.getElementById('f_vlan_id').value = vlan_id;
    document.getElementById('f_name').value = name;
    document.getElementById('f_desc').value = desc;
    document.getElementById('f_color').value = color;
    document.getElementById('form_title').textContent = 'Editar VLAN ' + vlan_id;
    document.getElementById('vlan_form').scrollIntoView({behavior:'smooth'});
}
function resetForm() {
    document.getElementById('f_vlan_id').value = '';
    document.getElementById('f_name').value = '';
    document.getElementById('f_desc').value = '';
    document.getElementById('f_color').value = '#198754';
    document.getElementById('form_title').textContent = 'Nueva VLAN';
}
</script>
<?php
Html::footer();
