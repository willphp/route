<?php
/*--------------------------------------------------------------------------
 | Software: [WillPHP framework]
 | Site: www.113344.com
 |--------------------------------------------------------------------------
 | Author: no-mind <24203741@qq.com>
 | WeChat: www113344
 | Copyright (c) 2020-2022, www.113344.com. All Rights Reserved.
 |-------------------------------------------------------------------------*/
namespace willphp\route\build;
use willphp\config\Config;
use willphp\cache\Cache;
use willphp\request\Request;
use willphp\middleware\Middleware;
use willphp\container\Container;
/**
 * 路由处理
 * Class Base
 * @package willphp\route\build;
 */
class Base {		
	protected $filter = '[a-zA-Z0-9\x7f-\xff\%\/\.\-_]+'; //$_GET参数过滤 
	protected $patterns = [
			':num' => '[0-9\-]+', //正数
			':float' => '[0-9\.\-]+', //浮点数
			':string' => '[a-zA-Z0-9\-_]+', //字母，数字，-_
			':alpha' => '[a-zA-Z\x7f-\xff0-9-_]+', //可包含中文字符
			':any' => '.*', //任意
			':page' => '[0-9]+', //分页码
	];
	protected $rule = []; //路由规则
	protected $module;  //当前模块
	protected $controller; //当前控制器
	protected $method; //当前方法
	protected $suffix; //默认url后缀
	protected $pathinfo; //当前pathinfo
	protected $route; //当前路由路径+get参数，如: home/index/index?id=1&cid=2
	/**
	 * 构造函数
	 */
	public function __construct() {
		defined('APP_NAME') or define('APP_NAME', 'home');
		$this->module = APP_NAME;
		$this->controller = Config::get('route.default_controller', 'index');
		$this->method = Config::get('route.default_method', 'index');	
		$this->suffix = Config::get('route.url_suffix', '.html');
		$this->rule = Cache::get('_route_');
		if (!$this->rule || Config::get('app.debug')) {
			$this->rule = $this->parseRule();
		}		
	}	
	/**
	 * 处理路由规则(生成正反规则并缓存)
	 * @return array 
	 */
	protected function parseRule() {
		$route_path = Config::get('route.route_path');
		$file = $route_path.'/'.APP_NAME.'.php';
		$rulelist = file_exists($file)? include $file : [];		
		$rule = [];
		foreach ($rulelist as $k => $v) {
			if (strpos($k, ':') !== false) {
				$k = str_replace(array_keys($this->patterns), array_values($this->patterns), $k);
			}
			$k = trim(strtolower($k), '/');
			$rule[$k] = trim(strtolower($v), '/');
		}
		$temp = array_flip($rule);
		$flip = [];
		foreach ($temp as $k => $v) {
			if (preg_match_all('/\(.*?\)/i', $v, $res)) {
				$pattern = [];
				$count = count($res[0]);
				for ($i=1;$i<=$count;$i++) {
					$pattern[] = '/\$\{'.$i.'\}/i';
				}
				$k = preg_replace($pattern, $res[0], $k);
				$i = 0;
				$v = preg_replace_callback('/\(.*?\)/i',function ($matches) use(&$i) {
					$i ++;
					return '${'.$i.'}';
				}, $v);
			}
			$flip[$k] = $v;
		}
		$route = ['route' => $rule, 'flip' => $flip];
		Cache::set('_route_', $route);
		return $route;
	}
	/**
	 * 路由启动
	 * @return $this
	 */
	public function bootstrap() {
		$this->pathinfo = $this->getPathInfo();				
		return $this;
	}
	/**
	 * 获取当前pathinfo信息
	 * @return string
	 */
	protected function getPathInfo() {
		$pathinfo = $this->controller.'/'.$this->method;
		$pathinfo_var = Config::get('route.pathinfo_var', 's'); //默认s
		$http = Request::get($pathinfo_var);
		if (isset($_SERVER['PATH_INFO'])) {
			$path = preg_replace('/\/+/', '/', $_SERVER['PATH_INFO']);
		} elseif (!empty($http)) {
			$path = $http;
			Request::setParam($pathinfo_var, null);
		} else  {
			$path = '';
		}
		$path = trim(strtolower($path), '/');
		if ($path) {
			$del_suffix = Config::get('route.del_suffix', ['.html']); //路由自动去除后缀列表
			if (!empty($this->suffix) && !in_array($this->suffix, $del_suffix)) {
				$del_suffix[] = $this->suffix;
			}
			$path = str_replace($del_suffix, '', $path);
			if (preg_match('#^'.$this->filter.'$#i', $path)) {
				$pathinfo = $path;
			}
		}
		return $pathinfo;
	}
	/**
	 * 获取当前操作方法
	 * @return string
	 */
	public function getAction() {
		return $this->method;
	}
	/**
	 * 获取当前路由路径+get参数
	 * @return string
	 */
	public function getRoute() {
		if (!$this->route) {
			$this->route = $this->module.'/'.$this->controller.'/'.$this->method;
			$param = Request::get();
			if (!empty($param)) {
				ksort($param);
				$this->route .= '?'.http_build_query($param);
			}
		}
		return $this->route;
	}
	/**
	 * 获取当前路由模板文件
	 * @return string
	 */
	public function getViewFile($file = '') {
		$path = $this->controller;
		if ($file == '') {
			$file = $this->method;
		} elseif (strpos($file, ':')) {
			list($path, $file) = explode(':', $file);
		} elseif (strpos($file, '/')) {
			$path = '';
		}		
		return trim($path.'/'.$file, '/').Config::get('view.prefix', '.html');	
	}	
	/**
	 * 根据pathinfo获取匹配路由
	 * @param string $path 
	 * @return string
	 */
	protected function getRule($path = '') {
		if (empty($path)) {
			$path = $this->pathinfo;
		}
		$route = $path;					
		$rule = $this->rule['route'];
		if (isset($rule[$path])) {
			$route = $rule[$path];
		} elseif (!empty($rule)) {
			foreach ($rule as $k => $v) {
				if (preg_match('#^'.$k.'$#i', $path)) {					
					if (strpos($v, '$') !== false && strpos($k, '(') !== false) {						
						$v = preg_replace('#^'.$k.'$#i', $v, $path);						
					}
					$route = $v;
					break;
				}
			}
		}
		return $route;
	}
	/**
	 * 获取解析路由
	 * @param string $route	
	 */
	protected function parseRoute($route = '') {
		$parse = [];	
		$parse['module'] = $this->module;
		$parse['controller'] = $this->controller;
		$parse['method'] = $this->method;
		$parse['params'] = [];
		if (empty($route)) {
			$route = $this->getRule();
		}		
		$path = explode('/', $route);
		$count = count($path);
		if ($count == 1) {
			$parse['controller'] = $path[0];
		}
		if ($count == 2) {
			$parse['controller'] = $path[0];
			$parse['method'] = $path[1];
		}
		if ($count >= 3) {
			$denyapp = Config::get('route.deny_app_list', []);
			$appdir = Config::get('route.app_dir', '.');
			if (!in_array($path[0], $denyapp) && is_dir($appdir.'/'.$path[0])) {
				$parse['module'] = $path[0];
				$parse['controller'] = $path[1];
				$parse['method'] = $path[2];
				$del_path = $path[0].'/'.$path[1].'/'.$path[2];
			} else {
				$parse['controller'] = $path[0];
				$parse['method'] = $path[1];
				$del_path = $path[0].'/'.$path[1];
			}
			$param = str_replace($del_path, '', $route);
			$param = trim($param, '/');
			if ($param) {				
				$param = explode('/', $param);					
				for($i = 0; $i < count($param); $i += 2) {					
					if (isset($param[$i + 1])) {
						$parse['params'][$param[$i]] = $param[$i + 1];
					}
				}
			}
		}	
		return $parse;
	}
	/**
	 * 执行控制器方法
	 * @param string $route 路由
	 * @param array $params 参数
	 * @return mixed
	 */
	public function executeControllerMethod($route = '', $params = []) {
		$parse = $this->parseRoute($route);
		$module = $parse['module'];
		$controller = ucfirst($parse['controller']);
		$method = $parse['method'];
		$params = array_merge(Request::get(), $parse['params'], $params); //参数
		if (isset($params['req'])) {
			unset($params['req']); //req为保留参数
		}
		$class = 'app\\'.$module.'\\controller\\'.$controller.'Controller';
		if (empty($route) && !method_exists($class, $method)) {
			$class = 'app\\'.$module.'\\controller\\EmptyController';
			$method = '_empty';
		}
		if (!method_exists($class, $method)) {
			throw new \Exception($controller.'Controller->'.$method.'() does not exist.');
		}
		$class = Container::make($class, true);
		try {
			$class_method = new \ReflectionMethod($class, $method); //类方法
			if (!$class_method->isPublic()) {
				throw new \Exception($controller.'Controller->'.$method.'()：The called method is not public.');
			}
			$bind = $extend = []; //绑定参数，扩展参数
			$method_args = $class_method->getParameters(); //方法属性
			foreach ($method_args as $arg) {
				$arg_name = $arg->getName(); //属性名称
				$dependency = $arg->getClass(); //属性是类
				if (isset($params[$arg_name])) {
					$bind[$arg_name] = $params[$arg_name];					
					$extend[$arg_name] = $params[$arg_name];					
				} elseif ($dependency) {
					$bind[$arg_name] = Container::build($dependency->name);
				} elseif ($arg->isDefaultValueAvailable()) {
					$bind[$arg_name] = $arg->getDefaultValue();
				} elseif ($arg_name != 'req') {
					throw new \Exception('['.$arg_name.'] parameter has no default value.');
				}
			}
			$get = array_merge($params, $extend); //get参数
			$bind['req'] = array_merge($get, Request::post()); //绑定参数
			$path = strtolower($module.'/'.$controller.'/'.$method);
			if (!empty($get)) {
				ksort($get);	
				$path .= '?'.http_build_query($get);
				if (empty($route)) {
					Request::setParam($get); //当执行默认操作时设置$_GET参数
				}
			}
			//当执行默认操作时设置当前路由信息
			if (empty($route)) {
				$this->module = $module;
				$this->controller = strtolower($controller);
				$this->method = $method;
				$this->route = $path;
			}
			Middleware::web('controller_start', $path); //记录路由信息
			$this->exeMiddleware($class); //处理控制器中间件
			return $class_method->invokeArgs($class, $bind);
		} catch (\ReflectionException $e) {
			if (!method_exists($class, '__call')) {
				throw new \Exception($e->getMessage());
			}
			$action = new \ReflectionMethod($class, '__call');
			return $action->invokeArgs($class, [$action, '']);
		}
	}	
	/**
	 * 处理控制器中间件
	 * @param string $controller
	 */
	protected function exeMiddleware($controller) {
		$middlewares = [];
		$class = new \ReflectionClass($controller);
		if ($class->hasProperty('middleware')) {
			$reflectionProperty = $class->getProperty('middleware');
			$reflectionProperty->setAccessible(true);
			$middlewares = $reflectionProperty->getValue($controller);
			if (!is_array($middlewares)) {
				Middleware::set($middlewares);
			} else {
				foreach ($middlewares as $key => $val) {
					if (!is_int($key)) {
						Middleware::set($key, $val);
					} else {
						Middleware::set($val);
					}
				}
			}
		}
	}
	/**
	 * 生成url @index/index
	 * @param string $route 路由
	 * @param array $params 参数
	 * @param string $suffix url后缀
	 * @return string
	 */
	public function buildUrl($route = '', $param = [], $suffix = '*') {
		if (in_array($route, ['','@','@/','/@'])) {
			$route = '/'; 
		}
		if ($suffix == '*') $suffix = Config::get('route.url_suffix', '.html');
		$temp = [];
		if (strpos($route, '?')) {
			list($route, $temp) = explode('?', $route);	
			parse_str($temp, $temp);
		}		
		$param = array_merge($temp, $param);
		$param = str_replace(['&', '='], '/', http_build_query($param));
		$route = trim($route.'/'.$param, '/');		
		if (empty($route)) {
			return __URL__;
		}
		if (substr($route, 0, 1) == '@') {
			$route = trim($route, '@');
			return __URL__.'/'.$route.$suffix;
		}		
		$url = $route;		
		$flip = $this->rule['flip'];		
		if (isset($flip[$route])) {
			$url = $flip[$route];				
		} elseif (!empty($flip)) {			
			foreach ($flip as $k => $v) {
				if (preg_match('#^'.$k.'$#i', $route)) {
					if (strpos($v, '$') !== false && strpos($k, '(') !== false) {
						$v = preg_replace('#^'.$k.'$#i', $v, $route);
					}
					$url = $v;
					break;
				}
			}			
		}		
		return __URL__.'/'.$url.$suffix;	
	}
	/**
	 * 根据参数生成url
	 * @param string|array $params 参数
	 * @return string
	 */
	public function pageUrl($param = '') {		
		$route = ($this->module == APP_NAME)? $this->controller.'/'.$this->method : $this->module.'/'.$this->controller.'/'.$this->method;
		if (empty($param)) {
			return $this->buildUrl($route);
		}
		if (is_array($param)) {
			return $this->buildUrl($route, $param);
		}
		return $this->buildUrl($route.'?'.$param);
	}
}