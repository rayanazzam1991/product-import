<?php

namespace App\Contracts;

interface WebhookNotification
{
    public function notify(): void;
}
