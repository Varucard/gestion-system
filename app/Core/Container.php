<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Contenedor de dependencias mínimo con autowiring por tipo.
 *
 * Las clases concretas (repositorios, servicios, controladores) se construyen
 * solas resolviendo los tipos de su constructor; solo hace falta registrar
 * explícitamente lo que no se puede inferir (por ejemplo, PDO).
 */
final class Container
{
  /** @var array<string, Closure> */
  private array $factories = [];

  /** @var array<string, object> */
  private array $instances = [];

  public function set(string $id, Closure $factory): void
  {
    $this->factories[$id] = $factory;
    unset($this->instances[$id]);
  }

  /**
   * @template T of object
   * @param class-string<T> $id
   * @return T
   */
  public function get(string $id): object
  {
    if (isset($this->instances[$id])) {
      return $this->instances[$id];
    }

    $instance = isset($this->factories[$id])
      ? ($this->factories[$id])($this)
      : $this->build($id);

    return $this->instances[$id] = $instance;
  }

  private function build(string $class): object
  {
    if (!class_exists($class)) {
      throw new RuntimeException("No se puede resolver la dependencia '{$class}'.");
    }

    $reflection = new ReflectionClass($class);
    $constructor = $reflection->getConstructor();

    if ($constructor === null) {
      return new $class();
    }

    $args = [];
    foreach ($constructor->getParameters() as $param) {
      $type = $param->getType();

      if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
        $args[] = $this->get($type->getName());
      } elseif ($param->isDefaultValueAvailable()) {
        $args[] = $param->getDefaultValue();
      } else {
        throw new RuntimeException("No se puede resolver el parámetro \${$param->getName()} de {$class}.");
      }
    }

    return $reflection->newInstanceArgs($args);
  }
}
