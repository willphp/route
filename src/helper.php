<?php
if (!function_exists('action')) {
	/**
	 * 执行控制器方法
	 * @param string $route 路由
	 * @param array $params 参数
	 * @return mixed
	 */
	function action($route = '', $params = []) {
		return \willphp\route\Route::executeControllerMethod($route, $params);
	}
}
if (!function_exists('url')) {
	/**
	 * 生成url
	 * @param string $route @：不过路由直接生成；/：根目录
	 * @param array $param 参数['id'=>1]
	 * @param string $suffix 后缀*:为系统默认后缀.html
	 * @return string 返回生成url
	 */
	function url($route = '', $param = [], $suffix = '*') {
		return \willphp\route\Route::buildUrl($route, $param, $suffix);
	}
}
