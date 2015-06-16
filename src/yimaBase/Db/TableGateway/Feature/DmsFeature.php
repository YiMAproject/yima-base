<?php
namespace yimaBase\Db\TableGateway\Feature;

use Poirot\Core\Interfaces\iDataSetConveyor;
use yimaBase\Db\TableGateway\Dms as DmsTable;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\Adapter\Driver\StatementInterface;
use Zend\Db\ResultSet\ResultSetInterface;
use Zend\Db\Sql\Select;
use Zend\Db\TableGateway\Feature\AbstractFeature;
use Zend\Db\TableGateway\Exception;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Db\Metadata\Metadata;
use Zend\Db\Sql\Expression;
use yimaBase\Db\TableGateway\Provider\PrimaryKeyProviderInterface;
use Zend\Db\TableGateway\TableGateway;

class DmsFeature extends AbstractFeature
{
	/**
	 * This is DMS Extra Columns Fields
	 *
	 * @var array | string
	 */
	protected $dmsColumns = array();
	
	/**
	 * DMS TableGateway
	 *
	 * @var AbstractTableGateway
	 */
	protected $dmsTable;
	
	/**
	 * @see preInsert
	 *
	 * @var array
	 */
	protected $storedValues;

    /**
     * Construct
     *
     * @param array $dmsFields
     *
     * @param null|TableGateway $dmsTable Dms TableGateway
     */
    public function __construct($dmsFields = array() , $dmsTable = null)
	{
		if (!empty($dmsFields)) {
			$this->setDmsColumns($dmsFields);
		}
	
		if ($dmsTable) {
            $this->setDmsTable($dmsTable);
		}
	}
	
	/**
	 * Set DMS TableGateway
     * : This Table Contains Content Of DMS Fields Data
	 *
	 * @param AbstractTableGateway $tableGateway TableGateway
     *
     * @return $this
	 */
	public function setDmsTable(AbstractTableGateway $tableGateway)
	{
		// If table don`t has an adapter yet
		if (!$tableGateway->getAdapter()
            && $tableGateway instanceof \Zend\Db\Adapter\AdapterAwareInterface
        ) {
			$tableGateway->setAdapter($this->tableGateway->adapter); 
		}
		
		$this->dmsTable = $tableGateway;

		return $this;
	}

    /**
     * Get DMS TableGateway
     *
     * @return AbstractTableGateway
     */
    public function getDmsTable()
	{
        if (!$this->dmsTable) {
            $this->setDmsTable(new DmsTable);
        }

		return $this->dmsTable;
	}
	
	/**
	 * Set Extra Table Columns (DMS Fields)
	 *
	 * @param array $fields DMS Fields
     *
     * @return $this
	 */
	public function setDmsColumns(array $fields)
	{
		$this->dmsColumns = $fields;

		return $this;
	}

    /**
     * Get Table Extra Columns(DMS Fields)
     *
     * @return array
     */
    public function getDmsColumns()
    {
        return $this->dmsColumns;
    }
	
	// ............................................................................................................
	
