<?php
namespace api\controllers\bases;

use Yii;
use yii\web\Controller;
use yii\web\Response;
/**
 * Site controller
 */
class BaseController extends Controller
{
	public function init()
	{
		parent::init();
		$this->layout = false;
		Yii::$app->response->format = Response::FORMAT_JSON;
	}
	/**
	 * 组装成功的响应数据
	 * @param  [type]  $data [description]
	 * @param  integer $code [description]
	 * @return [type]        [description]
	 */
	public function sendSucc($data, $code = 200)
	{
		return [
			'code' => $code,
			'data' => $data,
		];
	}
	/**
	 * 组装失败的响应数据
	 * @param  [type]  $data [description]
	 * @param  string  $msg  [description]
	 * @param  integer $code [description]
	 * @return [type]        [description]
	 */
	public function sendError($msg = '', $data = [], $code = 400)
	{
		return [
			'code' => $code,
			'msg' => $msg,
			'data' => $data,
		];
	}
}