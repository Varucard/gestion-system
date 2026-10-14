/**
 * Turnos: equipos según el cliente elegido, horarios libres del día elegido y cambio
 * de estado en la agenda.
 */
$(function () {
  const $form = $('#form_turno');

  if ($form.length) {
    const $equipo = $('#equipo_id');

    $('#cliente_id').on('change', function () {
      const clienteId = $(this).val();

      if (!clienteId) {
        window.cargarOpciones($equipo, [], 'Primero elegí el cliente');
        return;
      }

      $.getJSON($form.data('equipos-url').replace('{id}', clienteId))
        .done((equipos) => {
          window.cargarOpciones($equipo, equipos, equipos.length ? 'Elegí el equipo' : 'El cliente no tiene equipos cargados');
          // Con un solo equipo no hay nada que elegir.
          if (equipos.length === 1) {
            $equipo[0].tomselect ? $equipo[0].tomselect.setValue(String(equipos[0].id)) : $equipo.val(equipos[0].id);
          }
        })
        .fail(() => window.avisar('No se pudieron cargar los equipos del cliente.'));
    });

    // Horarios libres: botones para tocar en vez de escribir la hora. El campo de hora
    // sigue estando, por si hace falta un horario fuera de la grilla.
    const $hora = $('#hora');
    const $caja = $('#horarios_libres');
    const $lista = $caja.find('.js-horarios');

    function marcarElegido() {
      $lista.find('button').each(function () {
        const elegido = $(this).data('hora') === $hora.val();
        $(this).toggleClass('btn-seccion', elegido).toggleClass('btn-outline-secondary', !elegido).attr('aria-pressed', elegido);
      });
    }

    function cargarHorarios() {
      const fecha = $('#fecha').val();
      if (!fecha) {
        $caja.prop('hidden', true);
        return;
      }

      $.getJSON($form.data('horarios-url'), { fecha })
        .done((dia) => {
          $lista.empty();
          dia.horarios.forEach((h) => {
            const completo = h.libres === 0 && h.hora !== $hora.val();
            $('<button>', {
              type: 'button',
              class: 'btn btn-sm btn-outline-secondary',
              'data-hora': h.hora,
              disabled: completo,
              title: completo ? 'Completo' : `${h.libres} lugar(es) libre(s)`,
            }).text(completo ? `${h.hora} · completo` : h.hora).appendTo($lista);
          });
          if (dia.motivo) {
            $('<span>', { class: 'text-muted small' }).text(dia.motivo).appendTo($lista);
          }
          $caja.find('.js-horarios-titulo').prop('hidden', dia.horarios.length === 0);
          $caja.prop('hidden', false);
          marcarElegido();
        })
        .fail(() => $caja.prop('hidden', true));
    }

    $lista.on('click', 'button', function () {
      $hora.val($(this).data('hora')).trigger('input');
      marcarElegido();
    });
    $hora.on('input change', marcarElegido);
    $('#fecha').on('change', cargarHorarios);
    cargarHorarios();
  }

  window.estadoInline('.estado-turno-select', 'del turno');
});
