# Medir la usabilidad

Dos herramientas que se usan juntas, antes y después de cada tanda de cambios:

1. **Pruebas con personas del negocio** haciendo tareas reales mientras alguien mira. Con 3 a 5
   personas aparecen casi todos los problemas (Nielsen): no hace falta más.
2. **La encuesta SUS** dentro del sistema (*Órdenes › Usabilidad*): 10 preguntas que dan un
   puntaje de 0 a 100. El promedio de la industria es 68; por encima de 80 es excelente.

La prueba dice **qué** cuesta; la encuesta, **cuánto** mejoró.

## Antes de empezar

- Usar una **copia de prueba** con datos inventados (ver `docs/manual/README.md`, pasos 1 y 2), no
  la base del negocio: en las tareas se reciben equipos y se cobra.
- Una persona hace las tareas y otra mira y anota. Quien mira **no ayuda ni explica**: si la
  persona se traba, anota dónde y, después de un rato, le dice cómo seguir.
- Pedirle que **piense en voz alta**: qué busca, qué espera que pase, qué la sorprende.
- Aclarar que se prueba el sistema, no a la persona: si algo no sale, el problema es del sistema.

## Las tareas

Darlas de a una, en papel, con los datos que hagan falta. Cronometrar cada una.

| # | Tarea | Se cumple cuando… |
| --- | --- | --- |
| 1 | Llega Marta Gómez (DNI 30.555.444, tel. 11 4567-8901) con una notebook Lenovo IdeaPad 3, n° de serie PF2XYZ99, porque no enciende. Recibí el equipo, con el cargador y una foto de la tapa. | Hay una orden abierta con el motivo y la foto. |
| 2 | Al equipo de Marta hay que cambiarle las pastillas de freno. Cargalo en la orden y mandale el presupuesto por WhatsApp. | La orden tiene el servicio y se abrió WhatsApp con el link. |
| 3 | En el tablero, pasá el equipo de Marta a «En reparación». | La orden está en reparación. |
| 4 | Terminaste el trabajo. Avisale al cliente que está listo. | La orden está lista y se avisó. |
| 5 | Marta paga $ 20.000 en efectivo y se lleva el equipo. Registrá el pago y la entrega, con su firma. | Hay un pago, la orden figura entregada y con firma. |
| 6 | Dale un turno a Marta para el jueves que viene a las 10 para una limpieza. | Hay un turno ese día. |
| 7 | ¿Quién debe plata y cuánto? | Dice el total adeudado y al menos un cliente. |

## Qué anotar

Por cada tarea: si la terminó (sola, con ayuda o no), cuánto tardó, dónde se trabó y qué dijo.
Al final, que responda la encuesta SUS y una pregunta abierta: *¿qué fue lo que más te costó?*

## Después

- Juntar los problemas de todas las personas y ordenarlos por cuántas se trabaron en lo mismo y
  por cuánto les costó. Lo que les pasó a dos o más va primero.
- Cada cambio de la hoja de ruta que salga de acá dice qué problema resuelve.
- Después de hacer los cambios, otra ronda: las mismas tareas (con otros datos) y otra ronda de
  la encuesta, para comparar el puntaje.
