<?php
include("../../../inc/includes.php");
Session::checkLoginUser();
global $CFG_GLPI;

global $DB;

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_new = ($id === 0);

// ── Eliminar ─────────────────────────────────────────────────────────────
if (isset($_GET['delete']) && $id) {
    $DB->update('glpi_plugin_freeradius_devices', ['is_deleted' => 1], ['id' => $id]);
    Html::redirect($CFG_GLPI['root_doc'] . '/plugins/freeradius/front/device.php');
    exit;
}

// ── Guardar ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $mac     = strtolower(trim($_POST['mac_address'] ?? ''));
    $mac_raw = preg_replace('/[^0-9a-f]/i', '', $mac);
    if (strlen($mac_raw) === 12) {
        $mac = implode(':', str_split($mac_raw, 2));
    }

    if (!preg_match('/^[0-9a-f]{2}(:[0-9a-f]{2}){5}$/', $mac)) {
        $save_error = 'MAC address inválida: <code>' . htmlspecialchars($_POST['mac_address']) . '</code>. '
            . 'Formato correcto: aa:bb:cc:dd:ee:ff';
        goto render_form;
    }

    $dup = $DB->request([
        'FROM'  => 'glpi_plugin_freeradius_devices',
        'WHERE' => ['mac_address' => $mac, 'is_deleted' => 0, ['NOT' => ['id' => $id ?: 0]]],
        'LIMIT' => 1,
    ]);
    if (count($dup) > 0) {
        $ex = $dup->current();
        $save_error = 'La MAC <code>' . $mac . '</code> ya existe en <strong>'
            . htmlspecialchars($ex['name']) . '</strong> (ID ' . $ex['id'] . ').';
        goto render_form;
    }

    // El campo mac_address tiene índice UNIQUE a nivel de tabla, sin importar is_deleted.
    // Si la MAC pertenece a un dispositivo dado de baja, hay que purgarlo para poder reutilizarla.
    $DB->delete('glpi_plugin_freeradius_devices', [
        'mac_address' => $mac,
        'is_deleted'  => 1,
        ['NOT' => ['id' => $id ?: 0]],
    ]);

    $oui = PluginFreeradiusOui::lookup($mac, trim($_POST['name'] ?? ''));
    $data = [
        'name'          => trim($_POST['name'] ?? ''),
        'mac_address'   => $mac,
        'vlan'          => (int)($_POST['vlan'] ?? 0),
        'username'      => trim($_POST['username'] ?? ''),
        'description'   => trim($_POST['description'] ?? ''),
        'device_type'   => trim($_POST['device_type'] ?? '') ?: $oui['device_type'],
        'status'        => trim($_POST['status'] ?? 'authorized'),
        'oui_vendor'    => trim($_POST['oui_vendor'] ?? '') ?: $oui['vendor'],
        'itemtype'      => trim($_POST['itemtype'] ?? '') ?: null,
        'items_id'      => (int)($_POST['items_id'] ?? 0) ?: null,
        'glpi_users_id' => (int)($_POST['glpi_users_id'] ?? 0) ?: null,
        'date_mod'      => date('Y-m-d H:i:s'),
    ];

    if ($is_new) {
        $data['date_creation'] = date('Y-m-d H:i:s');
        try {
            $DB->insert('glpi_plugin_freeradius_devices', $data);
            $new_id = $DB->insertId();
            Html::redirect($CFG_GLPI['root_doc'] . '/plugins/freeradius/front/device.form.php?id=' . $new_id . '&saved=1');
        } catch (Exception $e) {
            $save_error = $e->getMessage();
        }
    } else {
        $DB->update('glpi_plugin_freeradius_devices', $data, ['id' => $id]);
        Html::redirect($CFG_GLPI['root_doc'] . '/plugins/freeradius/front/device.form.php?id=' . $id . '&saved=1');
    }
    exit;
}

render_form:
$device = [];
if (!$is_new) {
    $rows = $DB->request(['FROM' => 'glpi_plugin_freeradius_devices', 'WHERE' => ['id' => $id], 'LIMIT' => 1]);
    if (!count($rows)) { Html::redirect($CFG_GLPI['root_doc'] . '/plugins/freeradius/front/device.php'); exit; }
    $device = $rows->current();
}

