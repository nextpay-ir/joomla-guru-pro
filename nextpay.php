<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_Guru
 * @subpackage 	nextpay_gateway
 * @copyright   https://nextpay.ir
 * @license     GNU General Public License
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
if (!class_exists ('checkHack')) {
	require_once( JPATH_PLUGINS . '/gurupayment/nextpay/inputcheck.php');
}

jimport('joomla.application.menu');
jimport( 'joomla.html.parameter' );

class plgGurupaymentNextpay extends JPlugin{

	$_db = null;
    
	function plgGurupaymentNextpay(&$subject, $config){
		$this->_db = JFactory :: getDBO();
		parent :: __construct($subject, $config);
	}
	
	function onReceivePayment(&$post){
		if($post['processor'] != 'nextpay'){
			return 0;
		}	
		
		$params = new JRegistry($post['params']);
		$default = $this->params;
        
		$out['sid'] = $post['sid'];
		$out['order_id'] = $post['order_id'];
		$out['processor'] = $post['processor'];
		$Amount = round($this->getPayerPrice($out['order_id']),0);

		if(isset($post['txn_id'])){
			$out['processor_id'] = JRequest::getVar('tx', $post['txn_id']);
		}
		else{
			$out['processor_id'] = "";
		}
		if(isset($post['custom'])){
			$out['customer_id'] = JRequest::getInt('cm', $post['custom']);
		}
		else{
			$out['customer_id'] = "";
		}
		if(isset($post['mc_gross'])){
			$out['price'] = JRequest::getVar('amount', JRequest::getVar('mc_amount3', JRequest::getVar('mc_amount1', $post['mc_gross'])));
		}
		else{
			$out['price'] = $Amount;
		}
		$out['pay'] = $post['pay'];
		if(isset($post['email'])){
			$out['email'] = $post['email'];
		}
		else{
			$out['email'] = "";
		}
		$out["Itemid"] = $post["Itemid"];

		$cancel_return = JURI::root().'index.php?option=com_guru&controller=guruBuy&processor='.$param['processor'].'&task='.$param['task'].'&sid='.$param['sid'].'&order_id='.$post['order_id'].'&pay=fail';		
		//=====================================================================

		$app	= JFactory::getApplication();
		$jinput = $app->input;
		$trans_id = $jinput->get->post('trans_id', '', 'STRING');
		$order_id = $jinput->get->post('order_id', '', 'STRING');
	
		if (checkHack::checkUUID($trans_id)){
			if (isset($order_id)) {
				try {
					$client = new SoapClient("https://api.nextpay.org/gateway/verify.wsdl", array('encoding' => 'UTF-8'));
					$res = $client->PaymentVerification([
                        "api_key"   => $params->get('api_key'),
                        'order_id'	=> $order_id,
                        'trans_id' 	=> $trans_id,
                        'amount'	=> $Amount/10,
					]);

                    $res = $res->PaymentVerificationResult;

					$resultStatus = intval($res->code); 
					if ($resultStatus == 0) {
						$out['pay'] = 'ipn';
						$message = "کد پیگیری".$res->trans_id;
						$app->enqueueMessage($message, 'message');
					} 
					else {
						$out['pay'] = 'fail';
						$msg= $this->getGateMsg($res->code); 
						$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error'); 
					}
				}
				catch(\SoapFault $e) {
					$out['pay'] = 'fail';
					$msg= $this->getGateMsg(-32); 
					$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error'); 
				}
			}
			else {
				$out['pay'] = 'fail';
				$msg= $this->getGateMsg(-27); 
				$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error'); 
			}
		}
		else {
			$out['pay'] = 'fail';
			$msg= $this->getGateMsg(-21); 
			$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error'); 
		}

		return $out;
	}

