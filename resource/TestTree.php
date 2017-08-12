<?php

namespace Resource;


use Phalcon\Mvc\Model;

/**
 * Class TestTree
 *
 * @author  liqi created_at 2017/8/12上午11:27
 * @package \Resource
 */
class TestTree extends Model
{
    public $tree_pid;

    public $id;

    public $name;

    public $tree_lft;

    public $tree_rht;

    public $tree_root;

    public $tree_lv;

    public function initialize()
    {
        $this->setSource('test_tree');
    }
}
