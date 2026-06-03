<?php
include("../../../inc/includes.php");
Session::checkLoginUser();
global $CFG_GLPI;

Html::header('FreeRADIUS - Dashboard', $_SERVER['PHP_SELF'], 'plugins', 'freeradius');

$stats = PluginFreeradiusDevice::getStats();
$cfg   = PluginFreeradiusConfig::get();
$last_sync = $cfg['last_sync'] ?? null;
?>
<div class="container-fluid px-4 py-3">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h2 class="mb-0"><i class="ti ti-wifi me-2 text-primary"></i>FreeRADIUS — Dashboard</h2>
    <div class="d-flex gap-2">
      <?php if ($last_sync): ?>
        <span class="badge bg-secondary fs-6">
          <i class="ti ti-clock me-1"></i>Última sync: <?= htmlspecialchars($last_sync) ?>
        </span>
      <?php endif; ?>
      <a href="sync.php" class="btn btn-sm btn-primary">
        <i class="ti ti-refresh me-1"></i>Sincronizar
      </a>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="p-3 bg-primary bg-opacity-10 rounded-3">
            <i class="ti ti-device-laptop fs-2 text-primary"></i>
          </div>
          <div>
            <div class="fs-1 fw-bold text-primary"><?= $stats['total'] ?></div>
            <div class="text-muted small">Total dispositivos</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="p-3 bg-success bg-opacity-10 rounded-3">
            <i class="ti ti-circle-check fs-2 text-success"></i>
          </div>
          <div>
            <div class="fs-1 fw-bold text-success"><?= $stats['authorized'] ?></div>
            <div class="text-muted small">Autorizados</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="p-3 bg-danger bg-opacity-10 rounded-3">
            <i class="ti ti-ban fs-2 text-danger"></i>
          </div>
          <div>
            <div class="fs-1 fw-bold text-danger"><?= $stats['blocked'] ?></div>
            <div class="text-muted small">Bloqueados</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="p-3 bg-info bg-opacity-10 rounded-3">
            <i class="ti ti-link fs-2 text-info"></i>
          </div>
          <div>
            <div class="fs-1 fw-bold text-info"><?= $stats['linked'] ?></div>
            <div class="text-muted small">Vinculados a inventario</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <!-- Por VLAN -->
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent border-0 pt-3 pb-0">
          <h5 class="mb-0"><i class="ti ti-network me-2"></i>Distribución por VLAN</h5>
        </div>
        <div class="card-body">
          <?php foreach ($stats['by_vlan'] as $v): ?>
          <div class="d-flex align-items-center mb-2">
            <a href="device.php?vlan=<?= $v['vlan'] ?>" class="text-decoration-none flex-grow-1 d-flex align-items-center">
              <span class="badge me-2" style="background-color:<?= htmlspecialchars($v['color']) ?>;min-width:80px">
                VLAN <?= $v['vlan'] ?>
              </span>
              <span class="text-muted small me-3"><?= htmlspecialchars($v['name']) ?></span>
            </a>
            <div class="flex-grow-1 mx-2">
              <div class="progress" style="height:8px">
                <div class="progress-bar" role="progressbar"
                  style="width:<?= $stats['total'] ? round($v['count']/$stats['total']*100) : 0 ?>%;background-color:<?= htmlspecialchars($v['color']) ?>"
                  title="<?= $v['count'] ?> dispositivos"></div>
              </div>
            </div>
            <span class="fw-bold" style="min-width:30px;text-align:right"><?= $v['count'] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Por tipo -->
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent border-0 pt-3 pb-0">
          <h5 class="mb-0"><i class="ti ti-category me-2"></i>Distribución por tipo</h5>
        </div>
        <div class="card-body">
          <?php foreach ($stats['by_type'] as $t): ?>
          <?php $icon = PluginFreeradiusOui::getDeviceTypeIcon($t['type']); ?>
          <div class="d-flex align-items-center mb-3">
            <div class="p-2 bg-light rounded-2 me-3">
              <i class="<?= $icon ?> fs-5"></i>
            </div>
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between small">
                <span><?= htmlspecialchars($t['label']) ?></span>
                <span class="fw-bold"><?= $t['count'] ?></span>
              </div>
              <div class="progress mt-1" style="height:6px">
                <div class="progress-bar bg-primary" role="progressbar"
                  style="width:<?= $stats['total'] ? round($t['count']/$stats['total']*100) : 0 ?>%"></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Atajos rápidos -->
  <div class="row g-3 mt-2">
    <div class="col-md-4">
      <a href="device.php" class="card border-0 shadow-sm text-decoration-none h-100">
        <div class="card-body text-center py-4">
          <i class="ti ti-device-laptop fs-1 text-primary mb-2 d-block"></i>
          <div class="fw-semibold">Gestionar dispositivos</div>
          <div class="text-muted small">Ver, agregar y editar</div>
        </div>
      </a>
    </div>
    <div class="col-md-4">
      <a href="sync.php" class="card border-0 shadow-sm text-decoration-none h-100">
        <div class="card-body text-center py-4">
          <i class="ti ti-refresh fs-1 text-success mb-2 d-block"></i>
          <div class="fw-semibold">Sincronizar con RADIUS</div>
          <div class="text-muted small">Importar/exportar authorize</div>
        </div>
      </a>
    </div>
    <div class="col-md-4">
      <a href="config.php" class="card border-0 shadow-sm text-decoration-none h-100">
        <div class="card-body text-center py-4">
          <i class="ti ti-settings fs-1 text-secondary mb-2 d-block"></i>
          <div class="fw-semibold">Configuración</div>
          <div class="text-muted small">SSH, base de datos</div>
        </div>
      </a>
    </div>
  </div>
</div>
<?php
Html::footer();
