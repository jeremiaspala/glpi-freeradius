<?php
include("../../../inc/includes.php");
Session::checkLoginUser();
global $CFG_GLPI;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    PluginFreeradiusConfig::save([
        'radius_host'         => trim($_POST['radius_host'] ?? ''),
        'radius_ssh_user'     => trim($_POST['radius_ssh_user'] ?? ''),
        'radius_ssh_key'      => trim($_POST['radius_ssh_key'] ?? ''),
        'radius_su_pass'      => trim($_POST['radius_su_pass'] ?? ''),
        'radius_db_host'      => trim($_POST['radius_db_host'] ?? ''),
        'radius_db_user'      => trim($_POST['radius_db_user'] ?? ''),
        'radius_db_pass'      => trim($_POST['radius_db_pass'] ?? ''),
        'radius_db_name'      => trim($_POST['radius_db_name'] ?? ''),
        'authorize_file'      => trim($_POST['authorize_file'] ?? ''),
        'auto_reload_radius'  => isset($_POST['auto_reload_radius']) ? 1 : 0,
    ]);
    Html::redirect($CFG_GLPI['root_doc'] . '/plugins/freeradius/front/config.php?saved=1');
    exit;
}

$cfg = PluginFreeradiusConfig::get();

Html::header('FreeRADIUS - Configuración', $_SERVER['PHP_SELF'], 'plugins', 'freeradius');
?>
<div class="container-fluid px-4 py-3" style="max-width:800px">
  <div class="d-flex align-items-center gap-2 mb-4">
    <a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i>Dashboard</a>
    <h2 class="mb-0 ms-2"><i class="ti ti-settings me-2 text-primary"></i>Configuración FreeRADIUS</h2>
  </div>

  <?php if (isset($_GET['saved'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="ti ti-check me-1"></i>Configuración guardada.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <form method="POST">
            <input type="hidden" name="_glpi_csrf_token" value="<?= Session::getNewCSRFToken() ?>">
    <!-- Conexión SSH -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-transparent border-bottom">
        <h5 class="mb-0"><i class="ti ti-terminal me-2"></i>Conexión SSH al servidor FreeRADIUS</h5>
        <p class="text-muted small mb-0">
          Se usa para leer/escribir el archivo <code>authorize</code> y recargar FreeRADIUS.
        </p>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label fw-semibold">IP / Hostname del servidor FreeRADIUS <span class="text-danger">*</span></label>
            <input type="text" name="radius_host" class="form-control" required
              value="<?= htmlspecialchars($cfg['radius_host'] ?? '192.168.13.36') ?>"
              placeholder="192.168.13.36">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Usuario SSH</label>
            <input type="text" name="radius_ssh_user" class="form-control"
              value="<?= htmlspecialchars($cfg['radius_ssh_user'] ?? 'operador') ?>"
              placeholder="operador">
          </div>
          <div class="col-md-8">
            <label class="form-label fw-semibold">Ruta de la clave SSH privada</label>
            <input type="text" name="radius_ssh_key" class="form-control"
              value="<?= htmlspecialchars($cfg['radius_ssh_key'] ?? '/var/www/.ssh/id_rsa') ?>"
              placeholder="/var/www/.ssh/id_rsa">
            <div class="form-text">
              Clave privada del usuario <code>www-data</code> en el servidor GLPI.
              La clave pública debe estar en <code>~operador/.ssh/authorized_keys</code> en el servidor RADIUS.
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Contraseña <code>su root</code></label>
            <input type="password" name="radius_su_pass" class="form-control"
              value="<?= htmlspecialchars($cfg['radius_su_pass'] ?? '') ?>"
              placeholder="Contraseña de root">
            <div class="form-text">
              Necesaria para escribir el archivo <code>authorize</code>.
            </div>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Ruta del archivo authorize</label>
            <input type="text" name="authorize_file" class="form-control"
              value="<?= htmlspecialchars($cfg['authorize_file'] ?? '/etc/freeradius/3.0/mods-config/files/authorize') ?>"
              placeholder="/etc/freeradius/3.0/mods-config/files/authorize">
          </div>
          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="auto_reload_radius" id="auto_reload"
                value="1" <?= ($cfg['auto_reload_radius'] ?? 1) ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold" for="auto_reload">
                Recargar FreeRADIUS automáticamente al exportar
              </label>
            </div>
            <div class="form-text">Envía señal HUP al proceso freeradius después de escribir el archivo authorize.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Base de datos FreeRADIUS -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-transparent border-bottom">
        <h5 class="mb-0"><i class="ti ti-database me-2"></i>Base de datos MySQL (FreeRADIUS server)</h5>
        <p class="text-muted small mb-0">
          Credenciales MySQL en el servidor FreeRADIUS (accedido via SSH).
        </p>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Host MySQL (en el servidor RADIUS)</label>
            <input type="text" name="radius_db_host" class="form-control"
              value="<?= htmlspecialchars($cfg['radius_db_host'] ?? '127.0.0.1') ?>"
              placeholder="127.0.0.1">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Base de datos</label>
            <input type="text" name="radius_db_name" class="form-control"
              value="<?= htmlspecialchars($cfg['radius_db_name'] ?? 'freeradius') ?>"
              placeholder="freeradius">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Usuario MySQL</label>
            <input type="text" name="radius_db_user" class="form-control"
              value="<?= htmlspecialchars($cfg['radius_db_user'] ?? 'freeradius') ?>"
              placeholder="freeradius">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Contraseña MySQL</label>
            <input type="password" name="radius_db_pass" class="form-control"
              value="<?= htmlspecialchars($cfg['radius_db_pass'] ?? '') ?>"
              placeholder="contraseña">
          </div>
        </div>
      </div>
    </div>

    <!-- Info clave SSH -->
    <div class="alert alert-info mb-4">
      <h6 class="alert-heading"><i class="ti ti-key me-1"></i>Configuración de clave SSH</h6>
      <p class="mb-2 small">Para que GLPI pueda conectarse a FreeRADIUS sin contraseña, el servidor GLPI necesita una clave SSH configurada:</p>
      <ol class="small mb-0">
        <li>La clave <code><?= htmlspecialchars($cfg['radius_ssh_key'] ?? '/var/www/.ssh/id_rsa') ?></code> debe existir en el servidor GLPI (se genera automáticamente al instalar el plugin)</li>
        <li>La clave pública debe estar en <code>~<?= htmlspecialchars($cfg['radius_ssh_user'] ?? 'operador') ?>/.ssh/authorized_keys</code> en el servidor FreeRADIUS</li>
        <li>Verificar con el botón "Probar conexión" en la página de Sincronización</li>
      </ol>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary">
        <i class="ti ti-device-floppy me-1"></i>Guardar configuración
      </button>
      <a href="sync.php?action=test" class="btn btn-outline-info">
        <i class="ti ti-plug me-1"></i>Probar conexión SSH
      </a>
    </div>
  </form>
</div>
<?php
Html::footer();