// Modo: 'inventory' (basado en inventario) o 'new' (nuevo)
$mode = $_GET['mode'] ?? ($is_new ? 'inventory' : 'edit');

// Usuario vinculado
$linked_user = null;
if ($device['glpi_users_id'] ?? 0) {
    $ur = $DB->request(['FROM' => 'glpi_users', 'WHERE' => ['id' => $device['glpi_users_id']], 'LIMIT' => 1]);
    if (count($ur)) {
        $u = $ur->current();
        $linked_user = ['id' => $u['id'], 'label' => trim(($u['firstname'] ?? '') . ' ' . ($u['realname'] ?? '')) ?: $u['name'], 'name' => $u['name']];
    }
}

// Ítem GLPI vinculado
$linked_item_name = '';
if (($device['itemtype'] ?? '') && ($device['items_id'] ?? 0)) {
    $linked_item_name = PluginFreeradiusRadiusSync::getGlpiItemName($device['itemtype'], (int)$device['items_id']);
}

$vlans       = PluginFreeradiusVlan::getAll();
$status_opts = PluginFreeradiusDevice::getStatusOptions();

Html::header(($is_new ? 'Nuevo Dispositivo' : 'Editar: ' . ($device['name'] ?? '')) . ' — FreeRADIUS',
    $_SERVER['PHP_SELF'], 'plugins', 'freeradius');
