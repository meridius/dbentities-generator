# dbentities-generator
*Generator of database entities for use with Nette Database*

Are you tired of manually typing table and column names when dealing with Nette Database (and other similar DB frameworks) yet? You don't have to now!

## Why should I use that
- easy setup
- simple usage
- column and table name auto-hinting by IDE
- column / variable type hinting
- end of referring to table/column names by hard-to-trace strings
- simpler table joining

## How to setup
Simply run generators' `php index.php -s path/to/your_scheme.sql` which will parse given scheme SQL file and create respective entity files.

### Other options
    -s <path>             SQL file with schema to be parsed to entities.
    -n <namespace>        What namespace to put generated entities into. 
                          Will be used also as destination directory.
                            (default: DbEntity)
    -d <database name>    Used as part of namespace and directory for entities.
                            [optional] (default: none)
    -a                    Generate also absolute constants. This will generate: 
                            const __COLUMN_NAME = 'table.column_name';
                          Constant name is prefixed with (__) two underscores.
                            [optional] (default: true)
    -e                    Enquote table and column names. This will generate: 
                            const __COLUMN_NAME = '`table`.`column_name`';
                            [optional] (default: false)
    -f                    Remove destination directory if exists - use force.
                            [optional] (default: true)
    -h | --help           This help.

## Use of generated entities

### Where can I use them
Primary use of generated DB entities is targeted to projects based on Nette (and derived) frameworks using Nette Database.

### Prerequisites for other frameworks
Should you decide to use entities with other framework than Nette or none at all, please note that you will need to include these two components to your project:

- [Nette Utils](https://github.com/nette/utils) for Nette\Object
- [Nette Database](https://github.com/nette/database) for Nette\Database\Table\ActiveRow

### Example usage

- You can set values to entity straight to its properties.
- Function `$entity->getArray()` will get associative array from entity, ready for Nette Databases' `->insert()` or `->update()` functions.
- Passing an `ActiveRow` object to entity constructor will set properties to that entity based on values in `ActiveRow`.

#### Create new record

	$movie = new Movie;
	$movie->name = $values["name"];
	$movie->year = $values["year"];
	$movieManager->create($movie);

	# ---------- MovieManager ----------
	public function create(Movie $movie) {
		$this->db->table($movie->getTableName())->insert($movie->getArray());
	}

#### Get existing record
	
	$movie = $movieManager->getByName($values["name"]);
	echo $movie->year;
	
	# ---------- MovieManager ----------
	public function getByName($name) {
		$row = $this->db->table(Movie::getTableName())->where(array(
				Movie::NAME => $name,
			))->fetch();
		return ($row instanceof ActiveRow) ? new Movie($row) : null;
	}
	
#### Get existing record by value from another table
	public function getByTagName($tagTitle) {
		$row = $this->db->table(Movie::getTableName())
			->select(Movie::getTableName() . '.*')
			->where(array(
				Tag::__TITLE => $tagTitle, // see (-a) option for description
			))->fetch();
		return ($row instanceof ActiveRow) ? new Movie($row) : null;
	}

## Notes
The generator will parse MySQL files, syntax for other database types was not tested.