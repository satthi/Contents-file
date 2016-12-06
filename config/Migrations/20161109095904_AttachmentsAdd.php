<?php
use Migrations\AbstractMigration;

class AttachmentsAdd extends AbstractMigration
{
    public function up()
    {

        $this->table('attachments')
            ->addColumn('model', 'text', [
                'comment' => 'モデル名',
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('model_id', 'integer', [
                'comment' => 'モデルID',
                'default' => null,
                'limit' => 10,
                'null' => true,
            ])
            ->addColumn('field_name', 'text', [
                'comment' => 'フィールド名',
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('file_name', 'text', [
                'comment' => 'ファイル名',
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('file_content_type', 'text', [
                'comment' => 'ファイルタイプ',
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('file_size', 'integer', [
                'comment' => 'ファイルサイズ',
                'default' => null,
                'limit' => 10,
                'null' => true,
            ])
            ->addColumn('file_object', 'text', [
                'comment' => 'ファイル',
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('created', 'timestamp', [
                'comment' => '作成日',
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'timestamp', [
                'comment' => '更新日',
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                [
                    'model_id',
                ]
            )
            ->create();
    }

    public function down()
    {
        $this->dropTable('attachments');
    }
}
