<?php

use Directus\Application\Application;
use Directus\Authentication\Provider;
use Directus\Database\Schema\SchemaManager;
use Directus\Hook\Payload;
use Directus\Mail\Message;
use Directus\Permissions\Acl;
use Directus\Services\ItemsService;
use function Directus\get_project_info;
use function Directus\send_mail_with_template;

return [
    'actions' => [
        'item.create.directus_users:after' => function ($data) { // send account registration mail
            $app = Application::getInstance();

            /** @var Acl $acl */
            $acl = $app->fromContainer('acl');

            if ($acl->isPublic()) {
                /** @var Provider $auth */
                $container = Application::getInstance()->getContainer();
                $items = new ItemsService($container);
                $adminUser = $items->findByIds(SchemaManager::COLLECTION_USERS, 1, [], false)['data'];

                $email = $adminUser['email'];
                $userAddress = $data['email'];
                $data = [
                    'info' => get_project_info()
                ];

                send_mail_with_template('user-invitation.twig', $data, function (Message $message) use ($email, $userAddress) {
                    $message->setSubject(
                        sprintf('Confirm user: %s', $userAddress)
                    );
                    $message->setTo($email);
                });
            }
        }
    ],
    'filters' => [
        'item.create.directus_users:before' => function (Payload $payload) { // set default role to "User"
            $app = Application::getInstance();

            /** @var Acl $acl */
            $acl = $app->fromContainer('acl');

            $container = Application::getInstance()->getContainer();

            if ($acl->isPublic()) {
                $itemsService = new ItemsService($container);

                $item = $itemsService->findOne(
                    'directus_roles',
                    [
                        'filter' => [
                            'name' => 'User'
                        ]
                    ]
                );

                $payload->set('role', $item['data']['id']);
            }

            return $payload;
        }
    ]
];