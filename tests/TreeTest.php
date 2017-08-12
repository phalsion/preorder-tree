<?php
/**
 * Created by PhpStorm.
 * User: liqi
 * Date: 2017/8/12
 * Time: 下午2:24
 */

namespace Tests;


use Phalcon\Di;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\Framework\TestCase;
use Resource\TestTree;

class TreeTest extends TestCase
{
    use TestCaseTrait;

    const TABLE_NAME = 'test_tree';

    // only instantiate pdo once for test clean-up/fixture load
    static private $pdo = null;

    // only instantiate PHPUnit_Extensions_Database_DB_IDatabaseConnection once per test
    private $conn = null;

    final public function getConnection()
    {
        if ( $this->conn === null ) {
            if ( self::$pdo == null ) {
                self::$pdo = new \PDO($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo, $GLOBALS['DB_DBNAME']);
        }

        return $this->conn;
    }


    public static function setUpBeforeClass()
    {
        $pdo = new \PDO($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);

        $pdo->exec("
        DROP TABLE IF EXISTS `test_tree`;
        CREATE TABLE test_tree
                (
                    id INT AUTO_INCREMENT
                        PRIMARY KEY,
                    name VARCHAR(255) NULL,
                    tree_lft INT NULL,
                    tree_rht INT NULL,
                    tree_root INT NULL,
                    tree_lv INT NULL,
                    tree_pid INT NULL,
                    CONSTRAINT test_tree_id_uindex
                        UNIQUE (id)
                )
                ;
        ");
        parent::setUpBeforeClass();
    }

    /**
     * @param string $name
     *
     * @return \Tests\PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    public function getDataSet( $name = 'empty' )
    {
        return $this->createFlatXMLDataSet(dirname(__FILE__) . "/_files/$name.xml");
    }

    /**
     * @var \PreOrderTree\PreOrderTree $tree
     */
    protected $tree;

    public function setUp()
    {
        if ( !$this->tree )
            $this->tree = Di::getDefault()->getTree();
    }

    /**
     * @dataProvider nameRootDataProvider
     *
     * @param null $name
     *
     * @return \Resource\TestTree
     */
    public function testGenerateRoot( $name = null )
    {
        $node       = new TestTree();
        $node->name = $name;
        $node->save();
        $this->assertTrue($this->tree->generateRoot($node));
    }

    public function testAfterGenerateRoot()
    {
        $queryTable    = $this->getConnection()->createQueryTable(
            static::TABLE_NAME, 'SELECT * FROM ' . static::TABLE_NAME
        );
        $expectedTable = $this->getDataSet("generate")
            ->getTable(static::TABLE_NAME);
        $this->assertTablesEqual($expectedTable, $queryTable);
    }

    /**
     *
     * @dataProvider addNodeProvider
     *
     * @param  int    $root
     * @param  string $name
     *
     * @return \Resource\TestTree
     */
    public function testAddNode( $root, $name )
    {
        $root = TestTree::findFirst($root);

        $node       = new TestTree();
        $node->name = $name;
        $node->save();

        $this->assertTrue($this->tree->addNode($root, $node));
    }

    public function testAddSubTree()
    {
        /**
         * 将C1-1树 接到A2-2
         */
        $parent_entity = TestTree::findFirst(5);
        $sub_root      = TestTree::findFirst(3);

        $this->assertTrue($this->tree->addSubTree($parent_entity, $sub_root));
        $expect = $this->getDataSet('addSubTree')->getTable(static::TABLE_NAME);
        $actual = $this->getConnection()->createQueryTable(
            static::TABLE_NAME, 'SELECT * FROM ' . static::TABLE_NAME . ' WHERE tree_root = 1'
        );

        $this->assertTablesEqual($expect, $actual);
    }


    public function testUnlink()
    {
        /**
         * 将C1-1 与 A2-2分离
         */
        $sub_root = TestTree::findFirst(3);
        $this->assertTrue($this->tree->unlink($sub_root));

    }


    public function testChangeParent()
    {
        //将C2-1更换到A2-2
        $parent = TestTree::findFirst(5);
        $node   = TestTree::findFirst(10);
        $this->assertTrue($this->tree->changeParent($node, $parent));
        $expect = $this->getDataSet('changeParent')->getTable(static::TABLE_NAME);
        $actual = $this->getConnection()->createQueryTable(
            static::TABLE_NAME, 'SELECT * FROM ' . static::TABLE_NAME . ' WHERE tree_root IN (1,3) '
        );

        $this->assertTablesEqual($expect, $actual);
    }






//
//    public function testDeleteNode()
//    {
//        $node = TestTree::findFirst(14);
//        $this->assertTrue($this->tree->delete($node));
//    }


    public function nameRootDataProvider()
    {
        return [
            [ 'A1-1' ],
            [ 'B1-1' ],
            [ 'C1-1' ]
        ];
    }


    public function addNodeProvider()
    {

        return [
            [ 1, 'A2-1' ],
            [ 1, 'A2-2' ],
            [ 1, 'A2-3' ],
            [ 2, 'B2-1' ],
            [ 2, 'B2-2' ],
            [ 2, 'B2-3' ],
            [ 3, 'C2-1' ],
            [ 3, 'C2-2' ],
            [ 3, 'C2-3' ],
            [ 10, 'C3-1(C2-1)' ],
            [ 10, 'C3-2(C2-1)' ],
        ];
    }


}
