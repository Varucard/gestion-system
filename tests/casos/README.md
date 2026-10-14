# Casos compartidos PHP / JavaScript

Cada regla de datos que se aplica en el navegador y en el servidor (importes, cantidades,
teléfonos, números de serie) tiene sus casos acá, en JSON: `entrada` y lo que tiene que dar. Los leen
los dos tests:

- `tests/Unit/CasosCompartidosTest.php` (PHPUnit) contra `Validator`, `ClienteService` y `EquipoService`.
- `tests/js/reglas.test.mjs` (`node --test "tests/js/*.test.mjs"`) contra `public/assets/js/reglas.js`.

Si se cambia una regla, se agrega su caso acá y se cambian los dos lados. `null` significa "no
es válido" (en JS, `NaN` o `false`).

Diferencia a propósito: el tope de `Validator::IMPORTE_MAXIMO` solo existe en el servidor (en
el navegador, cada campo tiene su `data-max`). Por eso no hay casos por encima del tope.
