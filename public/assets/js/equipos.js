/**
 * Marca → modelo (en el formulario de equipos y en "Llegó un equipo") y, al dar de alta
 * un equipo, aviso si el cliente ya tiene otros.
 *
 * El modelo se puede escribir si no está en la lista (data-crear en el <select>): se agrega
 * al catálogo de la marca al guardar.
 */
$(function () {
  const $form = $('form[data-modelos-url]');
  const $modelo = $('#modelo_id');

  $('#marca_id').on('change', function () {
    const marcaId = $(this).val();

    if (!marcaId) {
      window.cargarOpciones($modelo, [], 'Primero elegí la marca');
      $modelo.prop('disabled', true);
      $modelo[0].tomselect?.disable();
      return;
    }
    // Marca escrita a mano ("nuevo:Lenovo"): todavía no tiene modelos, se escribe el modelo también.
    if (marcaId.startsWith($(this).data('crear') || '\u0000')) {
      window.cargarOpciones($modelo, [], 'Escribí el modelo');
      return;
    }

    $.getJSON($form.data('modelos-url').replace('{id}', marcaId))
      .done((modelos) => {
        const opciones = modelos.map((m) => ({ id: m.id, texto: m.nombre }));
        window.cargarOpciones($modelo, opciones, opciones.length ? 'Elegí o escribí el modelo' : 'Escribí el modelo');
      })
      .fail(() => window.avisar('No se pudieron cargar los modelos.'));
  });

  // Solo al dar de alta: avisar si el cliente ya tiene equipos asignados.
  if ($form.attr('id') !== 'form_equipo' || $form.data('nuevo') !== 1) {
    return;
  }

  let confirmado = false;

  $form.on('submit', function (event) {
    const clienteId = $('#cliente_id').val();
    if (confirmado || !clienteId) {
      return;
    }

    event.preventDefault();
    $.getJSON($form.data('equipos-url').replace('{id}', clienteId))
      .done((equipos) => {
        const cuantos = equipos.length === 1 ? 'un equipo cargado' : `${equipos.length} equipos cargados`;
        const mensaje = `Este cliente ya tiene ${cuantos}. ¿Le cargás otro?`;
        const seguir = equipos.length === 0 ? Promise.resolve(true) : window.confirmar(mensaje, { aceptar: 'Sí, cargar otro' });
        seguir.then((ok) => {
          if (ok) {
            confirmado = true;
            $form.trigger('submit');
          }
        });
      })
      .fail(() => {
        confirmado = true;
        $form.trigger('submit');
      });
  });
});