	public function preSelect(Select $select)
	{
        $sname = 'dms';
        $tableGateway = $this->tableGateway;
        $tableName    = $tableGateway->getTable();
        $tablePrimKey = $this->getPrimaryKey($tableGateway);

        $rawState  = $select->getRawState();
        $columns   = $rawState['columns'];
        if (!in_array('*', $columns) && !in_array($tablePrimKey, $columns))
            // We need primary key for postSelect
            array_unshift($columns, $tablePrimKey);

        if (!$this->getDmsColumns()) {
            // we have not any predefined dms columns
            // - look over dms table that which data is provided for this entity of table
            //   as extra data on dmsTable
            // - the query return left joined results of table rows included dms data as
            //   field and content
            // - later on postSelect we will filter data

            // NOTE: not defined dms columns bring down the performance and resources

            $dmsColumns = array_diff($columns, $tableGateway->getColumns());
            if ($dmsColumns) {
                // we`ve not need all dms columns from table, request specific columns
                // that are not default table columns and assumed as dms
                // select user_id, field_on_dms, ....
                $this->setDmsColumns($dmsColumns);
                return $this->preSelect($select);
            }

            /*
            SELECT mtp . * , dms.field as dms__field, dms.content as dms__content
            FROM  `typopages_page` AS mtp
            LEFT JOIN  `yimabase_dms` AS dms ON dms.foreign_key = mtp.page_id
            */
            $joinTable = $this->getDmsTable()->table;// get name of dms table

            $expression = new Expression(
                "$sname.foreign_key = ?.?
                AND $sname.model = ?
                "
                , [
                    $tableName, $tablePrimKey,
                    get_class($tableGateway),
                ]
                , [
                    Expression::TYPE_IDENTIFIER,
                    Expression::TYPE_IDENTIFIER
                ]
            );

            $select->join(
                [$sname => $joinTable]//join table name
                ,$expression //conditions
                ,[
                    'dms__content' => 'content',
                    'dms__field'   => 'field',
                ]
                ,'left'
            );

            // used in post select
            $this->setStoredValues(['pk' => $tablePrimKey, 'columns' => $rawState['columns']]);

            $select->columns($columns);

            return; // ===================================================================================
        }

		/*
		 SELECT `sampletable`.*, `dms__image`.`content` AS `image`
 		 FROM `sampletable`
		 LEFT JOIN `yimabase_dms` AS `dms__image`
 		  ON dms__image.foreign_key = `sampletable`.`sampletable_id`
      		 AND dms__image.model   = 'application\\Model\\TableGateway\\Sample'
     		 AND dms__image.field   = 'image'
		*/
		
		// Looking at SELECT requested COLUMNS for any DMS Columns ... {
        #  SELECT [column] FROM ...
		$vColumns  = array_values($columns);
		$dmsFields = $this->getDmsColumns();
		
		if (! array_intersect($vColumns, $dmsFields) && !in_array('*', $vColumns) )
            // We don't have any DMS columns on SELECT expression
			return;
        // ... }

		$tableClass   = get_class($tableGateway);
		$tablePrimKey = $this->getPrimaryKey($tableGateway);

		foreach ($this->getDmsColumns() as $dc) {
			if (($key = array_search($dc, $columns)) !== false ) {
				// we have dms column in select and must remove from SELECT 'dms_column'
				unset($columns[$key]);
			} elseif (!in_array('*', $vColumns)) {
				// we don't need any other dms fields, cause not in SELECT
				continue;
			}
			
			$name = 'Dms__'.$dc;

			$joinTable = $this->getDmsTable()->table;// get name of translation table
			$expression = new Expression("
				$name.foreign_key = ?.?
				AND $name.model = ?
				AND $name.field = ?
				"
				,array(
					$tableName, $tablePrimKey,
					$tableClass,
					$dc
				)
				,array(
					Expression::TYPE_IDENTIFIER,
					Expression::TYPE_IDENTIFIER
				)
			);
			
			$select->join(
				array($name => $joinTable)//join table name
				,$expression //conditions
				,array($dc => 'content') // this way we dont need postSelect replacement
				,'left'
			);
		}
		
		$select->columns($columns);

        // Filter dms columns on where clause:
        // SELECT `user`.`_user_id` AS `_user_id`, `Dms__location`.`content` AS `location`
        // FROM `user` LEFT JOIN `user_dms` AS `Dms__location` ON ...
        // WHERE `nation_code` = '0322358078' AND location = '1' <====== sql can't realize this
        // We use this instead:
        // Dms__location.content = '1'

        /** @var \Zend\Db\Sql\Where $where */
        $where      = $rawState['where'];
        foreach ($where->getPredicates() as $pr)
        {
            /** @var \Zend\Db\Sql\Predicate\Operator $predicate */
            $predicate = $pr[1];
            if (method_exists($predicate, 'getLeft')) {
                $operand = $predicate->getLeft();
                if (in_array($operand, $this->getDmsColumns()))
                    $predicate->setLeft('Dms__'.$operand.'.content');
            } elseif (method_exists($predicate, 'getIdentifier')) {
                $operand = $predicate->getIdentifier();
                if (in_array($operand, $this->getDmsColumns()))
                    $predicate->setIdentifier('Dms__'.$operand.'.content');
            }
        }
	}

