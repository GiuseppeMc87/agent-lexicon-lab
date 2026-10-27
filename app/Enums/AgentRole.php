<?php

namespace App\Enums;

enum AgentRole: string
{
    case Sender = 'sender';
    case Receiver = 'receiver';
}
