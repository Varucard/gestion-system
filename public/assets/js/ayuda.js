/*
 * Ayuda (views/ayuda): buscador dentro del manual, el capítulo que se está leyendo marcado
 * en el índice, el índice plegado en el celular y Esc para volver a la pantalla de la que se vino.
 */
(() => {
  const buscador = document.getElementById('ayuda_buscar');
  const resultado = document.getElementById('ayuda_resultado');
  const sinResultados = document.getElementById('ayuda_sin_resultados');
  const capitulos = [...document.querySelectorAll('.manual-capitulo')];
  const enlaces = new Map([...document.querySelectorAll('.ayuda-indice li[data-capitulo]')].map((li) => [li.dataset.capitulo, li]));
  const indice = document.querySelector('.ayuda-contenido');
  const angosta = window.matchMedia('(max-width: 991.98px)');

  if (!buscador || capitulos.length === 0) {
    return;
  }

  // Sin tildes ni mayúsculas: "camara" encuentra "Cámara".
  const normalizar = (texto) => texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  const textos = new Map(capitulos.map((capitulo) => [capitulo, normalizar(capitulo.textContent)]));

  // ---------- Buscador: quedan a la vista los capítulos que tienen todas las palabras ----------
  let demora;
  buscador.addEventListener('input', () => {
    clearTimeout(demora);
    demora = setTimeout(() => {
      const palabras = normalizar(buscador.value).split(/\s+/).filter((p) => p.length > 1);
      let visibles = 0;

      capitulos.forEach((capitulo) => {
        const coincide = palabras.every((palabra) => textos.get(capitulo).includes(palabra));
        capitulo.hidden = !coincide;
        enlaces.get(capitulo.id)?.toggleAttribute('hidden', !coincide);
        visibles += coincide ? 1 : 0;
      });

      sinResultados.hidden = visibles > 0;
      resultado.textContent = palabras.length === 0 ? '' : `${visibles} capítulo${visibles === 1 ? '' : 's'} con “${buscador.value.trim()}”`;
      if (palabras.length > 0 && indice) {
        indice.open = true;
      }
    }, 200);
  });

  // ---------- El capítulo que se está leyendo queda marcado en el índice ----------
  if ('IntersectionObserver' in window) {
    const observador = new IntersectionObserver((entradas) => {
      entradas.filter((e) => e.isIntersecting).forEach((entrada) => {
        enlaces.forEach((li, id) => li.querySelector('a').classList.toggle('activo', id === entrada.target.id));
      });
    }, { rootMargin: '0px 0px -75% 0px' });
    capitulos.forEach((capitulo) => observador.observe(capitulo));
  }

  // ---------- Esc: volver a la pantalla de la que se vino (si no se está escribiendo) ----------
  const volver = document.querySelector('.js-ayuda-volver');
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && volver && document.activeElement !== buscador && !document.querySelector('.modal.show')) {
      window.location.href = volver.href;
    }
  });

  // ---------- Celular: índice plegado, y se pliega al elegir un capítulo ----------
  if (indice && angosta.matches) {
    indice.open = false;
    indice.addEventListener('click', (event) => {
      if (event.target.closest('a')) {
        indice.open = false;
      }
    });
  }
})();
