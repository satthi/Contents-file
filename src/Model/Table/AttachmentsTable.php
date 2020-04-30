<?php
namespace ContentsFile\Model\Table;

use Cake\ORM\Table;
class AttachmentsTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('attachments');
        $this->addBehavior('Timestamp');
        $this->setPrimaryKey('id');
    }
}
