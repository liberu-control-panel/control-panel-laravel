<?php

namespace App\Filament\Admin\Resources\EmailResource;

use Symfony\Component\Process\Process;

class ContainerRestarter
{
    public function restart(): void
    {
        $dovecotProcess = new Process(['docker', 'restart', 'dovecot']);
        $dovecotProcess->run();

        $postfixProcess = new Process(['docker', 'restart', 'postfix']);
        $postfixProcess->run();
    }
}