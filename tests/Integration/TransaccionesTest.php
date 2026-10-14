<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\ClienteRepository;
use App\Repositories\MarcaRepository;
use App\Services\RecepcionService;

/**
 * Transacciones de verdad (BEGIN/COMMIT/ROLLBACK), savepoints y lo que se hace al confirmar.
 *
 * El resto de los tests corre adentro de una transacción que se descarta al terminar, así que
 * ahí cada transaction() es un savepoint y nunca se ejecuta el COMMIT de nivel superior. Acá se
 * deshace esa transacción externa al empezar: lo que se pruebe no debe dejar datos guardados.
 */
final class TransaccionesTest extends IntegrationTestCase
{
  private MarcaRepository $repo;

  protected function setUp(): void
  {
    parent::setUp();
    $this->db->rollBack(); // sin la transacción externa de los tests
    $this->repo = $this->make(MarcaRepository::class);
  }

  public function testLlegoUnEquipoEsTodoONadaConUnaTransaccionReal(): void
  {
    try {
      $this->make(RecepcionService::class)->recibir([
        'dni' => '31999888', 'nombre' => 'Eva', 'apellido' => 'Paz', 'telefono' => '1144556677', 'tipo' => 'notebook',
        'marca_id' => 'nuevo:MarcaQueNoQueda', 'modelo_id' => 'nuevo:Modelo', 'numero_serie' => 'NO#VALIDO',
      ]);
      $this->fail('El número de serie no es válido');
    } catch (\App\Exceptions\ValidationException) {
    }

    $this->assertNull($this->make(ClienteRepository::class)->porDni('31999888'), 'El cliente no quedó guardado');
    $this->assertNull($this->repo->porNombre('MarcaQueNoQueda'), 'La marca nueva tampoco');
    $this->assertFalse($this->db->inTransaction(), 'No quedó ninguna transacción abierta');
  }

  public function testUnSavepointQueFallaNoDeshaceLoDeAfuera(): void
  {
    $this->db->beginTransaction(); // datos de prueba descartables
    try {
      $this->repo->transaction(function () {
        $this->repo->save(new \App\Models\Marca('Afuera'));
        try {
          $this->repo->transaction(function () {
            $this->repo->save(new \App\Models\Marca('Adentro'));
            throw new \RuntimeException('falla adentro');
          });
        } catch (\RuntimeException) {
        }
      });

      $this->assertNotNull($this->repo->porNombre('Afuera'));
      $this->assertNull($this->repo->porNombre('Adentro'), 'Solo se deshizo lo del savepoint');
    } finally {
      $this->db->rollBack();
    }
  }

  public function testLoDeAlConfirmarPasaSoloSiSeConfirma(): void
  {
    $hechas = [];

    // Funciones comunes (no flecha): las flecha capturan $hechas por copia, no por referencia.
    $this->repo->transaction(function () use (&$hechas) {
      $this->repo->alConfirmar(function () use (&$hechas) { $hechas[] = 'confirmada'; });
    });
    $this->assertSame(['confirmada'], $hechas);

    try {
      $this->repo->transaction(function () use (&$hechas) {
        $this->repo->alConfirmar(function () use (&$hechas) { $hechas[] = 'deshecha'; });
        throw new \RuntimeException('se deshace');
      });
    } catch (\RuntimeException) {
    }
    $this->assertSame(['confirmada'], $hechas, 'Lo de una transacción deshecha no se hace');

    $this->repo->transaction(function () use (&$hechas) {
      $this->repo->transaction(function () use (&$hechas) {
        $this->repo->alConfirmar(function () use (&$hechas) { $hechas[] = 'anidada'; });
      });
      $this->assertSame(['confirmada'], $hechas, 'Lo del savepoint espera al COMMIT de afuera');
      try {
        $this->repo->transaction(function () use (&$hechas) {
          $this->repo->alConfirmar(function () use (&$hechas) { $hechas[] = 'savepoint deshecho'; });
          throw new \RuntimeException('falla adentro');
        });
      } catch (\RuntimeException) {
      }
    });
    $this->assertSame(['confirmada', 'anidada'], $hechas);

    $this->repo->alConfirmar(function () use (&$hechas) { $hechas[] = 'sin transacción'; });
    $this->assertSame('sin transacción', end($hechas), 'Sin transacción, se hace en el momento');
  }

  public function testElCandadoNoSeUsaDentroDeUnaTransaccion(): void
  {
    $this->expectException(\LogicException::class);
    $this->repo->transaction(fn() => $this->repo->conCandado('prueba', fn() => null));
  }
}
