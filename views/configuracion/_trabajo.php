<?php /** @var array<string, mixed> $valores */ ?>
<div class="row">
  <?php foreach (['validez' => 'Validez del presupuesto', 'garantia' => 'Garantía', 'tiempo_estimado' => 'Tiempo estimado'] as $campo => $etiqueta): ?>
    <?= $view->partial('componentes/campo', [
      'nombre' => $campo, 'etiqueta' => "{$etiqueta} (días) *", 'tipo' => 'number', 'valor' => $valores[$campo], 'columna' => 'col-md-4 mb-3',
      'atributos' => ['min' => 1, 'max' => 365, 'required' => true],
    ]) ?>
  <?php endforeach; ?>
</div>
<?= $view->partial('componentes/campo', [
  'nombre' => 'forma_pago', 'etiqueta' => 'Formas de pago (una por línea) *', 'tipo' => 'textarea', 'columna' => 'mb-3',
  'valor' => implode("\n", $valores['forma_pago']), 'atributos' => ['rows' => 5, 'required' => true],
]) ?>
<?= $view->partial('componentes/campo', [
  'nombre' => 'observaciones', 'etiqueta' => 'Observaciones del presupuesto (una por línea)', 'tipo' => 'textarea', 'columna' => 'mb-3',
  'valor' => implode("\n", $valores['observaciones']), 'atributos' => ['rows' => 4],
]) ?>
<div class="form-check form-switch mb-3">
  <input class="form-check-input" type="checkbox" role="switch" id="aceptar_inicia_trabajo" name="aceptar_inicia_trabajo" value="1"
    <?= (session()->hasOldInput() ? old('aceptar_inicia_trabajo') : $valores['aceptar_inicia_trabajo']) ? 'checked' : '' ?>>
  <label class="form-check-label" for="aceptar_inicia_trabajo">Cuando el cliente acepta el presupuesto desde el link, pasar la orden a <em>En reparación</em></label>
</div>
<?= $view->partial('componentes/campo', [
  'nombre' => 'garantia_repuestos_cliente', 'etiqueta' => 'Aviso de garantía para los repuestos que trae el cliente', 'tipo' => 'textarea', 'columna' => 'mb-3',
  'valor' => $valores['garantia_repuestos_cliente'], 'atributos' => ['rows' => 2],
  'ayuda' => 'Sale en el presupuesto y en el comprobante de entrega solo si la orden lleva algún repuesto que trajo el cliente. Vacío: no se muestra.',
]) ?>
<?= $view->partial('componentes/campo', [
  'nombre' => 'mensaje_legal', 'etiqueta' => 'Mensaje legal', 'tipo' => 'textarea', 'columna' => 'mb-3',
  'valor' => $valores['mensaje_legal'], 'atributos' => ['rows' => 2],
]) ?>
<h5 class="mt-4">Alertas del inicio</h5>
<div class="row">
  <?= $view->partial('componentes/campo', [
    'nombre' => 'alerta_listo_dias', 'etiqueta' => 'Avisar de un equipo listo sin retirar después de (días)', 'tipo' => 'number', 'columna' => 'col-md-6 mb-3',
    'valor' => $valores['alerta_listo_dias'], 'atributos' => ['min' => 1, 'max' => 60, 'required' => true],
  ]) ?>
  <?= $view->partial('componentes/campo', [
    'nombre' => 'alerta_presupuesto_dias', 'etiqueta' => 'Avisar de un presupuesto sin respuesta después de (días)', 'tipo' => 'number', 'columna' => 'col-md-6 mb-3',
    'valor' => $valores['alerta_presupuesto_dias'], 'atributos' => ['min' => 1, 'max' => 60, 'required' => true],
  ]) ?>
</div>
<h5 class="mt-2">Ticket</h5>
<?= $view->partial('componentes/campo', [
  'nombre' => 'ticket_ancho', 'etiqueta' => 'Ancho del papel de la impresora de tickets', 'tipo' => 'select', 'columna' => 'mb-3', 'clase' => 'ancho-max-420',
  'valor' => (string) $valores['ticket_ancho'], 'opciones' => ['80' => '80 mm (la más común)', '58' => '58 mm'],
]) ?>

