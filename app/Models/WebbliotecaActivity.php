<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class WebbliotecaActivity extends Model
{
    protected $fillable = ['external_id', 'type', 'summary', 'is_system', 'occurred_at'];

    protected $casts = [
        'summary'     => 'array',
        'is_system'   => 'boolean',
        'occurred_at' => 'datetime',
    ];

    /** Frase pronta para exibir: $activity->text */
    protected function text(): Attribute
    {
        return Attribute::get(function () {
            $s = $this->summary ?? [];
            $ator = $this->is_system ? 'Sistema' : 'Um usuário';

            return match ($this->type) {
                'emprestimo.criado' => "{$ator} emprestou “" . data_get($s, 'livro', 'um livro') . '”',
                'reserva.cancelada' => "{$ator} cancelou a reserva da " . data_get($s, 'sala', 'sala')
                    . ' (' . data_get($s, 'data', '?') . ', '
                    . data_get($s, 'hora_inicio', '?') . '–' . data_get($s, 'hora_fim', '?') . ')',
                'sala.expirada'     => 'A sala “' . data_get($s, 'nome', '?') . '” expirou',
                default             => str_replace('.', ' · ', $this->type),
            };
        });
    }
}