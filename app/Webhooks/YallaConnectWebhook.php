<?php

namespace App\Webhooks;

use App\Contracts\WebhookNotification;

class YallaConnectWebhook implements WebhookNotification
{
    public function notify(): void
    {

        usleep(2 * 1000 * 1000);

        // TODO implement the real communication
        //        WebhookCall::create()
        //            ->url('https://yalla-connect.com/webhooks')
        //            ->payload(['key' => 'value'])
        //            ->useSecret('sign-using-this-secret')
        //            ->dispatch();

    }
}
