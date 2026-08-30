<?php

namespace MadeByClowd\Nusantara\Exceptions;

/**
 * Thrown when decoding a native spatial boundary column is attempted without
 * the optional `brick/geo` dependency installed (it's a `suggest`, not a
 * `require`, since the boundary/GIS feature is opt-in).
 */
class MissingSpatialDependencyException extends SpatialWkbException {}
