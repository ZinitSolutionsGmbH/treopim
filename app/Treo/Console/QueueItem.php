<?php
/**
 * This file is part of EspoCRM and/or TreoCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * TreoCore is EspoCRM-based Open Source application.
 * Copyright (C) 2017-2019 TreoLabs GmbH
 * Website: https://treolabs.com
 *
 * TreoCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * TreoCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "TreoCore" word.
 */

declare(strict_types=1);

namespace Treo\Console;

use Espo\ORM\EntityManager;

/**
 * Class QueueItem
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class QueueItem extends AbstractConsole
{
    /**
     * @inheritdoc
     */
    public static function getDescription(): string
    {
        return 'Run Queue Manager job item.';
    }

    /**
     * @inheritdoc
     */
    public function run(array $data): void
    {
        if (empty($this->getConfig()->get('isInstalled'))) {
            exit(1);
        }

        /** @var EntityManager $entityManager */
        $entityManager = $this->getContainer()->get('entityManager');

        // get item
        $item = $entityManager->getEntity('QueueItem', $data['id']);
        if (empty($item)) {
            self::show('No such queue item!', self::ERROR, true);
        }

        // set user
        $this->getContainer()->setUser($item->get('createdBy'));
        $entityManager->setUser($item->get('createdBy'));

        // create service
        $service = $this->getContainer()->get('serviceFactory')->create($item->get('serviceName'));

        // get data
        $data = json_decode(json_encode($item->get('data')), true);

        // run
        $service->run($data);

        self::show('Queue Manager item ran!', self::SUCCESS, true);
    }
}
