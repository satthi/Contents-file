<?php
namespace ContentsFile\Test\App\Model\Entity;

use Cake\ORM\Entity;
use ContentsFile\Model\Entity\ContentsFileTrait;

/**
 * Post Entity.
 */
class Post extends Entity
{
use ContentsFileTrait;
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array
     */
    protected $_accessible = [
        'name' => true,
    ];

    public $contentsFileConfig = [
        'fields' => [
            'file' => [
                'resize' => false,
                'cacheTempDir' => CONTENTS_FILE_CACHE_PATH,
                'filePath' => CONTENTS_FILE_PATH,
            ],
            'img' => [
                'resize' => [
                    ['width' => 300],
                    ['width' => 300, 'height' => 400],
                ],
                'cacheTempDir' => CONTENTS_FILE_CACHE_PATH,
                'filePath' => CONTENTS_FILE_PATH,
            ],
        ],
    ];

    //&getメソッドをoverride
    public function &get($property)
    {
        $value = parent::get($property);

        $value = $this->getContentsFile($property, $value);

        return $value;
    }

    //setメソッドをoverride

    public function set($property, $value = null, array $options = [])
    {

        parent::set($property, $value , $options);

        $this->setContentsFile();
        return $this;
    }

}
