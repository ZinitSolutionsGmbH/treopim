<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2018 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
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
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\ORM\Repositories;

use \Espo\ORM\EntityManager;
use \Espo\ORM\EntityFactory;
use \Espo\ORM\EntityCollection;
use \Espo\ORM\Entity;
use \Espo\ORM\IEntity;
use Espo\Core\Exceptions\Forbidden;
use Symfony\Component\Workflow\Exception\LogicException;


class RDB extends \Espo\ORM\Repository
{
    /**
     * @var Object Mapper.
     */
    protected $mapper;

    /**
     * @var array Where clause array. To be used in further find operation.
     */
    protected $whereClause = array();

    /**
     * @var array Having clause array.
     */
    protected $havingClause = array();

    /**
     * @var array Parameters to be used in further find operations.
     */
    protected $listParams = array();

    public function __construct($entityType, EntityManager $entityManager, EntityFactory $entityFactory)
    {
        $this->entityType = $entityType;
        $this->entityName = $entityType;

        $this->entityFactory = $entityFactory;
        $this->seed = $this->entityFactory->create($entityType);
        $this->entityClassName = get_class($this->seed);
        $this->entityManager = $entityManager;
    }

    protected function getMapper()
    {
        if (empty($this->mapper)) {
            $this->mapper = $this->getEntityManager()->getMapper('RDB');
        }
        return $this->mapper;
    }

    public function handleSelectParams(&$params)
    {
    }

    protected function getEntityFactory()
    {
        return $this->entityFactory;
    }

    protected function getEntityManager()
    {
        return $this->entityManager;
    }

    public function reset()
    {
        $this->whereClause = array();
        $this->havingClause = array();
        $this->listParams = array();
    }

    protected function getNewEntity()
    {
        $entity = $this->entityFactory->create($this->entityType);
        if ($entity) {
            $entity->setIsNew(true);
            $entity->populateDefaults();
            return $entity;
        }
    }

    protected function getEntityById($id)
    {
        $entity = $this->entityFactory->create($this->entityType);

        if (!$entity) return null;

        $params = array();
        $this->handleSelectParams($params);
        if ($this->getMapper()->selectById($entity, $id, $params)) {
            return $entity;
        }

        return null;
    }

    public function get($id = null)
    {
        if (empty($id)) {
            return $this->getNewEntity();
        }
        return $this->getEntityById($id);
    }

    protected function beforeSave(Entity $entity, array $options = array())
    {
    }

    protected function afterSave(Entity $entity, array $options = array())
    {
    }

    public function save(Entity $entity, array $options = array())
    {
        $entity->setAsBeingSaved();

        // check workflow init states if it needs
        $this->workflowInitStates($entity);

        // run workflow method "can()" if it needs
        $this->workflowCan($entity);

        if (empty($options['skipBeforeSave']) && empty($options['skipAll'])) {
            $this->beforeSave($entity, $options);
        }
        if ($entity->isNew() && !$entity->isSaved()) {
            $result = $this->getMapper()->insert($entity);
        } else {
            $result = $this->getMapper()->update($entity);
        }
        if ($result) {
            $entity->setIsSaved(true);

            // run workflow method "apply()" if it needs
            $this->workflowApply($entity);

            if (empty($options['skipAfterSave']) && empty($options['skipAll'])) {
                $this->afterSave($entity, $options);
            }
            if ($entity->isNew()) {
                if (empty($options['keepNew'])) {
                    $entity->setIsNew(false);
                }
            } else {
                if ($entity->isFetched()) {
                    $entity->updateFetchedValues();
                }
            }
        }
        $entity->setAsNotBeingSaved();

        return $result;
    }

    protected function beforeRemove(Entity $entity, array $options = array())
    {
    }

    protected function afterRemove(Entity $entity, array $options = array())
    {
    }

    public function remove(Entity $entity, array $options = array())
    {
        $this->beforeRemove($entity, $options);
        $result = $this->getMapper()->delete($entity);
        if ($result) {
            $this->afterRemove($entity, $options);
        }
        return $result;
    }

    public function deleteFromDb($id, $onlyDeleted = false)
    {
        return $this->getMapper()->deleteFromDb($this->entityType, $id, $onlyDeleted);
    }

    public function find(array $params = array())
    {
        $params = $this->getSelectParams($params);

        if (empty($params['skipAdditionalSelectParams'])) {
            $this->handleSelectParams($params);
        }

        $dataArr = $this->getMapper()->select($this->seed, $params);

        $collection = new EntityCollection($dataArr, $this->entityType, $this->entityFactory);
        $collection->setAsFetched();

        $this->reset();

        return $collection;
    }

