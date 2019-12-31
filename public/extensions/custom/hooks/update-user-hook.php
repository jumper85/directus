<?php

use Directus\Application\Application;
use Directus\Hook\Payload;
use Directus\Permissions\Acl;
use Directus\Permissions\Exception\ForbiddenCollectionAlterException;

return [
    'filters' => [
        'item.update.directus_users:before' => function (Payload $payload) { // prevent normal user from updating role to admin
            $app = Application::getInstance();

            /** @var Acl $acl */
            $acl = $app->fromContainer('acl');

            if (!$acl->isAdmin() && $payload->has('role')) {
                throw new ForbiddenCollectionAlterException('Users - Role');
            }

            return $payload;
        }
    ]
];