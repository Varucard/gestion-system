<?php

/**
 * Valores por defecto de la configuración del sistema.
 *
 * Lo que se edita desde "Configuración > Sistema" se guarda en
 * storage/config/negocio.json y tiene prioridad sobre estos valores.
 *
 * Variables disponibles en las plantillas de mensajes:
 *   Generales: {cliente} {equipo} {negocio} {direccion} {telefono} {link_seguimiento}
 *   Turnos:    {fecha} {hora} {link_turno}
 *   Órdenes:   {numero} {total} {saldo} {link_presupuesto} {fecha_proximo}
 */
return [
  'negocio' => [
    'nombre' => 'Servicio Técnico PC',
    'cuit' => '20-12345678-9',
    'direccion' => 'Completar en Configuración > Negocio',
    'telefono' => '11-0000-0000',
    'whatsapp' => '+5491100000000',
    'email' => 'contacto@example.com',
  ],

  'trabajo' => [
    'validez' => 10,
    'garantia' => 10,
    'tiempo_estimado' => 3,
    'forma_pago' => [
      'Contado',
      'Tarjeta de Crédito',
      'Tarjeta de Débito',
      'Transferencia Bancaria',
      'Mercado Pago',
    ],
    'observaciones' => [
      'Tiempo estimado de reparación: 2-4 días hábiles desde la aceptación y recepción de repuestos.',
      'Los precios están sujetos a modificación si surgen imprevistos o variaciones en repuestos.',
      'Los equipos no retirados dentro de los 90 días de avisado que están listos se consideran abandonados.',
      'No nos responsabilizamos por la información guardada en el equipo: hacé una copia de seguridad antes de dejarlo.',
    ],
    'mensaje_legal' => 'Este documento no es una factura y no posee validez fiscal.',
    // Si el cliente acepta el presupuesto desde el link, la orden pasa a "En proceso".
    'aceptar_inicia_trabajo' => true,
  ],

  'turnos' => [
    // Horario de atención por día (1 = lunes … 7 = domingo). null = cerrado.
    'horario' => [
      '1' => ['desde' => '08:00', 'hasta' => '18:00'],
      '2' => ['desde' => '08:00', 'hasta' => '18:00'],
      '3' => ['desde' => '08:00', 'hasta' => '18:00'],
      '4' => ['desde' => '08:00', 'hasta' => '18:00'],
      '5' => ['desde' => '08:00', 'hasta' => '18:00'],
      '6' => ['desde' => '08:00', 'hasta' => '13:00'],
      '7' => null,
    ],
    // Rechazar turnos fuera del horario de atención o en feriados.
    'validar_horario' => true,
    // Cuántos turnos se aceptan en el mismo día y hora (p. ej. cantidad de técnicos en el mostrador).
    'cupos_por_horario' => 1,
    // Duración de cada franja en la agenda semanal (minutos).
    'intervalo_minutos' => 60,
    // Fechas no laborables (AAAA-MM-DD). Se pueden importar los feriados nacionales.
    'feriados' => [],
    // Enviar email para que el cliente confirme o cancele el turno al agendarlo.
    'enviar_confirmacion' => true,
    // Recordatorio automático el día hábil anterior al turno, desde esta hora.
    'recordatorio_automatico' => true,
    'recordatorio_hora' => '10:00',
  ],

  'notificaciones' => [
    // Canales a usar, en orden de preferencia. Disponibles: email (whatsapp: próximamente).
    'canales' => ['email'],
    // Mostrar el botón manual "WhatsApp" (abre wa.me con el mensaje armado).
    'boton_whatsapp_manual' => true,
    // Código de país para los links de WhatsApp (Argentina: 54).
    'codigo_pais' => '54',
    // Avisar por email al negocio cuando un cliente acepta o rechaza un presupuesto.
    'avisar_negocio' => true,
    // Avisarle al cliente que el equipo está listo para retirar cuando la orden pasa a "Finalizado".
    'avisar_listo' => true,
  ],

  'mensajes' => [
    'whatsapp_confirmacion' => 'Hola {cliente}, agendamos tu turno en {negocio} el {fecha} a las {hora} hs para tu {equipo}. '
      . 'Confirmalo o cancelalo desde acá: {link_turno}',
    'whatsapp_recordatorio' => 'Hola {cliente}, te recordamos tu turno en {negocio} el {fecha} a las {hora} hs para tu {equipo}. '
      . 'Dirección: {direccion}. Si no podés asistir, avisanos al {telefono}. ¡Gracias!',
    'email_confirmacion_asunto' => 'Confirmá tu turno en {negocio} - {fecha} {hora} hs',
    'email_confirmacion' => "Hola {cliente}:\n\nAgendamos tu turno en {negocio} para el {fecha} a las {hora} hs (equipo: {equipo}).\n\n"
      . "Confirmá tu asistencia o cancelá el turno desde este link:\n{link_turno}\n\n"
      . "Dirección: {direccion}\nTeléfono: {telefono}\n\n¡Gracias!",
    'email_recordatorio_asunto' => 'Recordatorio: tu turno en {negocio} es el {fecha} a las {hora} hs',
    'email_recordatorio' => "Hola {cliente}:\n\nTe recordamos tu turno en {negocio} el {fecha} a las {hora} hs para tu {equipo}.\n\n"
      . "Si todavía no lo confirmaste, o no podés asistir, entrá acá:\n{link_turno}\n\n"
      . "Dirección: {direccion}\nTeléfono: {telefono}\n\n¡Te esperamos!",
    'email_presupuesto_asunto' => 'Presupuesto N° {numero} de {negocio} - {equipo}',
    'email_presupuesto' => "Hola {cliente}:\n\nTe enviamos el presupuesto N° {numero} para tu {equipo} por un total de $ {total}. "
      . "Lo tenés adjunto en PDF.\n\nPodés aceptarlo o rechazarlo desde este link:\n{link_presupuesto}\n\n"
      . "Ante cualquier consulta, escribinos o llamanos al {telefono}.\n\n¡Gracias!",
    'email_presupuesto_modificado_asunto' => 'Cambió el presupuesto N° {numero} de tu {equipo}',
    'email_presupuesto_modificado' => "Hola {cliente}:\n\nTuvimos que modificar el presupuesto N° {numero} que habías aceptado para tu {equipo}. "
      . "El nuevo total es de $ {total}; lo tenés adjunto en PDF.\n\nPara seguir con el trabajo necesitamos que lo revises y lo vuelvas a aceptar desde este link:\n{link_presupuesto}\n\n"
      . "Ante cualquier consulta, escribinos o llamanos al {telefono}.\n\n¡Gracias!",
    'email_listo_asunto' => '¡Tu {equipo} está listo! - {negocio}',
    'email_listo' => "Hola {cliente}:\n\nTerminamos el trabajo de la orden N° {numero} en tu {equipo}: ya podés pasar a retirarlo.\n\n"
      . "Total: $ {total}\nSaldo a abonar: $ {saldo}\n\nTe esperamos en {direccion}. Ante cualquier consulta, llamanos al {telefono}.\n\n"
      . "¡Gracias por confiar en {negocio}!",
    'email_mantenimiento_asunto' => 'Se acerca el mantenimiento de tu {equipo}',
    'email_mantenimiento' => "Hola {cliente}:\n\nTe recordamos que el próximo mantenimiento de tu {equipo} (limpieza interna y cambio de pasta térmica)"
      . " está previsto para el {fecha_proximo}.\n\nPedí tu turno llamando al {telefono} o respondiendo este email.\n\n"
      . "{negocio}\n{direccion}",
    'whatsapp_presupuesto' => 'Hola {cliente}, te enviamos el presupuesto N° {numero} por $ {total}. Podés aceptarlo desde acá: {link_presupuesto}',
    'whatsapp_presupuesto_modificado' => 'Hola {cliente}, tuvimos que modificar el presupuesto N° {numero} de tu {equipo}. '
      . 'El nuevo total es $ {total}. Revisalo y volvé a aceptarlo desde acá: {link_presupuesto}',
    'whatsapp_listo' => 'Hola {cliente}, tu {equipo} ya está listo para retirar en {negocio} ({direccion}). Saldo a abonar: $ {saldo}. ¡Te esperamos!',
    'whatsapp_mantenimiento' => 'Hola {cliente}, se acerca el mantenimiento de tu {equipo} ({fecha_proximo}). ¡Pedí tu turno!',
  ],

  'mantenimiento' => [
    // Intervalo sugerido al cargar el próximo mantenimiento (limpieza) de una orden.
    'intervalo_meses' => 12,
    // Aviso automático al cliente antes de la fecha del próximo mantenimiento.
    'aviso_automatico' => true,
    'aviso_dias_antes' => 7,
  ],

  'stock' => [
    // Permitir finalizar órdenes aunque el stock de un repuesto quede negativo.
    'permitir_negativo' => true,
    // Margen sobre el costo para sugerir el precio de venta de los repuestos (%).
    'margen_sugerido' => 40,
  ],

  'backups' => [
    // Backup automático diario de la base y de las imágenes (storage/backups).
    'habilitado' => true,
    'hora' => '22:00',
    'conservar' => 14,
  ],

  'portal' => [
    // Página pública "Seguí tu equipo" (/seguimiento).
    'habilitado' => true,
    // Además del DNI, pedir el número de una de sus órdenes (recomendado).
    'requiere_orden' => true,
    'mostrar_montos' => true,
    'cantidad_ordenes' => 10,
  ],
];
