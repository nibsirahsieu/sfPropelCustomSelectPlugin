<?php
if (false === strpos(Propel::VERSION, '1.5')) {
  require_once 'propel/engine/builder/om/php5/PHP5ObjectBuilder.php';
} else {
  require_once 'builder/om/PHP5ObjectBuilder.php';
}
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

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
          \$this->setByName(\$selectedColumn, \$row[\$key], BasePeer::TYPE_COLNAME);
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
}
