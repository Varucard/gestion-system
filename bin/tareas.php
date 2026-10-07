<?php

declare(strict_types=1);

/**
 * Tareas periódicas: recordatorios de turnos, avisos de mantenimiento, backup y limpieza.
 *
 *   php bin/tareas.php
 *
 * En Docker la ejecuta el servicio "tareas" cada 15 minutos. Los recordatorios
 * solo salen en días hábiles, dentro del horario de atención y a partir de la
 * hora configurada, así que se puede ejecutar con la frecuencia que se quiera.
 */

use App\Core\App;
use App\Core\Env;
use App\Repositories\IntentoRepository;
use App\Services\BackupService;
use App\Services\NotificacionService;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = App::boot(dirname(__DIR__));
$ahora = new DateTimeImmutable('now');
$fallas = 0;

/**
 * Ejecuta una tarea aislada: si falla se registra y se sigue con las demás (un error en
 * los recordatorios no debe impedir el backup ni la limpieza).
 *
 * @template T
 * @param callable(): T $tarea
 * @return T|null
 */
$ejecutar = function (string $nombre, callable $tarea) use ($app, $ahora, &$fallas): mixed {
  try {
    return $tarea();
  } catch (Throwable $e) {
    $fallas++;
    $app->logger->error("Falló la tarea periódica: {$nombre}", ['exception' => $e]);
    fwrite(STDERR, '[' . $ahora->format('Y-m-d H:i') . "] Error en {$nombre}: " . $e->getMessage() . PHP_EOL);

    return null;
  }
};

$notificaciones = $app->container->get(NotificacionService::class);
$backups = $app->container->get(BackupService::class);

$resultado = $ejecutar('recordatorios', fn() => $notificaciones->enviarRecordatoriosPendientes($ahora));
$mantenimientos = $ejecutar('avisos de mantenimiento', fn() => $notificaciones->enviarAvisosMantenimiento($ahora));
$backup = $ejecutar('backup', fn() => $backups->corresponde($ahora) ? $backups->generar() : []) ?? [];
$purgados = $ejecutar('limpieza de intentos', fn() => $app->container->get(IntentoRepository::class)->purgar()) ?? 0;
$logsBorrados = $ejecutar('limpieza de logs', fn() => $app->logger->purgar((int) Env::get('LOG_DIAS', '30'))) ?? 0;

if ($mantenimientos !== null && $mantenimientos['omitido'] === null && $mantenimientos['enviados'] + $mantenimientos['sin_contacto'] + $mantenimientos['errores'] > 0) {
  $app->logger->info('Avisos de próximo mantenimiento: {enviados} enviados, {sin} sin contacto, {errores} con error', [
    'enviados' => $mantenimientos['enviados'], 'sin' => $mantenimientos['sin_contacto'], 'errores' => $mantenimientos['errores'],
  ]);
}

if ($resultado !== null && $resultado['omitido'] === null) {
  $app->logger->info('Recordatorios automáticos: {enviados} enviados, {sin} sin contacto, {errores} con error', [
    'enviados' => $resultado['enviados'], 'sin' => $resultado['sin_contacto'], 'errores' => $resultado['errores'],
  ]);
}

$mensaje = match (true) {
  $resultado === null => 'Recordatorios: error',
  $resultado['omitido'] !== null => 'Recordatorios: ' . rtrim($resultado['omitido'], '.'),
  default => "Recordatorios: {$resultado['enviados']} enviados, {$resultado['sin_contacto']} sin contacto, {$resultado['errores']} con error",
};
$mensaje .= $mantenimientos === null ? '. Mantenimientos: error' : ($mantenimientos['omitido'] === null ? ". Mantenimientos: {$mantenimientos['enviados']} avisados" : '');
$mensaje .= $backup !== [] ? '. Backup: ' . implode(', ', $backup) : '';

echo '[' . $ahora->format('Y-m-d H:i') . "] {$mensaje}. Limpieza: {$purgados} intentos, {$logsBorrados} archivos de log." . PHP_EOL;

exit($fallas > 0 ? 1 : 0);
