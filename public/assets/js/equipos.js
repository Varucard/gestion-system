/**
 * Formulario de equipos: cascada marca → modelo y aviso si el cliente ya tiene equipos.
 */
$(function () {
  const $form = $('#form_equipo');
  const $modelo = $('#modelo_id');

  $('#marca_id').on('change', function () {
    const marcaId = $(this).val();

    if (!marcaId) {
      window.cargarOpciones($modelo, [], 'Seleccione primero una marca');
      return;
    }

    $.getJSON($form.data('modelos-url').replace('{id}', marcaId))
      .done((modelos) => {
        const opciones = modelos.map((m) => ({ id: m.id, texto: m.nombre }));
        window.cargarOpciones($modelo, opciones, opciones.length ? 'Seleccione un modelo' : 'La marca no tiene modelos cargados');
      })
      .fail(() => window.avisar('No se pudieron cargar los modelos.'));
  });

  // Solo al dar de alta: avisar si el cliente ya tiene equipos asignados.
  if ($form.data('nuevo') !== 1) {
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
        const mensaje = `Este cliente ya tiene ${equipos.length} equipo(s) asignado(s). ¿Desea cargarle otro?`;
        const seguir = equipos.length === 0 ? Promise.resolve(true) : window.confirmar(mensaje, { aceptar: 'Cargar otro' });
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
