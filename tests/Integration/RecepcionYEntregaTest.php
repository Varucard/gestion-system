<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\OrdenRepository;
use App\Repositories\EquipoRepository;
use App\Services\DocumentoService;
use App\Services\EntregaService;
use App\Services\FirmaService;
use App\Services\OrdenService;

/** Cómo llegó el equipo (accesorios, golpes, fotos y firma) y su entrega con la firma del cliente. */
final class RecepcionYEntregaTest extends HttpTestCase
{
  /** @var list<string> */
  private array $temporales = [];

  protected function tearDown(): void
  {
    array_map('unlink', array_filter($this->temporales, 'is_file'));
    parent::tearDown();
  }

  /** Dibujo de una firma como lo manda el navegador: PNG transparente con un trazo (o vacío). */
  private static function firma(bool $conTrazo = true): string
  {
    $imagen = imagecreatetruecolor(400, 150);
    imagesavealpha($imagen, true);
    imagefill($imagen, 0, 0, imagecolorallocatealpha($imagen, 0, 0, 0, 127));
    if ($conTrazo) {
      imagesetthickness($imagen, 4);
      imageline($imagen, 20, 120, 380, 30, imagecolorallocate($imagen, 26, 26, 46));
      imageline($imagen, 20, 30, 200, 130, imagecolorallocate($imagen, 26, 26, 46));
    }
    ob_start();
    imagepng($imagen);

    return 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
  }

  /** Archivo como llega en $_FILES: una foto JPEG chica, o un archivo que no es imagen. */
  private function archivo(bool $esFoto = true): array
  {
    $ruta = tempnam(sys_get_temp_dir(), 'foto');
    $this->temporales[] = $ruta;
    if ($esFoto) {
      $imagen = imagecreatetruecolor(60, 40);
      imagefill($imagen, 0, 0, imagecolorallocate($imagen, 200, 30, 30));
      imagejpeg($imagen, $ruta);
    } else {
      file_put_contents($ruta, 'esto no es una foto');
    }

    return ['name' => $esFoto ? 'foto.jpg' : 'nota.txt', 'type' => 'image/jpeg', 'tmp_name' => $ruta, 'error' => UPLOAD_ERR_OK, 'size' => filesize($ruta)];
  }

  /** Varias fotos en un campo "fotos[]", con el formato de $_FILES. */
  private static function campoFotos(array ...$archivos): array
  {
    $campo = [];
    foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $clave) {
      $campo[$clave] = array_column($archivos, $clave);
    }

