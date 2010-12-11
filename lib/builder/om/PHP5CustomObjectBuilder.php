<?php

require_once 'propel/engine/builder/om/php5/PHP5ObjectBuilder.php';

class PHP5CustomObjectBuilder extends PHP5ObjectBuilder
{
  /**
	 * Adds the function body for the hydrate method
	 * @param      string &$script The script will be modified in this method.
	 * @see        addHydrate()
	 */
	/**
	 * Adds the function body for the hydrate method
	 * @param      string &$script The script will be modified in this method.
	 * @see        addHydrate()
	 */
	protected function addHydrateBody(&$script) {
		$table = $this->getTable();
		$platform = $this->getPlatform();
		$script .= "
    \$selectedColumns = ".$this->getPeerClassname()."::getSelectedColumns();
		try {
      if (empty(\$selectedColumns)) {";
		$n = 0;
		foreach ($table->getColumns() as $col) {
			if (!$col->isLazyLoad()) {
				$clo = strtolower($col->getName());
				if ($col->isLobType() && !$platform->hasStreamBlobImpl()) {
					$script .= "
        if (\$row[\$startcol + $n] !== null) {
          \$this->$clo = fopen('php://memory', 'r+');
          fwrite(\$this->$clo, \$row[\$startcol + $n]);
          rewind(\$this->$clo);
        } else {
          \$this->$clo = null;
        }";
				} elseif ($col->isPhpPrimitiveType()) {
					$script .= "
        \$this->$clo = (\$row[\$startcol + $n] !== null) ? (".$col->getPhpType().") \$row[\$startcol + $n] : null;";
				} elseif ($col->isPhpObjectType()) {
					$script .= "
        \$this->$clo = (\$row[\$startcol + $n] !== null) ? new ".$col->getPhpType()."(\$row[\$startcol + $n]) : null;";
				} else {
					$script .= "
        \$this->$clo = \$row[\$startcol + $n];";
				}
				$n++;
			} // if col->isLazyLoad()
		} /* foreach */
    $script .= "
      } else {
        foreach (\$selectedColumns as \$key => \$selectedColumn) {
          \$this->setByName(\$selectedColumn, \$row[\$key + \$startcol], BasePeer::TYPE_COLNAME);
        }
      }
    ";
    
		if ($this->getBuildProperty("addSaveMethod")) {
			$script .= "
			\$this->resetModified();
";
		}

		$script .= "
			\$this->setNew(false);

			if (\$rehydrate) {
				\$this->ensureConsistency();
			}";
    $script .="
      if (empty(\$selectedColumns)) {
        // FIXME - using NUM_COLUMNS may be clearer.
        return \$startcol + $n; // $n = ".$this->getPeerClassname()."::NUM_COLUMNS - ".$this->getPeerClassname()."::NUM_LAZY_LOAD_COLUMNS).
      } else {
        return \$startcol + count(\$selectedColumns);
      }
		} catch (Exception \$e) {
			throw new PropelException(\"Error populating ".$this->getStubObjectBuilder()->getClassname()." object\", \$e);
		}";
	}

  /**
	 * Adds the method that returns the referrer fkey collection.
	 * @param      string &$script The script will be modified in this method.
	 */
	protected function addRefFKGet(&$script, ForeignKey $refFK)
	{
		$table = $this->getTable();
		$tblFK = $refFK->getTable();

		$peerClassname = $this->getStubPeerBuilder()->getClassname();
		$fkPeerBuilder = $this->getNewPeerBuilder($refFK->getTable());
		$relCol = $this->getRefFKPhpNameAffix($refFK, $plural = true);

		$collName = $this->getRefFKCollVarName($refFK);
		$lastCriteriaName = $this->getRefFKLastCriteriaVarName($refFK);

		$className = $fkPeerBuilder->getObjectClassname();

		$script .= "
	/**
	 * Gets an array of $className objects which contain a foreign key that references this object.
	 *
	 * If this collection has already been initialized with an identical Criteria, it returns the collection.
	 * Otherwise if this ".$this->getObjectClassname()." has previously been saved, it will retrieve
	 * related $relCol from storage. If this ".$this->getObjectClassname()." is new, it will return
	 * an empty collection or the current collection, the criteria is ignored on a new object.
	 *
	 * @param      PropelPDO \$con
	 * @param      Criteria \$criteria
	 * @return     array {$className}[]
	 * @throws     PropelException
	 */
	public function get$relCol(\$criteria = null, PropelPDO \$con = null, array \$selectedColumns = null)
	{";

		$script .= "
		if (\$criteria === null) {
			\$criteria = new Criteria($peerClassname::DATABASE_NAME);
		}
		elseif (\$criteria instanceof Criteria)
		{
			\$criteria = clone \$criteria;
		}

		if (\$this->$collName === null) {
			if (\$this->isNew()) {
			   \$this->$collName = array();
			} else {
";
		foreach ($refFK->getLocalColumns() as $colFKName) {
			// $colFKName is local to the referring table (i.e. foreign to this table)
			$lfmap = $refFK->getLocalForeignMapping();
			$localColumn = $this->getTable()->getColumn($lfmap[$colFKName]);
			$colFK = $refFK->getTable()->getColumn($colFKName);

			$clo = strtolower($localColumn->getName());

			$script .= "
				\$criteria->add(".$fkPeerBuilder->getColumnConstant($colFK).", \$this->$clo);
";
		} // end foreach ($fk->getForeignColumns()

		$script .= "
        if (null !== \$selectedColumns) {
          ".$fkPeerBuilder->getPeerClassname()."::setSelectedColumns(\$selectedColumns);
        }
				".$fkPeerBuilder->getPeerClassname()."::addSelectColumns(\$criteria);
				\$this->$collName = ".$fkPeerBuilder->getPeerClassname()."::doSelect(\$criteria, \$con);
			}
		} else {
			// criteria has no effect for a new object
			if (!\$this->isNew()) {
				// the following code is to determine if a new query is
				// called for.  If the criteria is the same as the last
				// one, just return the collection.
";
		foreach ($refFK->getLocalColumns() as $colFKName) {
			// $colFKName is local to the referring table (i.e. foreign to this table)
			$lfmap = $refFK->getLocalForeignMapping();
			$localColumn = $this->getTable()->getColumn($lfmap[$colFKName]);
			$colFK = $refFK->getTable()->getColumn($colFKName);
			$clo = strtolower($localColumn->getName());
			$script .= "

				\$criteria->add(".$fkPeerBuilder->getColumnConstant($colFK).", \$this->$clo);
";
		} // foreach ($fk->getForeignColumns()
		$script .= "
        if (null !== \$selectedColumns) {
          ".$fkPeerBuilder->getPeerClassname()."::setSelectedColumns(\$selectedColumns);
        }
				".$fkPeerBuilder->getPeerClassname()."::addSelectColumns(\$criteria);
				if (!isset(\$this->$lastCriteriaName) || !\$this->".$lastCriteriaName."->equals(\$criteria)) {
					\$this->$collName = ".$fkPeerBuilder->getPeerClassname()."::doSelect(\$criteria, \$con);
				}
			}
		}
		\$this->$lastCriteriaName = \$criteria;
		return \$this->$collName;
	}
";
	} // addRefererGet()
}

