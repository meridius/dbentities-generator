<?php

namespace App;

use \Nette\PhpGenerator\PhpFile,
		\Meridius\Helpers\StringHelper,
		\Meridius\Helpers\FileHelper,
		\Nette\Object;

class EntityGenerator extends Object {

	private $destDir;
	private $inputFile;
	private $dbName;

	/**
	 * 
	 * @param string $inputSchemafileName
	 * @param string $destDir
	 * @param string $dbName
	 */
	public function __construct($inputSchemafileName, $destDir, $dbName) {
		if (file_exists($destDir)) {
			FileHelper::deleteDir($destDir);
		}
		mkdir($destDir);
		$this->destDir = StringHelper::toPascalCase($destDir);
		$this->inputFile = $inputSchemafileName;
		$this->dbName = StringHelper::toPascalCase($dbName);
	}

	public function generate() {
		$tableEntities = $this->parseSchema();
		foreach ($tableEntities as $tableEntity) {
			$this->generateEntityFile($tableEntity);
		}
	}

	/**
	 * 
	 * @return TableEntity[]
	 */
	private function parseSchema() {
		$tableEntities = array();
		$handle = fopen($this->inputFile, "r");
		$tableEntity = null;
		while (($row = fgets($handle)) !== false) {
			$row = str_replace("\r\n", "", $row);
			if ($tableName = $this->getTableName($row)) {
				if ($tableEntity instanceof TableEntity) {
					$tableEntities[] = clone $tableEntity;
					$tableEntity = null;
				}
				$tableEntity = new TableEntity();
				$tableEntity->tableName = $tableName;
			} else if ($columnName = $this->getColumnName($row)) {
				$tableEntity->columns[$columnName] = $this->getColumnAttributes($row);
			} else if ($this->isTableEnd($row)) {
				if ($tableEntity instanceof TableEntity) {
					$tableEntities[] = clone $tableEntity;
					$tableEntity = null;
				}
			}
		}
		fclose($handle);
		return $tableEntities;
	}
	
	/**
	 * 
	 * @param string $row
	 * @return string|bool table name OR false
	 */
	private function getTableName($row) {
		$matches = array();
		return preg_match("/^.*create table.*`(.*)` \($/i", $row, $matches)
			? $matches[1]
			: false;
	}
	
	/**
	 * 
	 * @param string $row
	 * @return string|bool column name OR false
	 */
	private function getColumnName($row) {
		return $this->getColumn($row, "name");
	}
	
	/**
	 * 
	 * @param string $row
	 * @return 
	 */
	private function getColumnAttributes($row) {
		$attributes = $this->getColumn($row, "attr");
		return $this->identifyVarType($attributes) . ' ' . $attributes;
	}
	
	private function getColumn($row, $resultType) {
		$matches = array();
		if (preg_match("/^\s*`(\w*)`\s+(.*)$/i", $row, $matches)) {
			switch ($resultType) {
				case "name":
					return $matches[1];
				case "attr":
					return trim($matches[2], ' ,');
				default:
					throw new \Exception("Invalid type '$resultType' given.");
			}
		} else {
			return false;
		}
	}
	
	/**
	 * 
	 * @param string $columnAttributes
	 * @return string
	 */
	private function identifyVarType($columnAttributes) {
		$types = array(
			'string' => array(
				'CHAR',
				'VARCHAR',
				'TINYTEXT',
				'TEXT',
				'BLOB',
				'MEDIUMTEXT',
				'MEDIUMBLOB',
				'LONGTEXT',
				'LONGBLOB',
				'ENUM',
				'SET',
				'TIME',
			),
			'int' => array(
				'TINYINT',
				'SMALLINT',
				'MEDIUMINT',
				'INT',
				'BIGINT',
				'TIMESTAMP',
				'YEAR',
			),
			'float' => array(
				'FLOAT',
				'DOUBLE',
				'DECIMAL',
			),
			'\DateTime' => array(
				'DATE',
				'DATETIME',
			),
		);
		$ar = explode(" ", $columnAttributes);
		$first = $ar[0];
		foreach ($types as $varType => $colTypes) {
			foreach ($colTypes as $colType) {
				if (preg_match('/^' . $colType . '\s*(\(.*\))?$/i', $first)) {
					return $varType;
				}
			}
		}
	}
	
	/**
	 * 
	 * @param string $row
	 * @return bool
	 */
	private function isTableEnd($row) {
		return preg_match("/^.*;$/", $row);
	}

	/**
	 * 
	 * @param \App\TableEntity $tableEntity
	 */
	private function generateEntityFile(TableEntity $tableEntity) {
		$fullName = implode(DIRECTORY_SEPARATOR, array(
			StringHelper::toCamelCase($this->destDir),
			StringHelper::toCamelCase($this->dbName),
			StringHelper::toPascalCase($tableEntity->tableName) . ".php",
		));
		$dirName = dirname($fullName);
		if (!is_dir($dirName)) {
			mkdir($dirName, 0777, true);
		}
		touch($fullName);
		$phpFile = new PhpFile();
		$namespace = $phpFile->addNamespace($this->destDir . "\\" . $this->dbName);
		$class = $namespace->addClass(StringHelper::toPascalCase($tableEntity->tableName));
		$class->addExtend("\\" . $this->destDir . "\\AbstractDBEntity");
		
		$mGetTableName = $class->addMethod("getTableName");
		$mGetTableName
			->addDocument("@return string")
			->setVisibility("public")
			->setStatic(true)
			->addBody("return '$tableEntity->tableName';");
		
		$mappingBody = array();
		foreach ($tableEntity->columns as $name => $attributes) {
			$class->addConst(StringHelper::toConstCase($name), $name);
			$prop = $class->addProperty(StringHelper::toCamelCase($name));
			$prop
				->addDocument("@var $attributes")
				->setVisibility("public");
			$mappingBody[] = "\tself::" . StringHelper::toConstCase($name) . ' => &$this->' . StringHelper::toCamelCase($name) . ",";
		}
		
		$mGetMappingArray = $class->addMethod("getMappingArray");
		$mGetMappingArray
			->addDocument("@return array")
			->setVisibility("protected")
			->addBody(
				'return array(' . "\n"
				. implode("\n", $mappingBody) . "\n"
				. ');'
		);
		file_put_contents($fullName, (string) $phpFile);
	}

}

class TableEntity extends Object {

	public $tableName;

	/** @var array columnName => [column attributes] */
	public $columns = array();
	
}
