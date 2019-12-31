<?php

use Directus\Application\Application;
use Directus\Hook\Payload;
use Directus\Permissions\Acl;

return [
    'filters' => [
        'item.read.directus_users' => function (Payload $payload) { // filter all fields for other users
            $app = Application::getInstance();

            /** @var Acl $acl */
            $acl = $app->fromContainer('acl');

            if (!$acl->isPublic()) {
                $users = $payload->getData();

                $currentUserId = $acl->getUserId();

                foreach ($users as $index => $user) {
                    if ((int)$currentUserId !== (int)$user['id']) {
                        $users[$index] = [
                            'id' => $user['id'],
                            'last_name' => $user['last_name'],
                        ];
                    }
                }

                $payload->replace($users);
            }

            return $payload;
        }
    ]
];