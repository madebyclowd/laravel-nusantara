<?php

namespace MadeByClowd\Nusantara\Exceptions;

/**
 * Thrown when a WKB geometry's type is not Polygon/MultiPolygon — the only
 * types this package's boundary column ever stores. Rejected via a cheap
 * header-only peek before `brick/geo`'s recursive WKB reader ever touches
 * the bytes, which doubles as this package's guard against the adversarial
 * deeply-nested-GeometryCollection DoS vector (an uncatchable C-stack
 * overflow, not a catchable exception — see GeometryReader's doc comment).
 */
class UnsupportedWkbGeometryTypeException extends SpatialWkbException {}
