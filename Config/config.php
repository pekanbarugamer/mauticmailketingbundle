<?php

return [
    'name'        => 'Mailketing integration',
    'description' => 'Allows to send E-mails with Mailketing',
    'version'     => '1.0',
    'author'      => 'Fadli Dzil Ikram',
    'services'    => [
        'other' => [
            'mautic.transport.mailketing_api' => [
                'class' => \MauticPlugin\MauticMailketingBundle\Swiftmailer\Transport\MailketingApiTransport::class,
                'arguments' => [
                    '%mautic.mailer_api_key%',
                    'translator',
                    'mautic.transport.mailketing_api.callback',
                ],
                'tags' => [
                    'mautic.email_transport',
                ],
                'tagArguments' => [
                    [
                        'transport_alias' => 'mautic.email.config.mailer_transport.mailketing',
                        'field_api_key' => true,
                    ],
                ],
            ],
            'mautic.transport.mailketing_api.callback' => [
                'class' => \MauticPlugin\MauticMailketingBundle\Swiftmailer\Callback\MailketingApiCallback::class,
                'arguments' => [
                    'mautic.email.model.transport_callback',
                    'monolog.logger.mautic',
                ],
            ],
        ],
    ],
];
