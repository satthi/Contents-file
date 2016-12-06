<?php
namespace ContentsFile\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * AttachmentsFixture
 *
 */
class AttachmentsFixture extends TestFixture
{

    /**
     * Fields
     *
     * @var array
     */
    // @codingStandardsIgnoreStart
    public $fields = [
        'id' => ['type' => 'integer', 'length' => 10, 'autoIncrement' => true, 'default' => null, 'null' => false, 'comment' => null, 'precision' => null, 'unsigned' => null],
        'model' => ['type' => 'text', 'length' => null, 'default' => null, 'null' => true, 'comment' => 'モデル名', 'precision' => null],
        'model_id' => ['type' => 'integer', 'length' => 10, 'default' => null, 'null' => true, 'comment' => 'モデルID', 'precision' => null, 'unsigned' => null, 'autoIncrement' => null],
        'field_name' => ['type' => 'text', 'length' => null, 'default' => null, 'null' => true, 'comment' => 'フィールド名', 'precision' => null],
        'file_name' => ['type' => 'text', 'length' => null, 'default' => null, 'null' => true, 'comment' => 'ファイル名', 'precision' => null],
        'file_content_type' => ['type' => 'text', 'length' => null, 'default' => null, 'null' => true, 'comment' => 'ファイルタイプ', 'precision' => null],
        'file_size' => ['type' => 'text', 'length' => null, 'default' => null, 'null' => true, 'comment' => 'ファイルサイズ', 'precision' => null],
        'created' => ['type' => 'timestamp', 'length' => null, 'default' => null, 'null' => true, 'comment' => '登録日時', 'precision' => null],
        'modified' => ['type' => 'timestamp', 'length' => null, 'default' => null, 'null' => true, 'comment' => '更新日時', 'precision' => null],
        '_indexes' => [
            'attachments_model_id' => ['type' => 'index', 'columns' => ['model_id'], 'length' => []],
        ],
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
    ];
}
