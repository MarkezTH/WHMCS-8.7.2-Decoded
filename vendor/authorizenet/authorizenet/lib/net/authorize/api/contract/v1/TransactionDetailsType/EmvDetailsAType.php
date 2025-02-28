<?php
namespace net\authorize\api\contract\v1\TransactionDetailsType;

/**
 * Class representing EmvDetailsAType
 */
class EmvDetailsAType
{

    /**
     * @property
     * \net\authorize\api\contract\v1\TransactionDetailsType\EmvDetailsAType\TagAType[]
     * $tag
     */
    private $tag = null;

    /**
     * Adds as tag
     *
     * @return self
     * @param
     * \net\authorize\api\contract\v1\TransactionDetailsType\EmvDetailsAType\TagAType
     * $tag
     */
    public function addToTag(\net\authorize\api\contract\v1\TransactionDetailsType\EmvDetailsAType\TagAType $tag)
    {
        $this->tag[] = $tag;
        return $this;
    }

    /**
     * isset tag
     *
     * @param scalar $index
     * @return boolean
     */
    public function issetTag($index)
    {
        return isset($this->tag[$index]);
    }

    /**
     * unset tag
     *
     * @param scalar $index
     * @return void
     */
    public function unsetTag($index)
    {
        unset($this->tag[$index]);
    }

    /**
     * Gets as tag
     *
     * @return
     * \net\authorize\api\contract\v1\TransactionDetailsType\EmvDetailsAType\TagAType[]
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * Sets a new tag
     *
     * @param
     * \net\authorize\api\contract\v1\TransactionDetailsType\EmvDetailsAType\TagAType[]
     * $tag
     * @return self
     */
    public function setTag(array $tag)
    {
        $this->tag = $tag;
        return $this;
    }


}

