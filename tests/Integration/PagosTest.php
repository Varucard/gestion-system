<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\ValidationException;
use App\Repositories\PagoRepository;
use App\Services\OrdenService;
use App\Services\PagoService;

final class PagosTest extends IntegrationTestCase
{
  private function orden(float $precio = 1000): array
  {
    $cliente = $this->crearCliente();
    $equipo = $this->crearEquipo($cliente);
    $id = $this->make(OrdenService::class)->guardar($equipo, [$this->crearServicio('Servicio', $precio)], []);

    return ['id' => $id, 'cliente' => $cliente];
  }

  private function pagar(int $ordenId, string $monto): void
  {
    $this->make(PagoService::class)->registrar($ordenId, ['monto' => $monto, 'forma_pago' => 'Contado', 'fecha' => date('Y-m-d')], null);
  }

  public function testPagosParcialesYSaldo(): void
  {
    $orden = $this->orden(1000);
    $pagos = $this->make(PagoService::class);

    $this->pagar($orden['id'], '400');
    $this->assertSame(600.0, $pagos->saldo($orden['id']));

    $this->pagar($orden['id'], '600');
    $this->assertSame(0.0, $pagos->saldo($orden['id']));
  }

  public function testNoSePagaMasQueElSaldo(): void
  {
    $orden = $this->orden(1000);

    $this->expectExceptionMessage('El monto supera el saldo pendiente');
    $this->pagar($orden['id'], '1000,01');
  }

  public function testFormaDePagoDebeEstarConfigurada(): void
  {
    $orden = $this->orden();

    $this->expectExceptionMessage('forma de pago válida');
    $this->make(PagoService::class)->registrar($orden['id'], ['monto' => '10', 'forma_pago' => 'Trueque'], null);
  }

  public function testDeudoresSoloConOrdenesFinalizadasConSaldo(): void
  {
    $orden = $this->orden(1000);
    $this->pagar($orden['id'], '300');
    $repo = $this->make(PagoRepository::class);

    $this->assertSame([], $repo->deudores(), 'Una orden pendiente todavía no genera deuda');

    $this->make(OrdenService::class)->cambiarEstado($orden['id'], 'finalizado');
    $this->assertSame([$orden['cliente'] => 700.0], $repo->saldosPorCliente());
  }

  public function testNoSeBajaElTotalPorDebajoDeLoPagado(): void
  {
    $orden = $this->orden(1000);
    $this->pagar($orden['id'], '800');
    $equipo = (int) $this->db->query("SELECT equipo_id FROM ordenes WHERE id = {$orden['id']}")->fetchColumn();
    $servicio = (int) $this->db->query('SELECT id FROM servicios LIMIT 1')->fetchColumn();

    try {
      $this->make(OrdenService::class)->guardar($equipo, [$servicio => ['cantidad' => '1', 'precio' => '500']], [], $orden['id']);
      $this->fail('Debía impedir que el total quede debajo de lo pagado');
    } catch (ValidationException $e) {
      $this->assertStringContainsString('por debajo de lo ya pagado', $e->getMessage());
    }
  }

  public function testComprobanteDeEntregaSoloParaOrdenesFinalizadas(): void
  {
    $orden = $this->orden(1000);
    $this->pagar($orden['id'], '250');
    $documentos = $this->make(\App\Services\DocumentoService::class);

    try {
      $documentos->datos($orden['id'], true);
      $this->fail('No debía emitir el comprobante de una orden pendiente');
    } catch (ValidationException) {
    }

    $this->make(OrdenService::class)->cambiarEstado($orden['id'], 'finalizado');
    $datos = $documentos->datos($orden['id'], true);

    $this->assertSame('Comprobante de entrega', $datos['titulo']);
    $this->assertSame(250.0, $datos['pagado']);
    $this->assertSame(750.0, $datos['saldo']);
  }

  public function testOrdenCanceladaNoAdmitePagos(): void
  {
    $orden = $this->orden();
    $this->make(OrdenService::class)->cambiarEstado($orden['id'], 'cancelado');

    $this->expectExceptionMessage('No se registran pagos en órdenes canceladas.');
    $this->pagar($orden['id'], '10');
  }
}
