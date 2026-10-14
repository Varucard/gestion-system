<?php
/**
 * @var array<string, mixed> $orden
 * @var array<string, mixed> $cliente
 * @var list<array<string, mixed>> $items
 * @var list<array<string, mixed>> $pagos
 * @var float $saldo
 * @var list<string> $formasPago
 * @var \App\Enums\EstadoOrden $estado
 * @var list<array<string, mixed>> $historial
 * @var bool $puedeEnviar
 * @var bool $whatsapp  mostrar los botones de WhatsApp (configuración y teléfono del cliente)
 * @var bool $avisaListo  al terminar se le avisa al cliente que el equipo está listo
 * @var array<string, mixed>|null $turno  turno del que vino la orden
 * @var list<array<string, mixed>> $fotos  fotos sacadas en esta orden (al recibir el equipo o después)
 * @var array<string, array<string, mixed>> $firmas  momento (recepcion | entrega) => firma
 * @var bool $puedeDeshacerEntrega  recién entregado, o es administrador (EntregaService::anular)
 */
use App\Enums\EstadoOrden;
use App\Services\OrdenService;

$pagado = (float) $orden['total'] - $saldo;
$id = (int) $orden['id'];
$editable = OrdenService::editable($estado);
$sinItems = $items === [];
// Solo los repuestos que salen del stock (no los que trajo el cliente).
$repuestos = count(array_filter($items, [\App\Services\StockService::class, 'mueveStock']));

/** Botón que cambia el estado de la orden (POST a ordenes/{id}/estado), con su confirmación. */
// Sin $confirmacion se hace al toque y después se ofrece "Deshacer" (OrdenController::cambiarEstado).
$botonEstado = fn(EstadoOrden $nuevo, string $icono, string $texto, string $clase, ?string $confirmacion = null) =>
  '<form action="' . e(url("ordenes/{$id}/estado")) . '" method="POST" class="d-inline"'
  . ($confirmacion ? ' data-confirm="' . e($confirmacion) . '" data-confirm-aceptar="Sí, ' . e(mb_strtolower($texto)) . '"' : '') . '>'
  . csrf_field() . '<input type="hidden" name="estado" value="' . e($nuevo->value) . '">'
  . '<button type="submit" class="btn ' . e($clase) . '">' . icono($icono) . ' ' . e($texto) . '</button></form>';

$alTerminar = array_filter([
  $repuestos > 0 ? ($repuestos === 1 ? 'se descuenta del stock el repuesto de la orden' : "se descuentan del stock los {$repuestos} repuestos de la orden") : null,
  $avisaListo ? "le avisamos al cliente por email que el equipo está listo" : null,
]);
$confirmarTerminar = '¿Terminar el trabajo?' . ($alTerminar ? ' Al confirmar, ' . implode(' y ', $alTerminar) . '.' : '');

