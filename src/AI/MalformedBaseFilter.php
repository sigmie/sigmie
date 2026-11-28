<?php

declare(strict_types=1);

namespace Sigmie\AI;

use Sigmie\Parse\ParseException;

/**
 * The server-controlled base filter of a tool does not parse. The agent cannot correct it and
 * must not read it, so a tool throws this instead of returning it as a tool error.
 */
class MalformedBaseFilter extends ParseException {}
