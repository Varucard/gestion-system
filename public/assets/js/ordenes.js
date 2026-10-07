/**
 * Órdenes y combos: detalle con cantidades (y precios en las órdenes), total,
 * combos, próximo mantenimiento sugerido y cambio de estado en el listado.
 */
$(function () {
  const $form = $('#form_orden');

  if ($form.length) {
    const conPrecio = $form.data('sin-precio') !== 1;
    const moneda = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const $tbody = $('#detalle_orden tbody');
    // Valores ya cargados (edición o vuelta con errores): { servicio: {id: {cantidad, precio}}, repuesto: {...} }
    const cargados = $form.data('detalle') || { servicio: {}, repuesto: {} };

    const numero = (valor) => parseFloat(String(valor).replace(',', '.')) || 0;

    function input(nombre, valor, extra) {
      return $('<input>', { type: 'number', min: '0', step: '0.01', name: nombre, value: valor, class: 'form-control form-control-sm', required: true, ...extra });
    }

    function filas() {
      // Conserva lo que el usuario ya escribió antes de reconstruir la tabla.
      $tbody.find('tr[data-tipo]').each(function () {
        const $tr = $(this);
        cargados[$tr.data('tipo')][$tr.data('id')] = {
          cantidad: $tr.find('.js-cantidad').val(),
          precio: $tr.find('.js-precio').val(),
        };
      });

      $tbody.find('tr[data-tipo]').remove();

      $('.js-item-precio').each(function () {
        const tipo = $(this).data('tipo');
        $(this).find('option:selected').each(function () {
          const id = $(this).val();
          const previo = cargados[tipo][id] || {};
          const $tr = $('<tr>', { 'data-tipo': tipo, 'data-id': id, 'data-precio-catalogo': $(this).data('precio') });

          const nombre = $(this).text().replace(/\s*\(\$.*$/s, '').trim();
          $tr.append($('<td>').text((tipo === 'repuesto' ? 'Repuesto: ' : '') + nombre));
          $tr.append($('<td>').append(input(`cantidad_${tipo}[${id}]`, previo.cantidad ?? 1, { class: 'form-control form-control-sm js-cantidad', min: '0.01', 'aria-label': `Cantidad de ${nombre}` })));
          if (conPrecio) {
            $tr.append($('<td>').append(input(`precio_${tipo}[${id}]`, previo.precio ?? $(this).data('precio'), { class: 'form-control form-control-sm js-precio', 'aria-label': `Precio unitario de ${nombre}` })));
          }
          $tr.append($('<td>', { class: 'text-end importe js-subtotal' }));
          $tbody.append($tr);
        });
      });

      $tbody.find('.js-sin-items').toggle($tbody.find('tr[data-tipo]').length === 0);
      calcularTotal();
    }

    function calcularTotal() {
      let total = 0;
      $tbody.find('tr[data-tipo]').each(function () {
        const precio = conPrecio ? numero($(this).find('.js-precio').val()) : numero($(this).data('precio-catalogo'));
        const subtotal = numero($(this).find('.js-cantidad').val()) * precio;
        $(this).find('.js-subtotal').text('$\u00a0' + moneda.format(subtotal));
        total += subtotal;
      });
      $('#total').val(moneda.format(total));
      $('#total_combo').text(moneda.format(total));
    }

    // Combo: suma sus ítems a la orden (sin quitar los ya elegidos) con sus cantidades.
    $('#agregar_combo').on('change', function () {
      const combo = $(this).find('option:selected').data('items');
      if (!combo) {
        return;
      }
      filas(); // guarda lo escrito hasta ahora
      combo.forEach((item) => {
        const select = document.querySelector(`.js-item-precio[data-tipo="${item.tipo}"]`);
        const actuales = $(select).val() || [];
        if (!actuales.includes(String(item.id))) {
          // Sin disparar "change": la tabla se rearma una sola vez, al final.
          select.tomselect
            ? select.tomselect.setValue([...actuales, String(item.id)], true)
            : $(select).val([...actuales, String(item.id)]);
        }
        cargados[item.tipo][item.id] = { ...(cargados[item.tipo][item.id] || {}), cantidad: item.cantidad };
      });
      filas();
      $(this).val('');
    });

    // Próximo mantenimiento sugerido: hoy + N meses.
    $('#sugerir_mantenimiento').on('click', function () {
      const fecha = new Date();
      fecha.setMonth(fecha.getMonth() + parseInt($(this).data('meses'), 10));
      $('#proximo_mantenimiento_fecha').val(fecha.toLocaleDateString('en-CA'));
    });

    $('.js-item-precio').on('change', filas);
    $tbody.on('input', 'input', calcularTotal);
    filas();
  }

  if (window.estadoInline) {
    window.estadoInline('.estado-orden-select', 'de la orden');
  }
});
