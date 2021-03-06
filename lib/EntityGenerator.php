<?php

namespace App;

use \Nette\PhpGenerator\PhpFile,
		\Meridius\Helpers\StringHelper,
		\Meridius\Helpers\FileHelper,
		\Nette\Object;

class EntityGenerator extends Object {

	private $inputFile;
	private $generateAbsoluteConstants;
	private $namespaceRoot;
	private $namespaceDb;
	private $destDirRoot;
	private $destDirDb;
	private $enquoteNames;
	private $abstractEntityFileName;

	/**
	 *
	 * @param string $inputSchemafileName
	 * @param string $namespaceRoot What namespace to put generated entities to. Will be used also as destination directory.
	 * @param string $dbName [optional] Defaults to null. Used as part of namespace and directory for entities.
	 * @param bool $generateAbsoluteConstants [optional] Defaults to true.<br />
	 *  This will also generate <b>const __COLUMN_NAME = 'table.column_name';</b><br />
	 *  Constant name is prefixed with (__) two underscores.
	 * @param bool $enquoteNames [optional] Defaults to false.<br />
	 *  This will enquote table and column names: <b>const __COLUMN_NAME = '`table`.`column_name`';</b>
	 * @param bool $useForce [optional] Defaults to true.<br />
	 *  Remove destination directory if exists.
	 */
	public function __construct(
		$inputSchemafileName, 
		$namespaceRoot, 
		$dbName = null, 
		$generateAbsoluteConstants = true, 
		$enquoteNames = false,
		$useForce = true
	) {
		$this->namespaceRoot = StringHelper::toPascalCase($namespaceRoot);
		$this->namespaceDb = StringHelper::toPascalCase($dbName);
		$this->destDirRoot = $namespaceRoot;
		$this->destDirDb = $dbName;
		$this->inputFile = $inputSchemafileName;
		$this->generateAbsoluteConstants = $generateAbsoluteConstants;
		$this->enquoteNames = $enquoteNames;
		if (file_exists($this->destDirRoot) && $useForce) {
			FileHelper::deleteDir($this->destDirRoot);
		}
		mkdir($this->destDirRoot);
		$this->copyAbstractEntity();
	}

	/**
	 * Will generate database entities.
	 */
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
	
	private function copyAbstractEntity() {
		$this->abstractEntityFileName = __DIR__ . DIRECTORY_SEPARATOR . 'AbstractDBEntity.php';
		$filename = $this->destDirRoot . DIRECTORY_SEPARATOR . basename($this->abstractEntityFileName);
		touch($filename);
		$content = file_get_contents($this->abstractEntityFileName);
		$contentChanged = preg_replace("/namespace \w*;/m", "namespace $this->namespaceRoot;", $content);
		file_put_contents($filename, $contentChanged);
	}
	
	/**
	 * 
	 * @param string $name
	 * @return string
	 */
	private function getName($name) {
		return $this->enquoteNames ? "`$name`" : $name;
	}

	/**
	 *
	 * @param string $row
	 * @return string|bool table name OR false
	 */
	private function getTableName($row) {
		$matches = array();
		return preg_match("/^\s*create table.*`(.*)` \($/i", $row, $matches) ? $matches[1] : false;
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
	 * @return string
	 */
	private function getColumnAttributes($row) {
		$attributes = $this->getColumn($row, "attr");
		return $this->identifyVarType($attributes) . ' ' . $attributes;
	}

	/**
	 * 
	 * @param string $row
	 * @param string $resultType
	 * @return boolean|array
	 * @throws \Exception
	 */
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
		$phpFile = new PhpFile();
		$namespaceName = empty($this->namespaceDb) ? $this->namespaceRoot : $this->namespaceRoot . '\\' . $this->namespaceDb;
		$namespace = $phpFile->addNamespace($namespaceName);
		$class = $namespace->addClass(StringHelper::toPascalCase($tableEntity->tableName));
		$class->addExtend("\\" . $this->namespaceRoot . "\\AbstractDBEntity");

		$mGetTableName = $class->addMethod("getTableName");
		$mGetTableName
			->addDocument("@return string")
			->setVisibility("public")
			->setStatic(true)
			->addBody("return '" . $this->getName($tableEntity->tableName) . "';");

		foreach ($tableEntity->columns as $name => $attributes) {
			$class->addConst(StringHelper::toConstCase($name), $this->getName($name));
		}

		if ($this->generateAbsoluteConstants) {
			foreach ($tableEntity->columns as $name => $attributes) {
				$class->addConst('__' . StringHelper::toConstCase($name), $this->getName($tableEntity->tableName) . '.' . $this->getName($name));
			}
		}

		$mappingBody = array();
		foreach ($tableEntity->columns as $name => $attributes) {
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
		$this->writeFile($phpFile, $tableEntity->tableName);
	}

	/**
	 * 
	 * @param PhpFile $content
	 * @param string $tableName
	 */
	private function writeFile(PhpFile $content, $tableName) {
		$fileName = StringHelper::toPascalCase($tableName) . ".php";
		$dirName = empty($this->destDirDb) ? '' : $this->destDirDb . DIRECTORY_SEPARATOR;
		$fullFileName = $this->destDirRoot . DIRECTORY_SEPARATOR . $dirName . $fileName;
		$fullDirName = dirname($fullFileName);
		if (!is_dir($fullDirName)) {
			mkdir($fullDirName, 0777, true);
		}
		touch($fullFileName);
		file_put_contents($fullFileName, (string) $content);
	}

}

class TableEntity extends Object {

	public $tableName;

	/** @var string[] columnName => columnAttributes */
	public $columns = array();

}
