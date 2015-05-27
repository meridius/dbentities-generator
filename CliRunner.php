<?php

namespace App;

use \Tester\Runner\CommandLine;

class CliRunner {
	
	/** @var array */
	private $options;
	
	/** @return int|NULL */
	public function run() {
		try {
			$cmd = $this->loadOptions();
		} catch (\Exception $ex) {
			return $ex->getMessage();
		}
		
		if ($cmd->isEmpty() || $this->options['--help']) {
			$cmd->help();
			return;
		}
		
		$generator = new EntityGenerator(
			$this->options['-s'], 
			$this->options['-n'], 
			$this->options['-d'], 
			$this->options['-a'], 
			$this->options['-f']
		);
		
		return $generator->generate() ? 0 : 1;
	}
	
	/** @return CommandLine */
	private function loadOptions() {
		$cmd = new CommandLine(<<<XX
Name:
    DB Entities Generator

Description:
    Generate database entities for use with Nette Database.

Usage: 
    index.php [options]

Options:
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
    -f                    Remove destination directory if exists - use force.
                            [optional] (default: true)
    -h | --help           This help.

XX
		, array(
			'-s' => array(CommandLine::REALPATH => TRUE),
			'-n' => array(CommandLine::VALUE => 'DbEntity'),
			'-d' => array(CommandLine::OPTIONAL => TRUE, CommandLine::VALUE => ''),
			'-a' => array(CommandLine::OPTIONAL => TRUE, CommandLine::VALUE => TRUE),
			'-f' => array(CommandLine::OPTIONAL => TRUE, CommandLine::VALUE => TRUE),
		));

		$this->options = $cmd->parse();
		
		return $cmd;
	}

	
}
