<?php

namespace SM\Integrate\Api\Data\Pool;

interface CodeInterface
{
    /**#@+
     * Constants defined for keys of the data array. Identical to the name of the getter in snake case
     */
    const ID = 'id';
    const POOL_ID = 'pool_id';
    const CODE = 'code';
    const USED = 'used';
    /**#@-*/

    /**
     * Get ID
     *
     * @return int
     */
    public function getId();

    /**
     * Set ID
     *
     * @param int $id
     * @return $this
     */
    public function setId($id);

    /**
     * Get pool ID
     *
     * @return int
     */
    public function getPoolId();

    /**
     * Set pool ID
     *
     * @param int $poolId
     * @return $this
     */
    public function setPoolId($poolId);

    /**
     * Get code
     *
     * @return string
     */
    public function getCode();

    /**
     * Set code
     *
     * @param string $code
     * @return $this
     */
    public function setCode($code);

    /**
     * Is used
     *
     * @return bool
     */
    public function getUsed();

    /**
     * Set used
     *
     * @param int $used
     * @return $this
     */
    public function setUsed($used);
}
