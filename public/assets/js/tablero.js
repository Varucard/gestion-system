/**
 * Tablero del local (ordenes/tablero): arrastrar una tarjeta a otra columna cambia el estado
 * de la orden (POST por AJAX a ordenes/{id}/estado, el mismo de la ficha y del listado).
 *
 * Terminar y volver a abrir se confirman (mueven stock y pueden avisar al cliente); pasar de
 * Recibido a En reparación, y al revés, se hace al soltar. Si el servidor no lo acepta, la
 * tarjeta vuelve a su lugar y se dice por qué. En el celular se arrastra manteniendo apretada
 * la tarjeta un momento (así el dedo puede desplazar la pantalla sin mover nada).
 */
$(function () {
  const tablero = document.querySelector('.tablero');
  if (!tablero || typeof Sortable === 'undefined') {
    return; // sin la librería queda el menú "Mover a…" de cada tarjeta
  }
  const urlEstado = (id) => tablero.dataset.urlEstado.replace('{id}', id);

  // En el celular las columnas se deslizan de costado: mientras se arrastra una tarjeta cerca
  // del borde, el tablero se desplaza solo hacia la columna de ese lado (la librería solo lo
  // hace bien en vertical).
  let arrastrando = false;
  let velocidad = 0;
  const BORDE = 56;
  const deslizar = () => {
    if (!arrastrando) {
      return;
    }
    if (velocidad !== 0) {
      tablero.scrollLeft += velocidad;
    }
    requestAnimationFrame(deslizar);
  };
  const alMover = (e) => {
    if (!arrastrando || tablero.scrollWidth <= tablero.clientWidth) {
      return;
    }
    const x = (e.touches ? e.touches[0] : e).clientX;
    const caja = tablero.getBoundingClientRect();
    velocidad = x > caja.right - BORDE ? 12 : (x < caja.left + BORDE ? -12 : 0);
  };
  document.addEventListener('pointermove', alMover, { passive: true });
  document.addEventListener('touchmove', alMover, { passive: true });

  function contar() {
    tablero.querySelectorAll('.tablero-columna').forEach((columna) => {
      columna.querySelector('.js-cantidad').textContent = columna.querySelectorAll('.tarjeta-orden').length;
    });
  }

  function confirmacion(desde, hacia, tarjeta) {
    if (hacia === 'finalizado') {
      if (tarjeta.dataset.sinItems) {
        return Promise.reject(new Error('Para terminar el trabajo, primero cargá en la orden los servicios o repuestos que se hicieron.'));
      }
      return window.confirmar('¿Terminar el trabajo? Se descuentan del stock los repuestos usados y, si está activado, se le avisa al cliente que está listo.',
        { aceptar: 'Sí, terminar el trabajo' });
    }
    if (desde === 'finalizado') {
      return window.confirmar('¿Volver a abrir el trabajo? Los repuestos vuelven al stock hasta que se termine de nuevo.',
        { aceptar: 'Sí, volver a abrir el trabajo' });
    }
    return Promise.resolve(true);
  }

  tablero.querySelectorAll('.js-tablero-lista').forEach((lista) => {
    Sortable.create(lista, {
      group: 'ordenes',
      sort: false, // el orden dentro de una columna no se guarda (van de la más vieja a la más nueva)
      draggable: '.tarjeta-orden',
      animation: 150,
      // Arrastre propio de la librería (con eventos de puntero) en vez del nativo del navegador:
      // se comporta igual con el mouse y con el dedo, y la tarjeta sigue al cursor sin "fantasma".
      forceFallback: true,
      fallbackTolerance: 4,
      // Llevando la tarjeta al borde, la pantalla se desplaza (en el celular, de costado hacia la
      // otra columna; en la PC, hacia abajo).
      scroll: true,
      bubbleScroll: true,
      scrollSensitivity: 70,
      scrollSpeed: 18,
      delay: 250,
      delayOnTouchOnly: true,
      // Los botones y links de la tarjeta se tocan sin arrastrar.
      filter: 'a, button, .dropdown-menu',
      preventOnFilter: false,
      ghostClass: 'tarjeta-fantasma',
      chosenClass: 'tarjeta-elegida',
      onStart() {
        arrastrando = true;
        tablero.classList.add('arrastrando');
        velocidad = 0;
        requestAnimationFrame(deslizar);
      },
      onEnd(evento) {
        arrastrando = false;
        tablero.classList.remove('arrastrando');
        velocidad = 0;
        const tarjeta = evento.item;
        const desde = evento.from.dataset.estado;
        const hacia = evento.to.dataset.estado;
        if (desde === hacia) {
          return;
        }
        const devolver = () => {
          evento.from.insertBefore(tarjeta, evento.from.children[evento.oldIndex] || null);
          contar();
        };
        contar();

        confirmacion(desde, hacia, tarjeta)
          .then((ok) => {
            if (!ok) {
              devolver();
              return;
            }
            // Al entrar o salir de "Listo" cambian los botones de la tarjeta (Entregar, el aviso al
            // cliente): se recarga para mostrarlos, y el mensaje queda para después de recargar.
            const recargar = hacia === 'finalizado' || desde === 'finalizado';
            $.post(urlEstado(tarjeta.dataset.id), { estado: hacia, recargar: recargar ? 1 : 0 })
              .done((respuesta) => {
                tarjeta.dataset.estado = hacia; // el menú "Mover a…" se acomoda solo (paginas.css)
                if (recargar) {
                  window.location.reload();
                  return;
                }
                window.avisoExito(respuesta.message || `Orden #${tarjeta.dataset.id}: ${evento.to.dataset.etiqueta}.`);
              })
              .fail((xhr) => {
                devolver();
                window.avisar(xhr.responseJSON?.message || 'No se pudo cambiar el estado.');
              });
          })
          .catch((error) => {
            devolver();
            window.avisar(error.message);
          });
      },
    });
  });
});
