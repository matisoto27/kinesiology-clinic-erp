<?php

namespace App\Enums;

enum RubroHorasProfesional: string
{
    case Gimnasio = 'gimnasio';
    case Pilates = 'pilates';
    case Kinesiologia = 'kinesiologia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Gimnasio => 'Gimnasio',
            self::Pilates => 'Pilates',
            self::Kinesiologia => 'Kinesiología',
        };
    }
}