?>
<div class="container-fluid px-4 py-3" style="max-width:1000px">

  <div class="d-flex align-items-center gap-2 mb-4">
    <a href="device.php" class="btn btn-sm btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i>Volver</a>
    <h2 class="mb-0 ms-2">
      <i class="ti ti-<?= $is_new ? 'plus' : 'edit' ?> me-2 text-primary"></i>
      <?= $is_new ? 'Nuevo dispositivo' : 'Editar: ' . htmlspecialchars($device['name'] ?? '') ?>
    </h2>
  </div>

  <?php if (isset($_GET['saved'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="ti ti-check me-1"></i>Guardado correctamente.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>
  <?php if (isset($save_error)): ?>
  <div class="alert alert-danger"><i class="ti ti-alert-circle me-1"></i><?= $save_error ?></div>
  <?php endif; ?>

  <?php if ($is_new): ?>
  <!-- ── Selector de modo ── -->
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card border-2 <?= $mode === 'inventory' ? 'border-primary' : 'border-light' ?> h-100 cursor-pointer mode-card"
           data-mode="inventory" onclick="setMode('inventory')" style="cursor:pointer">
        <div class="card-body text-center py-4">
          <i class="ti ti-database-search fs-1 <?= $mode === 'inventory' ? 'text-primary' : 'text-muted' ?> d-block mb-2"></i>
          <div class="fw-bold fs-5">Desde el inventario</div>
          <div class="text-muted small mt-1">El equipo ya existe en GLPI. Elegís el ítem, seleccionás su MAC y lo autorizás en FreeRADIUS.</div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card border-2 <?= $mode === 'new' ? 'border-primary' : 'border-light' ?> h-100 cursor-pointer mode-card"
           data-mode="new" onclick="setMode('new')" style="cursor:pointer">
        <div class="card-body text-center py-4">
          <i class="ti ti-device-laptop-off fs-1 <?= $mode === 'new' ? 'text-primary' : 'text-muted' ?> d-block mb-2"></i>
          <div class="fw-bold fs-5">Equipo nuevo</div>
          <div class="text-muted small mt-1">El equipo no está en el inventario. Ingresás la MAC y los datos manualmente.</div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <form method="POST" id="device_form">
    <input type="hidden" name="_glpi_csrf_token" value="<?= Session::getNewCSRFToken() ?>">
    <input type="hidden" name="itemtype"      id="itemtype_hidden"  value="<?= htmlspecialchars($device['itemtype'] ?? '') ?>">
    <input type="hidden" name="items_id"      id="items_id_hidden"  value="<?= htmlspecialchars($device['items_id'] ?? '') ?>">
    <input type="hidden" name="glpi_users_id" id="users_id_hidden"  value="<?= (int)($device['glpi_users_id'] ?? 0) ?: '' ?>">

    <div class="row g-4">
      <div class="col-lg-8">

        <!-- ── Panel: desde inventario ── -->
        <div id="panel_inventory" class="<?= (!$is_new || $mode !== 'inventory') ? 'd-none' : '' ?>">
          <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent border-bottom">
              <h5 class="mb-0"><i class="ti ti-database-search me-2 text-primary"></i>Seleccionar ítem del inventario</h5>
            </div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label fw-semibold small">Buscar equipo en GLPI</label>
                <div class="input-group input-group-sm">
                  <select id="inv_type_filter" class="form-select" style="max-width:160px">
                    <option value="">Todos los tipos</option>
                    <option value="Computer">Computer</option>
                    <option value="NetworkEquipment">NetworkEquipment</option>
                    <option value="Phone">Phone</option>
                    <option value="Unmanaged">Unmanaged</option>
                  </select>
                  <input type="text" id="inv_search_input" class="form-control"
                    placeholder="Nombre del equipo (mín. 2 caracteres)..." autocomplete="off">
                  <button type="button" class="btn btn-outline-primary" id="inv_search_btn">
                    <i class="ti ti-search"></i>
                  </button>
                </div>
              </div>
              <div id="inv_results_wrap" style="display:none" class="mb-3">
                <div class="list-group list-group-flush border rounded" id="inv_results_list"
                     style="max-height:200px;overflow-y:auto"></div>
              </div>
              <div id="inv_selected_item" class="alert alert-primary py-2 d-none">
                <i class="ti ti-circle-check me-1"></i>
                Ítem seleccionado: <strong id="inv_selected_name"></strong>
                <span class="badge bg-secondary ms-1" id="inv_selected_type"></span>
                <button type="button" class="btn btn-xs btn-outline-danger ms-2" onclick="clearInvSelection()">
                  <i class="ti ti-x"></i>
                </button>
              </div>
              <!-- MACs del ítem -->
              <div id="mac_picker_wrap" style="display:none">
                <label class="form-label fw-semibold small">MACs del equipo — elegí cuál autorizar en FreeRADIUS:</label>
                <div id="mac_picker_btns" class="d-flex flex-wrap gap-2"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Datos del dispositivo ── -->
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-header bg-transparent border-bottom">
            <h5 class="mb-0">Datos del dispositivo</h5>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required id="field_name"
                  value="<?= htmlspecialchars($device['name'] ?? '') ?>"
                  placeholder="Ej: NB-TPR-00042, cel-apellido">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">MAC Address <span class="text-danger">*</span></label>
                <input type="text" name="mac_address" class="form-control font-monospace" required
                  id="mac_input"
                  value="<?= htmlspecialchars($device['mac_address'] ?? ($_GET['mac'] ?? '')) ?>"
                  placeholder="aa:bb:cc:dd:ee:ff" autocomplete="off"
                  pattern="[0-9a-fA-F]{2}(:[0-9a-fA-F]{2}){5}"
                  title="6 grupos de 2 dígitos hex separados por ':'">
                <div class="form-text" id="mac_feedback">Formato: aa:bb:cc:dd:ee:ff</div>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">VLAN <span class="text-danger">*</span></label>
                <select name="vlan" class="form-select" required id="field_vlan">
                  <?php foreach ($vlans as $v): ?>
                    <option value="<?= $v['vlan_id'] ?>"
                      <?= ($device['vlan'] ?? 45) == $v['vlan_id'] ? 'selected' : '' ?>>
                      VLAN <?= $v['vlan_id'] ?> — <?= htmlspecialchars($v['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Estado</label>
                <select name="status" class="form-select">
                  <?php foreach ($status_opts as $k => $s): ?>
                    <option value="<?= $k ?>" <?= ($device['status'] ?? 'authorized') === $k ? 'selected' : '' ?>>
                      <?= $s['label'] ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Tipo de dispositivo</label>
                <select name="device_type" class="form-select" id="field_device_type">
                  <?php foreach (['Computer','Phone','NetworkEquipment','Printer','Peripheral'] as $t): ?>
                    <option value="<?= $t ?>" <?= ($device['device_type'] ?? 'Computer') === $t ? 'selected' : '' ?>>
                      <?= PluginFreeradiusOui::getDeviceTypeLabel($t) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Fabricante (OUI)</label>
                <input type="text" name="oui_vendor" class="form-control" id="oui_vendor_input"
                  value="<?= htmlspecialchars($device['oui_vendor'] ?? '') ?>"
                  placeholder="Se detecta automáticamente">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Descripción</label>
                <textarea name="description" class="form-control" rows="2"
                  placeholder="Notas adicionales"><?= htmlspecialchars($device['description'] ?? '') ?></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Usuario GLPI ── -->
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-header bg-transparent border-bottom d-flex align-items-center justify-content-between">
            <h5 class="mb-0"><i class="ti ti-user me-2"></i>Usuario responsable (GLPI)</h5>
            <?php if ($linked_user): ?>
            <button type="button" class="btn btn-xs btn-outline-danger" onclick="clearUser()">
              <i class="ti ti-unlink me-1"></i>Desvincular
            </button>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <?php if ($linked_user): ?>
            <div class="alert alert-info py-2 mb-3">
              <i class="ti ti-user-check me-1"></i>
              <strong><?= htmlspecialchars($linked_user['label']) ?></strong>
              <span class="text-muted small ms-1">(<?= htmlspecialchars($linked_user['name']) ?>)</span>
            </div>
            <?php endif; ?>
            <div class="mb-0">
              <label class="form-label fw-semibold small">Buscar usuario en GLPI</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="ti ti-user-search"></i></span>
                <input type="text" id="user_search_input" class="form-control"
                  placeholder="Nombre, apellido o usuario (mín. 2 caracteres)..."
                  value="<?= htmlspecialchars($linked_user ? $linked_user['label'] : '') ?>"
                  autocomplete="off">
              </div>
              <div id="user_results_wrap" class="mt-1" style="display:none">
                <div class="list-group list-group-flush border rounded" id="user_results_list"
                     style="max-height:160px;overflow-y:auto"></div>
              </div>
              <div class="form-text">
                También podés ingresar el nombre manualmente en "Usuario/Responsable" de arriba.
              </div>
            </div>
          </div>
        </div>

        <!-- ── Vínculo con inventario (modo edición) ── -->
        <?php if (!$is_new): ?>
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-header bg-transparent border-bottom d-flex align-items-center justify-content-between">
            <h5 class="mb-0"><i class="ti ti-link me-2"></i>Vínculo con inventario GLPI</h5>
            <?php if ($linked_item_name): ?>
            <button type="button" class="btn btn-xs btn-outline-danger" onclick="clearItem()">
              <i class="ti ti-unlink me-1"></i>Desvincular
            </button>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <?php if ($linked_item_name): ?>
            <div class="alert alert-success py-2 mb-3">
              <i class="ti ti-circle-check me-1"></i>
              <strong><?= htmlspecialchars($linked_item_name) ?></strong>
              <span class="badge bg-secondary ms-1"><?= htmlspecialchars($device['itemtype']) ?></span>
              <a href="<?= PluginFreeradiusRadiusSync::getGlpiItemUrl($device['itemtype'], (int)$device['items_id']) ?>"
                 target="_blank" class="ms-2 small"><i class="ti ti-external-link"></i> Ver</a>
            </div>
            <?php endif; ?>
            <div class="input-group input-group-sm">
              <select id="edit_type_filter" class="form-select" style="max-width:160px">
                <option value="">Todos los tipos</option>
                <option value="Computer">Computer</option>
                <option value="NetworkEquipment">NetworkEquipment</option>
                <option value="Phone">Phone</option>
                <option value="Unmanaged">Unmanaged</option>
              </select>
              <input type="text" id="edit_inv_search" class="form-control"
                placeholder="Buscar por nombre en inventario..." autocomplete="off">
              <button type="button" class="btn btn-outline-primary" id="edit_inv_btn">
                <i class="ti ti-search"></i>
              </button>
            </div>
            <div id="edit_inv_results" style="display:none" class="mt-1">
              <div class="list-group list-group-flush border rounded" id="edit_inv_list"
                   style="max-height:160px;overflow-y:auto"></div>
            </div>
            <div id="edit_mac_picker" style="display:none" class="mt-2">
              <label class="form-label small fw-semibold">MACs de <span id="edit_mac_item_name" class="text-primary"></span>:</label>
              <div id="edit_mac_btns" class="d-flex flex-wrap gap-2"></div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="ti ti-device-floppy me-1"></i>Guardar
          </button>
          <a href="device.php" class="btn btn-outline-secondary">Cancelar</a>
          <?php if (!$is_new): ?>
          <a href="device.form.php?delete=1&id=<?= $id ?>"
             class="btn btn-outline-danger ms-auto"
             onclick="return confirm('¿Eliminar este dispositivo?')">
            <i class="ti ti-trash me-1"></i>Eliminar
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── Panel lateral ── -->
      <div class="col-lg-4">
        <!-- OUI info -->
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-header bg-transparent border-bottom"><h6 class="mb-0">Información OUI</h6></div>
          <div class="card-body" id="oui_info_panel">
            <?php if ($device['mac_address'] ?? ''): ?>
            <?php $oui = PluginFreeradiusOui::lookup($device['mac_address'], $device['name'] ?? ''); ?>
            <div class="d-flex align-items-center gap-2">
              <i class="<?= PluginFreeradiusOui::getDeviceTypeIcon($oui['device_type']) ?> fs-3 text-primary"></i>
              <div>
                <div class="fw-semibold"><?= $oui['vendor'] ?></div>
                <div class="text-muted small"><?= PluginFreeradiusOui::getDeviceTypeLabel($oui['device_type']) ?></div>
              </div>
            </div>
            <?php else: ?>
            <p class="text-muted small mb-0">Ingresá una MAC para ver el fabricante.</p>
            <?php endif; ?>
          </div>
        </div>
        <?php if (!$is_new): ?>
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="small text-muted">
              <i class="ti ti-calendar me-1"></i>Creado: <?= $device['date_creation'] ?? '—' ?><br>
              <i class="ti ti-clock-edit me-1"></i>Modificado: <?= $device['date_mod'] ?? '—' ?>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </form>
</div>

<script>
const API_URL = '<?= $CFG_GLPI['root_doc'] ?>/plugins/freeradius/front/inventory_search.php';
let currentMode = '<?= $mode ?>';

// ── Modo nuevo / desde inventario ─────────────────────────────────────────
function setMode(mode) {
    currentMode = mode;
    document.querySelectorAll('.mode-card').forEach(c => {
        const active = c.dataset.mode === mode;
        c.classList.toggle('border-primary', active);
        c.classList.toggle('border-light', !active);
        c.querySelector('i').classList.toggle('text-primary', active);
        c.querySelector('i').classList.toggle('text-muted', !active);
    });
    document.getElementById('panel_inventory').classList.toggle('d-none', mode !== 'inventory');
}

// ── Campos ocultos ─────────────────────────────────────────────────────────
function setItem(itemtype, items_id) {
    document.getElementById('itemtype_hidden').value = itemtype || '';
    document.getElementById('items_id_hidden').value = items_id || '';
}
function clearItem() { setItem('', ''); }
function setUser(id) {
    document.getElementById('users_id_hidden').value = id || '';
}
function clearUser() {
    setUser('');
    document.getElementById('user_search_input').value = '';
}

// ── Búsqueda de inventario (modo nuevo) ───────────────────────────────────
let invTimer;
const invInput   = document.getElementById('inv_search_input');
const invResults = document.getElementById('inv_results_wrap');
const invList    = document.getElementById('inv_results_list');

function searchInventory(input, resultsWrap, resultsList, onSelect) {
    const q    = input.value.trim();
    const type = input.closest('.card-body')?.querySelector('select')?.value || '';
    if (q.length < 2) { resultsWrap.style.display = 'none'; return; }

    fetch(`${API_URL}?action=search&q=${encodeURIComponent(q)}&type=${encodeURIComponent(type)}`)
        .then(r => r.json())
        .then(items => {
            resultsList.innerHTML = '';
            if (!items.length) {
                resultsList.innerHTML = '<div class="list-group-item text-muted small py-1">Sin resultados</div>';
            } else {
                items.forEach(item => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action py-1 px-2 small';
                    btn.innerHTML = `<span class="badge bg-secondary me-1">${item.itemtype}</span>${item.name}`;
                    btn.addEventListener('click', () => { onSelect(item); resultsWrap.style.display='none'; });
                    resultsList.appendChild(btn);
                });
            }
            resultsWrap.style.display = 'block';
        }).catch(() => {});
}

function loadMacs(itemtype, items_id, macContainer, macLabel, onSelect) {
    fetch(`${API_URL}?action=macs&itemtype=${encodeURIComponent(itemtype)}&items_id=${items_id}`)
        .then(r => r.json())
        .then(macs => {
            macContainer.innerHTML = '';
            if (!macs.length) {
                macContainer.innerHTML = '<span class="text-muted small">Sin MACs registradas.</span>';
            } else {
                macs.forEach(m => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    const busy = m.in_radius;
                    btn.className = 'btn btn-sm ' + (busy ? 'btn-secondary' : 'btn-outline-primary');
                    if (busy) btn.disabled = true;
                    btn.innerHTML = `<code>${m.mac}</code> <small class="text-muted">${m.port_name}</small>`
                        + (busy ? ' <span class="badge bg-warning text-dark ms-1">ya en RADIUS</span>' : '');
                    if (!busy) {
                        btn.addEventListener('click', () => {
                            onSelect(m.mac);
                            macContainer.querySelectorAll('button').forEach(b => {
                                b.classList.remove('btn-primary');
                                if (!b.disabled) b.classList.add('btn-outline-primary');
                            });
                            btn.classList.remove('btn-outline-primary');
                            btn.classList.add('btn-primary');
                        });
                    }
                    macContainer.appendChild(btn);
                });
            }
            if (macLabel) macLabel.style.display = 'block';
        }).catch(() => {});
}

// Modo "desde inventario"
if (invInput) {
    invInput.addEventListener('input', () => { clearTimeout(invTimer); invTimer = setTimeout(() => searchInventory(invInput, invResults, invList, onInvSelect), 300); });
    document.getElementById('inv_search_btn')?.addEventListener('click', () => searchInventory(invInput, invResults, invList, onInvSelect));
}

function onInvSelect(item) {
    setItem(item.itemtype, item.id);
    document.getElementById('inv_selected_item').classList.remove('d-none');
    document.getElementById('inv_selected_name').textContent = item.name;
    document.getElementById('inv_selected_type').textContent = item.itemtype;
    if (!document.getElementById('field_name').value) document.getElementById('field_name').value = item.name;

    loadMacs(item.itemtype, item.id,
        document.getElementById('mac_picker_btns'),
        document.getElementById('mac_picker_wrap'),
        mac => {
            const mi = document.getElementById('mac_input');
            mi.value = mac;
            mi.dispatchEvent(new Event('input'));
            mi.dispatchEvent(new Event('blur'));
        }
    );
    document.getElementById('mac_picker_wrap').style.display = 'block';
}

function clearInvSelection() {
    clearItem();
    document.getElementById('inv_selected_item').classList.add('d-none');
    document.getElementById('mac_picker_wrap').style.display = 'none';
    invInput.value = '';
}

// Modo edición: buscador de ítem
let editTimer;
const editSearch = document.getElementById('edit_inv_search');
const editResults = document.getElementById('edit_inv_results');
const editList = document.getElementById('edit_inv_list');
if (editSearch) {
    editSearch.addEventListener('input', () => { clearTimeout(editTimer); editTimer = setTimeout(() => searchInventory(editSearch, editResults, editList, onEditSelect), 300); });
    document.getElementById('edit_inv_btn')?.addEventListener('click', () => searchInventory(editSearch, editResults, editList, onEditSelect));
}
function onEditSelect(item) {
    setItem(item.itemtype, item.id);
    editSearch.value = item.name;
    editResults.style.display = 'none';
    const mpick = document.getElementById('edit_mac_picker');
    document.getElementById('edit_mac_item_name').textContent = item.name;
    loadMacs(item.itemtype, item.id, document.getElementById('edit_mac_btns'), mpick, mac => {
        const mi = document.getElementById('mac_input');
        mi.value = mac;
        mi.dispatchEvent(new Event('input'));
    });
    mpick.style.display = 'block';
}

// ── Búsqueda de usuarios ───────────────────────────────────────────────────
let userTimer;
const userInput   = document.getElementById('user_search_input');
const userResults = document.getElementById('user_results_wrap');
const userList    = document.getElementById('user_results_list');

userInput?.addEventListener('input', () => {
    clearTimeout(userTimer);
    const q = userInput.value.trim();
    if (q.length < 2) { userResults.style.display = 'none'; return; }
    userTimer = setTimeout(() => {
        fetch(`${API_URL}?action=users&q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(users => {
                userList.innerHTML = '';
                if (!users.length) {
                    userList.innerHTML = '<div class="list-group-item text-muted small py-1">Sin resultados</div>';
                } else {
                    users.forEach(u => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action py-1 px-2 small';
                        btn.innerHTML = `<i class="ti ti-user me-1"></i>${u.label}`;
                        btn.addEventListener('click', () => {
                            setUser(u.id);
                            userInput.value = u.label;
                            userResults.style.display = 'none';
                            // También rellenar el campo username de texto libre
                            const uField = document.querySelector('input[name="username"]');
                            if (uField && !uField.value) uField.value = u.name;
                        });
                        userList.appendChild(btn);
                    });
                }
                userResults.style.display = 'block';
            }).catch(() => {});
    }, 300);
});

// ── Validación de MAC ──────────────────────────────────────────────────────
const macInput    = document.getElementById('mac_input');
const macFeedback = document.getElementById('mac_feedback');
const MAC_RE      = /^[0-9a-fA-F]{2}(:[0-9a-fA-F]{2}){5}$/;

macInput?.addEventListener('input', () => {
    const v = macInput.value.trim();
    if (!v) { macInput.classList.remove('is-valid','is-invalid'); macFeedback.textContent='Formato: aa:bb:cc:dd:ee:ff'; macFeedback.className='form-text'; return; }
    const ok = MAC_RE.test(v);
    macInput.classList.toggle('is-valid', ok);
    macInput.classList.toggle('is-invalid', !ok);
    macFeedback.textContent = ok ? '✓ MAC válida' : 'Inválida — use aa:bb:cc:dd:ee:ff';
    macFeedback.className = 'form-text ' + (ok ? 'text-success' : 'text-danger');
});

macInput?.addEventListener('blur', () => {
    const hex = macInput.value.replace(/[^0-9a-fA-F]/g,'');
    if (hex.length === 12) {
        const norm = hex.match(/.{2}/g).join(':').toLowerCase();
        macInput.value = norm;
        macInput.dispatchEvent(new Event('input'));
        // OUI lookup
        fetch(`${API_URL}?action=oui&mac=${encodeURIComponent(norm)}`)
            .then(r=>r.json()).then(d=>{ if(d.vendor && d.vendor!=='Desconocido') { const v=document.getElementById('oui_vendor_input'); if(!v.value) v.value=d.vendor; } }).catch(()=>{});
    }
});

document.getElementById('device_form')?.addEventListener('submit', e => {
    if (!MAC_RE.test(macInput?.value.trim())) {
        e.preventDefault();
        macInput.classList.add('is-invalid');
        macFeedback.textContent = 'MAC inválida — corregí antes de guardar.';
        macFeedback.className = 'form-text text-danger';
        macInput.focus();
    }
});
</script>
<?php Html::footer(); ?>
