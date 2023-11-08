<?php
use Migrations\AbstractMigration;

class PostsAdd extends AbstractMigration
{
    public function up()
    {
        $this->table('posts')
            ->addColumn('name', 'text', [
                'comment' => 'タイトル',
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
            ->create();
    }

    public function down()
    {
        $this->table('posts')->drop()->update();
    }
}
