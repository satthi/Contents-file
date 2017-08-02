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
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->table('attachments');
        $this->addBehavior('Timestamp');
        $this->primaryKey('id');
    }
}
