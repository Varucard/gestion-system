/**
 * Turnos: equipos según el cliente elegido y cambio de estado en la agenda.
 */
$(function () {
  const $form = $('#form_turno');

  if ($form.length) {
    const $equipo = $('#equipo_id');

    $('#cliente_id').on('change', function () {
      const clienteId = $(this).val();

      if (!clienteId) {
        window.cargarOpciones($equipo, [], 'Seleccione primero un cliente');
        return;
      }

      $.getJSON($form.data('equipos-url').replace('{id}', clienteId))
        .done((equipos) => {
          window.cargarOpciones($equipo, equipos, equipos.length ? 'Seleccione un equipo' : 'El cliente no tiene equipos activos');
        })
        .fail(() => window.avisar('No se pudieron cargar los equipos del cliente.'));
    });
  }

  window.estadoInline('.estado-turno-select', 'del turno');
});