	function onSendPayment(&$post){
		if($post['processor'] != 'nextpay'){
			return false;
		}

		$params = new JRegistry($post['params']);
		$param['option'] = $post['option'];
		$param['controller'] = $post['controller'];
		$param['task'] = $post['task'];
		$param['processor'] = $post['processor'];
		$param['order_id'] = @$post['order_id'];
		$param['sid'] = @$post['sid'];
		$param['Itemid'] = isset($post['Itemid']) ? $post['Itemid'] : '0';
		foreach ($post['products'] as $i => $item){ $price += $item['value']; }  
		$cancel_return = JURI::root().'index.php?option=com_guru&controller=guruBuy&processor='.$param['processor'].'&task='.$param['task'].'&sid='.$param['sid'].'&order_id='.$post['order_id'].'&pay=fail';


		$app	= JFactory::getApplication();
		$Amount = round($price,0)/10;
		$Description = 'خرید محصول از فروشگاه   ';
		$Email = ''; 
		$Mobile = ''; 
		$CallbackURL = JURI::root().'index.php?option=com_guru&controller=guruBuy&processor='.$param['processor'].'&task='.$param['task'].'&sid='.$param['sid'].'&order_id='.$post['order_id'].'&customer_id='.intval($post['customer_id']).'&pay=wait';
		
		try {
			$client = new SoapClient('https://api.nextpay.org/gateway/token.wsdl', array('encoding' => 'UTF-8')); 	
			$res = $client->TokenGenerator([
				'api_key' => $params->get('api_key'),
				'amount' => $Amount,
				'order_id' => $post['order_id'],
				'callback_uri' => $CallbackURL,
				]);

            $res = $res->TokenGeneratorResult;
			
			$resultStatus = intval($res->code); 
			if ($resultStatus == -1) {				
                $app->redirect('https://api.nextpay.org/gateway/payment‬‬'. $res->trans_id);
			} else {
				$msg= $this->getGateMsg($res->code);
				$app->redirect($cancel_return, '<h2>'.$msg.$resultStatus .'</h2>', $msgType='Error'); 
			}
		}
		catch(\SoapFault $e) {
			$msg= $this->getGateMsg(-42);
			$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error'); 
		}
	}
	
	function getGateMsg ($msgId) {
		$error_code = intval($error_code);
        $error_array = array(
          0 => "Complete Transaction",
	     -1 => "Default State",
	     -2 => "Bank Failed or Canceled",
	     -3 => "Bank Payment Pending",
	     -4 => "Bank Canceled",
	    -20 => "api key is not send",
	    -21 => "empty trans_id param send",
	    -22 => "amount not send",
	    -23 => "callback not send",
	    -24 => "amount incorrect",
	    -25 => "trans_id resend and not allow to payment",
	    -26 => "Token not send",
	    -27 => "order_id incorrect",
	    -28 => "custom field incorrect [must be json]",
	    -30 => "amount less of limit payment",
	    -31 => "fund not found",
	    -32 => "callback error",
	    -33 => "api_key incorrect",
	    -34 => "trans_id incorrect",
	    -35 => "type of api_key incorrect",
	    -36 => "order_id not send",
	    -37 => "transaction not found",
	    -38 => "token not found",
	    -39 => "api_key not found",
	    -40 => "api_key is blocked",
	    -41 => "params from bank invalid",
	    -42 => "payment system problem",
	    -43 => "gateway not found",
	    -44 => "response bank invalid",
	    -45 => "payment system deactivated",
	    -46 => "request incorrect",
	    -47 => "gateway is deleted or not found",
	    -48 => "commission rate not detect",
	    -49 => "trans repeated",
	    -50 => "account not found",
	    -51 => "user not found",
	    -52 => "user not verify",
	    -60 => "email incorrect",
	    -61 => "national code incorrect",
	    -62 => "postal code incorrect",
	    -63 => "postal add incorrect",
	    -64 => "desc incorrect",
	    -65 => "name family incorrect",
	    -66 => "tel incorrect",
	    -67 => "account name incorrect",
	    -68 => "product name incorrect",
	    -69 => "callback success incorrect",
	    -70 => "callback failed incorrect",
	    -71 => "phone incorrect",
	    -72 => "bank not response",
	    -73 => "callback_uri incorrect [with api's address website]",
	    -82 => "ppm incorrect token code"
        );
        
        if (array_key_exists($error_code, $error_array)) {
            return $error_array[$error_code];
        } else {
            return "error code : $error_code";
        }
	}


	function getPayerPrice ($id) {
		$user = JFactory::getUser();
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('amount')
			->from($db->qn('#__guru_order'));
		$query->where(
			$db->qn('userid') . ' = ' . $db->q($user->id) 
							. ' AND ' . 
			$db->qn('id') . ' = ' . $db->q($id)
		);
		$db->setQuery((string)$query); 
		$result = $db->loadResult();
		return $result;
	}
}
?>
