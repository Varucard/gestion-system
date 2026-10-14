/**
 * Reglas de datos que el navegador aplica igual que el servidor: importes y cantidades
 * escritos a la argentina, teléfonos y números de serie. Cada una tiene su par en PHP:
 *
 *   leerImporte          Validator::importe()
 *   cantidadAmbigua      Validator::cantidad() (rechaza "1.250")
 *   normalizarTelefono   ClienteService::normalizarTelefono()
 *   telefonoValido       ClienteService::telefonoValido()
 *   normalizarSerie      EquipoService::normalizarSerie()
 *   serieValida          EquipoService::serieValida()
 *
 * Los casos de tests/casos/*.json se prueban contra las dos (PHPUnit y node --test): si se
 * cambia una regla de un lado y no del otro, falla.
 *
 * Funciona en el navegador (window.Reglas) y en Node (require), para los tests.
 */
(function (raiz, crear) {
  'use strict';
  if (typeof module === 'object' && module.exports) {
    module.exports = crear();
  } else {
    raiz.Reglas = crear();
  }
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  /**
   * "10.000" = diez mil, "1.234,50", y también "46000.00" (punto decimal, cuando no puede ser
   * de miles). @returns {number} NaN si no es un número válido
   */
  function leerImporte(texto) {
    let t = String(texto ?? '').replace(/[$\s ]/g, '');
    if (t === '') {
      return NaN;
    }
    if (t.includes(',')) {
      if (!/^[1-9]\d{0,2}(\.\d{3})*,\d+$|^\d+,\d+$/.test(t)) {
        return NaN;
      }
      t = t.replace(/\./g, '').replace(',', '.');
    } else if (/^[1-9]\d{0,2}(\.\d{3})+$/.test(t)) {
      t = t.replace(/\./g, '');
    }
    return /^\d+(\.\d+)?$/.test(t) ? parseFloat(t) : NaN;
  }

  /** En una cantidad, "1.250" no se sabe si es 1250 o 1,25: se pide aclarar. */
  function cantidadAmbigua(texto) {
    return /^\s*\d{1,3}\.\d{3}\s*$/.test(String(texto ?? ''));
  }

  /** Solo los 10 números (área + número): sin +54, 9, 0 ni 15. */
  function normalizarTelefono(texto) {
    let d = String(texto ?? '').replace(/\D/g, '');
    if (d.length > 10 && d.startsWith('54')) {
      d = d.slice(2);
      if (d.length > 10 && d.startsWith('9')) {
        d = d.slice(1);
      }
    }
    if (d.length > 10 && d.startsWith('0')) {
      d = d.slice(1);
    }
    // El 15 va después del código de área, que tiene de 2 a 4 dígitos.
    if (d.length === 12) {
      for (const area of [2, 3, 4]) {
        if (d.slice(area, area + 2) === '15') {
          return d.slice(0, area) + d.slice(area + 2);
        }
      }
    }
    return d;
  }

  /** 10 números con un código de área que exista (11, o que empiece con 2 o 3). */
  function telefonoValido(normalizado) {
    return /^(11\d{8}|[23]\d{9})$/.test(normalizado);
  }

  /** Número de serie como se guarda: en mayúsculas y sin espacios. */
  function normalizarSerie(texto) {
    return String(texto ?? '').replace(/\s/g, '').toUpperCase();
  }

  /** De 3 a 50 letras, números, guiones, puntos o barras, ya normalizado. */
  function serieValida(serie) {
    return /^[A-Z0-9\/._-]{3,50}$/.test(serie);
  }

  return { leerImporte, cantidadAmbigua, normalizarTelefono, telefonoValido, normalizarSerie, serieValida };
});
