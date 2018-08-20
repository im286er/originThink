<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\swoole;

use Swoole\Http\Request;
use Swoole\Http\Response;
use think\App;
use think\Error;
use think\exception\HttpException;

/**
 * Swoole应用对象
 */
class Application extends App
{
    /**
     * 处理Swoole请求
     * @access public
     * @param  \Swoole\Http\Request    $request
     * @param  \Swoole\Http\Response   $response
     * @param  void
     */
    public function swoole(Request $request, Response $response)
    {
//        var_dump($_SERVER);
        unset($header);
        $header=$request->header;
        if(isset($header['x-requested-with'])){
            $request->server['HTTP_X_REQUESTED_WITH']=$header['x-requested-with'];
        }

        try {
            ob_start();

            // 重置应用的开始时间和内存占用
            $this->beginTime = microtime(true);
            $this->beginMem  = memory_get_usage();

            // 销毁当前请求对象实例
            $this->delete('think\Request');

            // 设置Cookie类Response
            $this->cookie->setResponse($response);

            // 重新实例化请求对象 处理swoole请求数据
            $this->request->withHeader($request->header)
                ->withServer($request->server)
                ->withGet($request->get ?: [])
                ->withPost($request->post ?: [])
                ->withCookie($request->cookie ?: [])
                ->withInput($request->rawContent())
                ->withFiles($request->files ?: [])
                ->setPathinfo(ltrim($request->server['path_info'], '/'));

            $_COOKIE = $request->cookie ?: $_COOKIE;

            // 更新请求对象实例
            $this->route->setRequest($this->request);

            $resp = $this->run();

            $resp->send();

            $content = ob_get_clean();
            $status  = $resp->getCode();

            // Trace调试注入
            if ($this->env->get('app_trace', $this->config->get('app_trace'))) {
                $this->debug->inject($resp, $content);
            }

            // 发送状态码
            $response->status($status);

            // 发送Header
            foreach ($resp->getHeader() as $key => $val) {
                $response->header($key, $val);
            }

            $response->end($content);
        } catch (HttpException $e) {
            $this->exception($response, $e);
        } catch (\Exception $e) {
            $this->exception($response, $e);
        } catch (\Throwable $e) {
            $this->exception($response, $e);
        }
    }

    protected function exception($response, $e)
    {
        if ($e instanceof \Exception) {
            $handler = Error::getExceptionHandler();
            $handler->report($e);

            $resp    = $handler->render($e);
            $content = $resp->getContent();
            $code    = $resp->getCode();

            $response->status($code);
            $response->end($content);
        } else {
            $response->status(500);
            $response->end($e->getMessage());
        }

        throw $e;
    }
}