<?php
/*--------------------------------------------------------------------------
 | Software: [WillPHP framework]
 | Site: www.113344.com
 |--------------------------------------------------------------------------
 | Author: no-mind <24203741@qq.com>
 | WeChat: www113344
 | Copyright (c) 2020-2022, www.113344.com. All Rights Reserved.
 |-------------------------------------------------------------------------*/
namespace willphp\route;
use willphp\config\Config;
use willphp\request\Request;
use willphp\cache\Cache;
use willphp\middleware\Middleware;
use willphp\container\Container;
class Route {
	protected static $link;
	public static function single()	{
		if (!self::$link) {
			self::$link = new RouteBuilder();
		}
		return self::$link;
	}
	public function __call($method, $params) {
		return call_user_func_array([self::single(), $method], $params);
	}
	public static function __callStatic($name, $arguments) {
		return call_user_func_array([self::single(), $name], $arguments);
	}
}
class RouteBuilder {
	protected $module; //当前模块
	protected $controller; //当前控制器
	protected $action; //当前方法
	protected $rule; //路由规则
	protected $uri; //当前uri
	protected $route; //当前路由+参数(array)
	protected $path; //当前路径+参数(string)
	public function __construct() {
		$this->module = APP_NAME;
		$this->controller = Config::get('route.default_controller', 'index');
		$this->action = Config::get('route.default_action', 'index');
		$this->rule = $this->parseRuleFile();
	}
	/**
	 * 路由启动
	 */
	public function bootstrap() {
		$this->uri = $this->getUri();
		if (!$this->route) {
			$route = $this->parseUri($this->uri, $_GET);
			$this->route = $route;
			$this->module = $route['module'];
			$this->controller = $route['controller'];
			$this->action = $route['action'];
			$this->path = $route['path'];
		}
		return $this;
	}
	/**
	 * 执行控制器方法
	 * @param string $uri
	 * @param array $params
	 * @throws \Exception
	 * @return boolean|mixed
	 */
	public function executeControllerAction($uri = '', $params = []) {
		$iscall = !empty($uri); //是否是调用
		if (!$iscall && ($cache = $this->getViewCache())) {
			return $cache;
		}
		$route = !$iscall? $this->route : $this->parseUri($uri, $params);
		$module = $route['module'];
		$controller = $route['controller'];
		$action = $route['action'];
		$params = $route['params'];
		$path = $controller.'/'.$action;
		if (!$iscall && 0 === strpos($action, '_')) {
			return $this->routeError($path.' 无法访问');
		}
		$classCtrl = $this->getClassCtrl($controller, $module);
		if (!method_exists($classCtrl, $action)) {
			if ($iscall) {
				return false;
			}
			return $this->routeError($path, 'empty');
		}
		$class = Container::make($classCtrl);
		try {
			$class_method = new \ReflectionMethod($class, $action);
			if (!$class_method->isPublic()) {
				return $this->routeError($path.' 无法访问');
			}
			$method_args = $class_method->getParameters();
			$bindReq = false;
			$binds = $extend = [];
			foreach ($method_args as $arg) {
				$arg_name = $arg->getName();
				if ($arg_name == 'req') {
					$bindReq = true;
					continue;
				}
				$dependency = $arg->getClass();
				if (isset($params[$arg_name])) {
					$binds[$arg_name] = $params[$arg_name];
				} elseif ($dependency) {
					$binds[$arg_name] = Container::build($dependency->name);
				} elseif ($arg->isDefaultValueAvailable()) {
					$binds[$arg_name] = $arg->getDefaultValue();
					$extend[$arg_name] = $binds[$arg_name];
				} elseif (isset($_POST[$arg_name])) {
					$binds[$arg_name] = $_POST[$arg_name];
				} else {
					return $this->routeError($path.' 参数不足');
				}
			}
			if (!$iscall) {
				Middleware::web('controller_start');
				$this->exeMiddleware($class); //处理控制器中间件
				$extend = array_merge($params, $extend); //扩展参数
				Request::setGet($extend);
				if ($bindReq) {
					//req过滤
					$req = array_merge($_GET, $_POST);
					$binds['req'] = $this->filterReq($req);
				}
				if (method_exists($class, '_before')) {
					$class->_before($action);
				}
				if (method_exists($class, '_before_'.$action)) {
					$class->{'_before_'.$action}();
				}
			}
			$res = $class_method->invokeArgs($class, $binds);
			if (!$iscall) {
				if (method_exists($class, '_after_'.$action)) {
					$class->{'_after_'.$action}();
				}
				if (method_exists($class, '_after')) {
					$class->_after($action);
				}
			}
			return $res;
		} catch (\ReflectionException $e) {
			if (!method_exists($class, '__call')) {
				throw new \Exception($e->getMessage());
			}
			$method = new \ReflectionMethod($class, '__call');
			return $method->invokeArgs($class, [$method, '']);
		}
	}
	protected function getClassCtrl($name, $module = '') {
		$module = empty($module)? APP_NAME : strtolower($module);
		$name = ucwords(str_replace(['-', '_'], ' ', $name));
		$name = str_replace(' ', '', $name);
		return 'app\\'.$module.'\\controller\\'.$name;
	}
	/**
	 * 获取页面缓存
	 * @return string
	 */
	protected function getViewCache() {
		if (IS_GET && Config::get('view.view_cache')) {
			$name = 'view.'.md5($this->path);
			return Cache::get($name);
		}
		return false;
	}
	/**
	 * 处理绑定参数req
	 * @param array $req
	 * @return array
	 */
	protected function filterReq($req = []) {
		array_walk_recursive($req, 'self::parseParam');
		return $req;
	}
	/**
	 * 处理参数
	 * @param string $value
	 * @param string $key
	 */
	protected static function parseParam(&$value, $key) {
		$filters = Config::get('route.filter_req');
		$html = Config::get('route._html', 'content');
		if (strpos($key, $html) !== false && isset($filters['_']) && function_exists($filters['_'])) {
			$value = $filters['_']($value);
		} elseif (isset($filters['*']) && function_exists($filters['*'])) {
			$value = $filters['*']($value);
		}
		if (!in_array($key, ['*','_']) && isset($filters[$key]) && function_exists($filters[$key])) {
			$value = $filters[$key]($value);
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
	 * 路由错误提示
	 * @param string $msg
	 * @param string $type
	 * @return mixed
	 */
	protected function routeError($msg = '无法访问', $type = 'fail') {
		$type = ($type == 'empty')? 'empty' : 'fail';
		$classError = $this->getClassCtrl('Error');
		$handler = Container::make($classError, true);
		return call_user_func_array([$handler, $type], [$msg]);
	}
	/**
	 * 获取路由+参数
	 * @param string $route
	 * @return array
	 */
	public function getRoute($route = '') {
		return empty($route)? $this->route : $this->parseUri($route);
	}
	/**
	 * 获取路径+参数
	 * @param string $route
	 * @return string
	 */
	public function getPath($route = '') {
		return empty($route)? $this->path : $this->parseUri($route, [], true);
	}
	/**
	 * 获取控制器
	 * @return string
	 */
	public function getController() {
		return $this->controller;
	}
	/**
	 * 获取方法
	 * @return string
	 */
	public function getAction() {
		return $this->action;
	}
	/**
	 * 处理uri
	 * @param string $uri 要处理的uri
	 * @param array $params 参数
	 * @param bool $getpath 是否只返回路径
	 * @return array|string
	 */
	public function parseUri($uri = '', $params = [], $getpath = false) {
		$args1 = $args2 = [];
		$route = [];
		$route['module'] = $this->module;
		$route['controller'] = $this->controller;
		$route['action'] = $this->action;
		if (strpos($uri, '?') !== false) {
			list($uri, $args1) = explode('?', $uri);
			parse_str($args1, $args1);
		}
		$uri = trim($uri, '/');
		$path = explode('/', $uri);
		$count = count($path);
		if ($count == 1) {
			$route['action'] = $path[0];
		}
		if ($count == 2) {
			$route['controller'] = $path[0];
			$route['action'] = $path[1];
		}
		if ($count >= 3) {
			$appList = Config::get('app.app_list', []); //可访问
			$classCtrl = $this->getClassCtrl($path[1], $path[0]);
			if (in_array($path[0], $appList) && method_exists($classCtrl, $path[2])) {
				$route['module'] = $path[0];
				$route['controller'] = $path[1];
				$route['action'] = $path[2];
				$clear = $path[0].'/'.$path[1].'/'.$path[2];
			} else {
				$route['controller'] = $path[0];
				$route['action'] = $path[1];
				$clear = $path[0].'/'.$path[1];
			}
			$extend = str_replace($clear, '', $uri);
			$extend = explode('/', trim($extend, '/'));
			$nums = count($extend);
			if ($nums > 1) {
				for($i=0;$i<$nums;$i+=2) {
					if (isset($extend[$i+1])) {
						$args2[$extend[$i]] = $extend[$i+1];
					}
				}
			}
		}
		$route['params'] = array_merge($args2, $args1, $params);
		$route['path'] = $route['module'].'/'.$route['controller'].'/'.$route['action'];
		if (!empty($route['params'])) {
			ksort($route['params']);
			$route['path'] .= '?'.http_build_query($route['params']);
		}
		return $getpath? $route['path'] : $route;
	}
	/**
	 * 获取当前uri
	 * @return string
	 */
	protected function getUri() {
		$path = $this->controller.'/'.$this->action;
		$pathinfo = '';
		$pathinfo_var = Config::get('route.pathinfo_var', 's'); //默认s
		$getpath = Request::get($pathinfo_var);
		if (isset($_SERVER['PATH_INFO'])) {
			$pathinfo = preg_replace('/\/+/', '/', $_SERVER['PATH_INFO']);
		} elseif (!empty($getpath)) {
			$pathinfo = $getpath;
			Request::setGet($pathinfo_var, null);
		}
		$pathinfo = trim(strtolower($pathinfo), '/');
		$get_validate = Config::get('route.get_validate', '#^[a-zA-Z0-9\x7f-\xff\%\/\.\-_]+$#i');
		if ($pathinfo && preg_match($get_validate, $pathinfo)) {
			$clear_suffix = Config::get('route.clear_suffix', []);
			$url_suffix = Config::get('route.url_suffix', '.html');
			if (!empty($url_suffix) && !in_array($url_suffix, $clear_suffix)) {
				$clear_suffix[] = $url_suffix;
			}
			$path = str_replace($clear_suffix, '', $pathinfo);
		}
		$uri = $path;
		$rule = $this->rule['just'];
		if (isset($rule[$path])) {
			$uri = $rule[$path];
		} elseif (!empty($rule)) {
			foreach ($rule as $k => $v) {
				if (preg_match('#^'.$k.'$#i', $path)) {
					if (strpos($v, '$') !== false && strpos($k, '(') !== false) {
						$v = preg_replace('#^'.$k.'$#i', $v, $path);
					}
					$uri = $v;
					break;
				}
			}
		}
		return $uri;
	}
	//处理路由规则
	protected function parseRuleFile() {
		$rule = ['just' => [], 'flip' => []];
		$mtime = 0;
		$file = ROOT_PATH.'/route/'.$this->module.'.php';
		if (!file_exists($file)) {
			return $rule;
		}
		$mtime = filemtime($file);
		$rule = Cache::get('route.'.$mtime); //获取缓存
		if (!$rule) {
			Cache::flush('route');
			$conf = include $file;
			$expkey = [':num', ':float', ':string', ':alpha', ':page', ':any'];
			$expval = ['[0-9\-]+', '[0-9\.\-]+', '[a-zA-Z0-9\-_]+', '[a-zA-Z\x7f-\xff0-9-_]+', '[0-9]+', '.*'];
			$just = $flip = [];
			foreach ($conf as $k => $v) {
				if (strpos($k, ':') !== false) {
					$k = str_replace($expkey, $expval, $k);
				}
				$k = trim(strtolower($k), '/');
				$just[$k] = trim(strtolower($v), '/');
			}
			$tmp = array_flip($just);
			foreach ($tmp as $k => $v) {
				if (preg_match_all('/\(.*?\)/i', $v, $res)) {
					$exp = [];
					$count = count($res[0]);
					for ($i=1;$i<=$count;$i++) {
						$exp[] = '/\$\{'.$i.'\}/i';
					}
					$k = preg_replace($exp, $res[0], $k);
					$i = 0;
					$v = preg_replace_callback('/\(.*?\)/i',function ($matches) use(&$i) {
						$i ++;
						return '${'.$i.'}';
					}, $v);
				}
				$flip[$k] = $v;
			}
			$rule = ['just' => $just, 'flip' => $flip];
			Cache::set('route.'.$mtime, $rule);
		}
		return $rule;
	}
	/**
	 * 根据参数 生成url
	 * @param string|array $params 参数
	 * @return string
	 */
	public function pageUrl($params = []) {
		$route = $this->controller.'/'.$this->action;
		if ($this->module != APP_NAME) {
			$route = $this->module.'/'.$route;
		}
		if (empty($params)) {
			return $this->buildUrl($route);
		}
		return is_array($params)? $this->buildUrl($route, $params) : $this->buildUrl($route.'?'.$params);
	}
	/**
	 * url生成
	 * @param string $route
	 * @param array $params
	 * @param string $suffix
	 * @return string
	 */
	public function buildUrl($route = '', $params = [], $suffix = '*') {
		if (is_null($route)) {
			return '';
		}
		if ($route == '[back]' || $route == 'javascript:history.back(-1);') {
			return 'javascript:history.back(-1);';
		}
		if ($route == '[history]') {
			return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'javascript:history.back(-1);';
		}
		if (filter_var($route, FILTER_VALIDATE_URL) !== false) {
			return $route;
		}
		if (in_array($route, ['','@','@/','/@'])) {
			return __URL__;
		}
		if ($suffix == '*') $suffix = Config::get('route.url_suffix', '.html');
		$tmp = [];
		if (strpos($route, '?') !== false) {
			list($route, $tmp) = explode('?', $route);
			parse_str($tmp, $tmp);
		}
		$route = trim($route, '/');
		if (empty($route)) {
			$route = $this->controller.'/'.$this->action;
		}
		if (preg_match('#^[a-zA-Z0-9\-_]+$#i', $route)) {
			$route = $this->controller.'/'.$route;
		}
		$params = array_merge($tmp, $params);
		if (!empty($params)) {
			$params = array_filter($params); //过滤空值和0
			$params = str_replace(['&', '='], '/', http_build_query($params));
			$route = trim($route.'/'.$params, '/');
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
}