<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\OrdenRepository;
use App\Services\DocumentoService;
use App\Services\OrdenService;
use App\Support\CodigoQr;

/** Ticket para impresora térmica, con QR, y el QR de los comprobantes A4. */
final class TicketTest extends HttpTestCase
{
  private function orden(bool $conItems = true): int
  {
    $equipo = $this->crearEquipo($this->crearCliente());

    return $this->make(OrdenService::class)->guardar($equipo, $conItems ? [$this->crearServicio('Cambio de pantalla', 15000) => ['cantidad' => '1']] : [], [],
      detalle: ['falla_reportada' => 'No enciende', 'accesorios' => 'Cargador original']);
  }

  public function testTicketDeRecepcionConQrAlSeguimiento(): void
  {
    $this->ingresarComo();
    $id = $this->orden(conItems: false);
    $this->configurar('trabajo', ['ticket_ancho' => 58]);

    $html = $this->pedir('GET', "/ordenes/{$id}/ticket/recepcion")->html();

    $this->assertStringContainsString('RECEPCIÓN', $html);
    $this->assertStringContainsString('No enciende', $html);
    $this->assertStringContainsString('Cargador original', $html);
    $this->assertStringContainsString('<svg', $html);
    $this->assertStringContainsString('size: 58mm', $html);
    $datos = $this->make(DocumentoService::class)->ticket($id, 'recepcion');
    $esperado = CodigoQr::svg(absolute_url('presupuesto/' . $this->make(OrdenRepository::class)->token($id) . '/seguimiento'));
    $this->assertTrue($esperado === $datos['qr'], 'el QR lleva al seguimiento del equipo');
  }

  public function testElTicketDeEntregaEsParaTrabajosTerminados(): void
  {
    $this->ingresarComo();
    $id = $this->orden();

    $r = $this->pedir('GET', "/ordenes/{$id}/ticket/entrega");
    $this->assertSame("/ordenes/{$id}", $r->destino);

    $this->make(OrdenService::class)->cambiarEstado($id, 'finalizado');
    $html = $this->pedir('GET', "/ordenes/{$id}/ticket/entrega")->html();
    $this->assertStringContainsString('SALDO', $html);
    $this->assertStringContainsString('$15.000,00', $html);
  }

  public function testElPresupuestoNecesitaTrabajosYElTipoTieneQueExistir(): void
  {
    $id = $this->orden(conItems: false);

    $this->assertValidationError(fn() => $this->make(DocumentoService::class)->ticket($id, 'presupuesto'), 'todavía no tiene servicios');
    $this->assertValidationError(fn() => $this->make(DocumentoService::class)->ticket($id, 'otro'), 'no existe');
  }

  public function testElPdfDelPresupuestoLlevaElQr(): void
  {
    $id = $this->orden();

    $pdf = $this->make(DocumentoService::class)->pdf($id);

    $this->assertStringStartsWith('%PDF', $pdf['contenido']);
    $this->assertStringContainsString('/Subtype /Image', $pdf['contenido']);
  }
}
