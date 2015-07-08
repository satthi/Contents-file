<?php
namespace ContentsFile\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * PostsFixture
 *
 */
class PostsFixture extends TestFixture
{

    /**
     * Fields
     *
     * @var array
     */
    // @codingStandardsIgnoreStart
    public $fields = [
        'id' => ['type' => 'integer', 'length' => 10, 'autoIncrement' => true, 'default' => null, 'null' => false, 'comment' => 'id', 'precision' => null, 'unsigned' => null],
        'name' => ['type' => 'text', 'length' => null, 'default' => null, 'null' => true, 'comment' => 'name', 'precision' => null],
        'created' => ['type' => 'timestamp', 'length' => null, 'default' => null, 'null' => true, 'comment' => 'created', 'precision' => null],
        'modified' => ['type' => 'timestamp', 'length' => null, 'default' => null, 'null' => true, 'comment' => 'modified', 'precision' => null],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
        ],
    ];
    // @codingStandardsIgnoreEnd

    /**
     * Records
     *
     * @var array
     */
    public $records = [
        [
            'name' => 'test',
            'created' => 1430991176,
            'modified' => 1430991176
        ],
    ];
}
