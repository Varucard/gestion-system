/**
 * Comportamiento común a todas las pantallas.
 */
(function () {
  'use strict';

  const raiz = document.documentElement;

  // ---------- Modo oscuro (Bootstrap: data-bs-theme en <html>) ----------
  const COLOR_BARRA = { light: '#f8f9fa', dark: '#111827' };

  function actualizarTema() {
    const tema = raiz.dataset.bsTheme === 'dark' ? 'dark' : 'light';
    document.querySelectorAll('.js-tema').forEach((boton) => {
      boton.querySelector('.bi')?.classList.replace(tema === 'dark' ? 'bi-moon-stars' : 'bi-sun', tema === 'dark' ? 'bi-sun' : 'bi-moon-stars');
      boton.querySelector('.js-tema-texto').textContent = tema === 'dark' ? 'Modo claro' : 'Modo oscuro';
    });
    // La barra del celular sigue al tema elegido en la app, no solo al del sistema.
    document.querySelectorAll('meta[name="theme-color"]').forEach((meta) => {
      meta.removeAttribute('media');
      meta.content = COLOR_BARRA[tema];
    });
  }

  actualizarTema();
  document.addEventListener('click', (event) => {
    if (!event.target.closest('.js-tema')) {
      return;
    }
    const tema = raiz.dataset.bsTheme === 'dark' ? 'light' : 'dark';
    raiz.dataset.bsTheme = tema;
    try { localStorage.setItem('theme', tema); } catch (e) { /* almacenamiento no disponible */ }
    actualizarTema();
  });

  // ---------- App instalable: el service worker vive junto al manifiesto ----------
  const manifiesto = document.querySelector('link[rel="manifest"]');
  if ('serviceWorker' in navigator && manifiesto) {
    navigator.serviceWorker.register(new URL('sw.js', manifiesto.href)).catch(() => { /* sin modo sin conexión */ });
  }

  // ---------- Confirmaciones y avisos (modal de Bootstrap en vez de confirm/alert) ----------
  let modal = null;

  function crearModal() {
    const el = document.createElement('div');
    el.className = 'modal fade';
    el.tabIndex = -1;
    el.setAttribute('aria-hidden', 'true');
    el.innerHTML = `
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-body d-flex gap-3 align-items-start">
            <i class="bi fs-3 js-modal-icono" aria-hidden="true"></i>
            <p class="mb-0 pt-1 js-modal-mensaje"></p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary js-modal-cancelar" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn js-modal-aceptar">Aceptar</button>
          </div>
        </div>
      </div>`;
    document.body.appendChild(el);
    return el;
  }

  /**
   * Muestra un mensaje en el modal. Con cancelar = false es un aviso de un solo botón.
   * @returns {Promise<boolean>} true si se aceptó
   */
  function mostrarModal(mensaje, { aceptar = 'Aceptar', cancelar = true, peligro = false } = {}) {
    if (!window.bootstrap) {
      return Promise.resolve(cancelar ? window.confirm(mensaje) : (window.alert(mensaje), true));
    }
    modal ??= crearModal();
    modal.querySelector('.js-modal-mensaje').textContent = mensaje;
    modal.querySelector('.js-modal-icono').className = `bi fs-3 js-modal-icono ${peligro ? 'bi-exclamation-triangle text-danger' : (cancelar ? 'bi-question-circle text-primary' : 'bi-info-circle text-primary')}`;
    modal.querySelector('.js-modal-cancelar').hidden = !cancelar;
    const boton = modal.querySelector('.js-modal-aceptar');
    boton.textContent = aceptar;
    boton.className = `btn js-modal-aceptar ${peligro ? 'btn-danger' : 'btn-primary'}`;

    return new Promise((resolve) => {
      let aceptado = false;
      const instancia = window.bootstrap.Modal.getOrCreateInstance(modal);
      boton.onclick = () => { aceptado = true; instancia.hide(); };
      modal.addEventListener('hidden.bs.modal', () => resolve(aceptado), { once: true });
      modal.addEventListener('shown.bs.modal', () => boton.focus(), { once: true });
      instancia.show();
    });
  }

  // Lo que borra, anula, cancela o da de baja se muestra en rojo.
  const PELIGRO = /eliminar|anular|cancelar|desactivar|deshacer|no acept/i;

  window.confirmar = (mensaje, opciones = {}) => mostrarModal(mensaje, { peligro: PELIGRO.test(mensaje), ...opciones });
  window.avisar = (mensaje) => mostrarModal(mensaje, { cancelar: false, aceptar: 'Entendido' });

  // Formularios con data-confirm="¿…?": se envían recién al aceptar el modal.
  document.addEventListener('submit', (event) => {
    const form = event.target;
    const mensaje = form.dataset?.confirm;
    if (!mensaje) {
      return;
    }
    if (form.dataset.confirmado === '1') {
      delete form.dataset.confirmado;
      return;
    }
    event.preventDefault();
    const boton = event.submitter;
    window.confirmar(mensaje).then((ok) => {
      if (ok) {
        form.dataset.confirmado = '1';
        boton ? form.requestSubmit(boton) : form.requestSubmit();
      }
    });
  });

  // ---------- Pestañas: la activa a la vista (en el celular la tira se desliza) ----------
  document.querySelectorAll('.nav-tabs .nav-link.active').forEach((pestana) => {
    pestana.scrollIntoView({ block: 'nearest', inline: 'center' });
  });

  // ---------- Avisos flotantes: se van solos ----------
  if (window.bootstrap) {
    document.querySelectorAll('.js-aviso').forEach((aviso) => {
      window.bootstrap.Toast.getOrCreateInstance(aviso, { delay: 6000 }).show();
    });
  }

  // ---------- Desplegables con buscador (Tom Select) ----------
  /**
   * Convierte un <select class="js-buscable"> en un desplegable con buscador.
   * El <select> original sigue siendo la fuente de verdad: sus opciones y su "change"
   * funcionan igual que antes, así que los scripts de cada pantalla no cambian.
   */
  window.initBuscable = function (select) {
    if (!window.TomSelect) {
      return;
    }
    select.tomselect?.destroy();
    new window.TomSelect(select, {
      plugins: select.multiple ? ['remove_button'] : ['clear_button'],
      placeholder: select.dataset.placeholder || '',
      maxOptions: null,
      render: {
        no_results: () => '<div class="no-results">Sin resultados</div>',
      },
    });
  };

  document.querySelectorAll('select.js-buscable').forEach((select) => window.initBuscable(select));

  if (!window.jQuery) {
    return;
  }

  const $ = window.jQuery;

  // ---------- CSRF en peticiones AJAX (solo al propio servidor) ----------
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  $.ajaxPrefilter((options, original, xhr) => {
    if (!options.crossDomain && csrf) {
      xhr.setRequestHeader('X-CSRF-Token', csrf);
    }
  });

  // ---------- DataTables ----------
  const idioma = {
    decimal: ',',
    thousands: '.',
    emptyTable: 'No hay registros cargados',
    info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
    infoEmpty: 'Sin registros',
    infoFiltered: '(filtrado de _MAX_ registros)',
    lengthMenu: 'Mostrar _MENU_ registros',
    loadingRecords: 'Cargando...',
    search: 'Buscar:',
    zeroRecords: 'No se encontraron resultados',
    paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' },
    processing: '<div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div>Cargando…',
  };

  // En pantallas chicas, las columnas que no entran se pliegan en un detalle por fila.
  const comunes = { responsive: true, pagingType: 'simple_numbers', autoWidth: false };

  /**
   * Opciones de cada tabla. data-vacio="…" en la <table> reemplaza el "No hay registros
   * cargados" por un estado vacío propio (texto plano: se escapa acá).
   */
  function opciones(tabla) {
    const vacio = tabla.dataset.vacio;
    const texto = vacio ? `<div class="vacio py-3"><i class="bi bi-inbox" aria-hidden="true"></i>${$('<div>').text(vacio).html()}</div>` : idioma.emptyTable;
    // Lo último que se pliega en pantallas chicas: la primera columna y la de acciones.
    const encabezados = [...tabla.querySelectorAll('thead th')].map((th) => th.textContent.trim().toLowerCase());
    const acciones = encabezados.indexOf('acciones');
    const prioridades = [{ targets: 0, responsivePriority: 1 }];
    if (acciones > 0) {
      prioridades.push({ targets: acciones, responsivePriority: 2 });
    }

    return { ...comunes, columnDefs: prioridades, language: { ...idioma, emptyTable: texto } };
  }

  // Tablas con todos los datos en la página.
  $('.js-datatable').each(function () {
    $(this).addClass('w-100').DataTable(opciones(this));
  });

  // Tablas paginadas en el servidor: data-server="url" y, opcional,
  // data-filtros="#form" con campos que se envían junto a cada consulta.
  $('table[data-server]').each(function () {
    const $tabla = $(this).addClass('w-100');
    const $filtros = $($tabla.data('filtros') || []);

    const tabla = $tabla.DataTable({
      ...opciones(this),
      serverSide: true,
      processing: true,
      searchDelay: 400,
      pageLength: 25,
      ajax: {
        url: $tabla.data('server'),
        data: (d) => {
          $filtros.serializeArray().forEach((campo) => { d[campo.name] = campo.value; });
        },
      },
    });

    $filtros.on('change submit', (e) => {
      e.preventDefault();
      tabla.ajax.reload();
    });
  });

  /**
   * Reemplaza las opciones de un <select> creando nodos de texto (sin inyectar HTML).
   * @param {jQuery} $select
   * @param {Array<{id: number, texto: string}>} opciones
   * @param {string} placeholder
   */
  window.cargarOpciones = function ($select, opciones, placeholder) {
    const select = $select[0];
    select.tomselect?.destroy();
    $select.empty().append(new Option('', ''));
    opciones.forEach((o) => $select.append(new Option(o.texto, o.id)));
    select.disabled = opciones.length === 0;
    select.dataset.placeholder = placeholder;
    $select.val('');
    window.initBuscable(select);
    $select.trigger('change');
  };

  /**
   * Cambio de estado inline (órdenes y turnos): POST por AJAX y recarga.
   */
  window.estadoInline = function (selector, entidad) {
    $(document).on('focus', selector, function () {
      $(this).data('anterior', $(this).val());
    });

    $(document).on('change', selector, function () {
      const $select = $(this);
      const etiqueta = $select.find('option:selected').text();

      window.confirmar(`¿Cambiar el estado ${entidad} a "${etiqueta}"?`, { peligro: false }).then((ok) => {
        if (!ok) {
          $select.val($select.data('anterior'));
          return;
        }

        $.post($select.data('url'), { estado: $select.val() })
          .done(() => window.location.reload())
          .fail((xhr) => {
            window.avisar(xhr.responseJSON?.message || 'No se pudo cambiar el estado.');
            $select.val($select.data('anterior'));
          });
      });
    });
  };
})();
