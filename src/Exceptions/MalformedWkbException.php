<?php

namespace MadeByClowd\Nusantara\Exceptions;

/**
 * Thrown when a native spatial column's binary value cannot be decoded as
 * well-known binary (WKB) — truncated/corrupt bytes, an invalid byte-order
 * flag, etc. Wraps the underlying decode library's exception type so this
 * package's public API stays stable regardless of the decode implementation.
 */
class MalformedWkbException extends SpatialWkbException {}
