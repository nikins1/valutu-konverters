<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Kļūda, kas rodas, ja ārējais kursu API nav pieejams vai atgriež nederīgu atbildi.
 *
 * Ideja pārņemta no GitHub projekta cmbuckley/exchange-rates (src/Exceptions/ServiceException.php),
 * MIT licence, (c) 2024 Chris Buckley. Licences teksts: docs/licenses/exchange-rates-LICENSE.md
 * Izmaiņas: mainīts nosaukumtelpas nosaukums (App\Exceptions).
 */
final class ServiceException extends RuntimeException
{
}
