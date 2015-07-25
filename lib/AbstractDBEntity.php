<?php

namespace dbEntity;

use \Nette\Database\Table\ActiveRow;

/**
 * Every entity extending this have to define (apart from functions):
 * - constants of table column names
 * - public properties for values
 */
abstract class AbstractDBEntity extends \Nette\Object implements IDBEntity {
	
	public function __construct(ActiveRow $row = null) {
		if (!is_null($row)) {
			$this->loadFromActiveRow($row);
		}
	}

	/**
	 * 
	 * @param \Nette\Database\Table\ActiveRow $row
	 * @return \dbEntities\AbstractDBEntity
	 */
	public function loadFromActiveRow(ActiveRow $row) {
		$referenceVar = $this->getReferenceArray();
		foreach ($referenceVar as $column => &$varRef) {
			if ($row->offsetExists($column)) {
				$varRef = $row[$column];
			}
		}
		return $this;
	}

	/**
	 * Return ID for row represent NULL value in all lists
	 * @return int
	 */
	public static function getNullId() {
		return -1;
	}

	/**
	 * Returns mapping array
	 * @optional
	 * @return array of table column => value for further processing by 
	 */
	protected function getMappingArray() {
//		return array(
//			self::EID_CREATED => &$this->eidCreated,
//		);
	}

	/**
	 * 
	 * @return array
	 */
	public function getArray() {
		try {
			$this->getReflection()->getMethod("getMappingArray");
			$isMappingMethodDefined = true;
		} catch (\Exception $e) {
			$isMappingMethodDefined = false;
		}
		return $isMappingMethodDefined ? $this->getArrayByMapping() : $this->getArrayByVariables(TRUE);
	}

	private function getReferenceArray() {
		try {
			$this->getReflection()->getMethod("getMappingArray");
			$isMappingMethodDefined = true;
		} catch (\Exception $e) {
			$isMappingMethodDefined = false;
		}
		return $isMappingMethodDefined ? $this->getMappingArray() : $this->getArrayByVariables(FALSE);
	}

	/**
	 * Return as array only values that were set in entity.
	 * Parsing is done by mapping array.
	 * @return array of setted values
	 */
	private function getArrayByMapping() {
		$a = $this->getMappingArray();
		$result = array();
		foreach ($a as $key => $value) {
			if (!is_null($value)) {
				$result[$key] = $value;
			}
		}
		return $result;
	}

	/**
	 * Return as array only values that were set in entity.
	 * Parsing is done by dynamically matching constants against properties.
	 * @param bool $unsetNull
	 * @return array of setted values
	 */
	private function getArrayByVariables($unsetNull) {
		$out = array();
		$constants = $this->getReflection()->getConstants();
		$variables = get_object_vars($this);
		$constantsNew = $this->changeArrayKeys($constants);
		$variablesNew = $this->changeArrayKeys($variables);
		foreach ($constantsNew as $name => $column) {
			if ($unsetNull) {
				if (!is_null($variablesNew[$name])) {
					$out[$column] = $variablesNew[$name];
				}
			} else {
				$out[$column] = &$variablesNew[$name];
			}
		}
		return $out;
	}

	/**
	 * Make array keys lowercase and strip "_" underscores.
	 * @param array $array
	 * @return array
	 */
	private function changeArrayKeys($array) {
		$aNew = array();
		foreach ($array as $key => $value) {
			$keyNew = str_replace("_", "", strtolower($key));
			$aNew[$keyNew] = $value;
		}
		return $aNew;
	}

}

interface IDBEntity {

	/**
	 * @return string
	 */
	public static function getTableName();
}