// Las etapas, con los mismos nombres que ve el cliente en su portal, más la entrega: un equipo
// "Listo" que no se entregó sigue en el local.
$entregado = $orden['entregado_en'] !== null;
$pasos = [EstadoOrden::Pendiente->label(), EstadoOrden::EnProceso->label(), EstadoOrden::Finalizado->label(), 'Entregado'];
$actual = $entregado ? 4 : array_search($estado, [EstadoOrden::Pendiente, EstadoOrden::EnProceso, EstadoOrden::Finalizado], true);
// Una sola acción principal: con saldo, cobrar (entregar queda al lado, de contorno); pagado, entregar.
$botonEntregar = '<a href="' . e(url("ordenes/{$id}/entregar")) . '" class="btn ' . ($saldo > 0 ? 'btn-outline-success' : 'btn-success') . ' btn-lg">' . icono('key') . ' Entregar el equipo</a>';
$botonAvisar = $whatsapp ? $view->partial('partials/boton_whatsapp', ['orden' => $id, 'tipo' => 'listo', 'texto' => 'Avisarle por WhatsApp', 'clase' => 'btn btn-outline-success btn-lg']) : '';
?>
<div class="card mt-3 etapa-orden">
  <div class="card-body">
    <?php if ($estado === EstadoOrden::Cancelado): ?>
      <p class="mb-3"><?= $view->partial('componentes/estado', ['estado' => $estado]) ?> Esta orden está cancelada.</p>
      <?= $botonEstado(EstadoOrden::Pendiente, 'arrow-counterclockwise', 'Volver a abrir la orden', 'btn-outline-primary') ?>
    <?php else: ?>
      <ol class="pasos-orden" aria-label="Etapas de la orden">
        <?php foreach ($pasos as $i => $paso): ?>
          <li class="<?= $i < $actual ? 'hecho' : ($i === $actual ? 'actual' : '') ?>" <?= $i === $actual ? 'aria-current="step"' : '' ?>><?= e($paso) ?></li>
        <?php endforeach; ?>
      </ol>

      <h2 class="h5 mt-3">Qué sigue</h2>
      <?php if ($editable && $sinItems): ?>
        <p class="mb-3">Cuando sepas qué hay que hacer, cargá los trabajos y repuestos: con eso se arma el presupuesto.</p>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= url("ordenes/{$id}/editar#trabajos") ?>" class="btn btn-seccion btn-lg"><?= icono('list-check') ?> Cargar trabajos y repuestos</a>
          <?php if ($estado === EstadoOrden::Pendiente): ?>
            <?= $botonEstado(EstadoOrden::EnProceso, 'play-fill', 'Empezar el trabajo', 'btn-outline-secondary btn-lg') ?>
          <?php endif; ?>
        </div>
      <?php elseif ($estado === EstadoOrden::Pendiente): ?>
        <?php $falta = !$orden['presupuesto_enviado'] && $orden['presupuesto_respuesta'] === null; ?>
        <p class="mb-3"><?= $falta
          ? 'El equipo está recibido. Mandale el presupuesto al cliente y, cuando arranquen, tocá «Empezar el trabajo».'
          : 'El presupuesto ya se mandó. Cuando arranquen, tocá «Empezar el trabajo».' ?></p>
        <div class="d-flex flex-wrap gap-2">
          <?php if ($falta && $whatsapp): ?>
            <?= $view->partial('partials/boton_whatsapp', ['orden' => $id, 'tipo' => 'presupuesto', 'texto' => 'Mandar el presupuesto por WhatsApp', 'clase' => 'btn btn-seccion btn-lg']) ?>
            <?= $botonEstado(EstadoOrden::EnProceso, 'play-fill', 'Empezar el trabajo', 'btn-outline-secondary btn-lg') ?>
          <?php else: ?>
            <?= $botonEstado(EstadoOrden::EnProceso, 'play-fill', 'Empezar el trabajo', 'btn-seccion btn-lg') ?>
          <?php endif; ?>
        </div>
      <?php elseif ($estado === EstadoOrden::EnProceso): ?>
        <p class="mb-3">Cuando el equipo esté terminado, tocá «Terminar el trabajo».</p>
        <div class="d-flex flex-wrap gap-2">
          <?= $botonEstado(EstadoOrden::Finalizado, 'check2-circle', 'Terminar el trabajo', 'btn-success btn-lg', $confirmarTerminar) ?>
        </div>
      <?php elseif (!$entregado && $saldo > 0): ?>
        <p class="mb-3">El equipo está listo. Falta cobrar <strong><?= importe($saldo) ?></strong>. Cuando lo retiren, tocá «Entregar el equipo».</p>
        <div class="d-flex flex-wrap gap-2">
          <a href="#registrar_pago" class="btn btn-seccion btn-lg" data-enfocar="#monto"><?= icono('cash-coin') ?> Cobrar</a>
          <?= $botonEntregar ?>
          <?= $botonAvisar ?>
        </div>
      <?php elseif (!$entregado): ?>
        <p class="mb-3">El equipo está listo y pagado. Cuando lo retiren, tocá «Entregar el equipo»: ahí el cliente puede firmar la conformidad.</p>
        <div class="d-flex flex-wrap gap-2">
          <?= $botonEntregar ?>
          <?= $botonAvisar ?>
        </div>
      <?php else: ?>
        <p class="mb-3">
          Entregado el <?= format_date($orden['entregado_en'], 'd/m/Y \a \l\a\s H:i') ?><?= isset($firmas['entrega']) ? ', con la firma del cliente' : '' ?>.
          <?= $saldo > 0 ? 'Quedó debiendo <strong>' . importe($saldo) . '</strong>.' : '' ?>
        </p>
        <div class="d-flex flex-wrap gap-2">
          <?php if ($saldo > 0): ?>
            <a href="#registrar_pago" class="btn btn-seccion btn-lg" data-enfocar="#monto"><?= icono('cash-coin') ?> Cobrar</a>
          <?php endif; ?>
          <a href="<?= url("ordenes/{$id}/entrega") ?>" class="btn btn-outline-secondary btn-lg"><?= icono('receipt') ?> Comprobante de entrega</a>
          <a href="<?= url("ordenes/{$id}/ticket/entrega?imprimir=1") ?>" class="btn btn-outline-secondary btn-lg"><?= icono('receipt-cutoff') ?> Ticket</a>
        </div>
      <?php endif; ?>

      <div class="d-flex flex-wrap gap-3 mt-3 small">
        <?php if ($editable): ?>
          <form action="<?= url("ordenes/{$id}/estado") ?>" method="POST" class="d-inline" >
            <?= csrf_field() ?><input type="hidden" name="estado" value="cancelado">
            <button type="submit" class="btn btn-link btn-sm p-0 text-danger">Cancelar la orden</button>
          </form>
        <?php endif; ?>
        <?php if ($estado === EstadoOrden::EnProceso): ?>
          <form action="<?= url("ordenes/{$id}/estado") ?>" method="POST" class="d-inline" >
            <?= csrf_field() ?><input type="hidden" name="estado" value="pendiente">
            <button type="submit" class="btn btn-link btn-sm p-0">Volver a «<?= e(EstadoOrden::Pendiente->label()) ?>»</button>
          </form>
        <?php elseif ($estado === EstadoOrden::Finalizado && $entregado && $puedeDeshacerEntrega): ?>
          <form action="<?= url("ordenes/{$id}/entrega/anular") ?>" method="POST" class="d-inline"
            data-confirm="¿Deshacer la entrega? El equipo vuelve a figurar como listo para retirar<?= isset($firmas['entrega']) ? ' y se borra la firma del cliente' : '' ?>." data-confirm-aceptar="Sí, deshacer la entrega">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-link btn-sm p-0">Deshacer la entrega</button>
          </form>
        <?php elseif ($estado === EstadoOrden::Finalizado): ?>
          <form action="<?= url("ordenes/{$id}/estado") ?>" method="POST" class="d-inline"
            data-confirm="¿Volver a abrir el trabajo?<?= $repuestos > 0 ? ' Los repuestos vuelven al stock hasta que se termine de nuevo.' : '' ?>" data-confirm-aceptar="Sí, volver a abrir el trabajo">
            <?= csrf_field() ?><input type="hidden" name="estado" value="en_proceso">
            <button type="submit" class="btn btn-link btn-sm p-0">Volver a abrir el trabajo</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
  <?php if ($editable): ?>
    <a href="<?= url("ordenes/{$id}/editar") ?>" class="btn btn-outline-primary"><?= icono('pencil') ?> Editar orden</a>
  <?php endif; ?>
  <div class="dropdown">
    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <?= icono('printer') ?> Imprimir / Enviar
    </button>
    <ul class="dropdown-menu">
      <?php // Tickets para la impresora térmica, con el QR para seguir el equipo (o aceptar el presupuesto). ?>
      <li><h6 class="dropdown-header">Ticket (impresora de tickets)</h6></li>
      <li><a class="dropdown-item" href="<?= url("ordenes/{$id}/ticket/recepcion?imprimir=1") ?>"><?= icono('receipt-cutoff') ?> De recepción, con QR para seguir el equipo</a></li>
      <?php if (!$sinItems): ?>
        <li><a class="dropdown-item" href="<?= url("ordenes/{$id}/ticket/presupuesto?imprimir=1") ?>"><?= icono('receipt-cutoff') ?> Del presupuesto</a></li>
      <?php endif; ?>
      <?php if ($estado === EstadoOrden::Finalizado): ?>
        <li><a class="dropdown-item" href="<?= url("ordenes/{$id}/ticket/entrega?imprimir=1") ?>"><?= icono('receipt-cutoff') ?> De entrega</a></li>
      <?php endif; ?>
    <?php if (!$sinItems): ?>
        <li><hr class="dropdown-divider"></li>
        <li><h6 class="dropdown-header">Presupuesto</h6></li>
        <li><a class="dropdown-item" href="<?= url("ordenes/{$id}/presupuesto") ?>"><?= icono('file-text') ?> Ver e imprimir</a></li>
        <li><a class="dropdown-item" href="<?= url("ordenes/{$id}/presupuesto/pdf") ?>"><?= icono('file-earmark-pdf') ?> Descargar PDF</a></li>
        <?php if ($puedeEnviar && $editable && $cliente['email']): ?>
          <li>
            <form action="<?= url("ordenes/{$id}/enviar-presupuesto") ?>" method="POST"
              data-confirm="¿Enviar el presupuesto por email a <?= e($cliente['email']) ?>?" data-confirm-aceptar="Sí, enviarlo">
              <?= csrf_field() ?>
              <button type="submit" class="dropdown-item"><?= icono('envelope') ?> Mandar por email al cliente</button>
            </form>
          </li>
        <?php elseif ($puedeEnviar && $editable): ?>
          <?php // Sin email no se puede mandar: se dice por qué y cómo resolverlo, en vez de un botón gris. ?>
          <li><a class="dropdown-item" href="<?= url("clientes/{$cliente['id']}/editar") ?>"><?= icono('envelope-plus') ?> Cargarle el email al cliente para mandárselo</a></li>
        <?php endif; ?>
        <?php if ($editable && $whatsapp): ?>
          <?php // El mensaje lleva el link para ver y aceptar el presupuesto: no hace falta adjuntar el PDF. ?>
          <li><?= $view->partial('partials/boton_whatsapp', ['orden' => $id, 'tipo' => 'presupuesto', 'texto' => 'Mandar por WhatsApp, con el link para aceptarlo']) ?></li>
        <?php endif; ?>
        <?php if ($estado === EstadoOrden::Finalizado): ?>
          <li><hr class="dropdown-divider"></li>
          <li><h6 class="dropdown-header">Comprobante de entrega</h6></li>
          <li><a class="dropdown-item" href="<?= url("ordenes/{$id}/entrega") ?>"><?= icono('receipt') ?> Ver e imprimir</a></li>
          <li><a class="dropdown-item" href="<?= url("ordenes/{$id}/entrega/pdf") ?>"><?= icono('file-earmark-pdf') ?> Descargar PDF</a></li>
          <?php if ($whatsapp && !$entregado): ?>
            <li><?= $view->partial('partials/boton_whatsapp', ['orden' => $id, 'tipo' => 'listo', 'texto' => 'Avisar por WhatsApp que está listo']) ?></li>
          <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
    </ul>
  </div>
  <a href="<?= url('ordenes') ?>" class="btn btn-outline-secondary ms-auto">Volver a órdenes</a>
