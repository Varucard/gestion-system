/**
 * Firma en la pantalla (componentes/firma): se dibuja con el dedo, un lápiz o el mouse y el
 * dibujo queda como PNG en el campo oculto "firma". Sin librerías: Pointer Events + canvas.
 */
(function () {
  'use strict';

  document.querySelectorAll('[data-firma]').forEach((caja) => {
    const lienzo = caja.querySelector('canvas');
    const campo = caja.querySelector('input[name="firma"]');
    const error = caja.querySelector('.js-firma-error');
    const ctx = lienzo.getContext('2d');
    let dibujando = false;
    let ultimo = null;
    let hayTrazos = false;

    // El canvas se dibuja a la resolución real de la pantalla (si no, la firma sale borrosa).
    // Tinta oscura siempre (--firma-tinta en tokens.css): el recuadro es blanco también en modo oscuro.
    const tinta = getComputedStyle(document.documentElement).getPropertyValue('--firma-tinta').trim() || '#1a1a2e';

    let anchoDibujado = 0; // ancho en pantalla con el que se armó el lienzo

    function ajustar() {
      const caja = lienzo.getBoundingClientRect();
      anchoDibujado = caja.width;
      // Hasta 3×: con más (zoom del navegador en una pantalla densa) la firma pasaba el tamaño
      // que acepta el servidor (FirmaService::MAX_LADO).
      const escala = Math.min(window.devicePixelRatio || 1, 3);
      lienzo.width = Math.max(1, Math.round(caja.width * escala));
      lienzo.height = Math.max(1, Math.round(caja.height * escala));
      ctx.setTransform(escala, 0, 0, escala, 0, 0);
      ctx.lineWidth = 2.5;
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.strokeStyle = tinta;
      ctx.fillStyle = tinta;
    }

    // Si el recuadro cambia de tamaño con una firma hecha (girar el celular), se redibuja
    // escalada: si no, el trazo nuevo salía corrido respecto del dedo.
    // Se mantiene la proporción: no se agranda, y se achica solo si el recuadro quedó más chico.
    function reajustarConFirma() {
      const anterior = document.createElement('canvas');
      anterior.width = lienzo.width;
      anterior.height = lienzo.height;
      anterior.getContext('2d').drawImage(lienzo, 0, 0);
      const escalaAnterior = lienzo.width / (anchoDibujado || lienzo.width);
      const [ancho, alto] = [anterior.width / escalaAnterior, anterior.height / escalaAnterior];
      ajustar();
      const caja = lienzo.getBoundingClientRect();
      const k = Math.min(1, caja.width / ancho, caja.height / alto);
      ctx.drawImage(anterior, 0, 0, ancho * k, alto * k);
      campo.value = lienzo.toDataURL('image/png');
    }

    const punto = (e) => {
      const r = lienzo.getBoundingClientRect();
      return { x: e.clientX - r.left, y: e.clientY - r.top };
    };

    function borrar() {
      ajustar();
      hayTrazos = false;
      campo.value = '';
      caja.classList.remove('firmado', 'is-invalid');
      error?.classList.remove('d-block');
    }

    lienzo.addEventListener('pointerdown', (e) => {
      e.preventDefault();
      lienzo.setPointerCapture(e.pointerId);
      dibujando = true;
      ultimo = punto(e);
      ctx.beginPath();
      ctx.arc(ultimo.x, ultimo.y, ctx.lineWidth / 2, 0, Math.PI * 2);
      ctx.fill();
    });

    lienzo.addEventListener('pointermove', (e) => {
      if (!dibujando) {
        return;
      }
      // Los eventos agrupados dan un trazo más suave en pantallas táctiles.
      const eventos = e.getCoalescedEvents ? e.getCoalescedEvents() : [e];
      eventos.forEach((ev) => {
        const p = punto(ev);
        ctx.beginPath();
        ctx.moveTo(ultimo.x, ultimo.y);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        ultimo = p;
      });
    });

    const terminar = () => {
      if (!dibujando) {
        return;
      }
      dibujando = false;
      hayTrazos = true;
      campo.value = lienzo.toDataURL('image/png');
      caja.classList.add('firmado');
      caja.classList.remove('is-invalid');
      error?.classList.remove('d-block');
    };
    lienzo.addEventListener('pointerup', terminar);
    lienzo.addEventListener('pointercancel', terminar);

    caja.querySelector('.js-firma-borrar').addEventListener('click', borrar);

    // Cuando el recuadro aparece (estaba plegado u oculto) o cambia de ancho (girar el celular)
    // y todavía no se firmó, se reajusta; con una firma hecha se deja como está para no borrarla.
    let ancho = 0;
    const alCambiarDeTamano = () => {
      const nuevo = Math.round(lienzo.getBoundingClientRect().width);
      if (nuevo === ancho || nuevo === 0) {
        return;
      }
      ancho = nuevo;
      hayTrazos ? reajustarConFirma() : ajustar();
    };
    if (typeof ResizeObserver === 'function') {
      new ResizeObserver(alCambiarDeTamano).observe(lienzo);
    } else {
      window.addEventListener('resize', alCambiarDeTamano);
      caja.closest('details')?.addEventListener('toggle', alCambiarDeTamano);
    }

    if (caja.hasAttribute('data-requerida')) {
      caja.closest('form')?.addEventListener('submit', (e) => {
        if (campo.value === '') {
          e.preventDefault();
          e.stopImmediatePropagation();
          caja.classList.add('is-invalid');
          error?.classList.add('d-block');
          caja.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }, true);
    }

    ajustar();
  });
})();
