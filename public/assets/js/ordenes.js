/**
 * Órdenes y combos: detalle con cantidades (y precios en las órdenes), total,
 * combos, próximo mantenimiento sugerido y cambio de estado en el listado.
 *
 * En las órdenes, cada repuesto elige si es del negocio, a costo (precio de costo, sin
 * editar) o lo trae el cliente (sin precio); y se pueden sumar a mano piezas que trae el
 * cliente y no están en el catálogo.
 */
$(function () {
  const $form = $('#form_orden');

  if ($form.length) {
    const conPrecio = $form.data('sin-precio') !== 1;
    const moneda = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const $tbody = $('#detalle_orden tbody');
    // Valores ya cargados (edición o vuelta con errores):
    // { servicio: {id: {cantidad, precio}}, repuesto: {id: {cantidad, precio, modo}}, pieza: {0: {descripcion, cantidad}} }
    const cargados = $form.data('detalle') || { servicio: {}, repuesto: {} };
    // Solo en las órdenes: { negocio: 'Del negocio', costo: 'A costo…', cliente: 'Lo trae el cliente' }
    const modos = $form.data('modos') || null;

    // Montos y cantidades se escriben a la argentina ("1.200,50"): ver window.leerImporte (app.js).
    const numero = (valor) => {
      const n = typeof valor === 'number' ? valor : window.leerImporte(valor);
      return Number.isNaN(n) ? 0 : n;
    };

    // Lo que viene del servidor es un número (38000) y se muestra prolijo ("38.000,00");
    // lo que ya escribió el usuario se deja como está.
    function input(nombre, valor, extra, cantidad) {
      const n = typeof valor === 'number' ? valor : window.leerImporte(valor);
      const texto = Number.isNaN(n) ? (valor ?? '') : window.formatoImporte(n, cantidad);
      return $('<input>', {
        type: 'text', inputmode: 'decimal', autocomplete: 'off', name: nombre, value: texto, required: true,
        'data-min': cantidad ? '0.01' : '0', 'data-formato': cantidad ? 'cantidad' : null, ...extra,
      });
    }

    function botonQuitar(nombre, clase) {
      return $('<td>', { class: 'text-end' }).append($('<button>', {
        type: 'button', class: `btn btn-sm btn-accion btn-outline-danger ${clase}`, title: `Quitar ${nombre}`, 'aria-label': `Quitar ${nombre}`,
      }).html('<i class="bi bi-x-lg" aria-hidden="true"></i>'));
    }

    // Avisa al borrador del formulario (app.js) que algo cambió fuera de un campo.
    const avisarCambio = () => $form[0].dispatchEvent(new Event('change', { bubbles: true }));

    // Pieza que trae el cliente y no está en el catálogo: se describe a mano y va sin precio.
    function filaPieza(descripcion = '', cantidad = 1) {
      const $tr = $('<tr>', { class: 'js-pieza' });
      $tr.append($('<td>')
        .append($('<input>', {
          type: 'text', name: 'pieza_descripcion[]', value: descripcion, required: true, maxlength: 150, autocomplete: 'off',
          class: 'form-control form-control-sm', placeholder: 'Qué trae (ej: memoria RAM 8 GB)', 'aria-label': 'Repuesto que trae el cliente',
        }))
        .append($('<small>', { class: 'text-muted' }).text('Lo trae el cliente')));
      $tr.append($('<td>').append(input('pieza_cantidad[]', cantidad,
        { class: 'form-control form-control-sm js-importe js-cantidad', 'aria-label': 'Cantidad del repuesto que trae el cliente' }, true)));
      $tr.append($('<td>', { class: 'text-muted' }).text('Sin precio'));
      $tr.append($('<td>', { class: 'text-end text-muted' }).text('—'));
      $tr.append(botonQuitar('repuesto que trae el cliente', 'js-quitar-pieza'));
      $tbody.append($tr);
      return $tr;
    }

    function hayItems() {
      $tbody.find('.js-sin-items').toggle($tbody.find('tr[data-tipo], tr.js-pieza').length === 0);
    }

    $('#agregar_pieza').on('click', function () {
      filaPieza().find('input').first().trigger('focus');
      hayItems();
    });

    $tbody.on('click', '.js-quitar-pieza', function () {
      $(this).closest('tr').remove();
      hayItems();
      avisarCambio();
    });

    // Del negocio: precio del catálogo, editable. A costo: el precio de costo, sin editar.
    // Lo trae el cliente: sin precio (el campo no se envía). Al cargar la tabla (inicial) se
    // respeta el precio que ya tenía el ítem.
    function aplicarModo($tr, inicial) {
      const modo = $tr.find('.js-modo').val() || 'negocio';
      const $precio = $tr.find('.js-precio');
      if (!inicial && modo !== 'cliente') {
        $precio.val(window.formatoImporte(numero($tr.data(modo === 'costo' ? 'costo' : 'precio-catalogo'))));
      }
      $precio.prop('readonly', modo === 'costo').prop('disabled', modo === 'cliente').toggleClass('d-none', modo === 'cliente');
      $tr.find('.js-sin-precio').toggleClass('d-none', modo !== 'cliente');
    }

    $tbody.on('change', '.js-modo', function () {
      aplicarModo($(this).closest('tr'), false);
      calcularTotal();
    });

    // Quitar un ítem desde su fila: lo saca también del buscador de arriba.
    $tbody.on('click', '.js-quitar-item', function () {
      const $tr = $(this).closest('tr');
      const select = document.querySelector(`.js-item-precio[data-tipo="${$tr.data('tipo')}"]`);
      if (select.tomselect) {
        select.tomselect.removeItem(String($tr.data('id')));
      } else {
        $(select).find(`option[value="${$tr.data('id')}"]`).prop('selected', false);
        $(select).trigger('change');
      }
    });

    function filas() {
      // Conserva lo que el usuario ya escribió antes de reconstruir la tabla.
      $tbody.find('tr[data-tipo]').each(function () {
        const $tr = $(this);
        cargados[$tr.data('tipo')][$tr.data('id')] = {
          cantidad: $tr.find('.js-cantidad').val(),
          precio: $tr.find('.js-precio').val(),
          modo: $tr.find('.js-modo').val(),
        };
      });

      $tbody.find('tr[data-tipo]').remove();
      // Servicios y repuestos van antes que las piezas que trae el cliente.
      const $piezas = $tbody.find('tr.js-pieza').first();

      $('.js-item-precio').each(function () {
        const tipo = $(this).data('tipo');
        $(this).find('option:selected').each(function () {
          const id = $(this).val();
          const previo = cargados[tipo][id] || {};
          const $tr = $('<tr>', { 'data-tipo': tipo, 'data-id': id, 'data-precio-catalogo': $(this).data('precio') });

          const nombre = $(this).text().replace(/\s*\(\$.*$/s, '').trim();
          const $nombre = $('<td>').text((tipo === 'repuesto' ? 'Repuesto: ' : '') + nombre);
          $tr.append($nombre);
          $tr.append($('<td>').append(input(`cantidad_${tipo}[${id}]`, previo.cantidad ?? 1,
            { class: 'form-control form-control-sm js-importe js-cantidad', 'aria-label': `Cantidad de ${nombre}` }, true)));
          if (conPrecio) {
            $tr.append($('<td>')
              .append(input(`precio_${tipo}[${id}]`, previo.precio ?? $(this).data('precio'),
                { class: 'form-control form-control-sm js-importe js-precio', 'aria-label': `Precio unitario de ${nombre}` }, false))
              .append($('<span>', { class: 'text-muted js-sin-precio d-none' }).text('Sin precio')));
          }
          if (tipo === 'repuesto' && modos) {
            const costo = $(this).data('costo');
            const modo = previo.modo || 'negocio';
            $tr.attr('data-costo', costo ?? '');
            const $modo = $('<select>', { name: `modo_repuesto[${id}]`, class: 'form-select form-select-sm mt-1 js-modo', 'aria-label': `Cómo va ${nombre}` });
            Object.entries(modos).forEach(([valor, texto]) => {
              // Sin costo cargado no se puede pasar a costo (salvo que ya estuviera así).
              const sinCosto = valor === 'costo' && costo === undefined && modo !== 'costo';
              $modo.append($('<option>', { value: valor, text: sinCosto ? `${texto}: sin costo cargado` : texto, disabled: sinCosto }));
            });
            $nombre.append($modo.val(modo));
          }
          $tr.append($('<td>', { class: 'text-end importe js-subtotal' }));
          $tr.append(botonQuitar(nombre, 'js-quitar-item'));
          $piezas.length ? $tr.insertBefore($piezas) : $tbody.append($tr);
          if (modos && tipo === 'repuesto') {
            aplicarModo($tr, true);
          }
        });
      });

      hayItems();
      calcularTotal();
    }

    function calcularTotal() {
      let total = 0;
      $tbody.find('tr[data-tipo]').each(function () {
        // Lo que trae el cliente no se cobra.
        if ($(this).find('.js-modo').val() === 'cliente') {
          $(this).find('.js-subtotal').text('—');
          return;
        }
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

    // Al recuperar un borrador, las piezas que trae el cliente se rearman con lo que se había escrito.
    $form.on('borrador:restaurado', function (event) {
      const datos = event.originalEvent?.detail || [];
      const valores = (nombre) => datos.filter(([n]) => n === nombre).map(([, v]) => v);
      const cantidades = valores('pieza_cantidad[]');
      $tbody.find('tr.js-pieza').remove();
      valores('pieza_descripcion[]').forEach((descripcion, i) => filaPieza(descripcion, cantidades[i] ?? 1));
      hayItems();
    });

    $('.js-item-precio').on('change', filas);
    $tbody.on('input', 'input', calcularTotal);
    Object.values(cargados.pieza || {}).forEach((p) => filaPieza(p.descripcion, p.cantidad));
    filas();
  }

  if (window.estadoInline) {
    window.estadoInline('.estado-orden-select', 'de la orden');
  }
});