    /**
     * Note: Filter unstructured data from prePost when we don't have
     *       dmsColumns defined
     *
     * @param StatementInterface $statement
     * @param ResultInterface $result
     * @param ResultSetInterface $resultSet
     *
     * @throws \Exception
     */
    public function postSelect(StatementInterface $statement, ResultInterface $result, ResultSetInterface $resultSet)
    {
        if ($this->getDmsColumns())
            // We have used the query with presented Predefined DMS Columns
            // Nothing to do
            return;

        $storedValues = $this->getStoredValues();
        $pkColumn     = $storedValues['pk'];

        $totalResultSet = array();
        foreach($resultSet as $rc)
        {
            if (!$rc instanceof iDataSetConveyor)
                throw new \Exception(sprintf(
                    'ResultSet must instance of iDataSetConveyor to DmsFeature interact to. "%s" given.'
                    , get_class($rc)
                ));

            $rc = $rc->toArray();

            $pk = $rc[$pkColumn]; // get pk column value
            foreach ($rc as $c => $v) {
                // iterate trough result containing duplicated rows because of join over dms values
                if ($c == 'dms__content' || $c == 'dms__field')
                    // dms virtual columns avoided and set as field->content
                    continue;

                if ($c == $pkColumn
                    && (!in_array($pkColumn, $storedValues['columns'])
                        && !in_array('*', $storedValues['columns'])
                    )
                )
                    continue;


                $totalResultSet[$pk][$c] = $v;
            }

            if (isset($rc['dms__content']) && isset($rc['dms__field'])) {
                if (in_array($rc['dms__field'], $storedValues['columns'])
                    || in_array('*', $storedValues['columns'])
                )
                {
                    $column = $rc['dms__field'];
                    $value  = $rc['dms__content'];
                    $totalResultSet[$pk][$column] = $value;
                }

                unset($rc['dms__field']);
                unset($rc['dms__content']);
            }
        }

        $resultSet->initialize($totalResultSet);
    }
	
	/**
	 * Tammai e column haaii ke be insert daade shode raa jostejoo karde
	 * va field haaye dms raa joda va baraaie postInsert negah midaarad.
	 * badihi ast ke digar filed haaye dms dar insert mojood nist banaa
	 * bar in dar feature haaye ba'd az in dide nemishavad
	 * exp.
	 * $projectTable->insert(array(
    		'title' 	  => $locale.' Title',						# locale
    		'description' => $locale.' Description', 				# locale
    		
    	(r)	'note'		  => '* this commented note on this post',  # locale
    			
    	(r)	'image' 	  => 'http://image/path/'.$locale.'.jpg',
    	(r)	'url' 	  	  => 'http://google.com',
    	));
    	
    	(r) : removed from columns rawSet
	 */
	public function preInsert($insert)
	{
		// reset on insert values
		$this->setStoredValues();
		
		/* value haaie ersaal shode ro mibinim agar haavie
		 * column dms bood aanhaa ro kenaar migzaarim
		 * va dar postInsert aanhaa raa be table translate ezaafe mikonim
		 */
		$rawData    = $insert->getRawState();
		$insertCols = $rawData['columns'];
		$insertVals = $rawData['values'];

        $arrayDiff  = array_diff($insertCols, $this->tableGateway->getColumns());
		if ($arrayDiff && $arrayDiff !== $insertCols) {
            if (!$this->getDmsColumns()) {
                // we set extra fields as dms columns if no any dms was set
                // and insert has a column name more than real table columns
                $this->setDmsColumns($arrayDiff);
            }
        }
        $dmsColumns = $this->getDmsColumns();

		$storedVal = array();// dms column must insert on postInsert
		foreach ($insertCols as $key=>$cl) {
			if (in_array($cl,$dmsColumns)) {
				$storedVal[$cl] = $insertVals[$key];
				unset($insertCols[$key]);
				unset($insertVals[$key]);
			}
		}
		
		$insert->values($insertVals,'merge');
		$insert->columns($insertCols);
		
		$this->setStoredValues($storedVal);
	}
	
