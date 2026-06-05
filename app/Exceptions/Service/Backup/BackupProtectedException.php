<?php

namespace Pterodactyl\Exceptions\Service\Backup;

use Pterodactyl\Exceptions\DisplayException;

class BackupProtectedException extends DisplayException
{
    public function __construct()
    {
        parent::__construct('Cannot delete one of the two oldest downloadable backups. Only a root administrator can remove protected backups.');
    }
}
