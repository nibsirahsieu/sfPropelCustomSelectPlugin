<?php

if (false !== strpos(Propel::VERSION, '1.4')) {
  require_once 'propel/engine/builder/om/php5/PHP5PeerBuilder.php';
} else {
  require_once 'builder/om/PHP5PeerBuilder.php';
}
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class PHP5CustomPeerBuilder extends PHP5PeerBuilder
{
  /**
	 * Adds the addSelectColumns(), doCount(), etc. methods.
	 * @param      string &$script The script will be modified in this method.
	 */
	protected function addSelectMethods(&$script)
	{
    $this->addGetSelectedColumns($script);
    $this->addSetSelectedColumns($script);
    $this->addGetStartColumn($script);
		parent::addSelectMethods($script);
	}
  
  /**
	 * Adds constant and variable declarations that go at the top of the class.
	 * @param      string &$script The script will be modified in this method.
	 * @see        addColumnNameConstants()
	 */
	protected function addConstantsAndAttributes(&$script)
	{
		parent::addConstantsAndAttributes($script);
    $script .= "
  /**
	* An array to hold the user selected columns
	*/
  protected static \$selectedColumns = array();
  ";
	}

  /**
	 * Adds the getSelectedColumns() method.
	 * @param      string &$script The script will be modified in this method.
	 */
  protected function addGetSelectedColumns(&$script)
  {
    $script .= "
  /**
	 * return the user selected columns.
	 *
	 * @return     an array Array of selected columns
	 */
  public static function getSelectedColumns()
  {
    return self::\$selectedColumns;
  }
  ";
  }

  /**
	 * Adds the setSelectedColumns() method.
	 * @param      string &$script The script will be modified in this method.
	 */
  protected function addSetSelectedColumns(&$script)
  {
    $script .= "
  /**
	 * Set selected columns.
	 *
	 * @param      array Array of selected columns \$selectedColumns.
   * @return     void
	 */
  public static function setSelectedColumns(array \$selectedColumns)
  {
    self::\$selectedColumns = \$selectedColumns;
  }
  ";
  }

  /**
	 * Adds the getStartColumn() method.
	 * @param      string &$script The script will be modified in this method.
	 */
  protected function addGetStartColumn(&$script)
  {
    $script .= "
  /**
	 * return the starting column for hydrating object.
	 *
	 * @return int of starting column.
	 */
  public static function getStartColumn()
  {
    if (empty(self::\$selectedColumns)) {
      return ".$this->getPeerClassname()."::NUM_COLUMNS - ".$this->getPeerClassname()."::NUM_LAZY_LOAD_COLUMNS;
    } else {
      return count(self::\$selectedColumns);
    }
  }
  ";
  }

  /**
	 * Adds the addSelectColumns() method.
	 * @param      string &$script The script will be modified in this method.
	 */
	protected function addAddSelectColumns(&$script)
	{
		$script .= "
	/**
	 * Add all the columns needed to create a new object.
	 *
	 * Note: any columns that were marked with lazyLoad=\"true\" in the
	 * XML schema will not be added to the select list and only loaded
	 * on demand.
	 *
	 * @param      criteria object containing the columns to add.
	 * @throws     PropelException Any exceptions caught during processing will be
	 *		 rethrown wrapped into a PropelException.
	 */
	public static function addSelectColumns(Criteria \$criteria, \$alias = null)
	{
    if (empty(self::\$selectedColumns)) {
      if (null === \$alias) {";
      foreach ($this->getTable()->getColumns() as $col) {
        if (!$col->isLazyLoad()) {
          $script .= "
        \$criteria->addSelectColumn(".$this->getPeerClassname()."::".$this->getColumnName($col).");";
        } // if !col->isLazyLoad
      } // foreach
      $script .= "
      } else {";
      foreach ($this->getTable()->getColumns() as $col) {
        if (!$col->isLazyLoad()) {
          if ($col->getPeerName()) {
            $const = strtoupper($col->getPeerName());
          } else {
            $const = strtoupper($col->getName());
          }
          $script .= "
        \$criteria->addSelectColumn(\$alias . '." . $const."');";
        } // if !col->isLazyLoad
      } // foreach
    $script .="
      }
    } else {
      foreach (self::\$selectedColumns as \$selectedColumn) {
        \$criteria->addSelectColumn(\$selectedColumn);
      }
    }
  }
";
	} // addAddSelectColumns()
  
  /**
	 * Adds the doSelectJoin*() methods.
	 * @param      string &$script The script will be modified in this method.
	 */
	protected function addDoSelectJoin(&$script)
	{
		$table = $this->getTable();
		$className = $this->getObjectClassname();
		$countFK = count($table->getForeignKeys());
		$join_behavior = $this->getJoinBehavior();

		if ($countFK >= 1) {

			foreach ($table->getForeignKeys() as $fk) {

				$joinTable = $table->getDatabase()->getTable($fk->getForeignTableName());

				if (!$joinTable->isForReferenceOnly()) {

					// This condition is necessary because Propel lacks a system for
					// aliasing the table if it is the same table.
					if ( $fk->getForeignTableName() != $table->getName() ) {

						$thisTableObjectBuilder = $this->getNewObjectBuilder($table);
						$joinedTableObjectBuilder = $this->getNewObjectBuilder($joinTable);
						$joinedTablePeerBuilder = $this->getNewPeerBuilder($joinTable);

						$joinClassName = $joinedTableObjectBuilder->getObjectClassname();

						$script .= "

	/**
	 * Selects a collection of $className objects pre-filled with their $joinClassName objects.
	 * @param      Criteria  \$criteria
	 * @param      PropelPDO \$con
	 * @param      String    \$join_behavior the type of joins to use, defaults to $join_behavior
	 * @return     array Array of $className objects.
	 * @throws     PropelException Any exceptions caught during processing will be
	 *		 rethrown wrapped into a PropelException.
	 */
	public static function doSelectJoin".$thisTableObjectBuilder->getFKPhpNameAffix($fk, $plural = false)."(Criteria \$criteria, \$con = null, \$join_behavior = $join_behavior)
	{
		\$criteria = clone \$criteria;

		// Set the correct dbName if it has not been overridden
		if (\$criteria->getDbName() == Propel::getDefaultDB()) {
			\$criteria->setDbName(self::DATABASE_NAME);
		}

		".$this->getPeerClassname()."::addSelectColumns(\$criteria);
		\$startcol = ".$this->getPeerClassname()."::getStartColumn();
		".$joinedTablePeerBuilder->getPeerClassname()."::addSelectColumns(\$criteria);
";

            $script .= $this->addCriteriaJoin($fk, $table, $joinTable, $joinedTablePeerBuilder);

            // apply behaviors
            $this->applyBehaviorModifier('preSelect', $script);

            $script .= "
		\$stmt = ".$this->basePeerClassname."::doSelect(\$criteria, \$con);
		\$results = array();

		while (\$row = \$stmt->fetch(PDO::FETCH_NUM)) {
			\$key1 = ".$this->getPeerClassname()."::getPrimaryKeyHashFromRow(\$row, 0);
			if (null !== (\$obj1 = ".$this->getPeerClassname()."::getInstanceFromPool(\$key1))) {
				// We no longer rehydrate the object, since this can cause data loss.
				// See http://propel.phpdb.org/trac/ticket/509
				// \$obj1->hydrate(\$row, 0, true); // rehydrate
			} else {
";
						if ($table->getChildrenColumn()) {
							$script .= "
				\$omClass = ".$this->getPeerClassname()."::getOMClass(\$row, 0);
				\$cls = substr('.'.\$omClass, strrpos('.'.\$omClass, '.') + 1);
";
						} else {
							$script .= "
				\$cls = ".$this->getPeerClassname()."::getOMClass(false);
";
						}
						$script .= "
				" . $this->buildObjectInstanceCreationCode('$obj1', '$cls') . "
				\$obj1->hydrate(\$row);
				".$this->getPeerClassname()."::addInstanceToPool(\$obj1, \$key1);
			} // if \$obj1 already loaded

			\$key2 = ".$joinedTablePeerBuilder->getPeerClassname()."::getPrimaryKeyHashFromRow(\$row, \$startcol);
			if (\$key2 !== null) {
				\$obj2 = ".$joinedTablePeerBuilder->getPeerClassname()."::getInstanceFromPool(\$key2);
				if (!\$obj2) {
";
						if ($joinTable->getChildrenColumn()) {
							$script .= "
					\$omClass = ".$joinedTablePeerBuilder->getPeerClassname()."::getOMClass(\$row, \$startcol);
					\$cls = substr('.'.\$omClass, strrpos('.'.\$omClass, '.') + 1);
";
						} else {
							$script .= "
					\$cls = ".$joinedTablePeerBuilder->getPeerClassname()."::getOMClass(false);
";
						}

						$script .= "
					" . $this->buildObjectInstanceCreationCode('$obj2', '$cls') . "
					\$obj2->hydrate(\$row, \$startcol);
					".$joinedTablePeerBuilder->getPeerClassname()."::addInstanceToPool(\$obj2, \$key2);
				} // if obj2 already loaded

				// Add the \$obj1 (".$this->getObjectClassname().") to \$obj2 (".$joinedTablePeerBuilder->getObjectClassname().")";
					if ($fk->isLocalPrimaryKey()) {
						$script .= "
				// one to one relationship
				\$obj1->set" . $joinedTablePeerBuilder->getObjectClassname() . "(\$obj2);";
					} else {
					$script .= "
				\$obj2->add" . $joinedTableObjectBuilder->getRefFKPhpNameAffix($fk, $plural = false)."(\$obj1);";
					}
					$script .= "

			} // if joined row was not null

			\$results[] = \$obj1;
		}
		\$stmt->closeCursor();
		return \$results;
	}
";
					} // if fk table name != this table name
				} // if ! is reference only
			} // foreach column
		} // if count(fk) > 1

	} // addDoSelectJoin()

  /**
	 * Adds the doSelectJoinAll() method.
	 * @param      string &$script The script will be modified in this method.
	 */
	protected function addDoSelectJoinAll(&$script)
	{
		$table = $this->getTable();
		$className = $this->getObjectClassname();
		$join_behavior = $this->getJoinBehavior();

		$script .= "

	/**
	 * Selects a collection of $className objects pre-filled with all related objects.
	 *
	 * @param      Criteria  \$criteria
	 * @param      PropelPDO \$con
	 * @param      String    \$join_behavior the type of joins to use, defaults to $join_behavior
	 * @return     array Array of $className objects.
	 * @throws     PropelException Any exceptions caught during processing will be
	 *		 rethrown wrapped into a PropelException.
	 */
	public static function doSelectJoinAll(Criteria \$criteria, \$con = null, \$join_behavior = $join_behavior)
	{
		\$criteria = clone \$criteria;

		// Set the correct dbName if it has not been overridden
		if (\$criteria->getDbName() == Propel::getDefaultDB()) {
			\$criteria->setDbName(self::DATABASE_NAME);
		}

		".$this->getPeerClassname()."::addSelectColumns(\$criteria);
		\$startcol = ".$this->getPeerClassname()."::getStartColumn();
";
		$index = 2;
		foreach ($table->getForeignKeys() as $fk) {

			// Want to cover this case, but the code is not there yet.
			// Propel lacks a system for aliasing tables of the same name.
			if ( $fk->getForeignTableName() != $table->getName() ) {
				$joinTable = $table->getDatabase()->getTable($fk->getForeignTableName());
				$new_index = $index + 1;

				$joinedTablePeerBuilder = $this->getNewPeerBuilder($joinTable);
				$joinClassName = $joinedTablePeerBuilder->getObjectClassname();

				$script .= "
		".$joinedTablePeerBuilder->getPeerClassname()."::addSelectColumns(\$criteria);
		\$startcol$new_index = \$startcol$index + ".$joinedTablePeerBuilder->getPeerClassname()."::getStartColumn();
";
				$index = $new_index;

			} // if fk->getForeignTableName != table->getName
		} // foreach [sub] foreign keys

		foreach ($table->getForeignKeys() as $fk) {
			// want to cover this case, but the code is not there yet.
			if ( $fk->getForeignTableName() != $table->getName() ) {
				$joinTable = $table->getDatabase()->getTable($fk->getForeignTableName());
				$joinedTablePeerBuilder = $this->getNewPeerBuilder($joinTable);
        $script .= $this->addCriteriaJoin($fk, $table, $joinTable, $joinedTablePeerBuilder);
			}
		}

		// apply behaviors
    $this->applyBehaviorModifier('preSelect', $script);

    $script .= "
		\$stmt = ".$this->basePeerClassname."::doSelect(\$criteria, \$con);
		\$results = array();

		while (\$row = \$stmt->fetch(PDO::FETCH_NUM)) {
			\$key1 = ".$this->getPeerClassname()."::getPrimaryKeyHashFromRow(\$row, 0);
			if (null !== (\$obj1 = ".$this->getPeerClassname()."::getInstanceFromPool(\$key1))) {
				// We no longer rehydrate the object, since this can cause data loss.
				// See http://propel.phpdb.org/trac/ticket/509
				// \$obj1->hydrate(\$row, 0, true); // rehydrate
			} else {";

		if ($table->getChildrenColumn()) {
			$script .= "
				\$omClass = ".$this->getPeerClassname()."::getOMClass(\$row, 0);
        \$cls = substr('.'.\$omClass, strrpos('.'.\$omClass, '.') + 1);
";
		} else {
			$script .= "
				\$cls = ".$this->getPeerClassname()."::getOMClass(false);
";
		}

		$script .= "
				" . $this->buildObjectInstanceCreationCode('$obj1', '$cls') . "
				\$obj1->hydrate(\$row);
				".$this->getPeerClassname()."::addInstanceToPool(\$obj1, \$key1);
			} // if obj1 already loaded
";

		$index = 1;
		foreach ($table->getForeignKeys() as $fk ) {
			// want to cover this case, but the code is not there yet.
			// Why not? -because we'd have to alias the tables in the JOIN
			if ( $fk->getForeignTableName() != $table->getName() ) {
				$joinTable = $table->getDatabase()->getTable($fk->getForeignTableName());

				$thisTableObjectBuilder = $this->getNewObjectBuilder($table);
				$joinedTableObjectBuilder = $this->getNewObjectBuilder($joinTable);
				$joinedTablePeerBuilder = $this->getNewPeerBuilder($joinTable);


				$joinClassName = $joinedTableObjectBuilder->getObjectClassname();
				$interfaceName = $joinClassName;

				if ($joinTable->getInterface()) {
					$interfaceName = $this->prefixClassname($joinTable->getInterface());
				}

				$index++;

				$script .= "
			// Add objects for joined $joinClassName rows

			\$key$index = ".$joinedTablePeerBuilder->getPeerClassname()."::getPrimaryKeyHashFromRow(\$row, \$startcol$index);
			if (\$key$index !== null) {
				\$obj$index = ".$joinedTablePeerBuilder->getPeerClassname()."::getInstanceFromPool(\$key$index);
				if (!\$obj$index) {
";
				if ($joinTable->getChildrenColumn()) {
					$script .= "
					\$omClass = ".$joinedTablePeerBuilder->getPeerClassname()."::getOMClass(\$row, \$startcol$index);
          \$cls = substr('.'.\$omClass, strrpos('.'.\$omClass, '.') + 1);
";
				} else {
					$script .= "
					\$cls = ".$joinedTablePeerBuilder->getPeerClassname()."::getOMClass(false);
";
				} /* $joinTable->getChildrenColumn() */

				$script .= "
					" . $this->buildObjectInstanceCreationCode('$obj' . $index, '$cls') . "
					\$obj".$index."->hydrate(\$row, \$startcol$index);
					".$joinedTablePeerBuilder->getPeerClassname()."::addInstanceToPool(\$obj$index, \$key$index);
				} // if obj$index loaded

				// Add the \$obj1 (".$this->getObjectClassname().") to the collection in \$obj".$index." (".$joinedTablePeerBuilder->getObjectClassname().")";
				if ($fk->isLocalPrimaryKey()) {
					$script .= "
				\$obj1->set".$joinedTablePeerBuilder->getObjectClassname()."(\$obj".$index.");";
				} else {
					$script .= "
				\$obj".$index."->add".$joinedTableObjectBuilder->getRefFKPhpNameAffix($fk, $plural = false)."(\$obj1);";
				}
				$script .= "
			} // if joined row not null
";

			} // $fk->getForeignTableName() != $table->getName()
		} //foreach foreign key

		$script .= "
			\$results[] = \$obj1;
		}
		\$stmt->closeCursor();
		return \$results;
	}
";

	} // end addDoSelectJoinAll()

  /**
	 * Adds the doSelectJoinAllExcept*() methods.
	 * @param      string &$script The script will be modified in this method.
	 */
	protected function addDoSelectJoinAllExcept(&$script)
	{
		$table = $this->getTable();
		$join_behavior = $this->getJoinBehavior();

		// ------------------------------------------------------------------------
		// doSelectJoinAllExcept*()
		// ------------------------------------------------------------------------

		// 2) create a bunch of doSelectJoinAllExcept*() methods
		// -- these were existing in original Torque, so we should keep them for compatibility

		$fkeys = $table->getForeignKeys();  // this sep assignment is necessary otherwise sub-loops over
		// getForeignKeys() will cause this to only execute one time.
		foreach ($fkeys as $fk ) {

			$tblFK = $table->getDatabase()->getTable($fk->getForeignTableName());

			$excludedTable = $table->getDatabase()->getTable($fk->getForeignTableName());

			$thisTableObjectBuilder = $this->getNewObjectBuilder($table);
			$excludedTableObjectBuilder = $this->getNewObjectBuilder($excludedTable);
			$excludedTablePeerBuilder = $this->getNewPeerBuilder($excludedTable);

			$excludedClassName = $excludedTableObjectBuilder->getObjectClassname();


			$script .= "

	/**
	 * Selects a collection of ".$this->getObjectClassname()." objects pre-filled with all related objects except ".$thisTableObjectBuilder->getFKPhpNameAffix($fk).".
	 *
	 * @param      Criteria  \$criteria
	 * @param      PropelPDO \$con
	 * @param      String    \$join_behavior the type of joins to use, defaults to $join_behavior
	 * @return     array Array of ".$this->getObjectClassname()." objects.
	 * @throws     PropelException Any exceptions caught during processing will be
	 *		 rethrown wrapped into a PropelException.
	 */
	public static function doSelectJoinAllExcept".$thisTableObjectBuilder->getFKPhpNameAffix($fk, $plural = false)."(Criteria \$criteria, \$con = null, \$join_behavior = $join_behavior)
	{
		\$criteria = clone \$criteria;

		// Set the correct dbName if it has not been overridden
		// \$criteria->getDbName() will return the same object if not set to another value
		// so == check is okay and faster
		if (\$criteria->getDbName() == Propel::getDefaultDB()) {
			\$criteria->setDbName(self::DATABASE_NAME);
		}

		".$this->getPeerClassname()."::addSelectColumns(\$criteria);
		\$startcol = ".$this->getPeerClassname()."::getStartColumn();
";
			$index = 2;
			foreach ($table->getForeignKeys() as $subfk) {
				// want to cover this case, but the code is not there yet.
				// Why not? - because we would have to alias the tables in the join
				if ( !($subfk->getForeignTableName() == $table->getName())) {
					$joinTable = $table->getDatabase()->getTable($subfk->getForeignTableName());
					$joinTablePeerBuilder = $this->getNewPeerBuilder($joinTable);
					$joinClassName = $joinTablePeerBuilder->getObjectClassname();

					if ($joinClassName != $excludedClassName) {
						$new_index = $index + 1;
						$script .= "
		".$joinTablePeerBuilder->getPeerClassname()."::addSelectColumns(\$criteria);
		\$startcol$new_index = \$startcol$index + ".$joinTablePeerBuilder->getPeerClassname()."::getStartColumn();
";
						$index = $new_index;
					} // if joinClassName not excludeClassName
				} // if subfk is not curr table
			} // foreach [sub] foreign keys

			foreach ($table->getForeignKeys() as $subfk) {
				// want to cover this case, but the code is not there yet.
				if ( $subfk->getForeignTableName() != $table->getName() ) {
					$joinTable = $table->getDatabase()->getTable($subfk->getForeignTableName());
					$joinedTablePeerBuilder = $this->getNewPeerBuilder($joinTable);
					$joinClassName = $joinedTablePeerBuilder->getObjectClassname();

					if ($joinClassName != $excludedClassName)
					{
            $script .= $this->addCriteriaJoin($subfk, $table, $joinTable, $joinedTablePeerBuilder);
					}
				}
			} // foreach fkeys

			// apply behaviors
      $this->applyBehaviorModifier('preSelect', $script);

      $script .= "

		\$stmt = ".$this->basePeerClassname ."::doSelect(\$criteria, \$con);
		\$results = array();

		while (\$row = \$stmt->fetch(PDO::FETCH_NUM)) {
			\$key1 = ".$this->getPeerClassname()."::getPrimaryKeyHashFromRow(\$row, 0);
			if (null !== (\$obj1 = ".$this->getPeerClassname()."::getInstanceFromPool(\$key1))) {
				// We no longer rehydrate the object, since this can cause data loss.
				// See http://propel.phpdb.org/trac/ticket/509
				// \$obj1->hydrate(\$row, 0, true); // rehydrate
			} else {";
			if ($table->getChildrenColumn()) {
				$script .= "
				\$omClass = ".$this->getPeerClassname()."::getOMClass(\$row, 0);
				\$cls = substr('.'.\$omClass, strrpos('.'.\$omClass, '.') + 1);
";
			} else {
				$script .= "
				\$cls = ".$this->getPeerClassname()."::getOMClass(false);
";
			}

			$script .= "
				" . $this->buildObjectInstanceCreationCode('$obj1', '$cls') . "
				\$obj1->hydrate(\$row);
				".$this->getPeerClassname()."::addInstanceToPool(\$obj1, \$key1);
			} // if obj1 already loaded
";

			$index = 1;
			foreach ($table->getForeignKeys() as $subfk ) {
		  // want to cover this case, but the code is not there yet.
		  if ( $subfk->getForeignTableName() != $table->getName() ) {

		  	$joinTable = $table->getDatabase()->getTable($subfk->getForeignTableName());

		  	$joinedTableObjectBuilder = $this->getNewObjectBuilder($joinTable);
		  	$joinedTablePeerBuilder = $this->getNewPeerBuilder($joinTable);

		  	$joinClassName = $joinedTableObjectBuilder->getObjectClassname();

		  	$interfaceName = $joinClassName;
		  	if ($joinTable->getInterface()) {
		  		$interfaceName = $this->prefixClassname($joinTable->getInterface());
		  	}

		  	if ($joinClassName != $excludedClassName) {

		  		$index++;

		  		$script .= "
				// Add objects for joined $joinClassName rows

				\$key$index = ".$joinedTablePeerBuilder->getPeerClassname()."::getPrimaryKeyHashFromRow(\$row, \$startcol$index);
				if (\$key$index !== null) {
					\$obj$index = ".$joinedTablePeerBuilder->getPeerClassname()."::getInstanceFromPool(\$key$index);
					if (!\$obj$index) {
	";

		  		if ($joinTable->getChildrenColumn()) {
		  			$script .= "
						\$omClass = ".$joinedTablePeerBuilder->getPeerClassname()."::getOMClass(\$row, \$startcol$index);
            \$cls = substr('.'.\$omClass, strrpos('.'.\$omClass, '.') + 1);
";
		  		} else {
		  			$script .= "
						\$cls = ".$joinedTablePeerBuilder->getPeerClassname()."::getOMClass(false);
";
		  		} /* $joinTable->getChildrenColumn() */
		  		$script .= "
					" . $this->buildObjectInstanceCreationCode('$obj' . $index, '$cls') . "
					\$obj".$index."->hydrate(\$row, \$startcol$index);
					".$joinedTablePeerBuilder->getPeerClassname()."::addInstanceToPool(\$obj$index, \$key$index);
				} // if \$obj$index already loaded

				// Add the \$obj1 (".$this->getObjectClassname().") to the collection in \$obj".$index." (".$joinedTablePeerBuilder->getObjectClassname().")";
				if ($subfk->isLocalPrimaryKey()) {
					$script .= "
				\$obj1->set".$joinedTablePeerBuilder->getObjectClassname()."(\$obj".$index.");";
				} else {
					$script .= "
				\$obj".$index."->add".$joinedTableObjectBuilder->getRefFKPhpNameAffix($subfk, $plural = false)."(\$obj1);";
				}
				$script .= "

			} // if joined row is not null
";
					} // if ($joinClassName != $excludedClassName) {
		  } // $subfk->getForeignTableName() != $table->getName()
			} // foreach
			$script .= "
			\$results[] = \$obj1;
		}
		\$stmt->closeCursor();
		return \$results;
	}
";
		} // foreach fk

	} // addDoSelectJoinAllExcept
}
