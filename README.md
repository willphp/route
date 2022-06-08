##路由处理

route组件用于处理php框架路由

###安装组件

使用 composer命令进行安装或下载源代码使用。

    composer require willphp/route

> WillPHP框架已经内置此组件，无需再安装。

###必须常量

设置如下：

	define('ROOT_PATH', strtr(realpath(__DIR__.'/../'),'\\', '/')); //根路径
	define('APP_NAME', 'home'); //设置应用名
	define('IS_GET', $_SERVER['REQUEST_METHOD'] == 'GET'); //是否get提交
	define('__URL__', trim('http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']), '/\\')); //url路径	

###路由配置

`config/route.php`配置文件可设置：
	
	'default_controller' => 'index', //默认控制器
	'default_action' => 'index', //默认方法
	'url_suffix' => '.html', //url函数自动添加后缀
	'clear_suffix' => ['.php'], //路由解析自动清除后缀列表
	'pathinfo_var' => 's', //pathinfo的$_GET变量
	'get_validate' => '#^[a-zA-Z0-9\x7f-\xff\%\/\.\-_]+$#i', //路由$_GET变量验证正则
	//过滤处理
	'_html' => 'content', //可写入html的字段(包含content的表单字段)
	//过滤$req
	'filter_req' => [
			'_' => 'remove_xss', //html字段处理
			'*' => 'clear_html', //其他所有字段处理
			'id' => 'intval',
			'p' => 'intval',
			//'pwd' => 'md5', //自动md5
	],

###路由设置

路由规则在 route/应用名.php 中设置，如：

    return [
        'index' => 'index/index',
        'index_p(:num)' => 'index/index/p/${1}',
    ];  

格式：

    路由(表达式)'  => '控制器/方法/[参数/匹配值]'  

匹配值第一个使用 ${1} 第二个使用 ${2} ...

表达式正则：

    (:num)      整数
    (:float)    浮点数
    (:page)     正数
    (:string)   大小写字母数字-_
    (:alpha)    大小写字母数字-_汉字
    (:any)      以上任意    

###URL生成

助手函数(已去除内置，请自行设置此函数)：

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


url函数会根据当前应用路由设置生成对应url链接。格式如下：

    url('控制器/方法', [参数], [后缀名]);     

示例:

    namespace app\home\controller;
    class Index{    
        public function index() {
            //经路由输出
            echo url('index/index').'<br>'; //index.html 
            //前边加@不经路由
            echo url('@index/index').'<br>'; //index/index.html 
            //不设置控制器，默认当前控制器
            echo url('test').'<br>'; //index/test.html 
            //test/index
            echo url('test/index').'<br>'; //test/index.html 
            //index/index/p/1 
            echo url('index?p=1').'<br>'; //index_p1.html   
            //index/index/p/2
            echo url('index', ['p'=>2], '.php'); //index_p2.php      
        }
    }
