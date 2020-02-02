<?php

class RCON {
	
	private $server;
	
	private $name = 'Scheduled Job';
	private $type = 'once';
	private $commands = array();

	private $runtime = NULL;
	private $delay = 0;
	
	private $interval_unit = NULL;
	private $interval_value = NULL;
	
	public function __construct( LiFServer $server ) {
		$this->server = $server;
	}
	
	public function add_command( $command, $param1 = '', $param2 = '', $detail = '' ) {
		$this->commands[] = array('command' => $command, 'param1' => $param1, 'param2' => $param2, 'detail' => $detail);
	}
	
	public function set_schedule( $unit, $value ) {
		$this->interval_unit  = $unit;
		$this->interval_value = $value;
		$this->type = 'repeat';
	}
	
	public function set_time( $runtime ) {
		$this->runtime = $runtime;
	}
	
	public function set_delay( $minutes ) {
		$this->delay = $minutes;
	}
	
	public function bind_event( $type, $value ) {
		/* reserved */
	}
	
	public function submit() {
		
		if( $this->server->get_ttmod_version() >= 1.4 ) {
			
			$runtime_expression = $this->runtime === NULL ? 'NOW()' : "'{$this->runtime['date']} {$this->runtime['hour']}:{$this->runtime['minute']}:00'";
			$runtime_expression = $this->delay > 0 ? "DATE_ADD(NOW(), INTERVAL $this->delay MINUTE)" : $runtime_expression;
			$interval_unit = $this->type === 'repeat' ? "'$this->interval_unit'" : 'NULL';
			$interval_value = $this->type === 'repeat' ? "'$this->interval_value'" : 'NULL';
			
			foreach( $this->commands AS $c ) {
				$this->server->passthru_db_query( "INSERT INTO nyu_rcon_schedule (name, type, runtime, interval_unit, interval_value, command, param1, param2, detail) VALUES ('{$this->name}', '{$this->type}', $runtime_expression, $interval_unit, $interval_value, '{$c['command']}', '{$c['param1']}', '{$c['param2']}', '{$c['detail']}')" );
			}
			
			$this->type === 'once' || $this->server->passthru_db_query("SELECT * FROM nyu_rcon_schedule WHERE command = 'reload_schedule'") || $this->server->passthru_db_query("INSERT INTO nyu_rcon_schedule (type, command) VALUES ('once', 'reload_schedule')");
		
		} else {
			
			// Add RCON command to legacy table through LiFServer object
			foreach( $this->commands AS $command ) $this->server->add_rcon_command( $command['command'], $command['param1'], $command['param2'], $command['detail'], $this->delay );
			
		}
		
	}
	
}