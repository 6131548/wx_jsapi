<?php
namespace app\index\controller;
use think\Db;
use app\index\controller\Wxpayh5;
class Jsapi extends Home
{
    /**
     * @return mixed
     */
    public function index(){
		
		if(is_wxBrowers()){
			$goods="充值";
			$order_no  = generateChargeOrderNo();
			$Uid = session('home_user_auth')['id'] ;
			$num =session('money');
//			echo $num;die;
			
			// echo $num;die;
			$total_fee = $num * 100;

			$attach = '微信支付';
			$jsApiParameters = wxpay(session('openid'),$goods,$order_no,$total_fee,$attach);
			
			if($jsApiParameters){
				$this->assign('data',$jsApiParameters);
				return $this->fetch('index');
			}

		}
	} 
	public function test(){
		
		$res = Db::name('home_pay_list')
			    	->field('id,uid,money')
			    	->where([
			    		'order_no'		=>	'CG1565340996678604',
			    		'rc_status'		=>	0
			    		])
			    	->find();
		Db::startTrans();
		try {			    	
			if($res){
				Db::name('home_pay_list')->update([
							'id'			=>	$res['id'],
							'paid_money'	=>	1/100,
							'rc_status'		=>	1,
							'update_time'	=>	time()
							]);
			$account = Db::name('home_finance')
							->field('id,rmb')
							->where('uid',$res['uid'])
							->find();	
			Db::name('home_finance')->update([
							'id'		=>	$account['id'],
							'rmb'		=>	$account['rmb']+$res['money'],
							'update_time'	=>	time()
							])	;	
			}
		 Db::commit();

	    }catch (\Exception $e) {
	      // 回滚事务
	      Db::rollback();
	      
	    }		    	
		
		// $this->RecordMoneyLog($res['uid'],1/100,1,2,'充值');								
	}
	/**
	 * [recharge 微信充值]
	 * @param  [type] $num [数量]
	 * @return [type]      [description]
	 */				
	public function recharge() {
		$order_no  = generateChargeOrderNo();
		$Uid = 1;
		//$Uid = session('home_user_auth')['id'] ;
		if(!session('money')){
			return $this->error('系统繁忙,订单生成失败!');
		}
		$num = $this->getRecharge(session('money'));
		//生成订单
		$res = $this->AddChargeOrder($order_no,$Uid,$num);
		if(!$res){
			return $this->error('系统繁忙,订单生成失败!');
		}
		if(is_wxBrowers()){
			$goods="充值";
			$total_fee = $num * 100;
			$attach = '微信支付';
			$jsApiParameters = wxpay(session('openid'),$goods,$order_no,$total_fee,$attach);
			
			if($jsApiParameters){
				$this->assign('data',$jsApiParameters);
				return $this->fetch('index');
			}

		}else{
			(new Wxpayh5)->charger($num,$order_no);
			// Test1->charge($num);
		}
	}
	/**
	 * [getrechargenum 获取金额数量]
	 * @param  [type] $num [description]
	 * @return [type]      [description]
	 */
	public function getrechargenum($num=null){
		if($num){
			session('money',$num);
			$this->redirect('index/Jsapi/recharge');
		}
	}

	/**
	 * [UpdateOrderStatus 回调信息]
	 */
	function UpdateOrderStatus() {
		
      $string = file_get_contents("php://input");//微信返回的xml支付结果
      //记录日志
      file_put_contents(APP_ROOT."/.././pay_log/wxjspay".date("Y-m-d").".txt",date('Y-m-d:h:i:s').PHP_EOL.$string.PHP_EOL, FILE_APPEND);
      $arr = (array) simplexml_load_string($string, 'SimpleXMLElement', LIBXML_NOCDATA);

      if ($arr['result_code'] == 'SUCCESS' && $arr['return_code'] == 'SUCCESS') {
           //操作数据库处理订单状态等
	        
	        if($arr['appid'] ==config('appId') && $arr['mch_id'] == config('mch_id') ) {
	        	
		      	$res = Db::name('home_pay_list')
			    	->field('id,uid,money')
			    	->where([
			    		'order_no'		=>	$arr['out_trade_no'],
			    		'rc_status'		=>	0
			    		])
			    	->find();
		    	if($res){
		    		$this->addstatus(99);
		    	}
	        	Db::startTrans();
			    try {
			      
				if($res){
					//修改订单
					Db::name('home_pay_list')->update([
						'id'			=>	$res['id'],
						'paid_money'	=>	$arr['cash_fee']/100,
						'rc_status'		=>	1,
						'pay_type'      =>  1,
						'update_time'	=>	time()
						]);
					
					$account = Db::name('home_finance')
						->field('id,rmb')
						->where('uid',$res['uid'])
						->find();
					//账户	

					Db::name('home_finance')->update([
						'id'		=>	$account['id'],
						'rmb'		=>	$account['rmb']+$res['money'],
						'update_time'	=>	time()
						])	;
					//日志
					$this->RecordMoneyLog($res['uid'],$arr['cash_fee']/100,0,2,'充值');
					
				}

			      Db::commit();

			    }catch (\Exception $e) {
			      // 回滚事务
			      Db::rollback();
			      
			    }
			    $res = Db::name('home_pay_list')
                    ->field('id,uid,money')
                    ->where([
                        'order_no'      =>  $arr['out_trade_no'],
                        'rc_status'        =>  1
                        ])
                    ->find();
                if($res){
                  return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
                }
	        }

	        
// 	          <xml><appid><![CDATA[wx8119f80b2cb6b541]]></appid>
// <attach><![CDATA[微信支付]]></attach>
// <bank_type><![CDATA[ICBC_DEBIT]]></bank_type>
// <cash_fee><![CDATA[1]]></cash_fee>
// <fee_type><![CDATA[CNY]]></fee_type>
// <is_subscribe><![CDATA[Y]]></is_subscribe>
// <mch_id><![CDATA[1480689022]]></mch_id>
// <nonce_str><![CDATA[b98qbcbizv16m7d5wstkgr0hakzszxh3]]></nonce_str>
// <openid><![CDATA[owcHBwfFbHxaoG0NEE6RUzgLLvs8]]></openid>
// <out_trade_no><![CDATA[CG1564974295623842]]></out_trade_no>
// <result_code><![CDATA[SUCCESS]]></result_code>
// <return_code><![CDATA[SUCCESS]]></return_code>
// <sign><![CDATA[0CB4AF95510514E319B52C9487A4B48BCD5FB8B32A814CDF61C5F0F0799B10FD]]></sign>
// <time_end><![CDATA[20190805110502]]></time_end>
// <total_fee>1</total_fee>
// <trade_type><![CDATA[JSAPI]]></trade_type>
// <transaction_id><![CDATA[4200000389201908052316014160]]></transaction_id>
// </xml>
      }else{
      	return '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[NO]]></return_msg></xml>';
      }
      
	}
	public function addstatus($num){
		$data=[
		'num'=>$num,
		'create_time'=>time()
		];
		Db::name('test')->insert($data);
	}

}	