</div>

<div class="row g-3">
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><strong>Orden #<?= (int) $orden['id'] ?></strong></div>
      <div class="card-body">
        <p class="mb-1">Estado: <?= $view->partial('componentes/estado', ['estado' => $estado, 'entregado' => $entregado]) ?></p>
        <?php if ($entregado): ?><p class="mb-1">Entregado: <?= format_date($orden['entregado_en'], 'd/m/Y H:i') ?></p><?php endif; ?>
        <p class="mb-1">Fecha: <?= format_date($orden['created_at']) ?></p>
        <p class="mb-1">Técnico: <?= e($orden['tecnico'] ?? 'sin asignar') ?></p>
        <?php if ($orden['presupuesto_respuesta'] === 'aceptado'): ?>
          <p class="mb-1 text-success"><?= icono('check-lg') ?> Presupuesto aceptado por el cliente (<?= format_date($orden['presupuesto_respuesta_en'], 'd/m H:i') ?>)</p>
        <?php elseif ($orden['presupuesto_respuesta'] === 'rechazado'): ?>
          <p class="mb-1 text-danger"><?= icono('x-lg') ?> Presupuesto rechazado por el cliente (<?= format_date($orden['presupuesto_respuesta_en'], 'd/m H:i') ?>)</p>
        <?php elseif ($orden['presupuesto_enviado'] && $editable): ?>
          <p class="mb-1 text-muted">Presupuesto enviado el <?= format_date($orden['presupuesto_enviado'], 'd/m H:i') ?>, sin respuesta</p>
        <?php endif; ?>
        <?php if ($turno): ?>
          <p class="mb-1">Vino con <a href="<?= url("turnos/{$turno['id']}/editar") ?>">turno del <?= format_date($turno['fecha']) ?> a las <?= e(substr($turno['hora'], 0, 5)) ?></a></p>
        <?php endif; ?>
        <?php if ($orden['fecha_realizado']): ?><p class="mb-1">Finalizada: <?= format_date($orden['fecha_realizado']) ?></p><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><strong>Cliente</strong></div>
      <div class="card-body">
        <p class="mb-1"><a href="<?= url("clientes/{$cliente['id']}") ?>"><?= e("{$cliente['apellido']}, {$cliente['nombre']}") ?></a></p>
        <p class="mb-1">Tel: <?= e($cliente['telefono']) ?></p>
        <?php if ($cliente['email']): ?><p class="mb-1"><?= e($cliente['email']) ?></p><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><strong>Equipo</strong></div>
      <div class="card-body">
        <p class="mb-1"><a href="<?= url("equipos/{$orden['equipo_id']}") ?>"><?= e(equipo_texto($orden, false)) ?></a></p>
        <?php if ($orden['numero_serie']): ?><p class="mb-1">N° de serie: <?= e($orden['numero_serie']) ?></p><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($orden['falla_reportada'] || $orden['diagnostico'] || $orden['trabajo_realizado'] || $orden['notas_internas'] || $orden['proximo_mantenimiento_fecha']): ?>
  <div class="card mt-3">
    <div class="card-body">
      <div class="row g-3">
        <?php if ($orden['falla_reportada']): ?>
          <div class="col-md-6"><strong>Falla reportada</strong><div><?= nl2br(e($orden['falla_reportada'])) ?></div></div>
        <?php endif; ?>
        <?php if ($orden['diagnostico']): ?>
          <div class="col-md-6"><strong>Diagnóstico</strong><div><?= nl2br(e($orden['diagnostico'])) ?></div></div>
        <?php endif; ?>
        <?php if ($orden['trabajo_realizado']): ?>
          <div class="col-md-6"><strong>Trabajo realizado</strong><div><?= nl2br(e($orden['trabajo_realizado'])) ?></div></div>
        <?php endif; ?>
        <?php if ($orden['proximo_mantenimiento_fecha']): ?>
          <div class="col-md-6">
            <strong>Próximo mantenimiento</strong>
            <div>
              <?= format_date($orden['proximo_mantenimiento_fecha']) ?>
              <?php if ($orden['proximo_mantenimiento_avisado']): ?><span class="small text-muted">(avisado el <?= format_date($orden['proximo_mantenimiento_avisado']) ?>)</span><?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
        <?php if ($orden['notas_internas']): ?>
          <div class="col-md-6"><strong>Notas internas</strong><div class="text-muted"><?= nl2br(e($orden['notas_internas'])) ?></div></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php $hayLlegada = $orden['accesorios'] || $orden['estado_ingreso'] || $fotos !== [] || $firmas !== []; ?>