	public function postInsert($statement, $result)
	{
		$dmsColumns = $this->getStoredValues();
		if (empty($dmsColumns)) {
			// we dont have any dms columns
			return;
		}
		
		// for Inserting into translation table we need ID of last inserted row
		$lastID = $result->getGeneratedValue();
		$this->addDmsRows($dmsColumns,$lastID);
	}
	
	public function preUpdate($update)
	{
		// reset stored values
		$this->setStoredValues();

        // Looking on query, the columns of dms table must stored and perform action on postUpdate:
		$rawState  = $update->getRawState();
		$dataset   = $rawState['set'];

        // Find columns belong to base table (without dms):
        if ($this->getDmsColumns())
            $columns = array_diff_key($dataset, array_flip($this->getDmsColumns()));
        elseif ($this->tableGateway->columns)
            $columns = array_intersect_key($dataset, array_flip($this->tableGateway->columns));
        else
            throw new \Exception('Both TableGateway Columns And Dms Columns Are Empty, Update Can`t Happen.');

		// store dmsColumns for postUpdate
		$storedData           = array_diff_key($dataset, $columns);
		$storedData['@where'] = clone $update->where;

		if (empty($columns)) {
			// reson: Column not found: 1054 Unknown column 'note' in 'field list'
            // exp. *note is dms field
			$tableGateway = $this->tableGateway;
			$tablePrimKey = $this->getPrimaryKey($tableGateway);
			$columns   = [$tablePrimKey => 0];
				
			// we don't want change anything in base table
			// store where part and change it to nothing happend.
			$update->where(array('1 = ?' => 0));
		}
		
		$update->set($columns);
		$this->setStoredValues($storedData);
	}
	
	public function postUpdate($statement, $result)
	{
		$storedValues = $this->getStoredValues();
	
		$where = $storedValues['@where'];
		unset($storedValues['@where']);
	
		if (empty($storedValues))
			// we dont have any dms field
			return;

		$tableGateway = $this->tableGateway;
	
		# we dont want use tableGateway baraaie inke feature haa raa niaaz nadaarim
		# be alave inke hamin feature rooie table hatman hast va az select e in estefaade mikonim,
        # kaahesh performance
		$sql       = $tableGateway->getSql();
		$select    = $sql->select()->where($where);
		$statement = $sql->prepareStatementForSqlObject($select);
		$rows      = $statement->execute();
		
		if (! count($rows) > 0)
			// we don't have any update match
			return;

		// get query data
		$primaryKey = $this->getPrimaryKey($tableGateway);
		$model      = get_class($tableGateway);
	
		// @TODO $result Affected ro raa az PDOstatement migirad va nemishavad aan raa ta'ghir daad
	
		$dmsTable = $this->getDmsTable(); $affectedRows = 0;
		foreach ($rows as $row) {
			$foreignKey = $row[$primaryKey];
			foreach ($storedValues as $column => $val) {
				// delete previous dms field post and insert new one
				$dmsTable->delete(array(
					'foreign_key = ?' => $foreignKey,
					'model = ?' 	  => $model,
					'field = ?' 	  => $column,
				));
				
				$dmsTable->insert(array(
					'foreign_key' => $foreignKey,
					'model' 	  => $model,
					'field' 	  => $column,
					'content' 	  => $val,
				));$r = 1;
				
				/* (!) Zamani ke az update estefaade mikonim momken ast ke ghablan in dms vojood nadaashte baashad, 
				 *     va dar natije in field raa nakhaahim daasht.
				 *     
				 * $r = $dmsTable ->update(array('content' => $val),array(
					'foreign_key = ?' => $foreignKey,
					'field = ?' 	  => $column,
					'model = ?' 	  => $model,
				)); */
	
				$affectedRows = ($r) ? $affectedRows+$r : $affectedRows;
			}
		}
	}
	
