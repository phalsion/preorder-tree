<?php

namespace PreOrderTree;

/**
 * Class PreOrderTree
 *
 * @author  liqi created_at 2017/8/12下午2:02
 * @package \PreOrderTree
 */
class PreOrderTree extends Tree
{

    /**
     * 更换子树的父节点
     *
     * @param $entity
     * @param $parent_entity
     *
     * @return bool
     */
    public function changeParent( $entity, $parent_entity )
    {
        $this->unlink($entity);
        $this->addSubTree($parent_entity->refresh(), $entity->refresh());

        return true;
    }
}
