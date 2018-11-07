<?php
namespace api\controllers;

use Yii;
use api\controllers\bases\BaseController;
use common\helpers\HttpHelper;
use yii\mongodb\Query;
/**
 * Site controller
 */
class TestController extends BaseController
{

	public function actionTest()
	{
		$query = new Query;
		$mqishu = $query
			->from('qishu')
			->one();
		$temp = json_decode($this->_request($mqishu), true);
		if ($temp['state'] != 0) {
			return;
		}else{
			$source = $temp['result'][0];
			$qishu = $source['code'];
			$query = new Query;
			$isHave = $query->select(['code'])
				->from('sourcedata')
				->where(['code' => $qishu])
				->one();
			// 如果已经存在则不再执行
			if ($isHave) {
				return;
			}
			// 保存原始数据
			$collection = Yii::$app->mongodb->getCollection('sourcedata');
			$collection->insert($source);
			// 更新下一期的期数  
			$collection = Yii::$app->mongodb->getCollection('qishu');
	        $collection->update(['_id' => '5bdec51dcd5ad6f93e0bc2a1'], ['qishu' => $qishu+1]);

			// 更新并发送通知
			$this->updateBalls($source);
		}

	}
	/**
	 * 更新随机生成号码的中奖情况
	 * @param  [type] $source [description]
	 * @return [type]         [description]
	 */
	public function updateBalls($source)
	{
		$query = new Query;
		$balls = $query
			->from('balls')
			->where(['qishu' => $source['code']])
			->all();
		$redArr = explode(',', $source['red']);
		$blue = $source['blue'];
		$level = [];
		foreach ($source['prizegrades'] as $key => $item) {
			$level[$item['type']] = $item['typemoney'];
		}
		$openidArr = [];
		foreach ($balls as $key => $item) {
			if (isset($openidArr[$item['openid']])) {
				$openidArr[$item['openid']] = [];
			}
			$myRedArr = array_slice($item['balls'], 0, 6);
			$myBlue = $item['balls'][6];
			$temp = array_intersect($redArr, $myRedArr);
			$result = '未中奖';
			$msg = '0';
			if (count($temp) == 6) {
				if ($myBlue == $blue) {
					$result = '一等奖';
					$msg = $level[1];
					$openidArr[$item['openid']][] = 1;
				}else{
					$result = '二等奖';
					$msg = $level[2];
					$openidArr[$item['openid']][] = 2;
				}
			}
			if (count($temp) == 5) {
				if ($myBlue == $blue) {
					$result = '三等奖';
					$msg = $level[3];
					$openidArr[$item['openid']][] = 3;
				}else{
					$result = '四等奖';
					$msg = $level[4];
					$openidArr[$item['openid']][] = 4;
				}
			}
			if (count($temp) == 4) {
				if ($myBlue == $blue) {
					$result = '四等奖';
					$msg = $level[4];
					$openidArr[$item['openid']][] = 4;
				}else{
					$result = '五等奖';
					$msg = $level[5];
					$openidArr[$item['openid']][] = 5;
				}
			}
			if (count($temp) == 3) {
				if ($myBlue == $blue) {
					$result = '五等奖';
					$msg = $level[5];
					$openidArr[$item['openid']][] = 5;
				}
			}
			if (count($temp) == 2 || count($temp) == 1 || count($temp) == 0) {
				if ($myBlue == $blue) {
					$result = '六等奖';
					$msg = $level[6];
					$openidArr[$item['openid']][] = 6;
				}
			}
			$collection = Yii::$app->mongodb->getCollection('balls');
	        $collection->update(['id' => $item['id']], ['result' => $result, 'msg' => $msg]);
		}
		$pbInfo = ['qishu' => $source['code'], 'balls' => $source['red'].','.$source['blue'], 'date' => $source['date']];


		$this->sendMessage($openidArr, $pbInfo);
	}
	/**
	 * 发送通知
	 * @param  [type] $openidArr [description]
	 * @return [type]            [description]
	 */
	public function sendMessage($openidArr, $pbInfo)
	{
		$temp = (new Query)->select(['openid', 'formid'])
			->from('customer')
			->where(['openid' => array_keys($openidArr)])
			->all();
		if (empty($temp)) {
			return;
		}
		$formidArr = [];
		foreach ($temp as $key => $item) {
			$formidArr[$item['openid']] = $item['formid'];
		}
		// 团购小程序的
        $template_id = '98Cbfqo2UbbqD_8VenBWfzIk0zP44XyN-_b5F6ilWT4';
        foreach ($openidArr as $openid => $item) {
	        $sendInfo['touser'] = $openid;
	        $sendInfo['template_id'] = $template_id;
	        $sendInfo['page'] = '/pages/main/main'; // 跳转页
	        $sendInfo['form_id'] = isset($formidArr[$openid])?$formidArr[$openid]:''; 
	        $sendInfo['data']['keyword1'] = ['value' => '双色球']; 
	        $sendInfo['data']['keyword2'] = ['value' => $pbInfo['balls']]; 
	        $sendInfo['data']['keyword3'] = ['value' => $pbInfo['date']]; 
	        $sendInfo['data']['keyword4'] = ['value' => '第 '.$pbInfo['qishu'].' 期']; 
	        $sendInfo['data']['keyword5'] = ['value' => empty($item)?'开奖啦！赶紧戳进来看看。':'开奖啦！你的幸运码有中奖额，赶紧戳进来看看。']; 
	        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=".$this->getAcessToken();

	        $postData = json_encode($sendInfo);
	        $result = HttpHelper::httpCurl($url, 'post', 'json', $postData);
        }
	}
	// 获取token
    public function getAcessToken()
    {
        $token = Yii::$app->cache->get('wxapp-acess-token');
        if (empty($token)) {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".Yii::$app->params['wxappconfig']['prod']['app_id']."&secret=".Yii::$app->params['wxappconfig']['prod']['app_secret'];
            $result = HttpHelper::httpCurl($url);
            // var_dump($result);exit;
            if ($result && isset($result['access_token'])) {
                Yii::$app->cache->set('wxapp-acess-token', $result['access_token'], 7000);
                return $result['access_token'];
            }   
        }
        return $token;
    }
	/**
	 * 请求获取当期中奖情况
	 * @return [type] [description]
	 */
	public function _request($qishu)
	{
		$refer = 'http://www.cwl.gov.cn/kjxx/ssq/';
		$ch = curl_init();
		//设置是直接打印出来 ture不打印
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, 'http://www.cwl.gov.cn/cwl_admin/kjxx/findKjxx/forIssue?name=ssq&code='.$qishu);
		//伪造来源refer
		curl_setopt($ch, CURLOPT_REFERER, $refer);
		$out_put = curl_exec($ch);
		curl_close($ch);
		return $out_put;
	}
}