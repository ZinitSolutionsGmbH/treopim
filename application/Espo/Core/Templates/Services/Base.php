<?php
/**
 * This file is part of EspoCRM and/or TreoPIM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2018 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * TreoPIM is EspoCRM-based Open Source Product Information Management application.
 * Copyright (C) 2017-2018 Zinit Solutions GmbH
 * Website: http://www.treopim.com
 *
 * TreoPIM as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * TreoPIM as well as EspoCRM is distributed in the hope that it will be useful,
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
 * and "TreoPIM" word.
 */

namespace Espo\Core\Templates\Services;


class Base extends \Espo\Services\Record
{
    /**
     * @todo treoinject
     *
     * Mass update action
     *
     * @param       $data
     * @param array $params
     *
     * @return array
     */
    public function massUpdate($data, array $params)
    {
        // prepare where
        $where = [];
        if (array_key_exists('ids', $params) && is_array($params['ids'])) {
            $values = [];
            foreach ($params['ids'] as $id) {
                $values[] = [
                    'type'      => 'equals',
                    'attribute' => 'id',
                    'value'     => $id
                ];
            }
            $where[] = [
                'type'  => 'or',
                'value' => $values
            ];
        } elseif (array_key_exists('where', $params)) {
            $where = $params['where'];
        }


        // filter input
        $this->filterInput($data);

        // prepare select params
        $p['where'] = $where;
        if (!empty($params['selectData']) && is_array($params['selectData'])) {
            foreach ($params['selectData'] as $k => $v) {
                $p[$k] = $v;
            }
        }
        $selectParams = $this->getSelectParams($p);

        // get collection
        $collection = $this->getRepository()->find($selectParams);

        // prepare count
        $count = count($collection);

        if ($count > 0) {
            // prepare max
            $max = $this->getConfig()->get('modules.massUpdateMax.default');
            if (!empty($this->getConfig()->get('modules.massUpdateMax.' . $this->entityType))) {
                $max = $this->getConfig()->get('modules.massUpdateMax.' . $this->entityType);
            }

            if ($count < $max) {
                $this->massUpdateIteration($collection, $data);
            } else {
                $this
                    ->getServiceFactory()
                    ->create('MassUpdateProgressManager')
                    ->push(
                        [
                            'entityType'   => $this->entityType,
                            'selectParams' => $selectParams,
                            'data'         => $data
                        ]
                    );
            }
        }

        return [
            'count' => $count
        ];
    }

    /**
     * @todo treoinject
     *
     * MassUpdate iteration
     *
     * @param array $collection
     * @param array $data
     */
    public function massUpdateIteration($collection, $data): void
    {
        $idsUpdated = [];
        foreach ($collection as $entity) {
            if ($this->getAcl()->check($entity, 'edit') && $this->checkEntityForMassUpdate($entity, $data)) {
                $entity->set($data);
                if ($this->checkAssignment($entity)) {
                    if ($this->getRepository()->save($entity)) {
                        $idsUpdated[] = $entity->id;

                        $this->processActionHistoryRecord('update', $entity);
                    }
                }
            }
        }

        // call after mass update action
        $this->afterMassUpdate($idsUpdated, $data);
    }
}

