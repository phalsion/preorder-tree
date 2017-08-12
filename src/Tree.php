<?php

namespace PreOrderTree;


use Phalcon\Di\Injectable;

/**
 * Class Tree
 *
 * @author  liqi created_at 2017/8/12上午11:18
 * @package \PreOrderTree
 */
class Tree extends Injectable
{
    private $_primary;

    private $_tree_lft;

    private $_tree_rht;

    private $_tree_lv;

    private $_tree_pid;

    private $_tree_root;

    public function __construct( $columns = [] )
    {
        $this->_tree_lft  = $columns['tree_lft'] ?? 'tree_lft';
        $this->_tree_rht  = $columns['tree_rht'] ?? 'tree_rht';
        $this->_tree_lv   = $columns['tree_lv'] ?? 'tree_lv';
        $this->_tree_pid  = $columns['tree_pid'] ?? 'tree_pid';
        $this->_tree_root = $columns['tree_root'] ?? 'tree_root';
        $this->_primary   = $columns['id'] ?? 'id';
    }


    public function generateRoot( $entity )
    {
        $entity->{$this->_tree_lft}  = 1;
        $entity->{$this->_tree_rht}  = 2;
        $entity->{$this->_tree_root} = $entity->{$this->_primary};
        $entity->{$this->_tree_lv}   = 1;
        $entity->{$this->_tree_pid}  = 0;

        return $this->persist($entity);
    }

    /**
     * 添加子节点
     *
     * @param $parent_entity
     * @param $entity
     *
     * @return bool
     */
    public function addNode( $parent_entity, $entity )
    {
        $value = (int) $parent_entity->{$this->_tree_rht};
        //偏移父节点之后的节点的左值和右值
        //为新节点预留左值和右值
        $this->offset($parent_entity, '+2');

        //配置新节点的树结构参数
        $entity->{$this->_tree_lft}  = $value;
        $entity->{$this->_tree_rht}  = $value + 1;
        $entity->{$this->_tree_root} = $parent_entity->{$this->_tree_root};
        $entity->{$this->_tree_lv}   = $parent_entity->{$this->_tree_lv} + 1;
        $entity->{$this->_tree_pid}  = $parent_entity->{$this->_primary};

        //保存树节点
        $this->persist($entity);

        return true;
    }

    /**
     * 添加子树
     *
     * @param \Phalcon\Mvc\Model $parent_entity 要添加的子树父节点
     * @param \Phalcon\Mvc\Model $sub_root      子树根节点
     *
     * @return bool
     */
    public function addSubTree( $parent_entity, $sub_root )
    {
        $offset = $this->getOffset($sub_root);
        //为子树预留左右值的空间
        $this->offset($parent_entity, sprintf("%+d", $offset));

        $sub_offset   = $parent_entity->{$this->_tree_rht} - $sub_root->{$this->_tree_lft};
        $level_offset = $parent_entity->{$this->_tree_lv} - $sub_root->{$this->_tree_lv} + 1;
        //偏移子树左右值以适配父级节点所在树结构
        $this->offsetSubTree($sub_root, sprintf("%+d", $sub_offset));

        //将子树合并进父级树结构
        $this->setRoot($sub_root->refresh(), $parent_entity->{$this->_tree_root}, sprintf("%+d", $level_offset));

        $sub_root                     = $sub_root->refresh();
        $sub_root->{$this->_tree_pid} = $parent_entity->{$this->_primary};

        $this->persist($sub_root);

        return true;
    }


    //删除子节点
    public function delete( $entity )
    {
        if ( 1 < $entity->{$this->_tree_rht} - $entity->{$this->_tree_lft} ) {
            //该节点下还有其他节点需要点用删除子树节点进行删除
            return $this->error($entity);
        }

        //解除与树的关联
        $this->unlink($entity);

        //删除
        if ( false === $entity->delete() ) {
            $this->error($entity);
        }

        return true;
    }


    /**
     * 保存实体
     *
     * @param $entity
     *
     * @return bool
     * @throws \PreOrderTree\PreOrderTreeException
     */
    protected function persist( $entity )
    {
        if ( false === $entity->save($entity) ) {
            return $this->error($entity);
        }

        return true;
    }

