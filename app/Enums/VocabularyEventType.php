<?php

namespace App\Enums;

enum VocabularyEventType: string
{
    case Created = 'created';
    case Revised = 'revised';
}
