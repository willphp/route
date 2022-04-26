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
if (!function_exists('clear_html')) {
	/**
	 * 清除html代码
	 * @param string $string 字符串
	 * @return string 返回清除结果
	 */
	function clear_html($string){
		$string = strip_tags($string);
		$string = trim($string);
		$string = preg_replace('/\t/','',$string);
		$string = preg_replace('/\r\n/','',$string);
		$string = preg_replace('/\r/','',$string);
		$string = preg_replace('/\n/','',$string);
		return trim($string);
	}
}
if (!function_exists('remove_xss')) {
	/**
	 * XSS处理script
	 * @param string $val
	 * @return string 返回清除结果
	 */
	function remove_xss($val) {
		$val = preg_replace('/([\x00-\x08,\x0b-\x0c,\x0e-\x19])/', '', $val);
		$search = 'abcdefghijklmnopqrstuvwxyz';
		$search .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$search .= '1234567890!@#$%^&*()';
		$search .= '~`";:?+/={}[]-_|\'\\';
		for ($i = 0; $i < strlen($search); $i++) {
			$val = preg_replace('/(&#[xX]0{0,8}'.dechex(ord($search[$i])).';?)/i', $search[$i], $val);
			$val = preg_replace('/(�{0,8}'.ord($search[$i]).';?)/', $search[$i], $val);
		}
		$ra1 = array('javascript', 'vbscript', 'expression', 'applet', 'meta', 'xml', 'blink', 'link', 'style', 'script', 'embed', 'object', 'iframe', 'frame', 'frameset', 'ilayer', 'layer', 'bgsound', 'title', 'base');
		$ra2 = array('onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy', 'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged', 'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup', 'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange', 'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete', 'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop', 'onsubmit', 'onunload');
		$ra = array_merge($ra1, $ra2);
		$found = true;
		while ($found == true) {
			$val_before = $val;
			for ($i = 0; $i < sizeof($ra); $i++) {
				$pattern = '/';
				for ($j = 0; $j < strlen($ra[$i]); $j++) {
					if ($j > 0) {
						$pattern .= '(';
						$pattern .= '(&#[xX]0{0,8}([9ab]);)';
						$pattern .= '|';
						$pattern .= '|(�{0,8}([9|10|13]);)';
						$pattern .= ')*';
					}
					$pattern .= $ra[$i][$j];
				}
				$pattern .= '/i';
				$replacement = substr($ra[$i], 0, 2).'<x>'.substr($ra[$i], 2); // add in <> to nerf the tag
				$val = preg_replace($pattern, $replacement, $val); // filter out the hex tags
				if ($val_before == $val) {
					$found = false;
				}
			}
		}
		return $val;
	}
}