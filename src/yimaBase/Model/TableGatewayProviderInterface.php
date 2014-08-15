<?php
namespace yimaBase\Model;
use Zend\Db\TableGateway\TableGateway;

/**
 * Interface TableGatewayProviderInterface
 * @package yimaBase\Model
 */
interface TableGatewayProviderInterface
{
    /**
     * Get Table Gateway
     *
     * @return TableGateway
     */
    public function getTableGateway();
}
