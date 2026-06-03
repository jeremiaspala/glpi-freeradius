<?php
include("../../../inc/includes.php");
Session::checkLoginUser();
global $CFG_GLPI;

$action  = $_POST['action'] ?? $_GET['action'] ?? '';
$result  = null;

if ($action === 'import') {
    $sync   = new PluginFreeradiusRadiusSync();
    $result = $sync->importFromRadius();
} elseif ($action === 'push') {
    $sync   = new PluginFreeradiusRadiusSync();
    $result = $sync->pushToRadius();
} elseif ($action === 'test') {
    $sync   = new PluginFreeradiusRadiusSync();
    $result = $sync->testConnection();
}

Html::header('FreeRADIUS - Sincronización', $_SERVER['PHP_SELF'], 'plugins', 'freeradius');

$cfg       = PluginFreeradiusConfig::get();
$last_sync = $cfg['last_sync'] ?? null;
$sync      = new PluginFreeradiusRadiusSync();
$unknown   = ($action === '') ? $sync->getUnknownMacs() : [];
?>
<div class="container-fluid px-4 py-3" style="max-width:960px">
  <div class="d-flex align-items-center gap-2 mb-4">
    <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i>Dashboard</a>
    <h2 class="mb-0 ms-2"><i class="ti ti-refresh me-2 text-primary"></i>Sincronización con FreeRADIUS</h2>
  </div>

  <?php if ($result): ?>
  <div class="alert alert-<?= isset($result['error']) || (isset($result['success']) && !$result['success']) ? 'danger' : 'success' ?> mb-3">
    <?php if (isset($result['error'])): ?>
      <i class="ti ti-alert-circle me-1"></i><?= htmlspecialchars($result['error']) ?>
    <?php elseif (isset($result['success'])): ?>
      <i class="ti ti-check me-1"></i><?= htmlspecialchars($result['message']) ?>
    <?php elseif (isset($result['ssh'])): ?>
      <i class="ti ti-<?= $result['ssh'] ? 'check' : 'x' ?> me-1"></i>
      Conexión SSH: <strong><?= $result['ssh'] ? 'OK' : 'ERROR' ?></strong>
      <?php if (!$result['ssh']): ?> — <?= htmlspecialchars($result['msg']) ?><?php endif; ?>
    <?php else: ?>
      <i class="ti ti-check me-1"></i>
      Importados: <strong><?= $result['imported'] ?? 0 ?></strong>,
      Actualizados: <strong><?= $result['updated'] ?? 0 ?></strong>,
      Total procesados: <strong><?= $result['count'] ?? 0 ?></strong>
      <?php if (!empty($result['errors'])): ?>
        <ul class="mt-2 mb-0 small">
          <?php foreach ($result['errors'] as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <!-- Estado servidor -->
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <h5><i class="ti ti-server me-2"></i>Servidor RADIUS</h5>
          <dl class="row small mb-2">
            <dt class="col-5 text-muted">Host:</dt>
            <dd class="col-7"><code><?= htmlspecialchars($cfg['radius_host'] ?? '') ?></code></dd>
            <dt class="col-5 text-muted">SSH user:</dt>
            <dd class="col-7"><code><?= htmlspecialchars($cfg['radius_ssh_user'] ?? '') ?></code></dd>
            <dt class="col-5 text-muted">Archivo:</dt>
            <dd class="col-7"><code class="small"><?= htmlspecialchars(basename($cfg['authorize_file'] ?? '')) ?></code></dd>
            <dt class="col-5 text-muted">Última sync:</dt>
            <dd class="col-7"><?= $last_sync ? date('d/m/Y H:i', strtotime($last_sync)) : '<em>nunca</em>' ?></dd>
          </dl>
          <form method="POST">
            <input type="hidden" name="_glpi_csrf_token" value="<?= Session::getNewCSRFToken() ?>">
            <input type="hidden" name="action" value="test">
            <button type="submit" class="btn btn-sm btn-outline-info w-100">
              <i class="ti ti-plug me-1"></i>Probar conexión
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Importar -->
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex flex-column">
          <h5><i class="ti ti-download me-2 text-success"></i>Importar desde RADIUS</h5>
          <p class="text-muted small flex-grow-1">
            Lee el archivo <code>authorize</code> de FreeRADIUS y actualiza la base de datos local de GLPI.
            Las MACs nuevas se agregan y las existentes se actualizan.
          </p>
          <form method="POST">
            <input type="hidden" name="_glpi_csrf_token" value="<?= Session::getNewCSRFToken() ?>">
            <input type="hidden" name="action" value="import">
            <button type="submit" class="btn btn-success w-100"
              onclick="return confirm('¿Importar todos los dispositivos del archivo authorize de FreeRADIUS?')">
              <i class="ti ti-download me-1"></i>Importar ahora
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Exportar -->
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex flex-column">
          <h5><i class="ti ti-upload me-2 text-warning"></i>Exportar a RADIUS</h5>
          <p class="text-muted small flex-grow-1">
            Genera el archivo <code>authorize</code> con todos los dispositivos autorizados en GLPI
            y lo escribe en el servidor FreeRADIUS. Recarga FreeRADIUS automáticamente.
          </p>
          <form method="POST">
            <input type="hidden" name="_glpi_csrf_token" value="<?= Session::getNewCSRFToken() ?>">
            <input type="hidden" name="action" value="push">
            <button type="submit" class="btn btn-warning w-100"
              onclick="return confirm('¿Sobrescribir el archivo authorize en FreeRADIUS con los datos actuales de GLPI?\n\nEsto reemplazará toda la configuración actual de acceso.')">
              <i class="ti ti-upload me-1"></i>Exportar y recargar
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- MACs desconocidas -->
  <?php if (!empty($unknown)): ?>
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-bottom">
      <h5 class="mb-0">
        <i class="ti ti-question-mark me-2 text-warning"></i>
        MACs vistas en logs sin registrar
        <span class="badge bg-warning text-dark ms-2"><?= count($unknown) ?></span>
      </h5>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">MAC Address</th>
            <th>Fabricante</th>
            <th>Tipo</th>
            <th>Estado en log</th>
            <th class="text-end pe-3">Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($unknown as $u): ?>
          <tr>
            <td class="ps-3"><code><?= htmlspecialchars($u['mac']) ?></code></td>
            <td><?= htmlspecialchars($u['oui_vendor']) ?></td>
            <td>
              <i class="<?= PluginFreeradiusOui::getDeviceTypeIcon($u['device_type']) ?> me-1"></i>
              <?= PluginFreeradiusOui::getDeviceTypeLabel($u['device_type']) ?>
            </td>
            <td>
              <span class="badge bg-<?= $u['status'] === 'OK' ? 'success' : 'danger' ?>">
                <?= $u['status'] ?>
              </span>
            </td>
            <td class="text-end pe-3">
              <a href="device.form.php?mac=<?= urlencode($u['mac']) ?>"
                 class="btn btn-xs btn-outline-primary">
                <i class="ti ti-plus me-1"></i>Agregar
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php
Html::footer();
