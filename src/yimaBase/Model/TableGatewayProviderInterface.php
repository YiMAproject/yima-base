<?php
namespace yimaBase\Model;
use Zend\Db\TableGateway\TableGateway;

interface TableGatewayProviderInterface
{
    /**
     * Get Table Gateway
     *
     * @return TableGateway
     */
    public function getTableGateway();
}
