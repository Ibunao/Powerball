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
		$red = ["01", "02", "03", "04", "05", "06", "07", "08", "09", "10", "11", "12", "13", "14", "15", "16", "17", "18", "19", "20", "21", "22", "23", "24", "25", "26", "27", "28", "29", "30", "31", "32", "33"];
		// 打乱数组
		shuffle($red);
		shuffle($red);
		shuffle($red);
		$blue = ["01", "02", "03", "04", "05", "06", "07", "08", "09", "10", "11", "12", "13", "14", "15", "16"];
		shuffle($blue);
		$req = Yii::$app->request;
		$info = $req->get();
		$openid = $info['openid'];
		$data = json_decode($info['data'], true);
		$renew = false;
		if ($data['create']) {
			$renew = true;
		}
		unset($data['create']);
		unset($data['__webviewId__']);
		$redKeys = array_rand($red, 6);
		$redBalls = [];
		foreach ($redKeys as $key => $value) {
			$redBalls[] = $red[$value];
		}
		asort($redBalls);
		$redBalls = array_values($redBalls);
		$blueKey = array_rand($blue, 1);
		$i = 0;
		foreach ($data as $key => $value) {
			if ($renew || empty($value)) {
				if ($i == 6) {
					$data[$key] = $blue[$blueKey];
				}else{
					$data[$key] = $redBalls[$i];
				}
			}
			$i++;
		}

		$data['create'] = true;

		return $this->sendSucc($data);
	}
}