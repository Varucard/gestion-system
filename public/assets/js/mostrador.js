/**
 * Entrada con PIN en el mostrador (auth/mostrador): al tocar un nombre aparece el PIN; los
 * números grandes escriben en el campo y con el cuarto número se entra solo.
 */
(function () {
  'use strict';

  const form = document.getElementById('entrada_pin');
  if (!form) {
    return;
  }
  const paso = form.querySelector('.js-paso-pin');
  const pin = form.querySelector('#pin');
  // Se envía una sola vez: un toque de más (o dos rápidos) no manda el PIN otra vez, que contaría
  // como otro intento equivocado.
  let enviando = false;
  form.addEventListener('submit', (e) => {
    if (enviando) {
      e.preventDefault();
      return;
    }
    enviando = true;
    paso.classList.add('enviando');
  });
  // Al volver con el botón "Atrás", la página puede venir del caché con "enviando" puesto.
  window.addEventListener('pageshow', () => { enviando = false; paso.classList.remove('enviando'); });

  const enviarSiEstaCompleto = () => {
    if (!enviando && /^\d{4}$/.test(pin.value)) {
      form.requestSubmit();
    }
  };

  form.querySelectorAll('input[name="usuario_id"]').forEach((opcion) => {
    opcion.addEventListener('change', () => {
      paso.hidden = false;
      pin.value = '';
      pin.focus();
    });
  });

  pin.addEventListener('input', () => {
    pin.value = pin.value.replace(/\D/g, '').slice(0, 4);
    enviarSiEstaCompleto();
  });

  form.querySelectorAll('.js-tecla').forEach((tecla) => {
    tecla.addEventListener('click', () => {
      if (enviando) {
        return;
      }
      pin.value = tecla.dataset.tecla === 'borrar' ? pin.value.slice(0, -1) : (pin.value + tecla.dataset.tecla).slice(0, 4);
      enviarSiEstaCompleto();
    });
  });

  if (!paso.hidden) {
    pin.focus();
  }
})();
