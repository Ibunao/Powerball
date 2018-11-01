<?php
namespace api\controllers;

use Yii;
use api\controllers\bases\BaseController;
use common\helpers\HttpHelper;
use yii\mongodb\Query;
/**
 * Site controller
 */
class InfoController extends BaseController
{
	/**
	 * 通过code获取openid
	 * @param  [type] $code [description]
	 * @return [type]       [description]
	 */
	public function actionCode()
	{
		$req = Yii::$app->request;
		$userInfo = $req->get();
		if (empty($userInfo)) {
			return $this->sendError('请求参数有误');
		}
		$url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.Yii::$app->params['wxappconfig']['prod']['app_id'].'&secret='.Yii::$app->params['wxappconfig']['prod']['app_secret'].'&js_code='.$userInfo['code'].'&grant_type=authorization_code';
        $temp = HttpHelper::httpCurl($url);
        
        if (isset($temp['openid'])) {
        	unset($userInfo['code']);
            $userInfo['openid'] = $temp['openid'];
            $userInfo['createtime'] = time();
            $userInfo['updatetime'] = time();
            $query = new Query;
            $temp = $query->select(['openid'])
            	->from('customer')
            	->all();
            if (empty($temp)) {
            	$collection = Yii::$app->mongodb->getCollection('customer');
				$collection->insert($userInfo);
            }
            return $this->sendSucc(['userInfo' => $userInfo]);
        }
        return $this->sendError('获取openid失败');
	}
	/**
	 * 更新formid
	 * @return [type] [description]
	 */
	public function actionFormid()
	{
		$req = Yii::$app->request;
		$info = $req->get();
		if ($info['openid'] && $info['formid']) {
			$collection = Yii::$app->mongodb->getCollection('customer');
	        $collection->update(['openid' => $info['openid']], ['formid' => $info['formid']]);
		}
	}
	/**
	 * 随机号码
	 * @return [type] [description]
	 */
	public function actionRandom()
	{
		$req = Yii::$app->request;
		$info = $req->get();
		return $this->sendSucc($info);
	}
}