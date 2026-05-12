<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

/**
 * High-level shape category of a `PhpType`.
 *
 * - `Scalar`     — primitive (`int`, `string`, `bool`, `float`); no import needed.
 * - `ClassName`  — single named class/enum reference; `importFqcn` carries the FQCN.
 * - `ListOf`     — homogenous array; `innerType` carries the element type.
 * - `Union`      — 2+ alternatives; `unionMembers` carries the components.
 */
enum PhpTypeKind: string
{
  case Scalar = 'scalar';
  case ClassName = 'class';
  case ListOf = 'list';
  case Union = 'union';
}
