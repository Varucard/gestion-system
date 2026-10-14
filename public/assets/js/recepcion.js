/**
 * "Llegó un equipo": busca el DNI y muestra solo lo que falta cargar.
 * Ver views/recepcion/form.php (estados del formulario).
 */
$(function () {
  const $form = $('#form_recepcion');
  if (!$form.length) {
    return;
  }

  const $dni = $('#dni');
  const $resultado = $('#resultado_dni');
  const $clienteNuevo = $('#cliente_nuevo');
  const $equipos = $('#equipos_cliente');
  const $equipoNuevo = $('#equipo_nuevo');
  const $avisoAbierta = $('#aviso_abierta');
  const normalizar = (texto) => String(texto || '').replace(/\D/g, '');
  let buscado = $form.data('estado') === 'sin-buscar' ? null : normalizar($dni.val());

  function alerta(tipo, icono, partes) {
    const $alerta = $('<div>', { class: `alert alert-${tipo} mb-2` }).append($('<i>', { class: `bi bi-${icono} me-1`, 'aria-hidden': 'true' }));
    partes.forEach((parte) => $alerta.append(parte));
    return $alerta;
  }

  function habilitar($fieldset, si) {
    $fieldset.prop('disabled', !si).prop('hidden', !si);
  }

  // Botón de abajo: "Recibir el equipo" o, si el equipo elegido ya tiene una orden abierta, "Abrir
  // otra orden igual" (secundario). Abrirla se confirma, y recién ahí confirmar_otra lleva el N° de
  // la orden que se vio: el servidor lo compara con las abiertas en ese momento.
  const $recibir = $('.js-recibir');
  let abierta = $recibir.data('abierta') || null;
  function modoOtra(id) {
    abierta = id || null;
    $('#confirmar_otra').val('');
    $recibir.toggleClass('btn-seccion', !abierta).toggleClass('btn-outline-secondary', Boolean(abierta));
    $recibir.find('.js-recibir-texto').text(abierta ? $recibir.data('texto-otra') : $recibir.data('texto-normal'));
  }

  $form.on('submit', (event) => {
    if (!abierta || $('#confirmar_otra').val() === String(abierta)) {
      return;
    }
    event.preventDefault();
    window.confirmar('¿Abrir otra orden para un equipo que ya está en el local? Si viene por lo mismo, seguí con la orden abierta.',
      { aceptar: 'Sí, abrir otra orden', textoCancelar: 'No, volver' }).then((ok) => {
      if (ok) {
        $('#confirmar_otra').val(String(abierta));
        $form[0].requestSubmit();
      }
    });
  });

  function mostrar(estado) {
    $form.attr('data-estado', estado);
    $('.js-tras-buscar').prop('hidden', estado === 'sin-buscar');
    habilitar($clienteNuevo, estado === 'nuevo');
    $equipos.prop('hidden', estado !== 'conocido');
    if (estado !== 'conocido') {
      habilitar($equipoNuevo, estado === 'nuevo');
      $avisoAbierta.empty();
      modoOtra(null);
    }
  }

  // ---------- Qué equipo trae: uno de los del cliente, u "Otro" (se carga ahora) ----------
  function elegirEquipo() {
    const $elegido = $equipos.find('input[name="equipo_id"]:checked');
    const nuevo = !$elegido.length || $elegido.val() === '';
    habilitar($equipoNuevo, nuevo);

    // Ya está en el local: lo principal es seguir con esa orden; abrir otra queda como secundario.
    const id = nuevo ? null : Number($elegido.data('abierta')) || null;
    $avisoAbierta.empty();
    if (id) {
      $avisoAbierta.append(alerta('warning', 'exclamation-triangle', [
        $('<span>', { class: 'me-auto' }).text(` Este equipo ya está en el local: tiene la orden #${id} abierta o lista sin retirar.`),
        $('<a>', { href: $form.data('orden-url').replace('{id}', id), class: 'btn btn-primary' })
          .html('<i class="bi bi-arrow-right" aria-hidden="true"></i> ').append(document.createTextNode(`Seguir con la orden #${id}`)),
      ]).addClass('d-flex flex-wrap gap-2 align-items-center'));
    }
    modoOtra(id);
    return id;
  }
  $equipos.on('change', 'input[name="equipo_id"]', elegirEquipo);

  function armarEquipos(lista) {
    $equipos.empty();
    lista.forEach((equipo, i) => {
      const abiertaEquipo = equipo.abiertas.reduce((mayor, o) => Math.max(mayor, o.id), 0) || null;
      const $texto = $('<span>').text(equipo.texto);
      if (abiertaEquipo) {
        $texto.append(' ', $('<span>', { class: 'badge text-bg-warning ms-1' }).text(`En el local · orden #${abiertaEquipo}`));
      }
      $equipos.append($('<label>', { class: 'equipo-opcion' }).append(
        $('<input>', { type: 'radio', class: 'form-check-input', name: 'equipo_id', value: equipo.id, checked: i === 0, 'data-abierta': abiertaEquipo || undefined }),
        $texto,
      ));
    });
    $equipos.append($('<label>', { class: 'equipo-opcion' }).append(
      $('<input>', { type: 'radio', class: 'form-check-input', name: 'equipo_id', value: '', checked: lista.length === 0 }),
      $('<span>').html('<i class="bi bi-plus-lg" aria-hidden="true"></i> ').append(document.createTextNode('Otro equipo (lo cargo ahora)')),
    ));
  }

  let buscando = null; // DNI que se está buscando: el blur y el clic en "Buscar" no lo piden dos veces
  function buscar() {
    if (!$dni[0].checkValidity()) {
      $dni[0].reportValidity(); // muestra el error debajo del campo (app.js)
      return;
    }
    const dni = normalizar($dni.val());
    if (buscando === dni) {
      return;
    }
    buscando = dni;

    $.getJSON($form.data('cliente-url'), { dni })
      .always(() => { buscando = null; })
      .done((cliente) => {
        // Llegó tarde: mientras tanto se siguió escribiendo. Se ignora para no mostrar a otra persona.
        if (normalizar($dni.val()) !== dni) {
          return;
        }
        buscado = dni;
        $resultado.empty();

        if (!cliente) {
          $resultado.append(alerta('info', 'info-circle', ['Cliente nuevo: completá sus datos y los del equipo.']));
          mostrar('nuevo');
          $('#nombre').trigger('focus');
          return;
        }

        $resultado.append(alerta('success', 'check-circle-fill', [
          'Ya es cliente: ', $('<strong>').text(cliente.nombre), '. ',
          cliente.activo ? '' : '(estaba dado de baja: al recibir el equipo se vuelve a activar) ',
        ]));
        armarEquipos(cliente.equipos);
        mostrar('conocido');
        const id = elegirEquipo();
        // Si el equipo ya está en el local, el cursor va a "Seguir con la orden", no a cargar una nueva.
        (id ? $avisoAbierta.find('a.btn-primary') : $equipos.find('input:checked')).trigger('focus');
      })
      .fail(() => window.avisar('No se pudo buscar el DNI. Revisá la conexión e intentá de nuevo.'));
  }

  // Borrador recuperado (app.js): si había un DNI, se vuelve a buscar para mostrar lo que corresponde.
  $form.on('borrador:restaurado', () => {
    if ($dni.val().trim() !== '' && $form.attr('data-estado') === 'sin-buscar') {
      buscar();
    }
  });

  $('.js-buscar-dni').on('click', buscar);
  $dni.on('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault(); // Enter busca; no envía el formulario
      buscar();
    }
  });
  // Al salir del campo con un DNI completo, se busca solo.
  $dni.on('change', () => {
    if (normalizar($dni.val()) !== buscado && $dni[0].checkValidity()) {
      buscar();
    }
  });
  // Si se corrige el DNI, lo de abajo deja de valer hasta volver a buscar.
  $dni.on('input', () => {
    if (normalizar($dni.val()) !== buscado) {
      $resultado.empty();
      mostrar('sin-buscar');
    }
  });
});
