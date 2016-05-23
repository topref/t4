<?php

namespace T4\Tests\Orm\Relations;

use T4\Core\Collection;
use T4\Dbal\QueryBuilder;
use T4\Orm\Model;

require_once realpath(__DIR__ . '/../../../framework/boot.php');

class Category extends Model {
    protected static $schema = [
        'table' => 'cats',
        'columns' => ['num' => ['type' => 'int']],
        'relations' => [
            'items' => ['type' => self::HAS_MANY, 'model' => Item::class]
        ]
    ];
}

class Item extends Model {
    protected static $schema = [
        'table' => 'items',
        'columns' => ['num' => ['type' => 'int']],
        'relations' => [
            'category' => ['type' => self::BELONGS_TO, 'model' => Category::class]
        ]
    ];
}

class HasManySaveTest
    extends BaseTest
{

    protected function setUp()
    {
        $this->getT4Connection()->execute('CREATE TABLE cats (__id SERIAL, num INT)');
        $this->getT4Connection()->execute('
        INSERT INTO cats (num) VALUES (1)
        ');
        Category::setConnection($this->getT4Connection());
        $this->getT4Connection()->execute('CREATE TABLE items (__id SERIAL, num INT, __category_id BIGINT)');
        $this->getT4Connection()->execute('
        INSERT INTO items (num, __category_id) VALUES (1, 1), (2, 1), (3, NULL)
        ');
        Item::setConnection($this->getT4Connection());
    }

    protected function tearDown()
    {
        $this->getT4Connection()->execute('DROP TABLE cats');
        $this->getT4Connection()->execute('DROP TABLE items');
    }

    public function testNoChanges()
    {
        $cat = Category::findByPK(1);
        $cat->save();

        $data =
            Category::getDbConnection()
            ->query(
                (new QueryBuilder())->select()->from(Category::getTableName())
            )->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertEquals(['__id' => 1, 'num' => 1], $data[0]);

        $data =
            Item::getDbConnection()
            ->query(
                (new QueryBuilder())->select()->from(Item::getTableName())
            )->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertEquals(['__id' => 1, 'num' => 1, '__category_id' => 1],    $data[0]);
        $this->assertEquals(['__id' => 2, 'num' => 2, '__category_id' => 1],    $data[1]);
        $this->assertEquals(['__id' => 3, 'num' => 3, '__category_id' => null], $data[2]);
    }

    public function testAdd()
    {
        $cat = Category::findByPK(1);
        $cat->items->add(Item::findByPK(3));
        $cat->save();

        $data =
            Category::getDbConnection()
            ->query(
                (new QueryBuilder())->select()->from(Category::getTableName())
            )->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertEquals(['__id' => 1, 'num' => 1], $data[0]);

        $data =
            Item::getDbConnection()
            ->query(
                (new QueryBuilder())->select()->from(Item::getTableName())
            )->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertEquals(['__id' => 1, 'num' => 1, '__category_id' => 1],    $data[0]);
        $this->assertEquals(['__id' => 2, 'num' => 2, '__category_id' => 1],    $data[1]);
        $this->assertEquals(['__id' => 3, 'num' => 3, '__category_id' => 1],    $data[2]);
    }

    public function testUnset()
    {
        $cat = Category::findByPK(1);
        unset($cat->items[0]);
        $cat->save();

        $data =
            Category::getDbConnection()
            ->query(
                (new QueryBuilder())->select()->from(Category::getTableName())
            )->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertEquals(['__id' => 1, 'num' => 1], $data[0]);

        $data =
            Item::getDbConnection()
            ->query(
                (new QueryBuilder())->select()->from(Item::getTableName())
            )->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertEquals(['__id' => 1, 'num' => 1, '__category_id' => null],    $data[0]);
        $this->assertEquals(['__id' => 2, 'num' => 2, '__category_id' => 1],    $data[1]);
        $this->assertEquals(['__id' => 3, 'num' => 3, '__category_id' => null],    $data[2]);
    }

    public function testClear()
    {
        $cat = Category::findByPK(1);
        $cat->items = new Collection();
        $cat->save();

        $data =
            Category::getDbConnection()
            ->query(
                (new QueryBuilder())->select()->from(Category::getTableName())
            )->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertEquals(['__id' => 1, 'num' => 1], $data[0]);

        $data =
            Item::getDbConnection()
            ->query(
                (new QueryBuilder())->select()->from(Item::getTableName())
            )->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertEquals(['__id' => 1, 'num' => 1, '__category_id' => null],    $data[0]);
        $this->assertEquals(['__id' => 2, 'num' => 2, '__category_id' => null],    $data[1]);
        $this->assertEquals(['__id' => 3, 'num' => 3, '__category_id' => null],    $data[2]);
    }
}