<?php
namespace api\controllers;

use Yii;
use api\controllers\bases\BaseController;
use common\helpers\HttpHelper;
use yii\mongodb\Query;
use yii\base\Security;
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
		$userInfo = json_decode($info['userInfo'], true);
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
		$saveBall = [];
		$i = 0;
		foreach ($data as $key => $value) {
			if ($renew || empty($value)) {
				if ($i == 6) {
					$data[$key] = $blue[$blueKey];
					$saveBall[] = $blue[$blueKey];
				}else{
					$data[$key] = $redBalls[$i];
					$saveBall[] = $redBalls[$i];
				}
			}
			$i++;
		}

		$data['create'] = true;
		$randomId = (new Security)->generateRandomString();
		$data['ballId'] = $randomId;
		$qishu = $this->qishu();
		$collection = Yii::$app->mongodb->getCollection('balls');
		$collection->insert([ 
			'date' => date("Y-m-d"),
			'id' => $randomId,
			'balls' => $saveBall, 
			'img' => $userInfo['avatarUrl'],
			'name' => $this->substrCut($userInfo['nickName']),
			'openid' => $userInfo['openid'],
			'qishu' => $qishu,
			'result' => '未开奖',
			'open' => false,
			'borderStyle' => 2,
			'createtime' => time(),
			'updatetime' => time(),
		]);

		return $this->sendSucc($data);
	}
	/**
	 * 获取期数
	 * @return [type] [description]
	 */
	public function qishu()
	{
		$query = new Query;
        $temp = $query->select(['qishu'])
        	->from('qishu')
        	->all();
        $qishu = 000000;
        foreach ($temp as $key => $item) {
        	$qishu = $item['qishu'];
        }
        return $qishu;
	}
	/**
	 * 只保留字符串首尾字符，隐藏中间用*代替（两个字符时只显示第一个）
	 * @param string $user_name 姓名
	 * @return string 格式化后的姓名
	 */
	function substrCut($userName){
		if (empty($userName)) {
			return $userName;
		}
	    $strlen     = mb_strlen($userName, 'utf-8');
	    $firstStr     = mb_substr($userName, 0, 1, 'utf-8');
	    $lastStr     = mb_substr($userName, -1, 1, 'utf-8');
	    return $firstStr . str_repeat("*", 2) . $lastStr;
	}
	public function actionTest()
	{
		// 手动创建第一期期数。
		// $data = [
		// 	'qishu' => 2018129,
		// 	'createtime' => time(),
		// 	'updatetime' => time(),
		// ];
		// $collection = Yii::$app->mongodb->getCollection('qishu');
		// $collection->insert($data);
		return $this->qishu();
	}
}