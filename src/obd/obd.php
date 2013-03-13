<?php
	// obd.php - Matt Layher, 3/13/13
	// OBD-II class for vehicle self-diagnostics, written in PHP
	//
	// changelog
	//
	// 3/13/13 MDL:
	//	- initial commit

	namespace obd;

	require_once 'php_serial.class.php';

	class obd
	{
		// CONSTANTS - - - - - - - - - - - - - - - - - - - -
		// Default communication baudrate (baud)
		const BAUD_DEFAULT = 9600;
		const BAUD_FAST = 38400;

		// STATIC VARIABLES - - - - - - - - - - - - - - - -

		// Verbosity
		private static $verbose = 0;

		// INSTANCE VARIABLES - - - - - - - - - - - - - - -

		// Instance variables
		private $device;
		private $baudrate;
		private $serial;

		// PUBLIC PROPERTIES - - - - - - - - - - - - - - - -

		// device:
		//  - get: device
		//	- set: device (validated by file_exists())
		public function get_device()
		{
			return $this->device;
		}
		public function set_device($device)
		{
			if (file_exists($device))
			{
				$this->device = $device;
				return true;
			}

			return false;
		}

		// baudrate:
		//  - get: baudrate
		//	- set: baudrate (validated by is_int())
		public function get_baudrate()
		{
			return $this->baudrate;
		}
		public function set_baudrate($baudrate)
		{
			if (is_int($baudrate))
			{
				$this->baudrate = $baudrate;
				return true;
			}

			return false;
		}

		// CONSTRUCTOR/DESTRUCTOR - - - - - - - - - - - - -

		// Construct obd object using specified device at specified baudrate
		public function __construct($device, $baudrate = self::BAUD_DEFAULT, $verbose = 0)
		{
			// Attempt to set device...
			if (!$this->set_device($device))
			{
				trigger_error("obd->__construct() unable to set device '" . $device . "'", E_USER_WARNING);
			}

			// Attempt to set baudrate...
			if (!$this->set_baudrate($baudrate))
			{
				trigger_error("obd->__construct() unable to set baudrate '" . $baudrate . "'", E_USER_WARNING);
			}

			// Set verbosity
			if ($verbose)
			{
				$this->verbose();
			}

			// Attempt connection
			$this->connect();

			return;
		}

		// Close connection on destruct
		public function __destruct()
		{
			$this->close();
			return;
		}

		// PRIVATE METHODS - - - - - - - - - - - - - - - - -

		// Connect to OBD-II device
		private function connect()
		{
			if (self::$verbose)
			{
				printf("obd->connect() opening connection to %s @ %d baud\n", $this->device, $this->baudrate);
			}

			// Use phpSerial library to create connection
			$this->serial = new \phpSerial\phpSerial();

			// Set parameters specified in object
			$this->serial->deviceSet($this->device);
			$this->serial->confBaudRate($this->baudrate);

			// Set default parameters for OBD-II communication
			$this->serial->confParity("none");
			$this->serial->confCharacterLength(8);
			$this->serial->confStopBits(1);
			$this->serial->confFlowControl("none");

			// Open connection!
			$this->serial->deviceOpen();

			// Attempt identification
			$id = $this->command("AT I");
			if (!$id)
			{
				trigger_error("odb->connect() failed to identify ELM327 ODB-II device", E_USER_ERROR);
				return false;
			}
			else
			{
				if (self::$verbose)
				{
					printf("obd->connect() id: %s\n", $id);
				}
			}

			// Disable echo, line feed, spaces
			$this->command("AT E0");
			$this->command("AT L0");
			$this->command("AT S0");

			if (self::$verbose)
			{
				printf("obd->connect() success!\n");
			}

			return true;
		}

		// Disconnect from OBD-II device
		private function close()
		{
			if (self::$verbose)
			{
				printf("obd->close() closing connection\n");
			}

			$this->serial->deviceClose();
			return true;
		}

		// PUBLIC METHODS - - - - - - - - - - - - - - - - -

		// Enable verbose mode
		public function verbose()
		{
			self::$verbose = 1;
			return true;
		}

		// Issue command and retrieve output from OBD-II device
		public function command($data)
		{
			$this->write($data);
			$out = $this->read();

			// Check for invalid command return from device
			if ($out === '?')
			{
				trigger_error("odb->command() received invalid command '" . $data . "'", E_USER_WARNING);
				return null;
			}

			return $out;
		}

		// Read data from OBD-II device
		public function read()
		{
			$data = substr(trim($this->serial->readPort()), 2);

			if (self::$verbose)
			{
				printf("obd->read(): '%s'\n", $data);
			}

			return $data;
		}

		// Write data to ODB-II device
		public function write($data)
		{
			if (self::$verbose)
			{
				printf("odb->write(): '%s'\n", $data);
			}

			return $this->serial->sendMessage($data . "\r");
		}
	}
