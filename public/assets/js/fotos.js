/**
 * Fotos del equipo (componentes/fotos). En el celular cada foto sacada con la cámara llega
 * sola: se van sumando a la lista en vez de reemplazar la anterior. Antes de subirlas, el
 * navegador las achica (lado mayor de 1600 px, JPEG): una foto de celular pasa de 4 MB a
 * unos 300 KB. El servidor igual las vuelve a procesar (ImageUpload).
 *
 * Mientras se preparan, el formulario no se envía: si se toca "Recibir el equipo", espera y se
 * envía solo al terminar (si no, se iban sin ninguna foto).
 */
(function () {
  'use strict';

  const LADO_MAXIMO = 1600;
  const CALIDAD = 0.85;
  // Un poco menos que el post_max_size del servidor (48 MB), para dejar lugar al resto del formulario.
  const PESO_MAXIMO = 40 * 1024 * 1024;
  // Sin DataTransfer (navegadores viejos) no se puede armar la lista: queda el campo común.
  const puedeArmarLista = (() => { try { return Boolean(new DataTransfer()); } catch (e) { return false; } })();

  async function achicar(archivo) {
    if (!archivo.type.startsWith('image/') || typeof createImageBitmap !== 'function') {
      return archivo;
    }
    try {
      const imagen = await createImageBitmap(archivo, { imageOrientation: 'from-image' });
      const escala = Math.min(1, LADO_MAXIMO / Math.max(imagen.width, imagen.height));
      if (escala === 1 && archivo.size < 1024 * 1024) {
        return archivo;
      }
      const lienzo = document.createElement('canvas');
      lienzo.width = Math.round(imagen.width * escala);
      lienzo.height = Math.round(imagen.height * escala);
      lienzo.getContext('2d').drawImage(imagen, 0, 0, lienzo.width, lienzo.height);
      const blob = await new Promise((listo) => lienzo.toBlob(listo, 'image/jpeg', CALIDAD));
      return blob ? new File([blob], archivo.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : archivo;
    } catch (e) {
      return archivo; // un formato que el navegador no abre (p. ej. HEIC): se sube tal cual
    }
  }

  document.querySelectorAll('[data-fotos]').forEach((caja) => {
    const campo = caja.querySelector('.js-fotos-campo');
    const vista = caja.querySelector('.fotos-vista');
    const maximo = Number(caja.dataset.maximo || 10);
    const form = caja.closest('form');
    let elegidas = [];
    let preparando = null; // promesa mientras se achican las fotos nuevas (las tandas van en fila)
    let tandasPendientes = 0;
    let sobrantes = 0;

    const nota = (texto, clase = 'text-muted') => {
      const p = document.createElement('p');
      p.className = `small mb-0 w-100 ${clase}`;
      p.textContent = texto;
      return p;
    };

    // El botón de guardar del formulario (en la orden) aparece recién cuando hay fotos elegidas.
    const enviarFotos = form?.querySelector('.js-fotos-enviar');

    function sincronizar() {
      if (enviarFotos) {
        enviarFotos.hidden = (puedeArmarLista ? elegidas.length : campo.files.length) === 0;
      }
      if (puedeArmarLista) {
        const lista = new DataTransfer();
        elegidas.forEach((f) => lista.items.add(f));
        campo.files = lista.files;
      }
      vista.replaceChildren(...elegidas.map((archivo, i) => {
        const figura = document.createElement('figure');
        figura.className = 'foto-miniatura';
        const img = document.createElement('img');
        img.alt = `Foto ${i + 1}`;
        img.src = URL.createObjectURL(archivo);
        img.addEventListener('load', () => URL.revokeObjectURL(img.src), { once: true });
        figura.append(img);
        if (puedeArmarLista) {
          const quitar = document.createElement('button');
          quitar.type = 'button';
          quitar.className = 'btn btn-light foto-quitar';
          quitar.setAttribute('aria-label', `Quitar la foto ${i + 1}`);
          quitar.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
          quitar.addEventListener('click', () => { elegidas.splice(i, 1); sobrantes = 0; sincronizar(); });
          figura.append(quitar);
        }
        return figura;
      }));
      if (sobrantes > 0) {
        vista.append(nota(`Se puede subir hasta ${maximo} fotos: ${sobrantes === 1 ? 'la última no se sumó' : `las últimas ${sobrantes} no se sumaron`}.`, 'text-danger'));
      } else if (elegidas.length >= maximo) {
        vista.append(nota(`Llegaste al máximo de ${maximo} fotos.`));
      }
    }

    campo.addEventListener('change', () => {
      const nuevas = [...campo.files];
      if (!puedeArmarLista) {
        elegidas = nuevas;
        sincronizar();
        return;
      }
      vista.append(nota('Preparando las fotos…'));
      // Si se eligen fotos de nuevo antes de que terminen las anteriores, la tanda nueva espera a
      // la otra: si no, la primera en terminar pisaba la lista y el envío salía sin la segunda.
      tandasPendientes++;
      preparando = (preparando ?? Promise.resolve()).then(() => Promise.all(nuevas.map(achicar))).then((achicadas) => {
        const todas = [...elegidas, ...achicadas];
        sobrantes = Math.max(0, todas.length - maximo);
        elegidas = todas.slice(0, maximo);
        tandasPendientes--;
        if (tandasPendientes === 0) {
          preparando = null;
          sincronizar();
        }
      });
    });

    // Al enviar: si las fotos se están preparando, se espera y se envía solo; si pesan
    // demasiado juntas (fotos que no se pudieron achicar), se avisa antes de perder todo.
    form?.addEventListener('submit', (e) => {
      if (preparando) {
        e.preventDefault();
        e.stopImmediatePropagation();
        const enviar = e.submitter && e.submitter.form === form ? e.submitter : null;
        preparando.then(() => {
          // requestSubmit no existe en Safari de iOS 15 o anterior: ahí se hace clic en el botón.
          if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(enviar ?? undefined);
          } else if (enviar) {
            enviar.click();
          } else {
            form.submit();
          }
        });
        return;
      }
      const peso = [...campo.files].reduce((total, f) => total + f.size, 0);
      if (peso > PESO_MAXIMO) {
        e.preventDefault();
        e.stopImmediatePropagation();
        window.avisar(`Las fotos pesan demasiado juntas (${Math.round(peso / 1024 / 1024)} MB). Sacá algunas e intentá de nuevo.`);
      }
    }, true);
  });
})();
