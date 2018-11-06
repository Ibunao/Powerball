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
	public $pageSize = 10;
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
		// 如果是重新生成或者第一次生成
		if ($data['create'] || (isset($data['first']) && $data['first'])) {
			$renew = true;
		}
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
		foreach ($data['generate'] as $key => $value) {
			if ($renew || empty($value)) {
				if ($i == 6) {
					$data['generate'][$key] = $blue[$blueKey];
					$saveBall[] = $blue[$blueKey];
				}else{
					$data['generate'][$key] = $redBalls[$i];
					$saveBall[] = $redBalls[$i];
				}
			// 如果是固定值直接记录
			}else{
				$saveBall[] = $value;
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
	 * 首页要显示的最后一期的号码
	 * @return [type] [description]
	 */
	public function actionLastBall()
	{
		return $this->sendSucc(['qishu'=> 2018126, 'balls' => ["08", "08", "08", "08", "08", "08", "08",]]);
	}
	/**
	 * 历史记录
	 * @return [type] [description]
	 */
	public function actionHistoryList()
	{
		
	}
	/**
	 * 我的生成列表
	 * @return [type] [description]
	 */
	public function actionMyList()
	{
		$req = Yii::$app->request;
		$openid = $req->get('openid', '');
		$page = $req->get('page', '0');
		if (!$openid) {
			return $this->sendSucc([]);
		}
		$query = new Query;
		$pipelines[] = [
			'$group'=>[
				'_id'=> '$date',
				'qishu' => ['$first' => '$qishu'],
				'result' => ['$first' => '$result'],
				'date' => ['$first' => '$date'],
			],

		];
        $pipelines[] = ['$sort' => ['date' => 1]];//1正序 -1倒序
		// 注意顺序，先skip在limit 
        $pipelines[] = ['$skip' => (int)$page * $this->pageSize];
        $pipelines[] = ['$limit' => $this->pageSize];

		$dateInfo = $query->select(['date', 'result', 'qishu'])
	    	->from('balls')
	    	->getCollection()
	    	->aggregate($pipelines);
	    $result = [];
	    $dateArr = [];
	    foreach ($dateInfo as $key => $item) {
	    	if ($item['result'] != "未开奖") {
	    		$item['result'] = '已开奖';
	    	}
	    	$result[$item['date']] = $item;
	    	$dateArr[] = $item['date'];
	    }
	    $temp = (new Query)->select(['balls', 'result', 'date', 'id', 'open'])
	    	->from('balls')
	    	->where(['date' => $dateArr])
	    	->orderBy(['date' => -1])
	    	->all();
	    foreach ($temp as $key => $ball) {
	    	$result[$ball['date']]['items'][] = $ball;
	    }
	    arsort($result);
	    $result = array_values($result);
	    return $this->sendSucc($result);
	}
	/**
	 * 帮买列表
	 * @return [type] [description]
	 */
	public function actionBangmaiList()
	{
		$req = Yii::$app->request;
		$openid = $req->get('openid', '');
		$page = $req->get('page', '0');
		if (!$openid) {
			return $this->sendSucc([]);
		}
		$query = new Query;
        $hbInfo = $query->select(['items'])
        	->from('helpbuy')
        	->where(['openid' => $openid])
        	->one();
        if (empty($hbInfo['items'])) {
        	return $this->sendSucc([]);
        }
    	$ballArr = $ballIdArr = [];
        foreach ($hbInfo['items'] as $item) {
        	$ballArr[$item['ballId']] = $item;
        	$ballIdArr[] = $item['ballId'];
        }
        $ballIdArr = array_slice($ballIdArr, -9);
        $ballInfoArr = $query->select(['img','balls', 'name', 'qishu', 'id'])
        	->from('balls')
        	->where(['id' => $ballIdArr])
        	->all();
        $result = [];
        foreach ($ballInfoArr as $key => $item) {
        	$item['date'] = date("Y-m-d H:m:i", $ballArr[$item['id']]['shareTime']);
        	$result[] = $item;
        }

        return $this->sendSucc($result);
	}
	/**
	 * 分享 帮人买
	 * @return [type] [description]
	 */
	public function actionBangmai()
	{
		$req = Yii::$app->request;
		$info = $req->get();
		$query = new Query;
		if ($info['openid'] && $info['ballId']) {

			$ballInfo = $query->select(['img','balls', 'name', 'qishu', 'openid'])
            	->from('balls')
            	->where(['id' => $info['ballId']])
            	->one();
            $hbInfo = $query->select(['items'])
            	->from('helpbuy')
            	->where(['openid' => $info['openid']])
            	->one();

            $data = [
            	'ballId' => $info['ballId'],
            	'shareTime' => time(),
            ];
            if (empty($hbInfo) && $ballInfo) {
            	// 保存
				$collection = Yii::$app->mongodb->getCollection('helpbuy');
		        $collection->insert(['openid' => $info['openid'], 'items' => [$data]]);
		        // 直接返回最后添加的数据
		        $ballInfo['date'] = date("Y-m-d H:m:i");
		        return $this->sendSucc([$ballInfo]);
            }else{
            	$ballArr = $ballIdArr = [];
	            foreach ($hbInfo['items'] as $item) {
	            	$ballArr[$item['ballId']] = $item;
	            	$ballIdArr[] = $item['ballId'];
	            }
	            $ballIdArr = array_slice($ballIdArr, -9);
	            $ballInfoArr = $query->select(['img','balls', 'name', 'qishu', 'id'])
	            	->from('balls')
	            	->where(['id' => $ballIdArr])
	            	->all();
	            $result = [];
	            foreach ($ballInfoArr as $key => $item) {
	            	$item['date'] = date("Y-m-d H:m:i", $ballArr[$item['id']]['shareTime']);
	            	$result[] = $item;
	            }
	            // 不符合更新的，直接返回数据
	            if (isset($ballArr[$info['ballId']]) || empty($ballInfo) || $ballInfo['openid'] == $info['openid']) {
	            	return $this->sendSucc($result);
	            }
	            
            	// 更新
            	$collection = Yii::$app->mongodb->getCollection('helpbuy');
		        $collection->update(['openid' => $info['openid']], ['$push' => ['items' => $data]]);
		        $ballInfo['date'] = date("Y-m-d H:m:i");
		        $result[] = $ballInfo;
		        return $this->sendSucc($result);
            }

		}else{
			return $this->sendError('缺少参数');
		}
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