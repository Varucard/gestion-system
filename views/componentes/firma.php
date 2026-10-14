<?php
/**
 * Recuadro para que el cliente firme con el dedo (o con el mouse), más la aclaración.
 * El dibujo viaja como PNG en el campo oculto "firma" (ver firma.js y FirmaService).
 *
 * @var string $texto      qué firma el cliente (va arriba del recuadro)
 * @var bool|null $requerida  no deja enviar el formulario sin firmar
 * @var string|null $aclaracion  nombre sugerido para la aclaración
 */
$view->script('firma.js');
$requerida = !empty($requerida);
?>
<div class="firma" data-firma <?= $requerida ? 'data-requerida' : '' ?>>
  <p class="mb-2"><?= e($texto) ?></p>
  <div class="firma-lienzo">
    <canvas role="img" aria-label="Recuadro para firmar con el dedo o con el mouse"></canvas>
    <span class="firma-guia" aria-hidden="true"><?= icono('pen') ?> Firmá acá</span>
  </div>
  <input type="hidden" name="firma" value="">
  <div class="d-flex flex-wrap align-items-start gap-3 mt-2">
    <button type="button" class="btn btn-outline-secondary js-firma-borrar"><?= icono('eraser') ?> Borrar y firmar de nuevo</button>
    <div class="flex-grow-1 ancho-max-420">
      <label for="firma_aclaracion" class="form-label visually-hidden">Aclaración</label>
      <input type="text" class="form-control" id="firma_aclaracion" name="firma_aclaracion" maxlength="100" autocomplete="off"
        placeholder="Aclaración (nombre de quien firma)" value="<?= e(old('firma_aclaracion', $aclaracion ?? '')) ?>">
    </div>
  </div>
  <div class="invalid-feedback js-firma-error"><?= $requerida ? 'Falta la firma del cliente.' : '' ?></div>
</div>