	public function preDelete($delete)
	{
		$tableGateway = $this->tableGateway;
		$prKey = $this->getPrimaryKey($tableGateway);
	
		// baraaie hazf kadane translation haa yek baar baayad select konim va sepas
		// az rooie ID(primary key) be dast aamade az jadvale translation foreinKey haaye
		// moshaabeh ro hazf konim
		$where = $delete->where;
		# we dont want use tableGateway baraaie inke feature haa raa niaaz nadaarim
		# be alave inke hamin feature rooie table hatman hast va az select e in estefaade mikonim, kaahesh performance
		$sql       = $tableGateway->getSql();
		$select    = $sql->select()->where($where);
		$statement = $sql->prepareStatementForSqlObject($select);
		$rows      = $statement->execute();
	
		if (! count($rows) > 0) {
			return;
		}
			
		// primary key of must deleted item
		$ids  = array();
		foreach ($rows as $row) {
			$ids[] = $row[$prKey];
		}
	
		// delete from translation table
		$modelColumn = get_class($tableGateway);
		// "foreign_key IN(?) AND model = $modelColumn"
		$this->getDmsTable()->delete(array(
				'foreign_key'   => $ids,
				'model = ?'     => $modelColumn
		));
	}
	
	// ............................................................................................................
	
	public function addDmsRows($rows, $foreignID)
	{
		foreach ($rows as $column => $value) {
			$this->addDmsRow($column,$value,$foreignID);
		}
	}
	
	/**
	 * Add Dms data for a row with specific primary key
	 *
	 * @param int   $pk   | primary key of row
	 */
	public function addDmsRow($column, $value, $foreignID)
	{
		$tableGateway = $this->tableGateway;
	
		// write it
		$trData = array(
			'model' 	  => get_class($tableGateway),
			'foreign_key' => $foreignID,
			'field' 	  => $column,
			'content'	  => $value
		);
	
		// insert to dms table
		$dmsTable = $this->getDmsTable();
		$dmsTable->insert($trData);
	}
	
	/**
	 * @see preInsert
	 *
	 * @param array $values
	 */
	protected function setStoredValues($values = array())
	{
		$this->storedValues = $values;
	}
	
	protected function getStoredValues()
	{
		return $this->storedValues;
	}

    /**
     * Get Primary Key Of Table
     *
     * @param $tableGateway
     * @return \string[]
     * @throws \Zend\Db\TableGateway\Exception\RuntimeException
     */
    protected function getPrimaryKey($tableGateway)
	{
		if ($tableGateway instanceof PrimaryKeyProviderInterface) {
			return $tableGateway->getPrimaryKey();
		}

		// try to catch primary key from metada
		
		$metadata = new Metadata($tableGateway->adapter);
		
		// process primary key
		$pkc = null;
		foreach ($metadata->getConstraints($tableGateway->table) as $constraint) {
			/** @var $constraint \Zend\Db\Metadata\Object\ConstraintObject */
			if ($constraint->getType() == 'PRIMARY KEY') {
				$pkc = $constraint;
				break;
			}
		}
		
		if ($pkc === null) {
			throw new Exception\RuntimeException('A primary key for this column could not be found in the metadata.');
		}
		
		if (count($pkc->getColumns()) == 1) {
			$pkck = $pkc->getColumns();
			$primaryKey = $pkck[0];
		} else {
			$primaryKey = $pkc->getColumns();
		}
		
		return $primaryKey;
	}
	
}
