<?php
declare(strict_types=1);
namespace nova\framework\core;

use nova\framework\http\Request;
use RuntimeException;

/**
 * 框架核心上下文类
 * 作为框架的核心容器，负责管理整个应用的生命周期、配置和运行时信息
 * 实现了单例模式，确保整个应用中只有一个Context实例
 * 
 * 主要功能：
 * - 管理应用配置
 * - 处理请求响应
 * - 维护运行时状态
 * - 提供依赖注入容器
 * - 管理应用实例
 * 
 * @example
 * ```php
 * $context = Context::instance();
 * $config = $context->config();
 * $request = $context->request();
 * ```
 */
class Context
{
    /**
     * @var float 应用启动时间戳
     * 记录应用开始执行的微秒级时间戳，用于性能分析
     */
    protected float $start_time = 0.0;

    /**
     * @var Config 配置对象实例
     * 存储和管理应用的所有配置信息
     */
    protected Config $config;

    /**
     * @var bool 调试模式开关
     * 控制应用是否运行在调试模式下，影响错误显示等行为
     */
    protected bool $debug = false;

    /**
     * @var Request 当前请求对象
     * 封装了当前HTTP请求的信息
     */
    protected Request $request;

    /**
     * @var string 会话ID
     * 当前用户会话的唯一标识符
     */
    protected string $session_id;

    /**
     * @var Loader 类加载器实例
     * 负责自动加载类文件
     */
    protected Loader $loader;

    /**
     * @var string 框架版本号
     * 当前框架的版本信息
     */
    const string VERSION = "5.0.1";

    /**
     * @var array<string, mixed> 实例容器
     * 存储框架中的服务实例，实现依赖注入
     */
    protected array $instances = [];

    /**
     * @var array<string, mixed> 变量存储
     * 用于在整个应用生命周期中存储临时数据
     */
    protected array $vars = [];

    /**
     * @var Context|null 单例实例
     * 存储Context的唯一实例
     */
    private static ?Context $instance = null;

    /**
     * 获取Context单例实例
     * 确保整个应用中只有一个Context实例
     * 
     * @return Context 返回Context实例
     * @throws RuntimeException 如果Context未初始化则抛出异常
     */
    static function instance(): Context
    {
        if (self::$instance === null) {
            global $context;
            if (!$context) {
                throw new RuntimeException('Context is not initialized');
            }
            self::$instance = $context;
        }
        return self::$instance;
    }
    /**
     * 构造函数
     * 初始化应用环境，设置基础配置，启动核心组件
     * 
     * @param Loader $loader 类加载器实例
     */
    public function __construct(Loader $loader)
    {
        $this->start_time = microtime(true);
        //初始化框架
        $this->initFramework();
        // 初始化配置对象
       $this->initConfig();
        // 检查域名
        $this->checkDomain();
        // 初始化请求对象
        $this->initRequest();
        // 初始化加载器
        $this->initLoader($loader);
    }

    /**
     * 获取或创建实例
     * 实现简单的依赖注入容器功能
     * 
     * @param string $name 实例名称
     * @param callable $create 创建实例的回调函数
     * @return mixed 返回已存在的实例或新创建的实例
     * 
     * @example
     * ```php
     * $db = $context->getOrCreateInstance('db', function() {
     *     return new Database();
     * });
     * ```
     */
    public function getOrCreateInstance(string $name, callable $create): mixed
    {
        if (!isset($this->instances[$name])) {
            $this->instances[$name] = $create();
        }
        return $this->instances[$name];
    }

    /**
     * 销毁所有注册的实例
     * 用于清理资源，通常在应用结束时调用
     */
    public function destroyInstances(): void
    {
        foreach ($this->instances as $instance) {
           unset($instance);
        }
    }

    /**
     * 初始化类加载器
     * 配置命名空间映射关系
     * 
     * @param Loader $loader 加载器实例
     */
    public function initLoader(Loader $loader): void
    {
        $this->loader = $loader;
        $this->loader->setNamespace($this->config->get('namespace', []));
    }
    /**
     * 初始化配置
     * 创建配置对象，设置调试模式和时区
     */
    public function initConfig(): void
    {
        $this->config = new Config();
        $this->debug = $this->config->get('debug') ?? false;
        date_default_timezone_set( $this->config->get('timezone', "Asia/Shanghai"));
    }

