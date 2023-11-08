<?php
declare(strict_types=1);

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
    protected array $_accessible = [
        '*' => true,
        'id' => false,
    ];

    public array $contentsFileConfig = [
        'fields' => [
            'file' => [
                'resize' => false,
            ],
            'img' => [
                'resize' => [
                    ['width' => 300],
                    ['width' => 300, 'height' => 400],
                ],
            ],
        ],
    ];

    //&getメソッドをoverride

    public function &get(string $property): mixed
    {
        $value = parent::get($property);
        $value = $this->getContentsFile($property, $value);

        return $value;
    }

    //setメソッドをoverride

    public function set(array|string $field, mixed $value = null, array $options = [])
    {
        parent::set($field, $value, $options);
        $this->setContentsFile();

        return $this;
    }
}
