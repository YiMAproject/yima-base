<?php
namespace yimaBase\Db\TableGateway;

use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Select;
use Zend\Db\TableGateway\AbstractTableGateway as ZendTableAbstract;
use Zend\Db\Adapter\AdapterAwareInterface;
use Zend\Db\TableGateway\Feature;
use Zend\Db\Adapter\Adapter;
use yimaBase\Db\TableGateway\Provider\PrimaryKeyProviderInterface;
use yimaBase\Db\TableGateway\Exception;

/**
 * Class AbstractTableGateway
 *
 * @package yimaBase\Db\TableGateway
 */
abstract class AbstractTableGateway extends ZendTableAbstract implements
	AdapterAwareInterface,	     # using global adapter when service invoked
	PrimaryKeyProviderInterface	 # to avoid using metadata and reduce performance on some features that need primaryKey
{
	# db table name
    protected $table = '';

    /**
     * Construct
     */
    final public function __construct()
    {
        // inject global db adapter into table from Feature, global adapter set via Application
    	$this->featureSet = new Feature\FeatureSet(
            array(
                new Feature\GlobalAdapterFeature,
    	    )
        );
    
    	// init Table, adding features, .....
    	$this->init();

        // all features::$tableGateway set to $this(object) from initialize()
    	$this->initialize();

        $this->postInit();
    }
    
    /**
     * Init Table 
     * 
     * AddFeatures and .....
     */
     public function init() {}

    /**
     * Post Init
     *
     */
    public function postInit() {}
    
    /**
     * We can call featureSet methods
     *
     * $projectTable   = $serviceLocator->get('Application\SampleTable');
     * $projectTable->apply('setLocale', array('fa_IR'));
     * 
     * @throws Exception\InvalidFeatureException
     */
    public function __call($method, $args)
    {
    	$featureSet = $this->featureSet;
    	if (method_exists($featureSet, $method)) {
    		switch ($method) {
    			case 'preInitialize':	case 'postInitialize':
    			case 'preSelect':		case 'postSelect':
    			case 'preInsert':		case 'postInsert':
    			case 'preUpdate':		case 'postUpdate':
    			case 'preDelete':		case 'postDelete':
    				throw new Exception\InvalidFeatureException(sprintf(
    					'Method %s is internal method and run during initialization.'
    				,$method));
    				break;
    		}
    		
    		$ret = call_user_func_array(array($featureSet, $method), $args);

    		return ($ret instanceof $featureSet) ? $this : $ret;
    	}
    	
    	return parent::__call($method, $args);
    }

    // .............................................................................................................

    /**
     * We don't want use default table columns as default select state
     *
     * @param Select $select
     * @return ResultSet
     * @throws \RuntimeException
     */
    protected function executeSelect(Select $select)
    {
        $selectState = $select->getRawState();
        if ($selectState['table'] != $this->table) {
            throw new \RuntimeException('The table name of the provided select object must match that of the table');
        }

        // We don't want use default table columns as default select columns state
        /*if ($selectState['columns'] == array(Select::SQL_STAR)
            && $this->columns !== array()) {
            $select->columns($this->columns);
        }*/

        // apply preSelect features
        $this->featureSet->apply('preSelect', array($select));

        // prepare and execute
        $statement = $this->sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();

        // build result set
        $resultSet = clone $this->resultSetPrototype;
        $resultSet->initialize($result);

        // apply postSelect features
        $this->featureSet->apply('postSelect', array($statement, $result, $resultSet));

        return $resultSet;
    }
    
    // Implemented Features .........................................................................................
        
    # implemented AdapterAwareInterface
    /**
     * @note Ehtemaal daarad be dalil e "Visibility from other objects" dar 
     * table haaii ke feature e globalAdapter raa daarad dochar e moshkel shavim
     *
     * @see yimaBase\Db\TableGateway\Feature\TablePrefixFeature
     * @see \Zend\Db\Adapter\AdapterAwareInterface::setDbAdapter()
     */
    public function setDbAdapter(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }
    
    /* implemented PrimaryKeyProviderInterface */
    public function getPrimaryKey()
    {
    	if (isset($this->primaryKey)) {
    		return $this->primaryKey;
    	}
    	
    	return null;
    }
}
