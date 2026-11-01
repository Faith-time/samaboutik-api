<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Le prestataire (Lightning, PayDunya) ou le taux de change ne répond pas.
 * Le message est affiché tel quel dans l'application.
 */
class PaiementIndisponibleException extends RuntimeException
{
}