    /**
     * 偏移位于树结构的指定实体之后的左右值
     *
     * @param $entity
     * @param $offset
     *
     * @return bool|void
     * @throws \PreOrderTree\PreOrderTreeException
     */
    protected function offset( $entity, $offset )
    {
        //偏移指定节点之后的所有节点的左右值
        $model_name = get_class($entity);

        /**
         * @var int $value 考虑到指定的节点可能含有子树节点所以只拿节点的右值作为参考
         */
        $value = (int) $entity->{$this->_tree_rht};
        $root  = (int) $entity->{$this->_tree_root};

        /**
         * @var string $update_left_query 修改位于该节点后所有的左值
         */
        $update_left_query = "UPDATE
                              $model_name 
                              SET 
                              {$this->_tree_lft}={$this->_tree_lft}$offset
                              WHERE
                              {$this->_tree_root}=$root 
                              AND 
                              {$this->_tree_lft}>$value";
        $r                 = $this->modelsManager->executeQuery($update_left_query);
        if ( false === $r->success() ) {
            return $this->error($r);
        }

        $update_rht_query = "UPDATE 
                             $model_name 
                             SET 
                             {$this->_tree_rht}={$this->_tree_rht}$offset 
                             WHERE 
                             {$this->_tree_root}=$root 
                             AND 
                             {$this->_tree_rht}>=$value";
        $r                = $this->modelsManager->executeQuery($update_rht_query);
        if ( false === $r->success() ) {
            return $this->error($r);
        }

        return true;
    }

    /**
     * 偏移子树所有的左右值
     *
     * @param $entity 子树根节点
     * @param $offset 偏移量
     *
     * @param $level_offset
     *
     * @return bool|void
     */
    protected function offsetSubTree( $entity, $offset )
    {
        $model_name = get_class($entity);

        /**
         * @var int $value 考虑到指定的节点可能含有子树节点所以只拿节点的右值作为参考
         */
        $left_value  = (int) $entity->{$this->_tree_lft};
        $right_value = (int) $entity->{$this->_tree_rht};
        $root        = (int) $entity->{$this->_tree_root};

        /**
         * @var string $update_left_query 修改位于该节点后所有的左值
         */
        $update_query = "UPDATE 
                              $model_name 
                              SET 
                              {$this->_tree_lft}={$this->_tree_lft}$offset,
                              {$this->_tree_rht}={$this->_tree_rht}$offset
                              WHERE 
                              {$this->_tree_root}=$root 
                              AND 
                              {$this->_tree_lft}>=$left_value 
                              AND
                              {$this->_tree_rht}<=$right_value";
        $r            = $this->modelsManager->executeQuery($update_query);
        if ( false === $r->success() ) {
            return $this->error($r);
        }

        return true;
    }


    //解除树节点的关联
    public function unlink( $entity )
    {
        $offset = $this->getOffset($entity);
        $this->setRoot($entity, null, sprintf("%+d", 1 - $entity->{$this->_tree_lv}));
        $this->offset($entity, "-$offset");
        $entity                     = $entity->refresh();
        $entity->{$this->_tree_pid} = 0;

        $this->persist($entity);

        return true;
    }

    /**
     * 合并2个树结构
     *
     * @param        $entity
     * @param null   $root
     * @param string $level_offset
     */
    protected function setRoot( $entity, $root = null, $level_offset = '+0' )
    {
        $model_name = get_class($entity);
        $left       = $entity->{$this->_tree_lft};
        $right      = $entity->{$this->_tree_rht};
        $orign_root = $entity->{$this->_tree_root};
        if ( null == $root ) {
            $root = $entity->{$this->_primary};
        }
        $set_root_query = "UPDATE
                           $model_name 
                           SET 
                           {$this->_tree_root}=$root,
                           {$this->_tree_lv}={$this->_tree_lv}$level_offset
                           WHERE 
                           {$this->_tree_root}=$orign_root 
                           AND 
                           {$this->_tree_lft}>=$left 
                           AND 
                           {$this->_tree_rht}<=$right";
        $r = $this->modelsManager->executeQuery($set_root_query);
        if ( false === $r->success() ) {
            return $this->error($r);
        }
    }

    /**
     * 抛出异常
     *
     * @param $entity
     *
     * @throws \PreOrderTree\PreOrderTreeException
     */
    protected function error( $entity )
    {
        throw new PreOrderTreeException(
            current($entity->getMessages())->getMessage()
        );
    }

    /**
     * 计算偏移量
     *
     * @param $entity
     *
     * @return mixed
     */
    protected function getOffset( $entity )
    {
        return $entity->{$this->_tree_rht} - $entity->{$this->_tree_lft} + 1;
    }

}
