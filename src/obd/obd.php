<?php
	// obd.php - Matt Layher, 3/13/13
	// ELM327 OBD-II class for vehicle self-diagnostics, written in PHP
	//
	// changelog
	//
	// 3/13/13 MDL:
	//	- initial commit

	namespace obd;
	use \serial\serial as serial;

	class obd
	{
		// CONSTANTS - - - - - - - - - - - - - - - - - - - -

		// Default communication baudrate (baud)
		const BAUD_DEFAULT = 9600;
		const BAUD_FAST = 38400;

		// Default units for system readings
		const UNIT_ENGLISH = 0;
		const UNIT_METRIC = 1;

		// STATIC VARIABLES - - - - - - - - - - - - - - - -

		// Verbosity
		private static $verbose = 0;

		// INSTANCE VARIABLES - - - - - - - - - - - - - - -

		// Instance variables
		private $device;
		private $baudrate;
		private $units;

		// Serial connection to OBD-II device
		private $serial;

		// Array of anonymous functions accessible via array
		// e.g. $obd->pid['engine_rpm']();
		public $pid;

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

		// units:
		//  - get: units
		//	- set: units (validated against constants)
		public function get_units()
		{
			return $this->units;
		}
		public function set_units($units)
		{
			if ($units === self::UNIT_ENGLISH || $units === self::UNIT_METRIC)
			{
				$this->units = $units;
				return true;
			}

			return false;
		}

		// CONSTRUCTOR/DESTRUCTOR - - - - - - - - - - - - -

		// Construct obd object using specified device at specified baudrate
		public function __construct($device, $baudrate = self::BAUD_DEFAULT, $units = self::UNIT_ENGLISH, $verbose = 0)
		{
			// Attempt to set device...
			if (!$this->set_device($device))
			{
				throw new \Exception("Unable to set device for OBD-II connection");
			}

			// Attempt to set baudrate...
			if (!$this->set_baudrate($baudrate))
			{
				throw new \Exception("Unable to set baudrate for OBD-II connection");
			}

			// Attempt to set units...
			if (!$this->set_units($units))
			{
				trigger_error("obd->__construct() invalid unit system specified, defaulting to obd::UNIT_ENGLISH", E_USER_WARNING);
				$this->units = self::UNIT_ENGLISH;
			}

			// Set verbosity
			if ($verbose)
			{
				$this->verbose();
			}

			// Create array of anonymous functions to handle varying PID calls
			$this->pid = array(
				"fuel_pressure" => function()
					{
						// 01 0A - fuel pressure
						$fuel = hexdec(substr($this->command("01 0A"), 5, 2)) * 3;

						// Check range
						if (!self::in_range($fuel, 0, 765))
						{
							$fuel = 0;
						}

						// English -> psi
						if ($this->units === self::UNIT_ENGLISH)
						{
							return sprintf("%0.2fpsi", $fuel * 0.145037738);
						}
						// Metric -> kPa
						else
						{
							return sprintf("%0.2fkPa", $fuel);
						}
					},
				"engine_rpm" => function()
					{
						// 01 0C - engine RPM
						$rpm = hexdec(substr($this->command("01 0C"), 5)) / 4;

						// Check range
						if (!self::in_range($rpm, 0, 16383.75))
						{
							$rpm = 0;
						}

						return sprintf("%0.2frpm", $rpm);
					},
				"speed" => function()
					{
						// 01 0D -> speed
						$speed = hexdec(substr($this->command("01 0D"), 5));

						// Check range
						if (!self::in_range($speed, 0, 255))
						{
							$speed = 0;
						}

						// English -> mph
						if ($this->units === self::UNIT_ENGLISH)
						{
							return sprintf("%0.2fmph", $speed * 0.621371192);
						}
						// Metric -> km/h
						else
						{
							// 01 0D -> last 4 bits -> hexdec
							return sprintf("%0.2fkm/h", $speed);
						}
					},
				"throttle" => function()
					{
						// 01 11 -> throttle
						$throttle = (hexdec(substr($this->command("01 11"), 5)) * 100) / 255;

						// Check range
						if (!self::in_range($throttle, 0, 100))
						{
							$throttle = 0;
						}

						return sprintf("%0.2f%%", $throttle);
					},
			);

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

			// Use my very own serial library to create connection
			$this->serial = new serial($this->device);

			// Set options for serial connection
			$options = array(
				"baud" => $this->baudrate,
				"bits" => 8,
				"stop" => 1,
				"parity" => 0,
			);
			$this->serial->set_options($options);

			// Attempt identification
			$id = $this->command("AT I");
			if (!$id)
			{
				throw new \Exception("odb->connect() failed to identify ELM327 ODB-II device");
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

			$this->reset();
			$this->serial->close();
			return true;
		}

		// STATIC METHODS - - - - - - - - - - - - - - - - -

		// Validate range on engine metrics
		private static function in_range($value, $min, $max)
		{
			return (($value >= $min) && ($value <= $max));
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

		// Issue a parameter reset to OBD-II device
		public function reset()
		{
			return $this->command("AT Z");
		}

		// Read data from OBD-II device
		public function read()
		{
			$data = trim($this->serial->read(), '> ');

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

			$bytes = $this->serial->write($data . "\r");
			return $bytes;
		}
	}
