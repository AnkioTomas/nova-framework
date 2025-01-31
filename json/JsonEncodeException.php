<?php
declare(strict_types=1);

namespace nova\framework\json;

use Exception;
use nova\framework\core\Logger;
use Throwable;

/**
 * JSON 编码异常类
 * 
 * 当数据转换为 JSON 字符串失败时抛出此异常
 */
class JsonEncodeException extends Exception
{
    /**
     * 构造函数
     *
     * @param string $message    错误信息
     * @param mixed $json       导致编码错误的数据
     * @param int $code         错误代码
     * @param Throwable|null $previous  上一个异常（用于异常链）
     */
    public function __construct(string $message = "", $json = null, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        Logger::warning("json encode error => $message",$json);
    }
}