<div class="card mt-3" id="como_llego">
  <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <strong>Cómo llegó</strong>
    <span class="small text-muted"><?= $fotos === [] ? 'Sin fotos' : count($fotos) . (count($fotos) === 1 ? ' foto' : ' fotos') ?></span>
  </div>
  <div class="card-body">
    <?php if (!$hayLlegada): ?>
      <p class="text-muted">No se cargó cómo llegó el equipo. Podés sumar fotos ahora (golpes, rayones, la pantalla).</p>
    <?php endif; ?>
    <?php if ($orden['accesorios'] || $orden['estado_ingreso']): ?>
      <div class="row g-3 mb-2">
        <?php if ($orden['accesorios']): ?>
          <div class="col-sm-4"><strong>Accesorios que dejó</strong><div><?= e($orden['accesorios']) ?></div></div>
        <?php endif; ?>
        <?php if ($orden['estado_ingreso']): ?>
          <div class="col-sm-8"><strong>Golpes, rayones o faltantes</strong><div><?= nl2br(e($orden['estado_ingreso'])) ?></div></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($fotos !== []): ?>
      <div class="fotos-vista mb-3">
        <?php foreach ($fotos as $i => $foto): ?>
          <?php $src = url("equipos/{$foto['equipo_id']}/imagenes/{$foto['id']}"); ?>
          <a href="<?= e($src) ?>" target="_blank" rel="noopener" class="foto-miniatura" title="Ver la foto <?= $i + 1 ?> (<?= format_date($foto['created_at'], 'd/m H:i') ?>)">
            <img src="<?= e($src) ?>" alt="Foto <?= $i + 1 ?> del equipo en esta orden" loading="lazy">
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="d-flex flex-wrap gap-4 mb-3">
      <?php foreach (\App\Services\FirmaService::MOMENTOS as $momento => $cuando): ?>
        <?php if (isset($firmas[$momento])): ?>
          <figure class="mb-0">
            <img src="<?= url("ordenes/{$id}/firmas/{$momento}") ?>?v=<?= e(strtotime((string) $firmas[$momento]['created_at'])) ?>" alt="Firma del cliente <?= e($cuando) ?>" class="firma-guardada">
            <figcaption class="small text-muted">Firma <?= e($cuando) ?><?= $firmas[$momento]['aclaracion'] ? ' · ' . e($firmas[$momento]['aclaracion']) : '' ?> · <?= format_date($firmas[$momento]['created_at'], 'd/m/Y H:i') ?></figcaption>
          </figure>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-start">
      <form action="<?= url("ordenes/{$id}/fotos") ?>" method="POST" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-start">
        <?= csrf_field() ?>
        <?= $view->partial('componentes/fotos', ['ayuda' => 'Se guardan en esta orden y en la ficha del equipo.']) ?>
        <?php // Aparece recién cuando se eligieron fotos (fotos.js). ?>
        <button type="submit" class="btn btn-seccion btn-lg js-fotos-enviar" hidden><?= icono('upload') ?> Guardar las fotos</button>
      </form>
      <?php if (!isset($firmas['recepcion']) && $estado !== EstadoOrden::Cancelado && !$entregado): ?>
        <a href="<?= url("ordenes/{$id}/firmar") ?>" class="btn btn-outline-secondary btn-lg"><?= icono('pen') ?> Que firme cómo llegó</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header"><strong>Detalle</strong></div>
  <div class="card-body table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr><th>Ítem</th><th class="text-end">Cantidad</th><th class="text-end">Precio unit.</th><th class="text-end">Subtotal</th></tr>
      </thead>
      <tbody>
        <?php if ($sinItems): ?>
          <tr><td colspan="4" class="text-muted">Todavía no se cargaron trabajos ni repuestos.<?= $editable ? ' <a href="' . e(url("ordenes/{$id}/editar")) . '">Cargarlos</a>' : '' ?></td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
          <tr>
            <td>
              <?= e(item_orden($item)) ?>
              <?php if ($item['a_costo']): ?><span class="badge text-bg-light border" title="Se cobra al precio de costo. El cliente no ve esta marca.">a costo</span><?php endif; ?>
            </td>
            <td class="text-end"><?= qty($item['cantidad']) ?></td>
            <td class="text-end"><?= $item['provisto_cliente'] ? '<span class="text-muted">—</span>' : importe($item['precio_unitario']) ?></td>
            <td class="text-end"><?= $item['provisto_cliente'] ? '<span class="text-muted">—</span>' : importe($item['costo']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><th colspan="3" class="text-end">Total</th><th class="text-end"><?= importe($orden['total']) ?></th></tr>
        <tr><td colspan="3" class="text-end">Pagado</td><td class="text-end"><?= importe($pagado) ?></td></tr>
        <tr class="<?= $saldo > 0 ? 'fila-saldo-pendiente' : 'fila-saldo-cero' ?>">
          <th colspan="3" class="text-end">Saldo</th><th class="text-end"><?= importe($saldo) ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<div class="card mt-3" id="pagos">
  <div class="card-header"><strong>Pagos</strong></div>
  <div class="card-body">
    <?php if ($pagos === []): ?>
      <?= $view->partial('componentes/vacio', ['icono' => 'cash-coin', 'texto' => 'Todavía no se registraron pagos.']) ?>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr><th>Fecha</th><th>Forma de pago</th><th class="d-none d-md-table-cell">Observación</th><th class="d-none d-md-table-cell">Registró</th><th class="text-end">Monto</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($pagos as $p): ?>
              <tr>
                <td><?= format_date($p['fecha']) ?></td>
                <td><?= e($p['forma_pago']) ?></td>
                <td class="d-none d-md-table-cell"><?= e($p['observacion'] ?? '') ?></td>
                <td class="d-none d-md-table-cell"><?= e($p['usuario'] ?? '—') ?></td>
                <td class="text-end"><?= importe($p['monto']) ?></td>
                <td class="text-end">
                  <?php if (auth()->esAdministrador()): ?>
                    <?= $view->partial('partials/delete_button', [
                      'action' => "pagos/{$p['id']}/anular",
                      'label' => 'Anular',
                      'icono' => 'x-circle',
                      'confirm' => '¿Anular el pago de $ ' . money($p['monto']) . '?',
                    ]) ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($saldo > 0 && $estado->value !== 'cancelado'): ?>
      <h6 class="mt-3" id="registrar_pago" data-enfocar="#monto">Registrar pago</h6>
      <form action="<?= url("ordenes/{$orden['id']}/pagos") ?>" method="POST" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <div class="col-sm-3">
          <label for="monto" class="form-label">Monto *</label>
          <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="text" inputmode="decimal" autocomplete="off" class="form-control js-importe" id="monto" name="monto" required
              data-min="0.01" data-max="<?= e((string) $saldo) ?>" data-error-max="No podés cobrar más que el saldo: $ <?= e(money($saldo)) ?>."
              value="<?= e(old('monto', money($saldo))) ?>">
          </div>
        </div>
        <div class="col-sm-3">
          <label for="forma_pago" class="form-label">Forma de pago *</label>
          <select class="form-select" id="forma_pago" name="forma_pago" required>
            <?php foreach ($formasPago as $forma): ?>
              <option <?= selected($forma === old('forma_pago')) ?>><?= e($forma) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-2">
          <label for="fecha" class="form-label">Fecha *</label>
          <input type="date" class="form-control" id="fecha" name="fecha" max="<?= date('Y-m-d') ?>" required value="<?= e(old('fecha', date('Y-m-d'))) ?>">
        </div>
        <div class="col-sm-4">
          <label for="observacion" class="form-label">Observación</label>
          <input type="text" class="form-control" id="observacion" name="observacion" maxlength="255" value="<?= e(old('observacion')) ?>">
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-seccion"><?= icono('check-lg') ?> Registrar pago</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header"><strong>Historial</strong></div>
  <div class="card-body">
    <?php if ($historial === []): ?>
      <?= $view->partial('componentes/vacio', ['icono' => 'clock-history', 'texto' => 'Sin movimientos registrados.']) ?>
    <?php else: ?>
      <ul class="list-unstyled mb-0 small">
        <?php foreach ($historial as $h): ?>
          <li class="mb-1">
            <span class="text-muted"><?= format_date($h['created_at'], 'd/m/Y H:i') ?></span> ·
            <strong><?= e($h['usuario_nombre'] ?? '—') ?></strong> · <?= e($h['descripcion']) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
