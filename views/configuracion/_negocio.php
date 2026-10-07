<?php /** @var array<string, mixed> $valores */ ?>
<div class="row">
  <?= $view->partial('componentes/campo', [
    'nombre' => 'nombre', 'etiqueta' => 'Nombre del negocio *', 'valor' => $valores['nombre'], 'columna' => 'col-md-6 mb-3',
    'atributos' => ['required' => true],
  ]) ?>
  <?= $view->partial('componentes/campo', [
    'nombre' => 'cuit', 'etiqueta' => 'CUIT *', 'valor' => $valores['cuit'], 'columna' => 'col-md-6 mb-3',
    'atributos' => ['required' => true, 'pattern' => '[0-9]{2}-?[0-9]{8}-?[0-9]', 'title' => 'Formato: 20-12345678-9'],
  ]) ?>
  <?= $view->partial('componentes/campo', [
    'nombre' => 'direccion', 'etiqueta' => 'Dirección *', 'valor' => $valores['direccion'], 'columna' => 'col-12 mb-3',
    'atributos' => ['required' => true],
  ]) ?>
  <?= $view->partial('componentes/campo', [
    'nombre' => 'telefono', 'etiqueta' => 'Teléfono *', 'valor' => $valores['telefono'], 'columna' => 'col-md-4 mb-3',
    'atributos' => ['required' => true],
  ]) ?>
  <?= $view->partial('componentes/campo', [
    'nombre' => 'whatsapp', 'etiqueta' => 'WhatsApp *', 'valor' => $valores['whatsapp'], 'columna' => 'col-md-4 mb-3',
    'atributos' => ['required' => true, 'placeholder' => '+5491136359867'],
  ]) ?>
  <?= $view->partial('componentes/campo', [
    'nombre' => 'email', 'etiqueta' => 'Email *', 'tipo' => 'email', 'valor' => $valores['email'], 'columna' => 'col-md-4 mb-3',
    'atributos' => ['required' => true],
  ]) ?>
</div>