    return ['fotos' => $campo];
  }

  private function recibir(array $datos, array $archivos = []): int
  {
    $equipo = $this->crearEquipo($this->crearCliente(), 'AB123CD');
    $r = $this->pedir('POST', '/recepcion', ['dni' => '30111222', 'equipo_id' => (string) $equipo, 'falla_reportada' => 'No enciende', ...$datos], archivos: $archivos);
    $this->assertMatchesRegularExpression('#^/ordenes/\d+$#', (string) $r->destino);

    return (int) basename((string) $r->destino);
  }

  private function ordenLista(): int
  {
    $equipo = $this->crearEquipo($this->crearCliente('30999888'), 'AC456DD');
    $id = $this->make(OrdenService::class)->guardar($equipo, [$this->crearServicio('Cambio de pantalla', 15000) => ['cantidad' => '1']], []);
    $this->make(OrdenService::class)->cambiarEstado($id, 'finalizado');

    return $id;
  }

  public function testRecibirConAccesoriosGolpesFotosYFirma(): void
  {
    $this->ingresarComo();

    $id = $this->recibir(
      ['accesorios' => 'Cargador', 'estado_ingreso' => 'Rayón en la tapa', 'firma' => self::firma(), 'firma_aclaracion' => 'Juan Pérez'],
      self::campoFotos($this->archivo(), $this->archivo()),
    );

    $orden = $this->make(OrdenRepository::class)->find($id);
    $this->assertSame('Cargador', $orden['accesorios']);
    $this->assertSame('Rayón en la tapa', $orden['estado_ingreso']);
    $this->assertCount(2, $this->make(EquipoRepository::class)->imagenesDeOrden($id));
    $firma = $this->make(FirmaService::class)->deOrden($id)['recepcion'];
    $this->assertSame('Juan Pérez', $firma['aclaracion']);
    $this->assertNotEmpty($this->mensajes('success'));

    // La firma guardada sigue teniendo los trazos después de re-codificarla.
    $guardada = (string) file_get_contents($this->make(FirmaService::class)->ruta($id, 'recepcion'));
    $this->assertTrue(FirmaService::tieneTinta($guardada));
    $this->assertStringStartsWith("\x89PNG", $this->pedir('GET', "/ordenes/{$id}/firmas/recepcion")->html());
  }

  public function testSiUnaFotoOLaFirmaFallanElEquipoQuedaRecibidoYSeAvisa(): void
  {
    $this->ingresarComo();

    $id = $this->recibir(['firma' => self::firma(conTrazo: false)], self::campoFotos($this->archivo(esFoto: false), $this->archivo()));

    $this->assertSame('pendiente', $this->make(OrdenRepository::class)->find($id)['estado']);
    $this->assertCount(1, $this->make(EquipoRepository::class)->imagenesDeOrden($id));
    $this->assertSame([], $this->make(FirmaService::class)->deOrden($id));
    $aviso = $this->mensajes('aviso')[0];
    $this->assertStringContainsString('la foto 1 no se guardó', $aviso);
    $this->assertStringContainsString('La firma no se guardó', $aviso);
  }

  public function testEstadoDeIngresoDemasiadoLargoNoSeAcepta(): void
  {
    $equipo = $this->crearEquipo($this->crearCliente());

    $this->assertValidationError(
      fn() => $this->make(OrdenService::class)->guardar($equipo, [], [], detalle: ['estado_ingreso' => str_repeat('a', 1001)]),
      'hasta 1000 caracteres',
    );
  }

  public function testFotosYFirmaDespuesDesdeLaOrden(): void
  {
    $this->ingresarComo();
    $id = $this->recibir([]);

    $this->pedir('POST', "/ordenes/{$id}/fotos", archivos: self::campoFotos($this->archivo()));
    $this->assertCount(1, $this->make(EquipoRepository::class)->imagenesDeOrden($id));

    $sinFirma = $this->pedir('POST', "/ordenes/{$id}/firmar", ['firma' => '']);
    $this->assertSame("/ordenes/{$id}/firmar", $sinFirma->destino);

    $this->pedir('POST', "/ordenes/{$id}/firmar", ['firma' => self::firma()]);
    $this->assertArrayHasKey('recepcion', $this->make(FirmaService::class)->deOrden($id));

    $ficha = $this->pedir('GET', "/ordenes/{$id}")->html();
    $this->assertStringContainsString('Cómo llegó', $ficha);
    $this->assertStringContainsString("/ordenes/{$id}/firmas/recepcion", $ficha);
  }

  public function testEntregarConFirmaYDeshacer(): void
  {
    $this->ingresarComo();
    $id = $this->ordenLista();

    $this->assertStringContainsString('Entregar el equipo', $this->pedir('GET', "/ordenes/{$id}/entregar")->html());
    $r = $this->pedir('POST', "/ordenes/{$id}/entregar", ['firma' => self::firma(), 'firma_aclaracion' => 'María López']);

    $this->assertSame("/ordenes/{$id}", $r->destino);
    $this->assertNotNull($this->make(OrdenRepository::class)->find($id)['entregado_en']);
    $this->assertSame("ordenes/{$id}/entrega/anular", json_decode($this->mensajes('deshacer')[0], true)['accion']);
    $comprobante = $this->make(DocumentoService::class)->datos($id, entrega: true);
    $this->assertStringStartsWith('data:image/png;base64,', (string) $comprobante['firma']);
    $this->assertSame('María López', $comprobante['firmaAclaracion']);

    $this->pedir('POST', "/ordenes/{$id}/entrega/anular");
    $this->assertNull($this->make(OrdenRepository::class)->find($id)['entregado_en']);
    $this->assertSame([], $this->make(FirmaService::class)->deOrden($id));
  }

  public function testSoloSeEntregaUnaVezYConElTrabajoTerminado(): void
  {
    $entregas = $this->make(EntregaService::class);
    $equipo = $this->crearEquipo($this->crearCliente());
    $abierta = $this->make(OrdenService::class)->guardar($equipo, [], []);

    $this->assertValidationError(fn() => $entregas->entregar($abierta, null, null, null), 'primero terminá el trabajo');

    $lista = $this->ordenLista();
    $entregas->entregar($lista, null, null, null);
    $this->assertValidationError(fn() => $entregas->entregar($lista, null, null, null), 'ya fue entregado');
  }

  public function testUnaFirmaEnBlancoNoEntregaElEquipo(): void
  {
    $id = $this->ordenLista();

    $this->assertValidationError(fn() => $this->make(EntregaService::class)->entregar($id, self::firma(conTrazo: false), null, null), 'en blanco');
    $this->assertNull($this->make(OrdenRepository::class)->find($id)['entregado_en']);
  }

  public function testUnEquipoEntregadoNoSeReabreSinDeshacerLaEntrega(): void
  {
    $id = $this->ordenLista();
    $this->make(EntregaService::class)->entregar($id, self::firma(), null, null);

    // Desde una pestaña vieja: se rechaza (si no, quedaba viva la firma de esa entrega).
    $this->assertValidationError(fn() => $this->make(OrdenService::class)->cambiarEstado($id, 'en_proceso'), 'Deshacer la entrega');
    $this->assertValidationError(fn() => $this->make(OrdenService::class)->cambiarEstado($id, 'cancelado'), 'Deshacer la entrega');

    $this->make(EntregaService::class)->anular($id);
    $this->make(OrdenService::class)->cambiarEstado($id, 'en_proceso');
    $this->assertNull($this->make(OrdenRepository::class)->find($id)['entregado_en']);
    $this->assertSame([], $this->make(FirmaService::class)->deOrden($id));
  }

  public function testPasadaMediaHoraSoloUnAdministradorDeshaceLaEntrega(): void
  {
    $id = $this->ordenLista();
    $this->make(EntregaService::class)->entregar($id, null, null, null);
    $this->db->exec("UPDATE ordenes SET entregado_en = NOW() - INTERVAL 2 HOUR WHERE id = {$id}");

    $this->assertValidationError(fn() => $this->make(EntregaService::class)->anular($id), 'solo un administrador');
    $this->make(EntregaService::class)->anular($id, administrador: true);
    $this->assertNull($this->make(OrdenRepository::class)->find($id)['entregado_en']);
  }

  public function testLaFirmaNoSeReemplazaYNoAceptaImagenesGigantes(): void
  {
    $id = $this->ordenLista();
    $firmas = $this->make(FirmaService::class);
    $firmas->guardar($id, 'recepcion', self::firma(), null, null);

    $this->assertValidationError(fn() => $firmas->guardar($id, 'recepcion', self::firma(), null, null), 'ya firmó');

    // Un PNG chico que declara 20000×20000 px en su cabecera no se llega a abrir (agotaría la
    // memoria del servidor): se arma uno de 10×10 y se le cambian las medidas.
    $chico = imagecreatetruecolor(10, 10);
    ob_start();
    imagepng($chico);
    $png = substr_replace((string) ob_get_clean(), pack('NN', 20000, 20000), 16, 8);
    $this->assertFalse(FirmaService::medidaValida($png));
    $this->assertValidationError(fn() => $firmas->guardar($id, 'entrega', 'data:image/png;base64,' . base64_encode($png), null, null), 'no llegó bien');
  }

  public function testSoloAceptaUnPngComoFirma(): void
  {
    $this->assertNull(FirmaService::decodificar('data:image/jpeg;base64,' . base64_encode('x')));
    $this->assertNull(FirmaService::decodificar('data:image/png;base64,' . base64_encode('no es png')));
    $this->assertNull(FirmaService::decodificar('cualquier cosa'));
    $this->assertNotNull(FirmaService::decodificar(self::firma()));
  }

  public function testSiYaFirmoComoLlegoNoSePideFirmarDeNuevo(): void
  {
    $this->ingresarComo();
    $id = $this->ordenLista();
    $this->make(FirmaService::class)->guardar($id, 'recepcion', self::firma(), null, null);

    $r = $this->pedir('GET', "/ordenes/{$id}/firmar");

    $this->assertSame("/ordenes/{$id}#como_llego", $r->destino);
    $this->assertStringContainsString('ya firmó', $this->mensajes('aviso')[0]);
  }

  public function testUnEquipoListoSinRetirarSigueEnElLocalAlRecibirlo(): void
  {
    $id = $this->ordenLista();
    $equipo = (int) $this->make(OrdenRepository::class)->find($id)['equipo_id'];
    $recepcion = $this->make(\App\Services\RecepcionService::class);

    $this->assertSame([$id], array_map('intval', array_column($recepcion->abiertas($equipo), 'id')));
    $this->assertValidationError(fn() => $recepcion->recibir(['dni' => '30999888', 'equipo_id' => (string) $equipo]), "ya tiene la orden #{$id} abierta");

    $this->make(EntregaService::class)->entregar($id, null, null, null);
    $this->assertSame([], $recepcion->abiertas($equipo), 'entregado: ya se fue');
  }
}
