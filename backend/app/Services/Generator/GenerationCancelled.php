<?php

namespace App\Services\Generator;

use RuntimeException;

/** Thrown when a generation is deleted while the worker is still on it. */
class GenerationCancelled extends RuntimeException {}