    /**
     * Remove collection of entities
     *
     * @param array $options
     */
    public function removeCollection(array $options = [])
    {
        // get collection
        $collection = $this->find();

        if (count($collection) > 0) {
            foreach ($collection as $item) {
                $this->remove($item, $options);
            }
        }
    }

    public function findOne(array $params = array())
    {
        $collection = $this->limit(0, 1)->find($params);
        if (count($collection)) {
            return $collection[0];
        }
        return null;
    }

    public function findByQuery($sql)
    {
        $dataArr = $this->getMapper()->selectByQuery($this->seed, $sql);

        $collection = new EntityCollection($dataArr, $this->entityType, $this->entityFactory);
        $this->reset();

        return $collection;
    }

    public function findRelated(Entity $entity, $relationName, array $params = array())
    {
        if (!$entity->id) {
            return [];
        }

        if ($entity->getRelationType($relationName) === Entity::BELONGS_TO_PARENT) {
            $entityType = $entity->get($relationName . 'Type');
        } else {
            $entityType = $entity->getRelationParam($relationName, 'entity');
        }

        if ($entityType) {
            if (empty($params['skipAdditionalSelectParams'])) {
                $this->getEntityManager()->getRepository($entityType)->handleSelectParams($params);
            }
        }

        $result = $this->getMapper()->selectRelated($entity, $relationName, $params);

        if (is_array($result)) {
            $collection = new EntityCollection($result, $entityType, $this->entityFactory);
            $collection->setAsFetched();
            return $collection;
        } else if ($result instanceof EntityCollection) {
            $collection = $result;
            return $collection;
        } else if ($result instanceof Entity) {
            $entity = $result;
            return $entity;
        } else {
            return $result;
        }
    }

    public function countRelated(Entity $entity, $relationName, array $params = array())
    {
        if (!$entity->id) {
            return;
        }
        $entityType = $entity->relations[$relationName]['entity'];
        if (empty($params['skipAdditionalSelectParams'])) {
            $this->getEntityManager()->getRepository($entityType)->handleSelectParams($params);
        }

        return intval($this->getMapper()->countRelated($entity, $relationName, $params));
    }

    public function isRelated(Entity $entity, $relationName, $foreign)
    {
        if (!$entity->id) {
            return;
        }

        if ($foreign instanceof Entity) {
            $id = $foreign->id;
        } else if (is_string($foreign)) {
            $id = $foreign;
        } else {
            return;
        }

        if (!$id) return;

        return !!$this->countRelated($entity, $relationName, array(
            'whereClause' => array(
                'id' => $id
            )
        ));
    }

    public function relate(Entity $entity, $relationName, $foreign, $data = null, array $options = array())
    {
        if (!$entity->id) {
            return;
        }

        $this->beforeRelate($entity, $relationName, $foreign, $data, $options);
        $beforeMethodName = 'beforeRelate' . ucfirst($relationName);
        if (method_exists($this, $beforeMethodName)) {
            $this->$beforeMethodName($entity, $foreign, $data, $options);
        }

        $result = false;
        $methodName = 'relate' . ucfirst($relationName);
        if (method_exists($this, $methodName)) {
            $result = $this->$methodName($entity, $foreign, $data, $options);
        } else {
            $d = $data;
            if ($d instanceof \stdClass) {
                $d = get_object_vars($d);
            }
            if ($foreign instanceof Entity) {
                $result = $this->getMapper()->relate($entity, $relationName, $foreign, $d);
            }
            if (is_string($foreign)) {
                $result = $this->getMapper()->addRelation($entity, $relationName, $foreign, null, $d);
            }
        }

        if ($result) {
            $this->afterRelate($entity, $relationName, $foreign, $data, $options);
            $afterMethodName = 'afterRelate' . ucfirst($relationName);
            if (method_exists($this, $afterMethodName)) {
                $this->$afterMethodName($entity, $foreign, $data, $options);
            }
        }

        return $result;
    }


    public function unrelate(Entity $entity, $relationName, $foreign, array $options = array())
    {
        if (!$entity->id) {
            return;
        }

        $this->beforeUnrelate($entity, $relationName, $foreign, $options);
        $beforeMethodName = 'beforeUnrelate' . ucfirst($relationName);
        if (method_exists($this, $beforeMethodName)) {
            $this->$beforeMethodName($entity, $foreign, $options);
        }

        $result = false;
        $methodName = 'unrelate' . ucfirst($relationName);
        if (method_exists($this, $methodName)) {
            $result = $this->$methodName($entity, $foreign);
        } else {
            if ($foreign instanceof Entity) {
                $result = $this->getMapper()->unrelate($entity, $relationName, $foreign);
            }
            if (is_string($foreign)) {
                $result = $this->getMapper()->removeRelation($entity, $relationName, $foreign);
            }
            if ($foreign === true) {
                $result = $this->getMapper()->removeAllRelations($entity, $relationName);
            }
        }

        if ($result) {
            $this->afterUnrelate($entity, $relationName, $foreign, $options);
            $afterMethodName = 'afterUnrelate' . ucfirst($relationName);
            if (method_exists($this, $afterMethodName)) {
                $this->$afterMethodName($entity, $foreign, $options);
            }
        }

        return $result;
    }

