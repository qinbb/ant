<?php
	/*
		Awesome New Templates
	*/
	
	require_once __DIR__ . DIRECTORY_SEPARATOR . 'ant' . DIRECTORY_SEPARATOR . 'ant.parser.php';
	require_once __DIR__ . DIRECTORY_SEPARATOR . 'ant' . DIRECTORY_SEPARATOR . 'ant.helper.php';
	require_once __DIR__ . DIRECTORY_SEPARATOR . 'ant' . DIRECTORY_SEPARATOR . 'ant.io.php';
	require_once __DIR__ . DIRECTORY_SEPARATOR . 'ant' . DIRECTORY_SEPARATOR . 'ant.fn.php';
	require_once __DIR__ . DIRECTORY_SEPARATOR . 'ant' . DIRECTORY_SEPARATOR . 'ant.cache.php';
	require_once __DIR__ . DIRECTORY_SEPARATOR . 'ant' . DIRECTORY_SEPARATOR . 'ant.exception.php';
	require_once __DIR__ . DIRECTORY_SEPARATOR . 'ant' . DIRECTORY_SEPARATOR . 'ant.inherit.php';

	class Ant
	{
		const MODE_FILE   = 0xFF;
		const MODE_STRING = 0x00;

		private static $cache_obj = null;
		private static $settings = array();

		private $mode = null;
		private static $global_events = array();
		private $local_events = array();

		private $assign      = array();
		private $tmpl_path   = "";
		private $cache_path  = "";
		private $string      = "";

		private static $fn = array();

		public static function init()
		{
			return new self();
		}

		public function bind($event,$call)
		{
			self::$global_events[$event] = $call;
			return $this;
		}

		public function on($event,$call,$parent = true)
		{
			$this->local_events[$event] = array($call,$parent);
			return $this;
		}

		public function fire($event,$string)
		{
			if(isset($this->local_events[$event])){
				if(is_callable($this->local_events[$event][0])){
					$s = call_user_func_array($this->local_events[$event][0],array($string));
					
					if($this->local_events[$event][1] == true){
						if(isset(self::$global_events[$event]))
							if(is_callable(self::$global_events[$event]))
								return call_user_func_array(self::$global_events[$event],array($s));
					}else{
						return $s;
					}
				}
			}else if(isset(self::$global_events[$event])){
				if(is_callable(self::$global_events[$event]))
					return call_user_func_array(self::$global_events[$event],array($string));
			}else{
				return $string;
			}
		}

		public function share($name,$call)
		{
			self::$fn[$name] = $call;
		}

		public function setup($s)
		{
			if(false == isset($s['view']))
				throw new \Ant\AntException('View path is not defined');
			
			if(false == @is_readable($s['view']))
				throw new \Ant\AntException('View path ' . $s['view'] . ' is not available');
				
			if(false == isset($s['cache']))
				throw new \Ant\AntException('Cache path is not defined');

			if(false == @is_readable($s['cache']) or false == @is_writeable($s['cache']))
				throw new \Ant\AntException('Cache path ' . $s['cache'] . ' is not available');

			if(false == isset($s['extension'])) $s['extension'] = 'php';
			if(false == isset($s['debug']))     $s['debug']     = false;
			if(false == isset($s['freeze']))    $s['freeze']    = false;

			self::$settings = $s;
			self::$cache_obj = new \Ant\Cache($s['cache']);

			return $this;
		}

		public static function settings($name = false)
		{	
			return $name != false ? self::$settings[$name] : self::$settings;
		}

		public static function __callStatic($name, $arguments)
		{
			if(method_exists('\Ant\Fn',$name))
				return call_user_func_array('\Ant\Fn::' . $name,$arguments);
			else if(array_key_exists($name, self::$fn) and is_callable(self::$fn[$name]))
				return call_user_func_array(self::$fn[$name], $arguments);
			else
				throw new \Ant\AntException('Undeclared method \'Ant::' . $name . '\'');
		}

		public static function getCache()
		{
			return self::$cache_obj;
		}

		public function get($path)
		{
			$this->mode = self::MODE_FILE;

			$this->tmpl_path  = self::$settings['view'] . DIRECTORY_SEPARATOR . \Ant\Helper::realPath($path) . '.' . self::$settings['extension'];
			
			if(false == file_exists($this->tmpl_path))
				throw new \Ant\AntException('Template file not found at ' . $this->tmpl_path);

			$this->cache_path = self::$settings['cache'] . DIRECTORY_SEPARATOR . $path . '.php';

			return $this;
		}

		public function fromString($s)
		{
			$this->mode = self::MODE_STRING;
			$this->string = $s;
			return $this;
		}

		public function assign(array $data = array())
		{
			$this->assign = $data;
			return $this;
		}

		public function draw()
		{
			switch($this->mode){
				case self::MODE_STRING:
					$s = $this->fire(
						'build',
						\Ant\Parser::parse(
							$this->fire(
								'prepare',
								$this->string
							)
						)
					);

					if(isset(self::$settings['minify']) and true === self::$settings['minify'])
						$s = \Ant\Helper::compress($s);

					ob_start();
					extract($this->assign);
					eval(' ?>' . $s . '<?php ');
					$echo = ob_get_contents();
					ob_end_clean();

					return $this->fire('exec',$echo);
				break;

				case self::MODE_FILE:
					if(false === self::$settings['freeze']){
						if(true === self::$settings['debug'] or false == self::$cache_obj->check($this->tmpl_path)){
							$io = \Ant\IO::init()->in($this->tmpl_path);

							$s = $this->fire(
								'build',
								\Ant\Parser::parse(
									$this->fire(
										'prepare',
										$io->get()
									),
									$this->tmpl_path
								)
							);

							if(isset(self::$settings['minify']) and true === self::$settings['minify'])
								$s = \Ant\Helper::compress($s);

							$io->out()
							->in($this->cache_path)
							->set($s)
							->out();
						}
					}

					ob_start();
					extract($this->assign);
					require $this->cache_path;
					$echo = ob_get_contents();
					ob_end_clean();

					return $this->fire('exec',$echo);
				break;
			}
		}
	}
?>
