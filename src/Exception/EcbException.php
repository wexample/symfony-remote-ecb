<?php

namespace Wexample\SymfonyRemoteEcb\Exception;

use RuntimeException;

/**
 * The ECB feed could not be read: unreachable, or not the XML it publishes.
 * A currency or a day it has no rate for is no failure: the provider answers null.
 */
class EcbException extends RuntimeException
{
}