    protected function beforeRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = array())
    {
    }

    protected function afterRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = array())
    {
    }

    protected function beforeUnrelate(Entity $entity, $relationName, $foreign, array $options = array())
    {
    }

    protected function afterUnrelate(Entity $entity, $relationName, $foreign, array $options = array())
    {
    }

    protected function beforeMassRelate(Entity $entity, $relationName, array $params = array(), array $options = array())
    {
    }

    protected function afterMassRelate(Entity $entity, $relationName, array $params = array(), array $options = array())
    {
    }

    public function updateRelation(Entity $entity, $relationName, $foreign, $data)
    {
        if (!$entity->id) {
            return;
        }
        if ($data instanceof \stdClass) {
            $data = get_object_vars($data);
        }
        if ($foreign instanceof Entity) {
            $id = $foreign->id;
        } else {
            $id = $foreign;
        }
        if (is_string($foreign)) {
            return $this->getMapper()->updateRelation($entity, $relationName, $id, $data);
        }
        return null;
    }

    public function massRelate(Entity $entity, $relationName, array $params = array(), array $options = array())
    {
        if (!$entity->id) {
            return;
        }
        $this->beforeMassRelate($entity, $relationName, $params, $options);

        $result = $this->getMapper()->massRelate($entity, $relationName, $params);
        if ($result) {
            $this->afterMassRelate($entity, $relationName, $params, $options);
        }
        return $result;
    }

    public function getAll()
    {
        $this->reset();
        return $this->find();
    }

    public function count(array $params = array())
    {
        if (empty($params['skipAdditionalSelectParams'])) {
            $this->handleSelectParams($params);
        }

        $params = $this->getSelectParams($params);
        $count = $this->getMapper()->count($this->seed, $params);
        $this->reset();
        return intval($count);
    }

    public function max($field)
    {
        $params = $this->getSelectParams();
        return $this->getMapper()->max($this->seed, $params, $field);
    }

    public function min($field)
    {
        $params = $this->getSelectParams();
        return $this->getMapper()->min($this->seed, $params, $field);
    }

    public function sum($field)
    {
        $params = $this->getSelectParams();
        return $this->getMapper()->sum($this->seed, $params, $field);
    }

    public function join()
    {
        $args = func_get_args();

        if (empty($this->listParams['joins'])) {
            $this->listParams['joins'] = [];
        }

        foreach ($args as &$param) {
            if (is_array($param)) {
                foreach ($param as $k => $v) {
                    $this->listParams['joins'][] = $v;
                }
            } else {
                $this->listParams['joins'][] = $param;
            }
        }

        return $this;
    }

    public function leftJoin()
    {
        $args = func_get_args();

        if (empty($this->listParams['leftJoins'])) {
            $this->listParams['leftJoins'] = [];
        }

        foreach ($args as &$param) {
            if (is_array($param)) {
                foreach ($param as $k => $v) {
                    $this->listParams['leftJoins'][] = $v;
                }
            } else {
                $this->listParams['leftJoins'][] = $param;
            }
        }

        return $this;
    }

    public function distinct()
    {
        $this->listParams['distinct'] = true;
        return $this;
    }

    public function where($param1 = array(), $param2 = null)
    {
        if (is_array($param1)) {
            $this->whereClause = $param1 + $this->whereClause;

        } else {
            if (!is_null($param2)) {
                $this->whereClause[$param1] = $param2;
            }
        }

        return $this;
    }

    public function having($param1 = array(), $param2 = null)
    {
        if (is_array($param1)) {
            $this->havingClause = $param1 + $this->havingClause;
        } else {
            if (!is_null($param2)) {
                $this->havingClause[$param1] = $param2;
            }
        }

        return $this;
    }

    public function order($field = 'id', $direction = "ASC")
    {
        $this->listParams['orderBy'] = $field;
        $this->listParams['order'] = $direction;

        return $this;
    }

    public function limit($offset, $limit)
    {
        $this->listParams['offset'] = $offset;
        $this->listParams['limit'] = $limit;

        return $this;
    }

    public function select($select)
    {
        $this->listParams['select'] = $select;
        return $this;
    }

    public function groupBy($groupBy)
    {
        $this->listParams['groupBy'] = $groupBy;
        return $this;
    }

    public function setListParams(array $params = array())
    {
        $this->listParams = $params;
    }

    public function getListParams()
    {
        return $this->listParams;
    }

    protected function getSelectParams(array $params = array())
    {
        if (isset($params['whereClause'])) {
            $params['whereClause'] = $params['whereClause'];
            if (!empty($this->whereClause)) {
                $params['whereClause'][] = $this->whereClause;
            }
        } else {
            $params['whereClause'] = $this->whereClause;
        }
        if (!empty($params['havingClause'])) {
            $params['havingClause'] = $params['havingClause'];
            if (!empty($this->havingClause)) {
                $params['havingClause'][] = $this->havingClause;
            }
        } else {
            $params['havingClause'] = $this->havingClause;
        }

        if (!empty($params['leftJoins']) && !empty($this->listParams['leftJoins'])) {
            foreach ($this->listParams['leftJoins'] as $j) {
                $params['leftJoins'][] = $j;
            }
        }

        if (!empty($params['joins']) && !empty($this->listParams['joins'])) {
            foreach ($this->listParams['joins'] as $j) {
                $params['joins'][] = $j;
            }
        }

        $params = array_replace_recursive($this->listParams, $params);

        return $params;
    }

    protected function getPDO()
    {
        return $this->getEntityManager()->getPDO();
    }

    /**
     * Check workflow init states if it needs
     *
     * @param Entity $entity
     *
     * @throws Forbidden
     */
    protected function workflowInitStates(Entity $entity): void
    {
        // workflow init states check only for new items
        if ($entity->isNew()) {

            // get workflow settings
            $workflowSettings = $this->getEntityManager()->getEspoMetadata()->get(['workflow', $entity->getEntityType()], []);

            if (!empty($workflowSettings)) {
                foreach ($workflowSettings as $field => $settings) {
                    if (!empty($settings['initStates']) && !in_array($entity->get($field), $settings['initStates'])) {
                        throw new Forbidden(sprintf(
                            'Init state "%s" is not defined for workflow "%s".',
                            $entity->get($field),
                            $entity->getEntityType() . '_' . $field));
                    }
                }
            }
        }
    }

    /**
     * Run workflow method "can()" if it needs
     *
     * @param Entity $to
     *
     * @throws Forbidden
     */
    protected function workflowCan(Entity $to): void
    {
        // prepare name
        $name = $to->getEntityType();

        // get workflow settings
        $workflowSettings = $this->getEntityManager()->getEspoMetadata()->get(['workflow', $name], []);

        // make clone of Entity for "from" place
        $from = clone $to;

        if (!empty($workflowSettings)) {
            foreach ($workflowSettings as $field => $settings) {
                if ($to->isNew()) {
                    // set value for "from" place
                    $from->set([$field => $to->get($field)]);
                } else {
                    // set fetched value for "from" place
                    $from->set([$field => $to->getFetched($field)]);
                }

                if ($to->isNew() || $from->get($field) != $to->get($field)) {
                    try {
                        $can = $this
                            ->getInjection('workflow')
                            ->get($from, $name . '_' . $field)
                            ->can($from, $from->get($field) . '_' . $to->get($field));
                    } catch (LogicException $e) {
                        throw new Forbidden($e->getMessage());
                    }

                    if (!$can) {
                        throw new Forbidden(sprintf(
                            'Transition "%s" is not defined for workflow "%s".',
                            $from->get($field) . '_' . $to->get($field),
                            $name . '_' . $field));
                    }
                }
            }
        }
    }

    /**
     * Run workflow method "apply()" if it needs
     *
     * @param Entity $to
     *
     * @throws Forbidden
     */
    protected function workflowApply(Entity $to): void
    {
        // prepare name
        $name = $to->getEntityType();

        // get workflow settings
        $workflowSettings = $this->getEntityManager()->getEspoMetadata()->get(['workflow', $name], []);

        // make clone of Entity for "from" place
        $from = clone $to;

        if (!empty($workflowSettings)) {
            foreach ($workflowSettings as $field => $settings) {
                if ($to->isNew()) {
                    // set value for "from" place
                    $from->set([$field => $to->get($field)]);
                } else {
                    // set fetched value for "from" place
                    $from->set([$field => $to->getFetched($field)]);
                }

                if ($to->isNew() || $from->get($field) != $to->get($field)) {
                    try {
                        $this
                            ->getInjection('workflow')
                            ->get($from, $name . '_' . $field)
                            ->apply($from, $from->get($field) . '_' . $to->get($field));
                    } catch (LogicException $e) {
                        throw new Forbidden($e->getMessage());
                    }
                }
            }
        }
    }
}
