<?php


namespace BirdSystem\Tests\Db\Model\Stub;

use BirdSystem\Db\Model\AbstractModel;

class CompositeKeyModel extends AbstractModel
{
    protected $primaryKeys = ['key_one', 'key_two'];

    protected $key_one;
    protected $key_two;

    public function setId($id)
    {
        $this->decodeCompositeKey($id);
    }

    public function getId()
    {
        return $this->encodeCompositeKey();
    }

    /**
     * @return mixed
     */
    public function getKeyOne()
    {
        return $this->key_one;
    }

    /**
     * @param mixed $key_one
     *
     * @return $this
     */
    public function setKeyOne($key_one)
    {
        $this->key_one = $key_one;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getKeyTwo()
    {
        return $this->key_two;
    }

    /**
     * @param mixed $key_two
     *
     * @return $this
     */
    public function setKeyTwo($key_two)
    {
        $this->key_two = $key_two;

        return $this;
    }

}