    /**
     * 初始化请求
     * 创建请求对象并设置会话ID
     */
    function initRequest(): void
    {
        $this->request = new Request();
        $this->session_id = $this->request->id();
    }

    /**
     * 获取会话ID
     * 
     * @return string 当前会话的唯一标识符
     */
    function getSessionId(): string
    {
        return $this->session_id;
    }

    /**
     * 检查域名是否合法
     * 验证当前请求的域名是否在允许列表中
     * 
     * @throws RuntimeException 当域名不在允许列表中时终止执行
     */
    function checkDomain(): void
    {
        $domains = $this->config->get("domain");
        $serverName = $_SERVER["HTTP_HOST"];
        if (!in_array("0.0.0.0", $domains) && !in_array($serverName, $domains)) {
            exit("[ NovaPHP ] Domain Error ：" . htmlspecialchars($serverName) . " not in config.domain list.");
        }

    }

    /**
     * 初始化框架
     * 定义框架所需的常量和基础路径
     */
    public function initFramework(): void
    {
//根目录
        defined('ROOT_PATH') or define('ROOT_PATH', dirname(__DIR__, 3));
        //目录分隔符
        defined('DS') or define('DS', DIRECTORY_SEPARATOR);
        //框架目录
        defined('FRAMEWORK_PATH') or define('FRAMEWORK_PATH', __DIR__);
        //应用目录
        defined('APP_PATH') or define('APP_PATH', ROOT_PATH . DS . 'app');
        //运行时目录
        defined('RUNTIME_PATH') or define('RUNTIME_PATH', ROOT_PATH . DS . 'runtime');
        //配置目录
        defined('CONFIG_PATH') or define('CONFIG_PATH', ROOT_PATH . DS . 'config');
        //日志目录
        defined('LOG_PATH') or define('LOG_PATH', RUNTIME_PATH . DS . 'logs');
        //临时目录
        defined('TEMP_PATH') or define('TEMP_PATH', RUNTIME_PATH . DS . 'temp');
        //缓存目录
        defined('CACHE_PATH') or define('CACHE_PATH', RUNTIME_PATH . DS . 'cache');
        //公共目录
        defined('PUBLIC_PATH') or define('PUBLIC_PATH', ROOT_PATH . DS . 'public');
        //vendor目录
        defined('VENDOR_PATH') or define('VENDOR_PATH', ROOT_PATH . DS . 'vendor');
        //框架版本
        defined('NOVA_VERSION') or define('NOVA_VERSION', self::VERSION);

    }
    /**
     * 获取会话ID
     * 
     * @return string 当前会话的唯一标识符
     */
    public function sessionId(): string
    {
        return $this->session_id;
    }

    /**
     * 获取配置对象实例
     * 
     * @return Config 配置对象
     */
    public function config(): Config
    {
        return $this->config;
    }

    /**
     * 获取调试模式状态
     * 
     * @return bool 是否处于调试模式
     */
    public function isDebug(): bool{
        return $this->debug;
    }

    /**
     * 获取当前请求对象
     * 
     * @return Request 当前HTTP请求对象
     */
    public function request(): Request
    {
        return $this->request;
    }

    /**
     * 计算应用运行时间
     * 返回从应用启动到当前时刻的运行时间
     * 
     * @return float 运行时间（秒）
     */
    public function calcAppTime(): float
    {
        return microtime(true) - $this->start_time;
    }

    /**
     * 析构函数
     * 清理实例资源
     */
    public function __destruct()
    {
        $this->destroyInstances();
    }

    /**
     * 设置变量值
     * 在应用范围内存储临时数据
     * 
     * @param string $name 变量名
     * @param mixed $value 变量值
     */
    public function set(string $name, mixed $value): void
    {
        $this->vars[$name] = $value;
    }

    /**
     * 获取变量值
     * 获取存储在应用范围内的临时数据
     * 
     * @param string $name 变量名
     * @param mixed|null $default 默认值
     * @return mixed 返回变量值，不存在时返回默认值
     */
    public function get(string $name,mixed $default = null): mixed
    {
        return $this->vars[$name] ?? $default;
    }
}