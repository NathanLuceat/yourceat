<?php

namespace App\Console\Commands;

use App\Services\WebbliotecaService;
use Illuminate\Console\Command;

class SyncWebblioteca extends Command
{
    protected $signature = 'webblioteca:sync';

    protected $description = 'Sincroniza um lote de atividades públicas da Webblioteca';

    public function handle(WebbliotecaService $service): int
    {
        $result = $service->sync();
        $this->line('Webblioteca: '.$result['status'].' ('.$result['count'].')');

        return $result['status'] === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
